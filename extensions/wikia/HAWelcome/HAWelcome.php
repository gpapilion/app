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
 * Global list of hooks.
 *
 * @see http://www.mediawiki.org/wiki/Manual:$wgHooks
 * @see http://www.mediawiki.org/wiki/Manual:Hooks/RevisionInsertComplete
 */
$wgHooks['RevisionInsertComplete'][] = 'HAWelcomeJob::onRevisionInsertComplete';

/**
 *
 * @see http://www.mediawiki.org/wiki/Manual:Job_queue/For_developers
 */
class HAWelcomeJob extends Job {

        /**
         *
         * @see http://www.mediawiki.org/wiki/Manual:$wgHooks
         * @see http://www.mediawiki.org/wiki/Manual:Hooks/RevisionInsertComplete
         */
        public static function onRevisionInsertComplete( &$revision, $data, $flags ) {
                wfProfileIn( __METHOD__ );
		wfDebug( __METHOD__ );
                wfProfileOut( __METHOD__ );
                // Always...
                return true; // ... to the single purpose of the moment.
        }

        /**
         *
         */
        public function run() {}
}

/*

    &$revision: the Revision object
    $data: URL to external object
    $flags: flags for this revision

*/