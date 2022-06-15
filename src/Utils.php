<?php
namespace Mihdan\IndexNow;

use Mihdan\IndexNow\Dependencies\phpseclib3\Crypt\EC\Curves\secp112r1;

class Utils {
	/**
	 * Get full plugin path.
	 *
	 * @return string
	 */
	public static function get_plugin_path(): string {
		return MIHDAN_INDEX_NOW_DIR;
	}

	/**
	 * Get plugin basename.
	 *
	 * @return string
	 */
	public static function get_plugin_basename(): string {
		return MIHDAN_INDEX_NOW_BASENAME;
	}

	/**
	 * Get plugin vesrion.
	 *
	 * @return string
	 */
	public static function get_plugin_version(): string {
		return MIHDAN_INDEX_NOW_VERSION;
	}

	/**
	 * Get plugin file.
	 *
	 * @return string
	 */
	public static function get_plugin_file(): string {
		return MIHDAN_INDEX_NOW_FILE;
	}

	/**
	 * Get plugin URL.
	 *
	 * @return string
	 */
	public static function get_plugin_url(): string {
		return MIHDAN_INDEX_NOW_URL;
	}

	/**
	 * Get plugin asset URL.
	 *
	 * @param string $asset Asset path.
	 *
	 * @return string
	 */
	public static function get_plugin_asset_url( string $asset ): string {
		return self::get_plugin_url() . 'assets/' . $asset;
	}

	/**
	 * Get plugin slug.
	 *
	 * @return string
	 */
	public static function get_plugin_slug(): string {
		return MIHDAN_INDEX_NOW_SLUG;
	}

	/**
	 * Get plugin prefix.
	 *
	 * @return string
	 */
	public static function get_plugin_prefix(): string {
		return MIHDAN_INDEX_NOW_PREFIX;
	}

	/**
	 * Get plugin name.
	 *
	 * @return string
	 */
	public static function get_plugin_name(): string {
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
	public static function generate_key(): string {
		return str_replace( '-', '', wp_generate_uuid4() );
	}

	/**
	 * Check if a string is JSON.
	 *
	 * @param mixed $string Input string.
	 *
	 * @return bool
	 */
	public static function is_json( $string ): bool {
		return is_string( $string ) && is_array( json_decode( $string, true ) ) && ( json_last_error() === JSON_ERROR_NONE );
	}
}
