<?php
/**
 * Plugin Name: Mihdan: Index Now
 * Description: Плагин уведомлений поисковых систем Яндекс/Google/Bing/Cloudflare о появлении новых страниц на сайте по протоколу IndexNow.
 * Version: 1.1.5
 * Author: Mikhail Kobzarev
 * Author URI: https://www.kobzarev.com/
 * Plugin URI: https://wordpress.org/plugins/mihdan-index-now/
 * GitHub Plugin URI: https://github.com/mihdan/mihdan-index-now
 *
 * @link https://github.com/mihdan/mihdan-index-now
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MIHDAN_INDEX_NOW_VERSION', '1.1.5' );
define( 'MIHDAN_INDEX_NOW_SLUG', 'mihdan-index-now' );
define( 'MIHDAN_INDEX_NOW_PREFIX', 'mihdan_index_now' );
define( 'MIHDAN_INDEX_NOW_NAME', 'IndexNow' );
define( 'MIHDAN_INDEX_NOW_FILE', __FILE__ );
define( 'MIHDAN_INDEX_NOW_BASENAME', plugin_basename( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';

$logger = new Logger();
$logger->setup_hooks();

$wposa = new WPOSA(
	MIHDAN_INDEX_NOW_NAME,
	MIHDAN_INDEX_NOW_VERSION,
	MIHDAN_INDEX_NOW_SLUG
);
$wposa->setup_hooks();

$settings = new Settings( $wposa );
$settings->setup_hooks();

( new Main( $logger, $settings ) )->setup_hooks();
