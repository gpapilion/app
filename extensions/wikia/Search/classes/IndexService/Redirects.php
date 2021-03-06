<?php
/**
 * Class definition for \Wikia\Search\IndexService\Redirects
 * @author relwell
 */
namespace Wikia\Search\IndexService;
use Wikia\Search\Utilities;
/**
 * Reports on redirect titles for a given page
 * @author relwell
 * @package Search
 * @subpackage IndexService
 */
class Redirects extends AbstractService
{
	/**
	 * Provided an Article, queries the database for all titles that are redirects to that page.
	 * @see \Wikia\Search\IndexService\AbstractService::execute()
	 * @return array
	 */
	public function execute() {
		wfProfileIn(__METHOD__);
		$key = $this->service->getGlobal( 'AppStripsHtml' ) ? Utilities::field( 'redirect_titles' ) : 'redirect_titles';
		$result = array( $key => $this->service->getRedirectTitlesForPageId( $this->currentPageId ) );
		wfProfileOut(__METHOD__);
		return $result;
	}
}