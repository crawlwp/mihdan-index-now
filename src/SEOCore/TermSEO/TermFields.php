<?php

namespace Mihdan\IndexNow\SEOCore\TermSEO;

use Mihdan\IndexNow\SEOCore\MetaBox\MetaFields;

/**
 * Per-term SEO values stored with the same meta keys as posts.
 */
class TermFields
{
	public static function get(int $term_id, string $key, $default = '')
	{
		$value = get_term_meta($term_id, $key, true);

		return $value !== '' ? $value : $default;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get_all(int $term_id): array
	{
		return [
			'seo_title'       => self::get($term_id, MetaFields::SEO_TITLE),
			'seo_description' => self::get($term_id, MetaFields::SEO_DESCRIPTION),
			'robots_index'    => self::get($term_id, MetaFields::ROBOTS_INDEX, 'index'),
			'robots_follow'   => self::get($term_id, MetaFields::ROBOTS_FOLLOW, 'follow'),
			'canonical_url'   => self::get($term_id, MetaFields::CANONICAL_URL),
			'og_title'        => self::get($term_id, MetaFields::OG_TITLE),
			'og_description'  => self::get($term_id, MetaFields::OG_DESCRIPTION),
			'og_image'        => self::get($term_id, MetaFields::OG_IMAGE, 0),
			'x_title'         => self::get($term_id, MetaFields::X_TITLE),
			'x_description'   => self::get($term_id, MetaFields::X_DESCRIPTION),
			'x_image'         => self::get($term_id, MetaFields::X_IMAGE, 0),
		];
	}

	public static function save(int $term_id): void
	{
		if (
			! isset($_POST[MetaFields::NONCE_NAME]) ||
			! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[MetaFields::NONCE_NAME])), MetaFields::NONCE_ACTION)
		) {
			return;
		}

		if (! current_user_can('edit_term', $term_id)) {
			return;
		}

		$text = [
			MetaFields::SEO_TITLE,
			MetaFields::OG_TITLE,
			MetaFields::X_TITLE,
		];

		foreach ($text as $key) {
			if (isset($_POST[$key])) {
				update_term_meta($term_id, $key, sanitize_text_field(wp_unslash($_POST[$key])));
			}
		}

		foreach ([MetaFields::SEO_DESCRIPTION, MetaFields::OG_DESCRIPTION, MetaFields::X_DESCRIPTION] as $key) {
			if (isset($_POST[$key])) {
				update_term_meta($term_id, $key, sanitize_textarea_field(wp_unslash($_POST[$key])));
			}
		}

		if (isset($_POST[MetaFields::CANONICAL_URL])) {
			update_term_meta($term_id, MetaFields::CANONICAL_URL, MetaFields::sanitize_url(wp_unslash($_POST[MetaFields::CANONICAL_URL])));
		}

		$index = isset($_POST[MetaFields::ROBOTS_INDEX]) ? sanitize_text_field(wp_unslash($_POST[MetaFields::ROBOTS_INDEX])) : 'index';
		update_term_meta($term_id, MetaFields::ROBOTS_INDEX, $index === 'noindex' ? 'noindex' : 'index');

		$follow = isset($_POST[MetaFields::ROBOTS_FOLLOW]) ? sanitize_text_field(wp_unslash($_POST[MetaFields::ROBOTS_FOLLOW])) : 'follow';
		update_term_meta($term_id, MetaFields::ROBOTS_FOLLOW, $follow === 'nofollow' ? 'nofollow' : 'follow');

		if (isset($_POST[MetaFields::OG_IMAGE])) {
			update_term_meta($term_id, MetaFields::OG_IMAGE, absint($_POST[MetaFields::OG_IMAGE]));
		}

		if (isset($_POST[MetaFields::X_IMAGE])) {
			update_term_meta($term_id, MetaFields::X_IMAGE, absint($_POST[MetaFields::X_IMAGE]));
		}
	}
}
