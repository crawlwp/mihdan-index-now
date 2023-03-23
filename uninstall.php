<?php
/**
 * Settings class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$options = [
	'mihdan_index_now_general',
	'mihdan_index_now_index_now',
	'mihdan_index_now_bing_webmaster',
	'mihdan_index_now_google_webmaster',
	'mihdan_index_now_yandex_webmaster',
	'mihdan_index_now_logs',
	'mihdan_index_now_version',
];

if ( is_multisite() ) {
	// Delete settings.
	foreach( $options as $option ) {
		delete_site_option( $option );
	}

	// Delete Log tables.
	$sites = get_sites( [ 'fields' => 'ids' ] );

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}index_now_log" ); // phpcs:ignore
		restore_current_blog();
	}
} else {
	// Delete settings.
	foreach( $options as $option ) {
		delete_option( $option );
	}

	// Delete Log table.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}index_now_log" ); // phpcs:ignore
}
