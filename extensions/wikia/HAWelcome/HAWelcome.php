<?php
/**
 * Terminate the script when called out of MediaWiki context.
 */
if ( !defined( 'MEDIAWIKI' ) ) {
    echo  'Invalid entry point. '
        . 'This code is a MediaWiki extension and is not meant to be executed standalone. '
        . 'Try vising the main page: /index.php or running a different script.';
    exit( 1 );
}

/**
 * Global list of extension credits.
 *
 * @see http://www.mediawiki.org/wiki/Manual:$wgExtensionCredits
 */
$wgExtensionCredits['other'][] = array(
    'path'              => __FILE__,
    'name'              => 'HAWelcome',
    'description'       => 'Sends a welcome message to users after their first edits.',
    'descriptionmsg'    => 'hawelcome-description',
    'version'           => 1009,
    'author'            => array(
        "Krzysztof 'Eloy' Krzyżaniak <eloy@wikia-inc.com>",
        "Maciej 'Marooned' Błaszkowski <marooned@wikia-inc.com>",
        "Michał 'Mix' Roszka <mix@wikia-inc.com>"
    ),
    'url'               => 'https://github.com/Wikia/app/tree/dev/extensions/wikia/HAWelcome/'
);

/**
 * Global list of hooks.
 *
 * @see http://www.mediawiki.org/wiki/Manual:$wgHooks
 * @see http://www.mediawiki.org/wiki/Manual:Hooks/RevisionInsertComplete
 */
$wgHooks['RevisionInsertComplete'][] = 'HAWelcomeJob::onRevisionInsertComplete';

/**
 * Command name for the job aka type of the job.
 *
 * @see http://www.mediawiki.org/wiki/Manual:RunJobs.php
 */
define( 'HAWELCOME_JOB_IDENTIFIER', 'HAWelcome' );

/**
 * Map the job to its handling class.
 */
$wgJobClasses[HAWELCOME_JOB_IDENTIFIER] = 'HAWelcomeJob';

/**
 * @see http://www.mediawiki.org/wiki/Manual:Job_queue/For_developers
 */
class HAWelcomeJob extends Job {

    /**
     * The default user to send welcome messages.
     */
    const DEFAULT_WELCOMER = 'Wikia';

    /**
     * Default switches to enable features, explained below.
     *
     * message-anon - if enabled, anonymous users will get a welcome message
     * message-user - if enabled, registered users will get a welcome message
     * page-user    - if enabled, a user profile page will be created, too (if not exists)
     */
    public $aSwitches = array( 'message-anon' => false, 'message-user' => false, 'page-user' => false );

    /**
     * The id of the recipient.
     */
    public $iRecipientId;

    /**
     * The name of the recipient.
     */
    public $sRecipientName;

    /**
     * The recipient object.
     */
    public $oRecipient;

    /**
     * The sender object.
     */
    public $oSender;

    /**
     * Flags for WikiPage::doEdit().
     */
    public $iFlags = 0;

    /**
     * @param $oRevision Object The Revision object.
     * @param $sData String URL to external object.
     * @param $sFlags String Flags for this revision.
     *
     * @see http://www.mediawiki.org/wiki/Manual:$wgHooks
     * @see http://www.mediawiki.org/wiki/Manual:Hooks/RevisionInsertComplete
     */
    public static function onRevisionInsertComplete( &$oRevision, $sData, $sFlags ) {

        wfProfileIn( __METHOD__ );

        wfDebug( __METHOD__ . "START.\n" );

        // Ignore revisions created in the command-line mode.
        // Otherwise HAWelcome::run() would invoke HAWelcome::onRevisionInsertComplete(), too.
        global $wgCommandLineMode;
        if ( !$wgCommandLineMode ) {

            wfDebug( __METHOD__ . " not in command line mode so going forward...\n" );

            // Abort, if the anonymous contributor has been messaged recently.
            if ( ! $oRevision->getRawUser() ) {
                wfDebug( __METHOD__ . " anon edited, checking cache\n" );
                global $wgMemc;
                if ( $wgMemc->get( wfMemcKey( 'HAWelcome-isPosted', $oRevision->getRawUserText() ) ) ) {
                    wfDebug( __METHOD__ . " anon recently messaged, aborting.\n" );
                    wfProfileOut( __METHOD__ );
                    return true; // so the calling method would continue.
                }
                    //$wgMemc->set( wfMemcKey( 'HAWelcome-isPosted', $oRevision->getRawUserText() ), true, 600 ); // for ten minutes ( 60 * 10 )
                wfDebug( __METHOD__ . " anon not messaged, going forward.\n" );
            }

            // Note, that this code is executed during an HTTP request. This is just a simple and quick
            // check to improve performance if the user is editing massively.
            // If this cache expires, a job will be created and a thorough check will be performed
            // as a part of the job.

            // Get the associated Title object.
            $oTitle = $oRevision->getTitle();
            wfDebug( __METHOD__ . " getting title.\n" );

            // Sometimes, for some reason or other, the Revision object
            // does not contain the associated Title object. It has to be
            // recreated based on the associated Page object.
            if ( !$oTitle ) {
                Wikia::log( sprintf(
                    '%s recreated Title for page %d, revision %d, URL %s',
                    __METHOD__, $oRevision->getPage(), $oRevision->getId(), $oTitle->getFullURL()
                ) );
                $oTitle = Title::newFromId( $oRevision->getPage(), Title::GAID_FOR_UPDATE );
                wfDebug( __METHOD__ . " recreated title.\n" );
            }

            // Parameters for the job:
            //
            // iUserId: the id of the user to be welcome (0 if anon)
            // sUserName: the name of the user to be welcome (IP if anon)
            $aParams = array(
                'iUserId' => $oRevision->getRawUser(),
                'sUserName' => $oRevision->getRawUserText()
            );

            wfDebug( sprintf( "%s: aParams: %s\n", __METHOD__, serialize( $aParams ) ) );

            // Schedule the job.
            $oJob = new self( $oTitle, $aParams );
            if ( $oJob->insert() ) {
                wfDebug( __METHOD__ . " scheduled a job!.\n" );
            } else {
                wfDebug( __METHOD__ . " didn't schedule a job!.\n" );
            }

        }

        wfDebug( __METHOD__ . "END.\n" );

        wfProfileOut( __METHOD__ );

        return true; // so the calling method would continue.

    }

    /**
     * The constructor.
     *
     * Remember, that the constructor is called while scheduling the
     * job in an HTTP request. For performance reasons it should do
     * as little as possible. Heavy code goes to the run() method.
     *
     * @param $oTitle Object The Title associated with the Job.
     * @param $aParams Array The Job parameters.
     * @param $iId Integer The Job identifier.
     */
    public function __construct( $oTitle, $aParams, $iId = 0 ) {
        wfProfileIn( __METHOD__ );
        wfDebug( __METHOD__ . "START.\n" );
        wfDebug( sprintf( "%s: oTitle: %s\n", __METHOD__, $oTitle->getCanonicalURL() ) );
        wfDebug( sprintf( "%s: aParams: %s\n", __METHOD__, serialize( $aParams ) ) );
        wfDebug( sprintf( "%s: iId: %d\n", __METHOD__, $iId ) );
        // Convert params to object properties (easier access).
        $this->iRecipientId = $aParams['iUserId'];
        $this->sRecipientName = $aParams['sUserName'];
        parent::__construct( HAWELCOME_JOB_IDENTIFIER, $oTitle, $aParams, $iId );
        wfDebug( __METHOD__ . "END.\n" );
        wfProfileOut( __METHOD__ );
    }

    /**
     * The main method called by the job executor.
     */
    public function run() {

        wfProfileIn( __METHOD__ );

        // Complete the job if the feature has been disabled by an admin of the wiki.
        if ( in_array( trim( wfMsg( 'welcome-user' ) ), array( '@disabled', '-' ) ) ) {
            wfProfileOut( __METHOD__ );
            return true;
        }

        // Create some recipient related objects.
        $this->oRecipient = User::newFromId( $this->iRecipientId );
        if ( ! $this->iRecipientId ) {
            $this->oRecipient->setName( $this->sRecipientName );
        }
        $this->oRecipientTalkPage = new Article( $this->oRecipient->getUserPage()->getTalkPage() );

        // MessageWall compatibility.
        global $wgEnableWallExt;
        $this->bMessageWallExt = ! empty( $wgEnableWallExt );

        // Override the default switches with user's values.
        $sSwitches = wfMsg( 'welcome_enabled' );
        foreach ( $this->aSwitches as $sSwitch => $bValue ) {
            if ( false !== strpos( $sSwitches, $sSwitch ) ) {
                $this->aSwitches[$sSwitch] = true;
            }
        }

        // Refresh (or don't) the sender data.
        $this->setSender();

        // If possible, mark edits as if they were made by a bot.
        if ( $this->oSender->isAllowed( 'bot' ) || '@bot' == trim( wfMsg( 'welcome-bot' ) ) ) {
            $this->iFlags = EDIT_FORCE_BOT;
        }

        // Start: anonymous users block
        if ( ! $this->iRecipientId && $this->aSwitches['message-anon'] ) {

            if (
                // The Message Wall extension is enabled and user's wall is empty or ...
                ( $this->bMessageWallExt && ! WallHelper::haveMsg( $this->oRecipient ) )
                // ... or the Message Wall extension is disabled and recipient's Talk Page does not exist
                || ( !$this->bMessageWallExt && !$this->oRecipientTalkPage->exists() )
            ) {
                $this->setMessage();
                $this->sendMessage();
            }

        // End: anonymous users block

        // Start: registered users block
        } else if ( $this->iRecipientId ) { // && ! $this->oRecipient->getEditCountLocal() ) {

            // If configured so, send a welcome message.
            if ( $this->aSwitches['message-user'] ) {
                $this->setMessage();
                $this->sendMessage();
            }

            // If configured so, create a user profile page, if not exists.
            if ( ! $this->aSwitches['page-user'] ) {
                $oRecipientProfile = new Article( $this->oRecipient->getUserPage() );
                if ( ! $oRecipientProfile->exists() ) {
                    $oRecipientProfile->doEdit( wfMsgForContentNoTrans( 'welcome-user-page', $this->sRecipientName ), false, $this->iFlags );
                }
            }

        // End: registered users block
        }

        wfProfileOut( __METHOD__ );

        return true;
    }

    //>TOD Mix Fri, 08 Mar 2013 11:52:23 +0100 - comments
    public function setMessage() {
        wfProfileIn( __METHOD__ );

        $sMessageKey = 'welcome-message-';
        // Determine the proper message key
        //
        // Is Message Wall enabled?
        $sMessageKey  .= $this->bMessageWallExt
            ? 'wall-'  : '';
        // Is recipient a registered user?
        $sMessageKey .= $this->iRecipientId
            ? 'user-'  : 'anon';
        // Is sender a staff member?
        $sMessageKey .= in_array( 'staff', $this->oSender->getEffectiveGroups() )
            ? '-staff' : '';

        $sPrefixedText = $this->title->getPrefixedText();

        // Article Comments and Message Wall hook up to this event.
        wfRunHooks( 'HAWelcomeGetPrefixText' , array( &$sPrefixedText, $this->title ) );

        $this->sMessage = wfMsgNoTrans(
            $sMessageKey,
            array(
                $sPrefixedText,
                $this->oSender->getUserPage()->getTalkPage()->getPrefixedText(),
                '~~~~',
                wfEscapeWikiText( $this->sRecipientName )
            )
        );

        wfProfileOut( __METHOD__ );
    }

    public function setSender() {
        wfProfileIn( __METHOD__ );

        // najpierw sprawdzam message ustawiony przez admina lub na
        // messagingu.
        $sSender = trim( wfMsgForContent( 'welcome-user' ) );

        // Próbuję utworzyć obiekt User na podstawie wildcarda ustawionego
        // jawnie przed admina lub na messagingu.
        if ( in_array( $sSender, array( '@latest', '@sysop' ) ) ) {

            // First, the cache.
            global $wgMemc;
            $iSender = (int) $wgMemc->get( wfMemcKey( 'last-sysop-id' ) );

            // Second, the database.
            if ( ! $iSender ) {

                // Fetch the list of users who are sysops and/or bots.
                $oDB = wfGetDB( DB_SLAVE );
                $oResult = $oDB->select(
                    array( 'user_groups' ),
                    array( 'ug_user', 'ug_group' ),
                    $oDB->makeList( array( 'ug_group' => array( 'sysop', 'bot' ) ), LIST_OR ),
                    __METHOD__
                );

                $aUsers = array( 'bot' => array(), 'sysop' => array() );

                // Classify the users as sysops or bots.
                while( $oRow = $oDB->fetchObject( $oResult ) ) {
                    array_push( $aUsers[$oRow->ug_group], $oRow->ug_user );
                }

                $oDB->freeResult( $oResult );

                // Filter out bots from sysops.
                $aAdmins = array( 'rev_user' => array_unique( array_diff( $aUsers['sysop'], $aUsers['bot'] ) ) );

                // Fetch the id of the latest active sysop (within the last 60 days) or 0.
                $iSender = (int) $oDB->selectField(
                    'revision',
                    'rev_user',
                    array(
                        $oDB->makeList( $aAdmins, LIST_OR ),
                        'rev_timestamp > ' . $oDB->addQuotes( $oDB->timestamp( time() - 5184000 ) ) // 60 days ago (24*60*60*60)
                    ),
                    __METHOD__,
                    array(
                        'ORDER BY' => 'rev_timestamp DESC',
                        'DISTINCT'
                    )
                );
            }
            // Create a User object and cache the fetched value.

            $oSender = User::newFromId( $iSender );
            $wgMemc->set( wfMemcKey( 'last-sysop-id' ), $oSender->getId() );

            // Overwrite the cache if the contributor happens to be a sysop.
            $aGroups = $this->oRecipient->getEffectiveGroups();
            if ( in_array( 'sysop', $aGroups ) && ! in_array( 'bot' , $aGroups ) ) {
                $wgMemc->set( wfMemcKey( 'last-sysop-id' ), $this->iRecipientId );
            }

        // Próbuję utworzyć obiekt User na podstawie wartości ustawionej
        // jawnie przez admina lub na messagingu.
        } else {
            $oSender = User::newFromName( $sSender );
        }

        // Zobaczmy, co mamy.
        if( $oSender instanceof User && $oSender->getId() ) {
            $this->oSender = $oSender;
            wfProfileOut( __METHOD__ );
            return;
        }

        // If no recently active admin has been found, fall back to a relevant staff member.
        global $wgLanguageCode;
        $oSender = Wikia::staffForLang( $wgLanguageCode );

        if( $oSender instanceof User && $oSender->getId() ) {
            $this->oSender = $oSender;
            wfProfileOut( __METHOD__ );
            return;
        }

        // This should not happen.
        $this->oSender = User::newFromName( self::DEFAULT_WELCOMER );
        Wikia::log( sprintf(
            '%s fell back to the default sender while trying to send a welcome message to %s (%d).',
            __METHOD__, $this->sRecipientName, $this->iRecipientId
        ) );
        wfProfileOut( __METHOD__ );
        return;
    }

    public function sendMessage() {

        wfProfileIn( __METHOD__ );

        // Post a message onto a message wall if enabled.
        if ( $this->bMessageWallExt ) {
            // See: extensions/wikia/Wall/WallMessage.class.php
            $mWallMessage = F::build(
                'WallMessage',
                array(
                    $this->sMessage, $this->sRecipientName, $this->oSender,
                    wfMsg( 'welcome-message-log' ), false, array(), false, false
                ),
                'buildNewMessageAndPost'
            );

            if ( $mWallMessage ) {
                $mWallMessage->setPostedAsBot( $this->oSender );
                $mWallMessage->sendNotificationAboutLastRev();
            }

        // Post a message onto a regular talk page.
        } else {

            // Replace the current active user object with the sender object for a moment.
            global $wgUser;
            $tmp = $wgUser;
            $wgUser = $this->oSender;

            // Prepend the message with the existing content of the talk page.
            $sMessage = ( $this->oRecipientTalkPage->exists() )
                ? $sMessage = $this->oRecipientTalkPage->getContent() . $this->sMessage
                : $this->sMessage;

            $this->oRecipientTalkPage->doEdit( $sMessage, wfMsg( 'welcome-message-log' ), $this->iFlags );

            // Restore the original active user object.
            $wgUser = $tmp;
        }

        wfProfileOut( __METHOD__ );
    }
}
