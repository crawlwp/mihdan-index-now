<?php
/**
 * Main class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

use Mihdan\IndexNow\Logger\Logger;
use Mihdan\IndexNow\Views\WPOSA;

abstract class WebmasterAbstract implements SearchEngineInterface {
	/**
	 * Logger instance.
	 *
	 * @var Logger $logger
	 */
	protected $logger;

	/**
	 * WPOSA instance.
	 *
	 * @var WPOSA $wposa
	 */
	protected $wposa;

	abstract public function get_token(): string;
	abstract public function get_ping_endpoint(): string;
	abstract public function get_quota(): array;
	abstract public function ping( int $post_id );

	/**
	 * WebmasterAbstract constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger, WPOSA $wposa ) {
		$this->logger = $logger;
		$this->wposa  = $wposa;
	}
}
