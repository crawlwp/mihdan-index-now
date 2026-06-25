<?php

namespace Mihdan\IndexNow;

if ( ! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

include_once(dirname(__FILE__) . '/mihdan-index-now.php');

function crawlwp_lite_mo_uninstall_function()
{
	global $wpdb;

	$drop_tables[] = "DROP TABLE IF EXISTS {$wpdb->prefix}crawlwp_log";
	$drop_tables[] = "DROP TABLE IF EXISTS {$wpdb->prefix}index_now_log";

	foreach ($drop_tables as $tables) {
		$wpdb->query($tables);
	}

	$options = [
		'crawlwp_general',
		'crawlwp_index_now',
		'crawlwp_bing_webmaster',
		'crawlwp_google_webmaster',
		'crawlwp_yandex_webmaster',
		'crawlwp_logs',
		'crawlwp_version',
		'crawlwp_lite_db_ver',
		'crawlwp_google_indexing_rate_limit_expiration',
		'crawlwp_bing_indexing_rate_limit_expiration',
		'crawlwp_yandex_indexing_rate_limit_expiration',
		'crawlwp_yandex_find_website_request_error',
	];

	foreach ($options as $option) {
		delete_site_option($option);
	}

	// ensure leftovers are deleted
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			'crawlwp%'
		)
	);

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			'mihdan_index_now%'
		)
	);

	wp_cache_flush();
}

if ( ! is_multisite()) {
	crawlwp_lite_mo_uninstall_function();
} elseif ( ! wp_is_large_network()) {

	$site_ids = get_sites(['fields' => 'ids', 'number' => 0]);

	foreach ($site_ids as $site_id) {
		switch_to_blog($site_id);
		crawlwp_lite_mo_uninstall_function();
		restore_current_blog();
	}
}
