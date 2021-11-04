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
delete_option( 'mihdan_index_now_contacts' );
delete_option( 'mihdan_index_now_general' );
