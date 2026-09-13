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

if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
	define('HOUR_IN_SECONDS', 3600);
}
if (!defined('DAY_IN_SECONDS')) {
	define('DAY_IN_SECONDS', 86400);
}

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

if (!function_exists('add_rewrite_rule')) {
	function add_rewrite_rule($regex, $query, $after = 'bottom')
	{
		$GLOBALS['crawlwp_test_state']['rewrite_rules'][$regex] = $query;
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($str)
	{
		return trim(strip_tags((string)$str));
	}
}

if (!function_exists('get_query_var')) {
	function get_query_var($var, $default = '')
	{
		return $GLOBALS['crawlwp_test_state']['query_vars'][$var] ?? $default;
	}
}

if (!function_exists('get_language_attributes')) {
	function get_language_attributes($doctype = 'html')
	{
		return 'lang="en-US"';
	}
}

if (!function_exists('is_rtl')) {
	function is_rtl()
	{
		return false;
	}
}

if (!function_exists('wp_sitemaps_get_max_urls')) {
	function wp_sitemaps_get_max_urls($object_type)
	{
		return 2000;
	}
}

if (!function_exists('wp_register_sitemap_provider')) {
	function wp_register_sitemap_provider($name, $provider)
	{
		$GLOBALS['crawlwp_test_state']['sitemap_providers'][$name] = $provider;
		return true;
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

if (!class_exists('WP_Sitemaps_Provider')) {
	$sitemaps_provider = dirname(CRAWLWP_TESTS_PLUGIN_DIR, 3) . '/wp-includes/sitemaps/providers/class-wp-sitemaps-provider.php';
	if (is_readable($sitemaps_provider)) {
		require_once $sitemaps_provider;
	} else {
		abstract class WP_Sitemaps_Provider {
			public $name;
			public $object_type;
			abstract public function get_url_list($page_num, $object_subtype = '');
			abstract public function get_max_num_pages($object_subtype = '');
		}
	}
}

if (!class_exists('WP_Sitemaps_Stylesheet')) {
	$sitemaps_stylesheet = dirname(CRAWLWP_TESTS_PLUGIN_DIR, 3) . '/wp-includes/sitemaps/class-wp-sitemaps-stylesheet.php';
	if (is_readable($sitemaps_stylesheet)) {
		require_once $sitemaps_stylesheet;
	}
}

if (!function_exists('wp_trim_words')) {
	function wp_trim_words($text, $num_words = 55, $more = null)
	{
		if ($more === null) {
			$more = '&hellip;';
		}
		$words_array = preg_split("/[\n\r\t ]+/", (string)$text, $num_words + 1, PREG_SPLIT_NO_EMPTY);
		if (is_array($words_array) && count($words_array) > $num_words) {
			array_pop($words_array);
			$text = implode(' ', $words_array);
			$text = $text . $more;
		} elseif (is_array($words_array)) {
			$text = implode(' ', $words_array);
		}
		return (string)$text;
	}
}

if (!function_exists('get_the_title')) {
	function get_the_title($post = 0)
	{
		if (is_object($post) && isset($post->post_title)) {
			return (string)$post->post_title;
		}
		return $GLOBALS['crawlwp_test_state']['title'] ?? '';
	}
}

if (!function_exists('get_the_post_thumbnail_url')) {
	function get_the_post_thumbnail_url($post = null, $size = 'post-thumbnail')
	{
		$post_id = is_object($post) && isset($post->ID) ? (int)$post->ID : (int)$post;
		return $GLOBALS['crawlwp_test_state']['post_thumbnail_url'][$post_id] ?? '';
	}
}

if (!function_exists('get_post_meta')) {
	function get_post_meta($post_id, $key = '', $single = false)
	{
		$meta = $GLOBALS['crawlwp_test_state']['post_meta'][$post_id][$key] ?? null;
		if ($single) {
			return $meta ?? '';
		}
		return $meta !== null ? [$meta] : [];
	}
}

if (!function_exists('update_post_meta')) {
	function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '')
	{
		$GLOBALS['crawlwp_test_state']['post_meta'][$post_id][$meta_key] = $meta_value;
		return true;
	}
}

if (!function_exists('delete_post_meta')) {
	function delete_post_meta($post_id, $meta_key, $meta_value = '')
	{
		unset($GLOBALS['crawlwp_test_state']['post_meta'][$post_id][$meta_key]);
		return true;
	}
}

if (!function_exists('get_post_types')) {
	function get_post_types($args = [], $output = 'names', $operator = 'and')
	{
		return ['post' => 'post', 'page' => 'page'];
	}
}

if (!function_exists('wp_is_post_revision')) {
	function wp_is_post_revision($post)
	{
		return false;
	}
}

if (!function_exists('wp_is_post_autosave')) {
	function wp_is_post_autosave($post)
	{
		return false;
	}
}

if (!function_exists('get_post')) {
	function get_post($post = null)
	{
		if ($post instanceof WP_Post) {
			return $post;
		}
		return $GLOBALS['crawlwp_test_state']['posts'][$post] ?? null;
	}
}

if (!function_exists('get_transient')) {
	function get_transient($transient)
	{
		return $GLOBALS['crawlwp_test_state']['transients'][$transient] ?? false;
	}
}

if (!function_exists('set_transient')) {
	function set_transient($transient, $value, $expiration = 0)
	{
		$GLOBALS['crawlwp_test_state']['transients'][$transient] = $value;
		return true;
	}
}

if (!function_exists('delete_transient')) {
	function delete_transient($transient)
	{
		unset($GLOBALS['crawlwp_test_state']['transients'][$transient]);
		return true;
	}
}

if (!function_exists('wp_kses_post')) {
	function wp_kses_post($data)
	{
		return (string)$data;
	}
}

if (!function_exists('update_meta_cache')) {
	function update_meta_cache($meta_type, $object_ids)
	{
		return true;
	}
}

if (!class_exists('WP_Query')) {
	class WP_Query
	{
		public $posts = [];

		public function __construct(array $args = [])
		{
			$this->posts = $GLOBALS['crawlwp_test_state']['wp_query_posts'] ?? [];
		}
	}
}

if (!class_exists('WP_Post')) {
	class WP_Post
	{
		public $ID = 0;
		public $post_title = '';
		public $post_content = '';
		public $post_excerpt = '';
		public $post_status = 'publish';
		public $post_type = 'post';
		public $post_password = '';

		/**
		 * @param array<string, mixed>|object $data
		 */
		public function __construct($data = [])
		{
			foreach ((array) $data as $k => $v) {
				$this->$k = $v;
			}
		}
	}
}

// Classes under test. Loaded explicitly so the suite never depends on vendor/.
require_once CRAWLWP_TESTS_PLUGIN_DIR . '/src/SEOCore/Importer/TokenMapper.php';
require_once CRAWLWP_TESTS_PLUGIN_DIR . '/src/SEOCore/Schema/Graph.php';
require_once CRAWLWP_TESTS_PLUGIN_DIR . '/src/SEOCore/InternalLinks/AutoLinker.php';
require_once CRAWLWP_TESTS_PLUGIN_DIR . '/src/SEOCore/MetaBox/MetaFields.php';
require_once CRAWLWP_TESTS_PLUGIN_DIR . '/src/SEOCore/SettingsFieldsTrait.php';
require_once CRAWLWP_TESTS_PLUGIN_DIR . '/src/SEOCore/SitemapSettings/SitemapSettings.php';
require_once CRAWLWP_TESTS_PLUGIN_DIR . '/src/SEOCore/SitemapSettings/SitemapStylesheet.php';
require_once CRAWLWP_TESTS_PLUGIN_DIR . '/src/SEOCore/SitemapSettings/CustomUrlsSitemapProvider.php';
require_once CRAWLWP_TESTS_PLUGIN_DIR . '/src/SEOCore/SitemapSettings/VideoSitemapProvider.php';
