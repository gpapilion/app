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
                if ( !$wgCommandLineMode ) {

                        // Get the associated Title object.
                        $oTitle = $oRevision->getTitle();

                        // Sometimes, for some reason or other, the Revision object
                        // does not contain the associated Title object. It has to be
                        // recreated based on the associated Page object.
                        if ( !$oTitle ) {
                                //TODO Make the log message more verbose.
                                Wikia::log( __METHOD__ . ' Recreated the Title object.' );
                                $oTitle = Title::newFromId( $oRevision->getPage(), Title::GAID_FOR_UPDATE );
                        }

                        $aParams = array();

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
                parent::__construct( HAWELCOME_JOB_IDENTIFIER, $oTitle, $aParams, $iId );
                wfProfileOut( __METHOD__ );
        }

        /**
         *
         */
        public function run() {
                wfProfileIn( __METHOD__ );
                wfDebug( __METHOD__ . PHP_EOL );
                wfProfileOut( __METHOD__ );
        }
}
