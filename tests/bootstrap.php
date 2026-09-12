<?php
/**
 * Test bootstrap.
 *
 * The SEOCore classes that carry real logic (TokenMapper, Graph, AutoLinker …)
 * are plain PHP: they only touch WordPress through a handful of helpers. This
 * bootstrap stubs those helpers so the logic can be unit tested without a
 * WordPress install, and loads the classes under test directly (the plugin's
 * Composer autoloader is not authoritative and vendor/ is stripped in builds).
 *
 * @package mihdan-index-now
 */

define('CRAWLWP_TESTS_DIR', __DIR__);
define('CRAWLWP_TESTS_PLUGIN_DIR', dirname(__DIR__));

/**
 * Values the stubs return. Tests may overwrite entries directly.
 *
 * @var array<string, mixed> $GLOBALS['crawlwp_test_state']
 */
$GLOBALS['crawlwp_test_state'] = [
	'options'   => [],
	'permalink' => 'https://example.test/current-post/',
	'home_url'  => 'https://example.test',
	'printed'   => '',
];

if (!function_exists('apply_filters')) {
	function apply_filters($hook, $value, ...$args)
	{
		return $value;
	}
}

if (!function_exists('add_filter')) {
	function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
	{
		return true;
	}
}

if (!function_exists('add_action')) {
	function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
	{
		return true;
	}
}

if (!function_exists('do_action')) {
	function do_action($hook, ...$args)
	{
	}
}

if (!function_exists('get_option')) {
	function get_option($name, $default = false)
	{
		return $GLOBALS['crawlwp_test_state']['options'][$name] ?? $default;
	}
}

if (!function_exists('update_option')) {
	function update_option($name, $value, $autoload = null)
	{
		$GLOBALS['crawlwp_test_state']['options'][$name] = $value;

		return true;
	}
}

if (!function_exists('get_permalink')) {
	function get_permalink($post = 0, $leavename = false)
	{
		return $GLOBALS['crawlwp_test_state']['permalink'];
	}
}

if (!function_exists('home_url')) {
	function home_url($path = '', $scheme = null)
	{
		return $GLOBALS['crawlwp_test_state']['home_url'] . $path;
	}
}

if (!function_exists('get_queried_object')) {
	function get_queried_object()
	{
		return $GLOBALS['crawlwp_test_state']['queried_object'] ?? null;
	}
}

if (!function_exists('is_admin')) {
	function is_admin()
	{
		return false;
	}
}

/**
 * Conditional tags default to false; a test flips them through
 * $GLOBALS['crawlwp_test_state']['is_singular'] = true and friends.
 */
function crawlwp_test_conditional(string $name): bool
{
	return !empty($GLOBALS['crawlwp_test_state'][$name]);
}

if (!function_exists('is_feed')) {
	function is_feed($feeds = '')
	{
		return crawlwp_test_conditional('is_feed');
	}
}

if (!function_exists('is_robots')) {
	function is_robots()
	{
		return crawlwp_test_conditional('is_robots');
	}
}

if (!function_exists('is_trackback')) {
	function is_trackback()
	{
		return crawlwp_test_conditional('is_trackback');
	}
}

if (!function_exists('is_singular')) {
	function is_singular($post_types = '')
	{
		return crawlwp_test_conditional('is_singular');
	}
}

if (!function_exists('is_home')) {
	function is_home()
	{
		return crawlwp_test_conditional('is_home');
	}
}

if (!function_exists('is_front_page')) {
	function is_front_page()
	{
		return crawlwp_test_conditional('is_front_page');
	}
}

if (!function_exists('in_the_loop')) {
	function in_the_loop()
	{
		return crawlwp_test_conditional('in_the_loop');
	}
}

if (!function_exists('is_main_query')) {
	function is_main_query()
	{
		return crawlwp_test_conditional('is_main_query');
	}
}

if (!function_exists('esc_html')) {
	function esc_html($text)
	{
		return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($text)
	{
		return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('esc_url')) {
	function esc_url($url, $protocols = null, $context = 'display')
	{
		return htmlspecialchars((string)$url, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('esc_url_raw')) {
	function esc_url_raw($url, $protocols = null)
	{
		return (string)$url;
	}
}

if (!function_exists('esc_xml')) {
	function esc_xml($text)
	{
		return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		return $text;
	}
}

if (!function_exists('_x')) {
	function _x($text, $context, $domain = 'default')
	{
		return $text;
	}
}

if (!function_exists('esc_html__')) {
	function esc_html__($text, $domain = 'default')
	{
		return esc_html($text);
	}
}

if (!function_exists('esc_attr__')) {
	function esc_attr__($text, $domain = 'default')
	{
		return esc_attr($text);
	}
}

if (!function_exists('wp_json_encode')) {
	function wp_json_encode($data, $options = 0, $depth = 512)
	{
		return json_encode($data, $options, $depth);
	}
}

if (!function_exists('wp_strip_all_tags')) {
	function wp_strip_all_tags($string, $remove_breaks = false)
	{
		$string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string)$string);

		return trim(strip_tags((string)$string));
	}
}

if (!function_exists('sanitize_title')) {
	function sanitize_title($title, $fallback_title = '', $context = 'save')
	{
		$title = strtolower(preg_replace('/[^a-z0-9\-_ ]/i', '', (string)$title));

		return trim(preg_replace('/[\s_]+/', '-', $title), '-');
	}
}

if (!function_exists('absint')) {
	function absint($number)
	{
		return abs((int)$number);
	}
}

if (!function_exists('trailingslashit')) {
	function trailingslashit($string)
	{
		return rtrim((string)$string, '/\\') . '/';
	}
}

if (!function_exists('untrailingslashit')) {
	function untrailingslashit($string)
	{
		return rtrim((string)$string, '/\\');
	}
}

if (!function_exists('wp_parse_args')) {
	function wp_parse_args($args, $defaults = [])
	{
		return array_merge($defaults, (array)$args);
	}
}

if (!function_exists('_doing_it_wrong')) {
	function _doing_it_wrong($function, $message, $version)
	{
	}
}

if (!function_exists('wp_trigger_error')) {
	function wp_trigger_error($function_name, $message, $error_level = E_USER_NOTICE)
	{
	}
}

if (!function_exists('wp_scrub_utf8')) {
	function wp_scrub_utf8($text)
	{
		return (string) $text;
	}
}

// Load WordPress HTML API when this suite runs inside a WP checkout so
// AutoLinker tests exercise WP_HTML_Processor instead of the DOM fallback.
$wp_includes = dirname(CRAWLWP_TESTS_PLUGIN_DIR, 3) . '/wp-includes';
$html_api    = $wp_includes . '/html-api';

if (!class_exists('WP_HTML_Processor') && is_readable($html_api . '/class-wp-html-processor.php')) {
	if (!class_exists('WP_Token_Map') && is_readable($wp_includes . '/class-wp-token-map.php')) {
		require_once $wp_includes . '/class-wp-token-map.php';
	}

	foreach ([
		'html5-named-character-references.php',
		'class-wp-html-attribute-token.php',
		'class-wp-html-span.php',
		'class-wp-html-doctype-info.php',
		'class-wp-html-text-replacement.php',
		'class-wp-html-decoder.php',
		'class-wp-html-tag-processor.php',
		'class-wp-html-unsupported-exception.php',
		'class-wp-html-active-formatting-elements.php',
		'class-wp-html-open-elements.php',
		'class-wp-html-token.php',
		'class-wp-html-stack-event.php',
		'class-wp-html-processor-state.php',
		'class-wp-html-processor.php',
	] as $file) {
		require_once $html_api . '/' . $file;
	}
}

// Classes under test. Loaded explicitly so the suite never depends on vendor/.
require_once CRAWLWP_TESTS_PLUGIN_DIR . '/src/SEOCore/Importer/TokenMapper.php';
require_once CRAWLWP_TESTS_PLUGIN_DIR . '/src/SEOCore/Schema/Graph.php';
require_once CRAWLWP_TESTS_PLUGIN_DIR . '/src/SEOCore/InternalLinks/AutoLinker.php';
