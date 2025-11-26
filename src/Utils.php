<?php

namespace Mihdan\IndexNow;

use Mihdan\IndexNow\Logger\Logger;

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

		return $_POST[$key] ?? $default;
	}

	public static function _GET_var($key, $default = false, $empty = false)
	{
		$bucket = $_GET;

		if ($empty) return ! empty($bucket[$key]) ? $bucket[$key] : $default;

		return $bucket[$key] ?? $default;
	}

	public static function _var($bucket, $key, $default = false, $empty = false)
	{
		if ($empty) {
			return isset($bucket[$key]) && ( ! empty($bucket[$key]) || self::is_boolean($bucket[$key])) ? $bucket[$key] : $default;
		}

		return $bucket[$key] ?? $default;
	}

	public static function _var_obj($bucket, $key, $default = false, $empty = false)
	{
		if ($empty) {
			return isset($bucket->$key) && ( ! empty($bucket->$key) || self::is_boolean($bucket->$key)) ? $bucket->$key : $default;
		}

		return $bucket->$key ?? $default;
	}

	public static function normalize_url($url)
	{
		$new_domain = apply_filters('crawlwp_normalized_new_url', defined('CRAWLWP_URL') ? CRAWLWP_URL : '');

		if ( ! empty($new_domain)) {
			$url = str_replace(untrailingslashit(home_url()), untrailingslashit($new_domain), $url);
		}

		return $url;
	}

	public static function normalized_home_url()
	{
		return self::normalize_url(home_url());
	}

	public static function normalized_get_permalink($post_id)
	{
		$post_id = $post_id instanceof \WP_Post ? $post_id->ID : $post_id;

		$url = '';

		if (class_exists('\SitePress')) {

			global $sitepress;

			if (is_object($sitepress) && method_exists($sitepress, 'get_default_language')) {

				$post_language = apply_filters('wpml_post_language_details', null, $post_id);

				if ( ! empty($post_language['language_code'])) {

					$target_language = $post_language['language_code'];

					// Get the post ID in the target language
					$translated_post_id = apply_filters('wpml_object_id', $post_id, 'any', false, $target_language);

					if ($translated_post_id) {
						// Switch WPML to the target language context
						$current_language = $sitepress->get_current_language();
						$sitepress->switch_lang($target_language);
						// Get the permalink in the correct language context
						$url = get_permalink($translated_post_id);
						// Switch back to the original language
						$sitepress->switch_lang($current_language);
					}
				}
			}
		}

		if (empty($url)) $url = get_permalink($post_id);

		return self::normalize_url($url);
	}

	public static function normalized_get_term_link($term, $taxonomy = '')
	{
		return self::normalize_url(get_term_link($term, $taxonomy));
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

	public static function wposa_get_option($option, $section, $default = '', $prefix = 'mihdan_index_now')
	{
		$section = str_replace($prefix . '_', '', $section);

		$options = get_option($prefix . '_' . $section);

		if (isset($options[$option])) {
			return apply_filters('wposa/get_option', $options[$option], $option, $section, $default);
		}

		return apply_filters('wposa/get_option', $default, $option, $section, $default);
	}

	/**
	 * @return Logger
	 */
	public static function get_logger()
	{
		return new Logger();
	}
}
