<?php

namespace Mihdan\IndexNow\SEOCore\MetaBox;

class MetaFields
{
	public const SEO_TITLE       = '_crawlwp_seo_title';
	public const SEO_DESCRIPTION = '_crawlwp_seo_description';
	public const FOCUS_KEYWORD   = '_crawlwp_focus_keyword';
	public const SEO_SLUG        = '_crawlwp_seo_slug';

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

	/** Cached SEO score (0–100 float stored as string). Updated on each metabox/inline save. */
	public const SEO_SCORE = '_crawlwp_seo_score';

	public const NONCE_ACTION = 'crawlwp_seo_metabox';
	public const NONCE_NAME   = '_crawlwp_seo_nonce';

	private static array $text_fields = [
		self::SEO_TITLE,
		self::SEO_DESCRIPTION,
		self::FOCUS_KEYWORD,
		self::SEO_SLUG,
		self::CANONICAL_URL,
		self::OG_TITLE,
		self::OG_DESCRIPTION,
		self::X_TITLE,
		self::X_DESCRIPTION,
		self::X_CREATOR,
		self::SCHEMA_HEADLINE,
		self::SCHEMA_BREADCRUMB,
		self::SCHEMA_SECTION,
		self::REDIRECT_URL,
	];

	private static array $select_fields = [
		self::ROBOTS_INDEX,
		self::ROBOTS_FOLLOW,
		self::MAX_SNIPPET,
		self::MAX_IMAGE,
		self::X_CARD_TYPE,
		self::SCHEMA_TYPE,
		self::SCHEMA_PAGE_TYPE,
		self::SCHEMA_ARTICLE_TYPE,
		self::REDIRECT_TYPE,
	];

	private static array $checkbox_fields = [
		self::OG_SYNC,
		self::X_SYNC,
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

		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		foreach (self::$text_fields as $key) {
			if (isset($_POST[$key])) {
				update_post_meta($post_id, $key, sanitize_text_field(wp_unslash($_POST[$key])));
			}
		}

		foreach (self::$select_fields as $key) {
			if (isset($_POST[$key])) {
				update_post_meta($post_id, $key, sanitize_text_field(wp_unslash($_POST[$key])));
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
				update_post_meta($post_id, $key, $sanitized);
			} else {
				update_post_meta($post_id, $key, []);
			}
		}
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
			'seo_slug'            => self::get($post_id, self::SEO_SLUG),
			'robots_index'        => self::get($post_id, self::ROBOTS_INDEX, 'index'),
			'robots_follow'       => self::get($post_id, self::ROBOTS_FOLLOW, 'follow'),
			'robots_advanced'     => self::get($post_id, self::ROBOTS_ADVANCED, []),
			'canonical_url'       => self::get($post_id, self::CANONICAL_URL),
			'max_snippet'         => self::get($post_id, self::MAX_SNIPPET, ''),
			'max_image'           => self::get($post_id, self::MAX_IMAGE, 'large'),
			'og_title'            => self::get($post_id, self::OG_TITLE),
			'og_description'      => self::get($post_id, self::OG_DESCRIPTION),
			'og_image'            => self::get($post_id, self::OG_IMAGE, 0),
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
			'redirect_url'        => self::get($post_id, self::REDIRECT_URL),
			'redirect_type'       => self::get($post_id, self::REDIRECT_TYPE, '301'),
		];
	}
}
