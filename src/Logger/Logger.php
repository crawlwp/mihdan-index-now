<?php
namespace Mihdan\IndexNow\Logger;

use Psr\Log\AbstractLogger;
use WP_Post;

class Logger extends AbstractLogger {
	public function get_logger_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'index_now_log';
	}

	public function setup_hooks() {
		add_action( 'after_delete_post', [ $this, 'maybe_delete_log_entries' ], 10, 2 );
	}

	/**
	 * Delete log entries fired when on post delete.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Instance of WP_Post.
	 */
	public function maybe_delete_log_entries( int $post_id, WP_Post $post ) {
		global $wpdb;

		$wpdb->delete( $this->get_logger_table_name(), [ 'post_id' => $post_id ] );
	}

	public function log( $level, $message, array $context = [] ) {
		global $wpdb;

		$defaults = [
			'created_at'    => current_time( 'mysql', 1 ),
			'level'         => $level,
			'message'       => $message,
			'search_engine' => 'site',
			'direction'     => 'outgoing',
			'status_code'   => 200,
		];

		$context = (array) wp_parse_args( $context, $defaults );

		$data = wp_kses_post_deep( $context );

		$wpdb->insert( $this->get_logger_table_name(), $data );
	}
}
