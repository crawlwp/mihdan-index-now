<?php

namespace Mihdan\IndexNow\SEOCore\MetaBox;

class MetaFields
{
	public const SEO_TITLE       = '_crawlwp_seo_title';
	public const SEO_DESCRIPTION = '_crawlwp_seo_description';
	public const FOCUS_KEYWORD   = '_crawlwp_focus_keyword';

	public const ROBOTS_INDEX    = '_crawlwp_robots_index';
	public const ROBOTS_FOLLOW   = '_crawlwp_robots_follow';
	public const ROBOTS_ADVANCED = '_crawlwp_robots_advanced';
	public const CANONICAL_URL   = '_crawlwp_canonical_url';
	public const MAX_SNIPPET     = '_crawlwp_max_snippet';
	public const MAX_IMAGE       = '_crawlwp_max_image_preview';

	public const REDIRECT_URL    = '_crawlwp_redirect_url';
	public const REDIRECT_TYPE   = '_crawlwp_redirect_type';

	public const OG_TITLE       = '_crawlwp_og_title';
	public const OG_DESCRIPTION = '_crawlwp_og_description';
	public const OG_IMAGE       = '_crawlwp_og_image';
	public const OG_IMAGE_ALT   = '_crawlwp_og_image_alt';
	public const OG_SYNC        = '_crawlwp_og_sync';

	public const X_TITLE       = '_crawlwp_x_title';
	public const X_DESCRIPTION = '_crawlwp_x_description';
	public const X_IMAGE       = '_crawlwp_x_image';
	public const X_CARD_TYPE   = '_crawlwp_x_card_type';
	public const X_CREATOR     = '_crawlwp_x_creator';
	public const X_SYNC        = '_crawlwp_x_sync';

	public const SCHEMA_PAGE_TYPE    = '_crawlwp_schema_page_type';
	public const SCHEMA_ARTICLE_TYPE = '_crawlwp_schema_article_type';
	public const SCHEMA_TYPE         = '_crawlwp_schema_type'; // legacy — kept for migration
	public const SCHEMA_HEADLINE     = '_crawlwp_schema_headline';
	public const SCHEMA_BREADCRUMB   = '_crawlwp_schema_breadcrumb';
	public const SCHEMA_SECTION      = '_crawlwp_schema_section';
	public const SCHEMA_EXTRA        = '_crawlwp_schema_extra';
	public const SCHEMA_CUSTOM       = '_crawlwp_schema_custom';

	public const PRIMARY_CATEGORY = '_crawlwp_primary_category';
	public const CORNERSTONE      = '_crawlwp_cornerstone';

	/** Cached SEO score (0–100 float stored as string). Updated on each metabox/inline save. */
	public const SEO_SCORE = '_crawlwp_seo_score';

	public const NONCE_ACTION = 'crawlwp_seo_metabox';
	public const NONCE_NAME   = '_crawlwp_seo_nonce';

	private static array $text_fields = [
		self::SEO_TITLE,
		self::FOCUS_KEYWORD,
		self::OG_TITLE,
		self::OG_IMAGE_ALT,
		self::X_TITLE,
		self::X_CREATOR,
		self::SCHEMA_HEADLINE,
		self::SCHEMA_BREADCRUMB,
		self::SCHEMA_SECTION,
	];

	private static array $textarea_fields = [
		self::SEO_DESCRIPTION,
		self::OG_DESCRIPTION,
		self::X_DESCRIPTION,
	];

	private static array $url_fields = [
		self::CANONICAL_URL,
		self::REDIRECT_URL,
	];

	private static array $page_types = [
		'WebPage',
		'ItemPage',
		'AboutPage',
		'FAQPage',
		'QAPage',
		'ProfilePage',
		'ContactPage',
		'MedicalWebPage',
		'CollectionPage',
		'RealEstateListing',
		'none',
	];

	private static array $article_types = [
		'Article',
		'BlogPosting',
		'SocialMediaPosting',
		'NewsArticle',
		'AdvertiserContentArticle',
		'SatiricalArticle',
		'ScholarlyArticle',
		'TechArticle',
		'Report',
		'none',
	];

	/**
	 * Select fields => [allowed values, fallback stored when the submitted value is not allowed].
	 * Must mirror the <option> values in views/metabox-template.php.
	 */
	private static function select_fields(): array
	{
		return [
			self::ROBOTS_INDEX        => [['index', 'noindex'], 'index'],
			self::ROBOTS_FOLLOW       => [['follow', 'nofollow'], 'follow'],
			self::MAX_SNIPPET         => [['', 'none', '160'], ''],
			self::MAX_IMAGE           => [['large', 'standard', 'none'], 'large'],
			self::X_CARD_TYPE         => [['summary_large_image', 'summary'], 'summary_large_image'],
			self::SCHEMA_TYPE         => [array_merge(self::$page_types, self::$article_types), ''],
			self::SCHEMA_PAGE_TYPE    => [self::$page_types, 'WebPage'],
			self::SCHEMA_ARTICLE_TYPE => [self::$article_types, 'Article'],
			self::REDIRECT_TYPE       => [['301', '302', '307', '410'], '301'],
		];
	}

	private static array $robots_advanced_values = [
		'noimageindex',
		'noarchive',
		'nosnippet',
		'notranslate',
	];

	private static array $checkbox_fields = [
		self::OG_SYNC,
		self::X_SYNC,
		self::CORNERSTONE,
	];

	private static array $image_fields = [
		self::OG_IMAGE,
		self::X_IMAGE,
	];

	private static array $array_fields = [
		self::ROBOTS_ADVANCED,
	];

	public static function save(int $post_id): void
	{
		if (
			! isset($_POST[self::NONCE_NAME]) ||
			! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
		) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (wp_is_post_revision($post_id)) {
			return;
		}

		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		foreach (self::$text_fields as $key) {
			if (isset($_POST[$key])) {
				update_post_meta($post_id, $key, sanitize_text_field(wp_unslash($_POST[$key])));
			}
		}

		foreach (self::$textarea_fields as $key) {
			if (isset($_POST[$key])) {
				update_post_meta($post_id, $key, sanitize_textarea_field(wp_unslash($_POST[$key])));
			}
		}

		foreach (self::$url_fields as $key) {
			if (isset($_POST[$key])) {
				$url = self::sanitize_url(wp_unslash($_POST[$key]));

				if ($key === self::REDIRECT_URL && $url !== '' && self::is_external_url($url) && ! self::can_redirect_externally($post_id)) {
					$url = '';
				}

				update_post_meta($post_id, $key, $url);
			}
		}

		foreach (self::select_fields() as $key => [$allowed, $fallback]) {
			if (isset($_POST[$key])) {
				$value = sanitize_text_field(wp_unslash($_POST[$key]));
				update_post_meta($post_id, $key, in_array($value, $allowed, true) ? $value : $fallback);
			}
		}

		foreach (self::$checkbox_fields as $key) {
			update_post_meta($post_id, $key, isset($_POST[$key]) ? '1' : '0');
		}

		foreach (self::$image_fields as $key) {
			if (isset($_POST[$key])) {
				update_post_meta($post_id, $key, absint($_POST[$key]));
			}
		}

		foreach (self::$array_fields as $key) {
			if (isset($_POST[$key]) && is_array($_POST[$key])) {
				$sanitized = array_map('sanitize_text_field', wp_unslash($_POST[$key]));
				$sanitized = array_values(array_intersect($sanitized, self::$robots_advanced_values));
				update_post_meta($post_id, $key, $sanitized);
			} else {
				update_post_meta($post_id, $key, []);
			}
		}

		if (isset($_POST[self::PRIMARY_CATEGORY])) {
			update_post_meta($post_id, self::PRIMARY_CATEGORY, absint($_POST[self::PRIMARY_CATEGORY]));
		}

		if (isset($_POST[self::SCHEMA_CUSTOM])) {
			$custom = trim((string) wp_unslash($_POST[self::SCHEMA_CUSTOM]));
			if ($custom !== '' && json_decode($custom, true) === null && json_last_error() !== JSON_ERROR_NONE) {
				$custom = '';
			}
			update_post_meta($post_id, self::SCHEMA_CUSTOM, $custom);
		}

		update_post_meta($post_id, self::SCHEMA_EXTRA, self::sanitize_schema_extra($_POST['crawlwp_schema_extra'] ?? []));
	}

	/**
	 * @param mixed $raw
	 * @return array<string,mixed>
	 */
	public static function sanitize_schema_extra($raw): array
	{
		if (! is_array($raw)) {
			return [];
		}

		$allowed_types = ['', 'FAQPage', 'HowTo', 'Recipe', 'Event', 'JobPosting', 'Course', 'VideoObject'];
		$type = sanitize_text_field((string) ($raw['extra_type'] ?? ''));

		$out = [
			'extra_type' => in_array($type, $allowed_types, true) ? $type : '',
		];

		$faq = [];
		$questions = is_array($raw['faq_q'] ?? null) ? $raw['faq_q'] : [];
		$answers   = is_array($raw['faq_a'] ?? null) ? $raw['faq_a'] : [];

		foreach ($questions as $i => $q) {
			$q = sanitize_text_field(wp_unslash((string) $q));
			$a = sanitize_textarea_field(wp_unslash((string) ($answers[$i] ?? '')));
			if ($q !== '' && $a !== '') {
				$faq[] = ['question' => $q, 'answer' => $a];
			}
		}

		$out['faq'] = $faq;

		$howto_steps = [];
		if (is_array($raw['howto_steps'] ?? null)) {
			foreach ($raw['howto_steps'] as $step) {
				$step = sanitize_text_field(wp_unslash((string) $step));
				if ($step !== '') {
					$howto_steps[] = ['text' => $step];
				}
			}
		}

		$out['howto'] = [
			'name'  => sanitize_text_field(wp_unslash((string) ($raw['howto_name'] ?? ''))),
			'steps' => $howto_steps,
		];

		$out['recipe'] = [
			'name'        => sanitize_text_field(wp_unslash((string) ($raw['recipe_name'] ?? ''))),
			'description' => sanitize_textarea_field(wp_unslash((string) ($raw['recipe_description'] ?? ''))),
			'prep_time'   => sanitize_text_field(wp_unslash((string) ($raw['recipe_prep'] ?? ''))),
			'cook_time'   => sanitize_text_field(wp_unslash((string) ($raw['recipe_cook'] ?? ''))),
			'ingredients' => array_values(array_filter(array_map('sanitize_text_field', preg_split('/\r\n|\r|\n/', wp_unslash((string) ($raw['recipe_ingredients'] ?? ''))) ?: []))),
		];

		$out['event'] = [
			'name'       => sanitize_text_field(wp_unslash((string) ($raw['event_name'] ?? ''))),
			'start_date' => sanitize_text_field(wp_unslash((string) ($raw['event_start'] ?? ''))),
			'end_date'   => sanitize_text_field(wp_unslash((string) ($raw['event_end'] ?? ''))),
			'location'   => sanitize_text_field(wp_unslash((string) ($raw['event_location'] ?? ''))),
		];

		$out['job'] = [
			'title'        => sanitize_text_field(wp_unslash((string) ($raw['job_title'] ?? ''))),
			'description'  => sanitize_textarea_field(wp_unslash((string) ($raw['job_description'] ?? ''))),
			'organization' => sanitize_text_field(wp_unslash((string) ($raw['job_org'] ?? ''))),
			'date_posted'  => sanitize_text_field(wp_unslash((string) ($raw['job_date'] ?? ''))),
		];

		$out['course'] = [
			'name'        => sanitize_text_field(wp_unslash((string) ($raw['course_name'] ?? ''))),
			'description' => sanitize_textarea_field(wp_unslash((string) ($raw['course_description'] ?? ''))),
			'provider'    => sanitize_text_field(wp_unslash((string) ($raw['course_provider'] ?? ''))),
		];

		$out['video'] = [
			'name'        => sanitize_text_field(wp_unslash((string) ($raw['video_name'] ?? ''))),
			'url'         => esc_url_raw(wp_unslash((string) ($raw['video_url'] ?? ''))),
			'description' => sanitize_textarea_field(wp_unslash((string) ($raw['video_description'] ?? ''))),
			'thumbnail'   => esc_url_raw(wp_unslash((string) ($raw['video_thumb'] ?? ''))),
			'upload_date' => sanitize_text_field(wp_unslash((string) ($raw['video_date'] ?? ''))),
		];

		return $out;
	}

	/**
	 * Comma-separated focus keywords; first is the primary.
	 *
	 * @return string[]
	 */
	public static function keywords(int $post_id): array
	{
		$raw = (string) self::get($post_id, self::FOCUS_KEYWORD, '');

		if ($raw === '') {
			return [];
		}

		$parts = array_map('trim', explode(',', $raw));

		return array_values(array_filter($parts, static function ($v) {
			return $v !== '';
		}));
	}

	/**
	 * Returns an absolute http(s) URL or a site-relative path (single leading '/'), else ''.
	 */
	public static function sanitize_url($raw): string
	{
		if (! is_string($raw)) {
			return '';
		}

		$url = esc_url_raw(trim($raw));

		if ($url === '') {
			return '';
		}

		if ($url[0] === '/') {
			return (isset($url[1]) && $url[1] === '/') ? '' : $url;
		}

		$parts = wp_parse_url($url);

		if (
			! is_array($parts) ||
			empty($parts['scheme']) ||
			empty($parts['host']) ||
			! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
		) {
			return '';
		}

		return $url;
	}

	/**
	 * True when the (already sanitized) URL points to a host other than the site's own.
	 */
	public static function is_external_url(string $url): bool
	{
		if ($url === '' || $url[0] === '/') {
			return false;
		}

		$host = wp_parse_url($url, PHP_URL_HOST);
		$home = wp_parse_url(home_url(), PHP_URL_HOST);

		if (! is_string($host) || ! is_string($home)) {
			return true;
		}

		return strtolower($host) !== strtolower($home);
	}

	private static function can_redirect_externally(int $post_id): bool
	{
		/**
		 * Filters whether the current user may store a redirect to an external host.
		 *
		 * @param bool $allowed Defaults to current_user_can('manage_options').
		 * @param int  $post_id The post being saved.
		 */
		return (bool) apply_filters('crawlwp_allow_external_redirects', current_user_can('manage_options'), $post_id);
	}

	public static function get(int $post_id, string $key, $default = '')
	{
		$value = get_post_meta($post_id, $key, true);

		return $value !== '' ? $value : $default;
	}

	public static function get_all(int $post_id): array
	{
		return [
			'seo_title'           => self::get($post_id, self::SEO_TITLE),
			'seo_description'     => self::get($post_id, self::SEO_DESCRIPTION),
			'focus_keyword'       => self::get($post_id, self::FOCUS_KEYWORD),
			'robots_index'        => self::get($post_id, self::ROBOTS_INDEX, 'index'),
			'robots_follow'       => self::get($post_id, self::ROBOTS_FOLLOW, 'follow'),
			'robots_advanced'     => self::get($post_id, self::ROBOTS_ADVANCED, []),
			'canonical_url'       => self::get($post_id, self::CANONICAL_URL),
			'max_snippet'         => self::get($post_id, self::MAX_SNIPPET, ''),
			'max_image'           => self::get($post_id, self::MAX_IMAGE, 'large'),
			'og_title'            => self::get($post_id, self::OG_TITLE),
			'og_description'      => self::get($post_id, self::OG_DESCRIPTION),
			'og_image'            => self::get($post_id, self::OG_IMAGE, 0),
			'og_image_alt'        => self::get($post_id, self::OG_IMAGE_ALT),
			'og_sync'             => self::get($post_id, self::OG_SYNC, '1'),
			'x_title'             => self::get($post_id, self::X_TITLE),
			'x_description'       => self::get($post_id, self::X_DESCRIPTION),
			'x_image'             => self::get($post_id, self::X_IMAGE, 0),
			'x_card_type'         => self::get($post_id, self::X_CARD_TYPE, 'summary_large_image'),
			'x_creator'           => self::get($post_id, self::X_CREATOR),
			'x_sync'              => self::get($post_id, self::X_SYNC, '1'),
			'schema_page_type'    => self::get($post_id, self::SCHEMA_PAGE_TYPE, 'WebPage'),
			'schema_article_type' => self::get($post_id, self::SCHEMA_ARTICLE_TYPE, 'Article'),
			'schema_type'         => self::get($post_id, self::SCHEMA_TYPE, ''), // legacy
			'schema_headline'     => self::get($post_id, self::SCHEMA_HEADLINE),
			'schema_breadcrumb'   => self::get($post_id, self::SCHEMA_BREADCRUMB),
			'schema_section'      => self::get($post_id, self::SCHEMA_SECTION),
			'schema_extra'        => self::get($post_id, self::SCHEMA_EXTRA, []),
			'schema_custom'       => self::get($post_id, self::SCHEMA_CUSTOM),
			'primary_category'    => (int) self::get($post_id, self::PRIMARY_CATEGORY, 0),
			'cornerstone'         => self::get($post_id, self::CORNERSTONE, '0'),
			'redirect_url'        => self::get($post_id, self::REDIRECT_URL),
			'redirect_type'       => self::get($post_id, self::REDIRECT_TYPE, '301'),
		];
	}
}
