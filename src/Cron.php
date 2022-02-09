<?php
namespace Mihdan\IndexNow;

use Mihdan\IndexNow\Logger\Logger;
use Mihdan\IndexNow\Views\WPOSA;

class Cron {
	public const EVENT_NAME = 'mihdan-index-now__clear-log';

	/**
	 * Logger instance.
	 *
	 * @var Logger $logger
	 */
	private $logger;

	/**
	 * WPOSA instance.
	 *
	 * @var WPOSA $wposa
	 */
	private $wposa;

	/**
	 * Cron constructor.
	 *
	 * @param Logger $logger Logger instance.
	 * @param WPOSA  $wposa
	 */
	public function __construct( Logger $logger, WPOSA $wposa ) {
		$this->logger = $logger;
		$this->wposa  = $wposa;
	}
	public function setup_hooks() {
		add_action( 'admin_init', [ $this, 'add_task' ] );
		add_action( self::EVENT_NAME, [ $this, 'clear_log' ] );
	}

	public function add_task() {
		if ( ! wp_next_scheduled( self::EVENT_NAME ) ) {
			wp_schedule_event( time(), 'hourly', self::EVENT_NAME );
		}
	}

	/**
	 * Clear log table.
	 *
	 * @return bool
	 */
	public function clear_log(): bool {
		global $wpdb;

		$lifetime   = $this->wposa->get_option( 'lifetime', 'logs', 1 );
		$table_name = $this->logger->get_logger_table_name();

		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table_name} WHERE DATEDIFF(NOW(), created_at)>=%d", $lifetime )
		);

		if ( $this->wposa->get_option( 'cron_events', 'logs', 'off' ) === 'on' ) {
			$data = [
				'direction' => 'internal',
			];

			$this->logger->info( 'Old log entries were deleted successfully.', $data );
		}

		return true;
	}
}
