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

	const ERRORS = [
		'Invalid params'                            => 'Переданы некорректные параметры в теле запроса.',
		'Invalid key'                               => 'Ключ не удалось загрузить или он не подходит к указанным в запросе адресам.',
		'Method not allowed'                        => 'Поддерживаются методы GET и POST.',
		'Invalid key location'                      => 'Параметр keyLocation указан неверно.',
		'Invalid url'                               => 'В запросе указан неверный URL-адрес или переданный ключ не подходит для его обработки.',
		'Key must be at least 8 characters'         => 'Ключ включает в себя меньше 8 символов.',
		'Key must be no longer than 128 characters' => 'Ключ включает в себя больше 128 символов.',
		'Key must consist of a-Z0-9 or \'-\''       => 'Ключ содержит неподходящие символы.',
		'No host provided'                          => 'Отсутствует параметр host в запросе.',
		'No key provided'                           => 'Отсутствует параметр key в запросе.',
		'No more than 10000 urls allowed'           => 'Параметр urlList содержит больше 10 000 URL-адресов.',
		'No url provided'                           => 'Отсутствует параметр url в запросе.',
		'Url list has to be an array'               => 'Отсутствует параметр urlList или он не является массивом.',
		'Url list cannot be empty'                  => 'Передан пустой параметр urlList.',
		'Url has to be an array of string'          => 'Параметр urlList должен содержать данные типа String.',
		'Too Many Requests'                         => 'Превышено количество запросов для одного IP-адреса.',
	];

	/**
	 * Table name for logger.
	 *
	 * @var string $table_name
	 */
	private static $table_name;

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
		self::$table_name = $wpdb->prefix . 'index_now_log';
	}

	public static function get_table_name() {
		return self::$table_name;
	}

	public function setup_hooks() {
		add_action( 'mihdan_index_now/debug', [ $this, 'debug' ] );
		add_action( 'mihdan_index_now/error', [ $this, 'error' ] );
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
	public function debug( $message, $context = array() ) {
		return $this->log( self::DEBUG, $message, $context );
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param string $level Error level.
	 * @param string $message Error message.
	 * @param array  $context Error context.
	 *
	 * @return mixed
	 */
	public function log( $level, $message, array $context = array() ) {

		$defaults = [
			'created_at' => current_time( 'mysql', 1 ),
			'level'      => $level,
			'message'    => $message,
			'direction'  => 'outgoing',
		];

		$context = wp_parse_args( $context, $defaults );

		$data = wp_kses_post_deep( $context );

		$response = $this->wpdb->insert( self::get_table_name(), $data );

		return $response;
	}
}
