<?php
/**
 * Logger class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

use wpdb;

/**
 * Logger class.
 */
class Logger {
	/**
	 * Describes log levels.
	 */

	const EMERGENCY = 'emergency';

	const ALERT = 'alert';

	const CRITICAL = 'critical';

	const ERROR = 'error';

	const WARNING = 'warning';

	const NOTICE = 'notice';

	const INFO = 'info';

	const DEBUG = 'debug';

	/**
	 * Table name for logger.
	 *
	 * @var string $table_name
	 */
	private $table_name;

	/**
	 * Instance of wpdb.
	 *
	 * @var wpdb $wpdb
	 */
	private $wpdb;

	/**
	 * Logger constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . '_index_now_log';
	}

	public function setup_hooks() {

	}

	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return bool
	 */
	public function emergency( $message, array $context = array() ) {
		return $this->log( self::EMERGENCY, $message, $context );
	}

	/**
	 * Action must be taken immediately.
	 *
	 * Example: Entire website down, database unavailable, etc. This should
	 * trigger the SMS alerts and wake you up.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return bool
	 */
	public function alert( $message, array $context = array() ) {
		return $this->log( self::ALERT, $message, $context );
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return bool
	 */
	public function critical( $message, array $context = array() ) {
		return $this->log( self::CRITICAL, $message, $context );
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return bool
	 */
	public function error( $message, array $context = array() ) {
		return $this->log( self::ERROR, $message, $context );
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return bool
	 */
	public function warning( $message, array $context = array() ) {
		return $this->log( self::WARNING, $message, $context );
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return bool
	 */
	public function notice( $message, array $context = array() ) {
		return $this->log( self::NOTICE, $message, $context );
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return bool
	 */
	public function info( $message, array $context = array() ) {
		return $this->log( self::INFO, $message, $context );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return bool
	 */
	public function debug( $message, array $context = array() ) {
		return $this->log( self::DEBUG, $message, $context );
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 *
	 * @return bool
	 */
	public function log( $level, $message, array $context = array() ) {
		$this->wpdb->insert(
			$this->table_name,
			[
				''
			]
		);
	}
}
