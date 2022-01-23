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

// Delete Log table.
$wpdb->query( "DROP TABLE {$wpdb->prefix}index_now_log" ); // phpcs:ignore

// Delete settings.
delete_option( 'mihdan_index_now_general' );
delete_option( 'mihdan_index_now_index_now' );
delete_option( 'mihdan_index_now_bing_webmaster' );
delete_option( 'mihdan_index_now_google_webmaster' );
delete_option( 'mihdan_index_now_yandex_webmaster' );
delete_option( 'mihdan_index_now_logs' );
delete_option( 'mihdan_index_now_version' );
