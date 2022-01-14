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
	 * Get plugin basename.
	 *
	 * @return string
	 */
	public static function get_plugin_basename() {
		return MIHDAN_INDEX_NOW_BASENAME;
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

	public static function is_response_code_success( $code ): bool {
		return ( $code >= 200 && $code < 300 );
	}

	/**
	 * Get user agent of browser/bot.
	 *
	 * @return mixed|string
	 */
	public static function get_user_agent(): string {
		return wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	}

	/**
	 * Get plugin DB version.
	 *
	 * @return string
	 */
	public static function get_db_version(): string {
		return get_option( self::get_plugin_prefix() . '_version', '1.0.0' );
	}

	/**
	 * Set plugin DB version.
	 *
	 * @param string $version Given version.
	 * @return bool
	 */
	public static function set_db_version( string $version ): bool {
		return update_option( self::get_plugin_prefix() . '_version', $version, false );
	}

	/**
	 * Generate random key.
	 *
	 * @return string
	 */
	public static function generate_key() {
		return str_replace( '-', '', wp_generate_uuid4() );
	}
}
