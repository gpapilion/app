<?php

/**
 * Controller to pull WAM data
 *
 * @author Sebastian Marzjan
 */

class WAMApiController extends WikiaApiController {
	const DEFAULT_PAGE_SIZE = 20;
	const MAX_PAGE_SIZE = 20;
	const DEFAULT_AVATAR_SIZE = 28;
	const DEFAULT_WIKI_IMAGE_WIDTH = 150;
	const DEFAULT_WIKI_ADMINS_LIMIT = 5;

	/**
	 * A method to get WAM index (list of wikis with their WAM ranks)
	 *
	 * @requestParam integer $wam_day [OPTIONAL] day for which the WAM scores are displayed. Defaults to yesterday
	 * @requestParam integer $wam_previous_day [OPTIONAL] day from which the difference in WAM scores is calculated.
	 *                             Defaults to day before yesterday
	 * @requestParam integer $vertical_id [OPTIONAL] vertical for which wiki list is to be pulled. By default pulls
	 *                             major verticals (2,3,9 - Gaming, Entertainment, Lifestyle)
	 * @requestParam string $wiki_lang [OPTIONAL] Language code if narrowing the results to specific language. Defaults to null
	 * @requestParam integer $wiki_id [OPTIONAL] Id of specific wiki to pull. Defaults to null
	 * @requestParam string $wiki_word [OPTIONAL] Fragment of url to search for amongst wikis. Defaults to null
	 * @requestParam boolean $fetch_admins [OPTIONAL] Determines if admins of each wiki are to be returned. Defaults to false
	 * @requestParam integer $avatar_size [OPTIONAL] Size of admin avatars in pixels if fetch_admins is enabled
	 * @requestParam boolean $fetch_wiki_images [OPTIONAL] Determines if image of each wiki isto be returned. Defaults to false
	 * @requestParam integer $wiki_image_width [OPTIONAL] Width of wiki image in pixels if fetch_wiki_images is enabled
	 * @requestParam integer $wiki_image_height [OPTIONAL] Height of wiki image in pixels if fetch_wiki_images is enabled. You can pass here -1 to keep aspect ratio
	 * @requestParam string $sort_column [OPTIONAL] Column by which to sort. Allowed values: wam_rank, wam_change. Defaults to WAM score (wam)
	 * @requestParam string $sort_direction [OPTIONAL] Either ASC or DESC. Defaults to ASC
	 * @requestParam integer $offset [OPTIONAL] offset from the beginning of data. Defaults to 0
	 * @requestParam integer $limit [OPTIONAL] limit on fetched number of wikis. Defaults to 20, max 20
	 *
	 * @responseParam array $wam_index The result list of wikis
	 * 	one item from index is an array that contain:
	 * 		wiki_id - wiki id
	 * 		wam - wam score
	 * 		wam_rank - wiki wam rank in whole wam index
	 * 		hub_wam_rank - wiki wam rank within its hub
	 *		peak_wam_rank - the peak WAM Rank achieved by this Wiki
	 * 		peak_hub_wam_rank - peak WAM Rank within its Hub
	 * 		top_1k_days - the number of days that the Wiki has been in the top 1000 Wikis
	 * 		top_1k_weeks - the number of weeks that the Wiki has been in the top 1000 Wikis
	 * 		first_peak - the first date that the Wiki achieved its peak_wam_rank
	 * 		last_peak - the last time that the Wiki was at its peak_wam_rank
	 * 		title - wiki title
	 * 		url - wiki url
	 * 		hub_id - wiki hub id
	 * 		wam_change - wam score change from $wam_previous_day
	 * 		wam_is_new - 1 if wiki wasn't classified on $wam_previous_day, 0 if this wiki was in index
	 * @responseParam array $wam_results_total The total count of wikis available for provided params
	 */
	public function getWAMIndex () {
		$options = array();
		$options['currentTimestamp'] = $this->request->getInt('wam_day', strtotime('00:00 -1 day'));
		$options['previousTimestamp'] = $this->request->getInt('wam_previous_day', $options['currentTimestamp'] - 60 * 60 * 24);
		$options['verticalId'] = $this->request->getInt('vertical_id', null);
		$options['wikiLang'] = $this->request->getVal('wiki_lang', null);
		$options['wikiId'] = $this->request->getInt('wiki_id', null);
		$options['wikiWord'] = $this->request->getVal('wiki_word', null);
		$options['fetchAdmins'] = $this->request->getBool('fetch_admins', false);
		$options['avatarSize'] = $this->request->getInt('avatar_size', self::DEFAULT_AVATAR_SIZE);
		$options['fetchWikiImages'] = $this->request->getBool('fetch_wiki_images', false);
		$options['wikiImageWidth'] = $this->request->getInt('wiki_image_width', self::DEFAULT_WIKI_IMAGE_WIDTH);
		$options['wikiImageHeight'] = $this->request->getInt('wiki_image_height', WikiService::IMAGE_HEIGHT_KEEP_ASPECT_RATIO);
		$options['sortColumn'] = $this->request->getVal('sort_column', 'wam_rank');
		$options['sortDirection'] = $this->request->getVal('sort_direction', 'DESC');
		$options['offset'] = $this->request->getInt('offset', 0);
		$options['limit'] = $this->request->getInt('limit', self::DEFAULT_PAGE_SIZE);

		if ($options['limit'] > self::MAX_PAGE_SIZE) {
			throw new InvalidParameterApiException('limit');
		}

		$wamIndex = WikiaDataAccess::cacheWithLock(
			F::app()->wf->SharedMemcKey(
				'wam_index_table',
				implode(':', $options)
			),
			6 * 60 * 60,
			function () use ($options) {
				$wamService = new WAMService();

				$wamIndex = $wamService->getWamIndex($options);

				if ($options['fetchAdmins']) {
					if (empty($wikiService)) {
						$wikiService = new WikiService();
					}
					foreach ($wamIndex['wam_index'] as &$row) {
						$row['admins'] = $wikiService->getWikiAdmins($row['wiki_id'], $options['avatarSize'], self::DEFAULT_WIKI_ADMINS_LIMIT);
					}
				}
				if ($options['fetchWikiImages']) {
					if (empty($wikiService)) {
						$wikiService = new WikiService();
					}

					$images = $wikiService->getWikiImages(array_keys($wamIndex['wam_index']), $options['wikiImageWidth'], $options['wikiImageHeight']);

					foreach ($wamIndex['wam_index'] as $wiki_id => &$wiki) {
						$wiki['wiki_image'] = (!empty($images[$wiki_id])) ? $images[$wiki_id] : null;
					}
				}

				return $wamIndex;
			}
		);

		$this->response->setVal('wam_index', $wamIndex['wam_index']);
		$this->response->setVal('wam_results_total', $wamIndex['wam_results_total']);
		$this->response->setCacheValidity(
			6 * 60 * 60 /* 6h */,
			6 * 60 * 60 /* 6h */,
			array(
				WikiaResponse::CACHE_TARGET_BROWSER,
				WikiaResponse::CACHE_TARGET_VARNISH
			)
		);

	}
}