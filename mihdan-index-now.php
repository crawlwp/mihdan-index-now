<?php
/**
 * Plugin Name: Index Now
 * Description: Плагин уведомлений поисковых систем Яндекс/Google/Bing/Cloudflare о появлении новых страниц на сайте по протоколу IndexNow.
 * Version: 2.3.0
 * Author: Mikhail Kobzarev
 * Author URI: https://www.kobzarev.com/
 * Plugin URI: https://wordpress.org/plugins/mihdan-index-now/
 * GitHub Plugin URI: https://github.com/mihdan/mihdan-index-now
 *
 * @link https://github.com/mihdan/mihdan-index-now
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

use Auryn\Injector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MIHDAN_INDEX_NOW_VERSION', '2.3.0' );
define( 'MIHDAN_INDEX_NOW_SLUG', 'mihdan-index-now' );
define( 'MIHDAN_INDEX_NOW_PREFIX', 'mihdan_index_now' );
define( 'MIHDAN_INDEX_NOW_NAME', 'IndexNow' );
define( 'MIHDAN_INDEX_NOW_FILE', __FILE__ );
define( 'MIHDAN_INDEX_NOW_DIR', __DIR__ );
define( 'MIHDAN_INDEX_NOW_BASENAME', plugin_basename( __FILE__ ) );
define( 'MIHDAN_INDEX_NOW_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';

( new Main( new Injector() ) )->init();
