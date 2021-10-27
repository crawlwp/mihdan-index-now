<?php
/**
 * Plugin Name: Mihdan: Index Now
 * Description: Плагин уведомлений поисковых систем Яндекс/Google/Bing/Cloudflare о появлении новых страниц на сайте по протоколу IndexNow.
 * Version: 1.0.0
 * Author: Mikhail Kobzarev
 * Author URI: https://www.kobzarev.com/
 * Plugin URI: https://github.com/mihdan/mihdan-index-now
 * GitHub Plugin URI: https://github.com/mihdan/mihdan-index-now
 *
 * @link https://github.com/mihdan/mihdan-index-now
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @link https://yandex.ru/support/webmaster/indexnow/key.html
 */
define( 'MIHDAN_INDEX_NOW_VERSION' , '1.0.0' );
define( 'MIHDAN_INDEX_NOW_SlUG' , 'mihdan-index-now' );
define( 'MIHDAN_INDEX_NOW_PREFIX' , 'mihdan_index_now' );
define( 'MIHDAN_INDEX_NOW_NAME' , 'IndexNow' );

require_once __DIR__ . '/src/class-wposa.php';
require_once __DIR__ . '/src/class-settings.php';
require_once __DIR__ . '/src/class-main.php';

( new Main(
	new Settings(
		new WPOSA(
			MIHDAN_INDEX_NOW_NAME,
			MIHDAN_INDEX_NOW_VERSION,
			MIHDAN_INDEX_NOW_SlUG
		)
	)
) )->setup_hooks();
