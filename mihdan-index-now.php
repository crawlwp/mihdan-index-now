<?php
/**
 * Plugin Name: IndexNow
 * Description: IndexNow is a small WordPress Plugin for quickly notifying search engines whenever their website content is created, updated, or deleted.
 * Version: 2.6.5
 * Author: Collins Agbonghama
 * Author URI: https://w3guy.com/
 * Plugin URI: https://wordpress.org/plugins/mihdan-index-now/
 * GitHub Plugin URI: https://github.com/crawlwp/mihdan-index-now
 * Requires PHP: 7.4
 * Requires at least: 5.9
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @link https://github.com/mihdan/mihdan-index-now
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

use \Mihdan\IndexNow\Dependencies\Auryn\Injector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MIHDAN_INDEX_NOW_VERSION', '2.6.5' );
define( 'MIHDAN_INDEX_NOW_SLUG', 'mihdan-index-now' );
define( 'MIHDAN_INDEX_NOW_PREFIX', 'mihdan_index_now' );
define( 'MIHDAN_INDEX_NOW_NAME', 'IndexNow' );
define( 'MIHDAN_INDEX_NOW_FILE', __FILE__ );
define( 'MIHDAN_INDEX_NOW_DIR', __DIR__ );
define( 'MIHDAN_INDEX_NOW_BASENAME', plugin_basename( __FILE__ ) );
define( 'MIHDAN_INDEX_NOW_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/vendor-prefixed/autoload.php';

( new Main( new Injector() ) )->init();
