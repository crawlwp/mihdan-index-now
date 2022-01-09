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

	protected function get_api_url(): string {
		return 'https://www.bing.com/indexnow';
	}

	protected function get_bot_useragent(): string {
		return 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)';
	}
}
