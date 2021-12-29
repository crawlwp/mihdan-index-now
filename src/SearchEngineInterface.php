<?php
/**
 * Main class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

interface SearchEngineInterface {
	public function get_slug(): string;
	public function get_name(): string;
}
