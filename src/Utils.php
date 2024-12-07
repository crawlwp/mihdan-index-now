<?php

namespace Mihdan\IndexNow;

class Utils
{
	/**
	 * Get full plugin path.
	 *
	 * @return string
	 */
	public static function get_plugin_path(): string
	{
		return MIHDAN_INDEX_NOW_DIR;
	}

	/**
	 * Get plugin basename.
	 *
	 * @return string
	 */
	public static function get_plugin_basename(): string
	{
		return MIHDAN_INDEX_NOW_BASENAME;
	}

	/**
	 * Get plugin vesrion.
	 *
	 * @return string
	 */
	public static function get_plugin_version(): string
	{
		return MIHDAN_INDEX_NOW_VERSION;
	}

	/**
	 * Get plugin file.
	 *
	 * @return string
	 */
	public static function get_plugin_file(): string
	{
		return MIHDAN_INDEX_NOW_FILE;
	}

	/**
	 * Get plugin URL.
	 *
	 * @return string
	 */
	public static function get_plugin_url(): string
	{
		return MIHDAN_INDEX_NOW_URL;
	}

	/**
	 * Get plugin asset URL.
	 *
	 * @param string $asset Asset path.
	 *
	 * @return string
	 */
	public static function get_plugin_asset_url(string $asset): string
	{
		return self::get_plugin_url() . 'assets/' . $asset;
	}

	/**
	 * Get plugin slug.
	 *
	 * @return string
	 */
	public static function get_plugin_slug(): string
	{
		return MIHDAN_INDEX_NOW_SLUG;
	}

	/**
	 * Get plugin prefix.
	 *
	 * @return string
	 */
	public static function get_plugin_prefix(): string
	{
		return MIHDAN_INDEX_NOW_PREFIX;
	}

	/**
	 * Get plugin name.
	 *
	 * @return string
	 */
	public static function get_plugin_name(): string
	{
		return MIHDAN_INDEX_NOW_NAME;
	}

	public static function is_response_code_success($code): bool
	{
		return ($code >= 200 && $code < 300);
	}

	/**
	 * Get user agent of browser/bot.
	 *
	 * @return mixed|string
	 */
	public static function get_user_agent(): string
	{
		return wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '');
	}

	/**
	 * Get plugin DB version.
	 *
	 * @return string
	 */
	public static function get_db_version(): string
	{
		return get_option(self::get_plugin_prefix() . '_version', '1.0.0');
	}

	/**
	 * Set plugin DB version.
	 *
	 * @param string $version Given version.
	 *
	 * @return bool
	 */
	public static function set_db_version(string $version): bool
	{
		return update_option(self::get_plugin_prefix() . '_version', $version, false);
	}

	/**
	 * Generate random key.
	 *
	 * @return string
	 */
	public static function generate_key(): string
	{
		return str_replace('-', '', wp_generate_uuid4());
	}

	public static function get_setting_data($section, $option, $default = '')
	{
		$options = get_option(self::get_plugin_prefix() . '_' . $section, []);

		if (isset($options[$option])) {
			return apply_filters('wposa/get_option', $options[$option], $option, $section, $default);
		}

		return apply_filters('wposa/get_option', $default, $option, $section, $default);
	}

	/**
	 * Check if a string is JSON.
	 *
	 * @param mixed $string Input string.
	 *
	 * @return bool
	 */
	public static function is_json($string): bool
	{
		return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() === JSON_ERROR_NONE);
	}

	public static function is_boolean($maybe_bool)
	{
		if (is_bool($maybe_bool)) return true;

		if (is_string($maybe_bool)) {

			$maybe_bool = strtolower($maybe_bool);

			$valid_boolean_values = [
				'false',
				'true',
				'0',
				'1',
			];

			return in_array($maybe_bool, $valid_boolean_values, true);
		}

		if (is_int($maybe_bool)) {
			return in_array($maybe_bool, array(0, 1), true);
		}

		return false;
	}

	public static function _POST_var($key, $default = false, $empty = false)
	{
		if ($empty) return ! empty($_POST[$key]) ? $_POST[$key] : $default;

		return isset($_POST[$key]) ? $_POST[$key] : $default;
	}

	public static function _GET_var($key, $default = false, $empty = false)
	{
		$bucket = $_GET;

		if ($empty) return ! empty($bucket[$key]) ? $bucket[$key] : $default;

		return isset($bucket[$key]) ? $bucket[$key] : $default;
	}

	public static function _var($bucket, $key, $default = false, $empty = false)
	{
		if ($empty) {
			return isset($bucket[$key]) && ( ! empty($bucket[$key]) || self::is_boolean($bucket[$key])) ? $bucket[$key] : $default;
		}

		return isset($bucket[$key]) ? $bucket[$key] : $default;
	}

	public static function _var_obj($bucket, $key, $default = false, $empty = false)
	{
		if ($empty) {
			return isset($bucket->$key) && ( ! empty($bucket->$key) || self::is_boolean($bucket->$key)) ? $bucket->$key : $default;
		}

		return isset($bucket->$key) ? $bucket->$key : $default;
	}

	public static function normalize_url($url)
	{
		if (defined('CRAWLWP_URL')) {
			return str_replace(trailingslashit(home_url()), CRAWLWP_URL, $url);
		}

		return $url;
	}

	/**
	 * Check if an admin settings page is CrawlWP'
	 *
	 * @return bool
	 */
	public static function is_admin_page()
	{
		$pages = [
			MIHDAN_INDEX_NOW_SLUG,
			CRAWLWP_PRO_SEO_INDEX_SLUG,
			CRAWLWP_PRO_SEO_STAT_SLUG
		];

		return (isset($_GET['page']) && in_array($_GET['page'], $pages));
	}

	/**
	 * Return currently viewed page url with query string.
	 *
	 * @return string
	 */
	public static function get_current_url_query_string()
	{
		$protocol = 'http://';

		if ((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1))
		    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
		) {
			$protocol = 'https://';
		}

		$url = $protocol . $_SERVER['HTTP_HOST'];

		$url .= $_SERVER['REQUEST_URI'];

		return esc_url_raw($url);
	}

	public static function clean_data($var, $callback = 'sanitize_textarea_field')
	{
		if (is_array($var)) {
			return array_map([__CLASS__, 'clean_data'], $var);
		} else {
			return is_scalar($var) ? call_user_func($callback, $var) : $var;
		}
	}
}
