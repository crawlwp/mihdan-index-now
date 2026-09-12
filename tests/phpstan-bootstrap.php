<?php
/**
 * Constants PHPStan needs to analyse the plugin outside a WordPress runtime.
 *
 * @package mihdan-index-now
 */

define('CRAWLWP_FILE', __DIR__ . '/../mihdan-index-now.php');
define('CRAWLWP_VERSION', '0.0.0');
define('CRAWLWP_SLUG', 'crawlwp');
define('CRAWLWP_PREFIX', 'crawlwp');
define('CRAWLWP_PLUGIN_URL', 'https://example.com/wp-content/plugins/mihdan-index-now/');
define('CRAWLWP_PLUGIN_DIR', __DIR__ . '/../');
define('CRAWLWP_SETTINGS_URL', 'https://example.com/wp-admin/admin.php?page=crawlwp');
define('CRAWLWP_API_SETTINGS_URL', CRAWLWP_SETTINGS_URL);
define('CRAWLWP_ADVANCED_SETTINGS_URL', CRAWLWP_SETTINGS_URL);
