<?php
/**
 * IndexNow via Yandex.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Providers\Yandex;

use \Mihdan\IndexNow\IndexNowAbstract;

class YandexIndexNow extends IndexNowAbstract {

	public function get_slug(): string {
		return 'yandex-index-now';
	}

	public function get_name(): string {
		return __( 'Yandex IndexNow', 'mihdan-index-now' );
	}

	public function get_api_url(): string {
		return 'https://yandex.com/indexnow';
	}
}
