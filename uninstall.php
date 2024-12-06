<?php

namespace Mihdan\IndexNow;

if ( ! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

include_once(dirname(__FILE__) . '/mihdan-index-now.php');

function crawlwp_lite_mo_uninstall_function()
{
	global $wpdb;

	$drop_tables[] = "DROP TABLE IF EXISTS {$wpdb->prefix}index_now_log";

	foreach ($drop_tables as $tables) {
		$wpdb->query($tables);
	}

	$options = [
		'mihdan_index_now_general',
		'mihdan_index_now_index_now',
		'mihdan_index_now_bing_webmaster',
		'mihdan_index_now_google_webmaster',
		'mihdan_index_now_yandex_webmaster',
		'mihdan_index_now_logs',
		'mihdan_index_now_version',
	];

	foreach ($options as $option) {
		delete_site_option($option);
	}

	// ensure leftovers are deleted
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
