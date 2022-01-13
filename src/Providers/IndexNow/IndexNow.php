<?php
/**
 * IndexNow via Yandex.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Providers\IndexNow;

use \Mihdan\IndexNow\IndexNowAbstract;

class IndexNow extends IndexNowAbstract {

	public function get_slug(): string {
		return 'index-now';
	}

	public function get_name(): string {
		return __( 'IndexNow', 'mihdan-index-now' );
	}

	protected function get_api_url(): string {
		return 'https://api.indexnow.org/indexnow';
	}

	protected function get_bot_useragent(): string {
		return 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)';
	}
}
