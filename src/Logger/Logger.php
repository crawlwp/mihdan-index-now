<?php
namespace Mihdan\IndexNow\Logger;

use Mihdan\IndexNow\Dependencies\Psr\Log\AbstractLogger;
use WP_Post;

class Logger extends AbstractLogger {
	public function get_logger_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'index_now_log';
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
