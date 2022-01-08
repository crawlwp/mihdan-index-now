<?php
/**
 * IndexNow via Bing.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Providers\Bing;

use \Mihdan\IndexNow\IndexNowAbstract;

class BingIndexNow extends IndexNowAbstract {

	public function get_slug(): string {
		return 'bing-index-now';
	}

	public function get_name(): string {
		return __( 'Bing IndexNow', 'mihdan-index-now' );
	}

	public function get_api_url(): string {
		return 'https://www.bing.com/indexnow';
	}
}
