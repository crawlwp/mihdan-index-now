<?php
namespace Mihdan\IndexNow;

class Utils {
	/**
	 * Get full plugin path.
	 *
	 * @return string
	 */
	public static function get_plugin_path() {
		return MIHDAN_INDEX_NOW_DIR;
	}

	/**
	 * Get plugin vesrion.
	 *
	 * @return string
	 */
	public static function get_plugin_version() {
		return MIHDAN_INDEX_NOW_VERSION;
	}

	/**
	 * Get plugin file.
	 *
	 * @return string
	 */
	public static function get_plugin_file() {
		return MIHDAN_INDEX_NOW_FILE;
	}

	/**
	 * Get plugin slug.
	 *
	 * @return string
	 */
	public static function get_plugin_slug() {
		return MIHDAN_INDEX_NOW_SLUG;
	}

	/**
	 * Get plugin prefix.
	 *
	 * @return string
	 */
	public static function get_plugin_prefix() {
		return MIHDAN_INDEX_NOW_PREFIX;
	}

	/**
	 * Get plugin name.
	 *
	 * @return string
	 */
	public static function get_plugin_name() {
		return MIHDAN_INDEX_NOW_NAME;
	}
}
