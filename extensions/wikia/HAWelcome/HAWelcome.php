<?php
/**
 * Terminate the script when called out of MediaWiki context.
 */
if ( !defined( 'MEDIAWIKI' ) ) {
        echo   'Invalid entry point. '
             . 'This code is a MediaWiki extension and is not meant to be executed standalone.'
	     . 'Try vising the main page: /index.php or running a different script.';
	exit( 1 );
}

/**
 * Global list of extension credits.
 *
 * @see http://www.mediawiki.org/wiki/Manual:$wgExtensionCredits
 */
$wgExtensionCredits['other'][] = array(
        'path'           => __FILE__,
	'name'           => 'HAWelcome',
	'description'    => 'Sends a welcome message to users after their first edits.',
	'descriptionmsg' => 'hawelcome-description',
	'version'        => 1009,
	'author'         => array(
                                "Krzysztof 'Eloy' Krzyżaniak <eloy@wikia-inc.com>",
				"Maciej 'Marooned' Błaszkowski <marooned@wikia-inc.com>",
				"Michał 'Mix' Roszka <mix@wikia-inc.com>"
	),
	'url'            => 'https://github.com/Wikia/app/tree/dev/extensions/wikia/HAWelcome/'
);

/**
 * Command name for the job aka type of the job.
 *
 * @see http://www.mediawiki.org/wiki/Manual:RunJobs.php
 */
define( 'HAWELCOME_JOB_IDENTIFIER', 'HAWelcome' );

/**
 * Global list of hooks.
 *
 * @see http://www.mediawiki.org/wiki/Manual:$wgHooks
 * @see http://www.mediawiki.org/wiki/Manual:Hooks/RevisionInsertComplete
 */
$wgHooks['RevisionInsertComplete'][] = 'HAWelcomeJob::onRevisionInsertComplete';

/**
 * Map the job to its handling class.
 */
$wgJobClasses[HAWELCOME_JOB_IDENTIFIER] = 'HAWelcomeJob';

/**
 *
 * @see http://www.mediawiki.org/wiki/Manual:Job_queue/For_developers
 */
class HAWelcomeJob extends Job {

        /**
         * The default user to send welcome messages.
         */
        const DEFAULT_WELCOMER = 'Wikia';

        /**
         * The id of the recipient.
         */
        public $iRecipientId;

        /**
         * The name of the recipient.
         */
        public $sRecipientName;

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

                // Ignore revisions created in the command-line mode.
                // Otherwise HAWelcome::run() would invoke HAWelcome::onRevisionInsertComplete(), too.
                global $wgCommandLineMode;
                if ( !$wgCommandLineMode ) {

                        // Get the associated Title object.
                        $oTitle = $oRevision->getTitle();

                        // Sometimes, for some reason or other, the Revision object
                        // does not contain the associated Title object. It has to be
                        // recreated based on the associated Page object.
                        if ( !$oTitle ) {
                                Wikia::log( sprintf(
                                        '%s recreated Title for page %d, revision %d, URL %s',
                                        __METHOD__, $oRevision->getPage(),
                                        $oRevision->getId(), $oTitle->getFullURL()
                                ) );
                                $oTitle = Title::newFromId( $oRevision->getPage(), Title::GAID_FOR_UPDATE );
                        }

                        // Parameters for the job:
                        //
                        // iUserId: the id of the user to be welcome (0 if anon)
                        // sUserName: the name of the user to be welcome (IP if anon)
                        $aParams = array(
                                'iUserId' => $oRevision->getRawUser(),
                                'sUserName' => $oRevision->getRawUserText()
                        );

                        // Schedule the job.
                        $oJob = new self( $oTitle, $aParams );
                        $oJob->insert();
                }

                wfProfileOut( __METHOD__ );

                // Always...
                return true; // ... to the single purpose of the moment.
        }

        /**
         * @param $oTitle Object
         * @param $aParams Array
         * @param $iId Integer
         */
        public function __construct( $oTitle, $aParams, $iId = 0 ) {
                wfProfileIn( __METHOD__ );
                $this->iRecipientId = $aParams['iUserId'];
                $this->sRecipientName = $aParams['sUserName'];
                parent::__construct( HAWELCOME_JOB_IDENTIFIER, $oTitle, $aParams, $iId );
                wfProfileOut( __METHOD__ );
        }

        /**
         *
         */
        public function run() {
                wfProfileIn( __METHOD__ );
                /**
                 * Create a sender object.
                 */
                global $wgUser;
                $wgUser = User::newFromName( self::DEFAULT_WELCOMER );

                /**
                 * Create a target page object.
                 */
                $oTargetPage = new Article( User::newFromName( $this->sRecipientName )->getUserPage()->getTalkPage() );

                /**
                 * Put a welcome message onto the target page.
                 */
                $oTargetPage->doEdit( 'HAWelcome Sample Message', 'HAWelcome Log Message', 0 );

                wfProfileOut( __METHOD__ );

                // Always...
                return true; // ... to the single purpose of the moment.
        }
}
