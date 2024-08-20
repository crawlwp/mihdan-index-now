<?php
/**
 * Plugin Name: CrawlWP - Index Now SEO
 * Description: IndexNow is a small WordPress Plugin for quickly notifying search engines whenever their website content is created, updated, or deleted.
 * Version: 3.0.0
 * Author: CrawlWP
 * Author URI: https://crawlwp.com/
 * Plugin URI: https://crawlwp.com/
 * Requires PHP: 7.4
 * Requires at least: 5.9
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Mihdan\IndexNow;

use \Mihdan\IndexNow\Dependencies\Auryn\Injector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MIHDAN_INDEX_NOW_VERSION', '3.0.0' );
define( 'MIHDAN_INDEX_NOW_SLUG', 'mihdan-index-now' );
define( 'MIHDAN_INDEX_NOW_SETTINGS_URL', admin_url( 'admin.php?page=' . MIHDAN_INDEX_NOW_SLUG ) );
define( 'MIHDAN_INDEX_NOW_PREFIX', 'mihdan_index_now' );
define( 'MIHDAN_INDEX_NOW_NAME', 'CrawlWP' );
define( 'MIHDAN_INDEX_NOW_FILE', __FILE__ );
define( 'MIHDAN_INDEX_NOW_DIR', __DIR__ );
define( 'MIHDAN_INDEX_NOW_BASENAME', plugin_basename( __FILE__ ) );
define( 'MIHDAN_INDEX_NOW_URL', plugin_dir_url( __FILE__ ) );

define('CRAWLWP_PRO_SEO_INDEX_SLUG', 'mihdan-seo-index');
define('CRAWLWP_PRO_SEO_STAT_SLUG', 'mihdan-seo-stats');
define('CRAWLWP_PRO_AUTO_INDEX_PAGE', admin_url('admin.php?page=' . CRAWLWP_PRO_SEO_INDEX_SLUG));
define('CRAWLWP_PRO_SEO_STAT_PAGE', admin_url('admin.php?page=' . CRAWLWP_PRO_SEO_STAT_SLUG));

require_once __DIR__ . '/vendor-prefixed/autoload.php';

( new Main( new Injector() ) )->init();
