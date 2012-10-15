<?php
/**
 * How to run this script:
 * $ cd maintenance/wikia/ImageReview/PromoteImage/upload.php
 * To upload an image on wikia.com (from wookiepedia [wikiid=147]) as a WikiaBot:
 * $ SERVER_ID=80433 php upload.php --conf /usr/wikia/docroot/wiki.factory/LocalSettings.php --originalimageurl="http://images.nandy.wikia-dev.com/__cb20120524124212/starwars/images/6/6b/Wikia-Visualization-Add-3.jpg" --destimagename="Wikia-Visualization-Add-3,starwars.jpg" --userid=4663069 --wikiid=147
 */
$dir = dirname(__FILE__) . '/';
$cmdLineScript = realpath($dir . '../../../commandLine.inc');
require_once($cmdLineScript);

$imageUrl = $options['originalimageurl'];
$destImageName = $options['destimagename'];
$sourceWikiId = intval($options['wikiid']);

//fb#45624
$user = F::build('User', array('WikiaBot'), 'newFromName');

if( !($user instanceof User) ) {
	echo 'ERROR: Could not get bot user object'."\n";
	exit(2);
}

if( empty($imageUrl) ) {
	echo 'ERROR: Invalid original image url'."\n";
	exit(3);
}

if( empty($destImageName) ) {
	echo 'ERROR: Invalid destination name'."\n";
	exit(4);
}

if( $sourceWikiId <= 0 ) {
	echo 'ERROR: Invalid source wiki id'."\n";
	exit(5);
}

$imageData = new stdClass();
$imageData->name = $destImageName;
$imageData->description = $imageData->comment = wfMsg('wikiahome-image-auto-uploaded-comment');

$result = ImagesService::uploadImageFromUrl($imageUrl, $imageData, $user);

if( $result['status'] === true ) {
	echo json_encode(array('id' => $result['page_id'], 'name' => $destImageName));
	exit(0);
} else {
	echo 'ERROR: Something went wrong with uploading the image.'."\n";
	print_r($result['errors']);
	exit(7);
}
