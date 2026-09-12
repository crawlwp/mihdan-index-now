<?php

namespace Mihdan\IndexNow\SEOCore\Importer;

use Mihdan\IndexNow\SEOCore\MetaBox\MetaFields;

/**
 * Writes a normalised SEO payload into CrawlWP post/term meta.
 *
 * Expected $data keys (all optional):
 *   title, description, focus_keyword, canonical, robots_index, robots_follow,
 *   og_title, og_description, og_image (attachment ID or URL),
 *   x_title, x_description, x_image, primary_category, cornerstone,
 *   redirect_url, redirect_type, schema_page_type, schema_article_type
 */
class Writer
{
	/**
	 * @param array<string,mixed> $data
	 */
	public static function write_post(int $post_id, array $data, bool $overwrite): bool
	{
		if ($post_id <= 0) {
			return false;
		}

		if (! $overwrite && self::has_value(get_post_meta($post_id, MetaFields::SEO_TITLE, true))) {
			return false;
		}

		self::persist('post', $post_id, $data);

		return true;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function write_term(int $term_id, array $data, bool $overwrite): bool
	{
		if ($term_id <= 0) {
			return false;
		}

		if (! $overwrite && self::has_value(get_term_meta($term_id, MetaFields::SEO_TITLE, true))) {
			return false;
		}

		self::persist('term', $term_id, $data);

		return true;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function persist(string $object_type, int $id, array $data): void
	{
		$map = [
			'title'               => MetaFields::SEO_TITLE,
			'description'         => MetaFields::SEO_DESCRIPTION,
			'focus_keyword'       => MetaFields::FOCUS_KEYWORD,
			'canonical'           => MetaFields::CANONICAL_URL,
			'robots_index'        => MetaFields::ROBOTS_INDEX,
			'robots_follow'       => MetaFields::ROBOTS_FOLLOW,
			'og_title'            => MetaFields::OG_TITLE,
			'og_description'      => MetaFields::OG_DESCRIPTION,
			'x_title'             => MetaFields::X_TITLE,
			'x_description'       => MetaFields::X_DESCRIPTION,
			'redirect_url'        => MetaFields::REDIRECT_URL,
			'redirect_type'       => MetaFields::REDIRECT_TYPE,
			'schema_page_type'    => MetaFields::SCHEMA_PAGE_TYPE,
			'schema_article_type' => MetaFields::SCHEMA_ARTICLE_TYPE,
			'primary_category'    => MetaFields::PRIMARY_CATEGORY,
			'cornerstone'         => MetaFields::CORNERSTONE,
		];

		foreach ($map as $key => $meta_key) {
			if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
				continue;
			}

			$value = $data[$key];

			if (in_array($key, ['title', 'description', 'og_title', 'og_description', 'x_title', 'x_description'], true) && is_string($value)) {
				$value = $value;
			}

			if ($key === 'robots_index') {
				$value = $value === 'noindex' || $value === 1 || $value === '1' || $value === true ? 'noindex' : 'index';
			}

			if ($key === 'robots_follow') {
				$value = $value === 'nofollow' || $value === 1 || $value === '1' || $value === true ? 'nofollow' : 'follow';
			}

			if ($key === 'canonical' || $key === 'redirect_url') {
				$value = MetaFields::sanitize_url((string) $value);
				if ($value === '') {
					continue;
				}
			}

			if ($key === 'redirect_type') {
				$value = (string) (int) $value;
				if (! in_array($value, ['301', '302', '307', '410'], true)) {
					$value = '301';
				}
			}

			if ($key === 'primary_category') {
				$value = absint($value);
				if ($value <= 0) {
					continue;
				}
			}

			if ($key === 'cornerstone') {
				$value = $value ? '1' : '0';
			}

			if (in_array($key, ['title', 'focus_keyword', 'og_title', 'x_title', 'schema_page_type', 'schema_article_type'], true)) {
				$value = sanitize_text_field((string) $value);
			}

			if (in_array($key, ['description', 'og_description', 'x_description'], true)) {
				$value = sanitize_textarea_field((string) $value);
			}

			self::update_meta($object_type, $id, $meta_key, $value);
		}

		if (! empty($data['og_image'])) {
			$image_id = self::to_attachment_id($data['og_image']);
			if ($image_id > 0) {
				self::update_meta($object_type, $id, MetaFields::OG_IMAGE, $image_id);
				self::update_meta($object_type, $id, MetaFields::OG_SYNC, '0');
			}
		}

		if (! empty($data['x_image'])) {
			$image_id = self::to_attachment_id($data['x_image']);
			if ($image_id > 0) {
				self::update_meta($object_type, $id, MetaFields::X_IMAGE, $image_id);
				self::update_meta($object_type, $id, MetaFields::X_SYNC, '0');
			}
		}

		if (! empty($data['og_title']) || ! empty($data['og_description'])) {
			self::update_meta($object_type, $id, MetaFields::OG_SYNC, '0');
		}

		if (! empty($data['x_title']) || ! empty($data['x_description'])) {
			self::update_meta($object_type, $id, MetaFields::X_SYNC, '0');
		}
	}

	/**
	 * @param mixed $value
	 */
	private static function update_meta(string $object_type, int $id, string $key, $value): void
	{
		if ($object_type === 'term') {
			update_term_meta($id, $key, $value);
			return;
		}

		update_post_meta($id, $key, $value);
	}

	/**
	 * @param mixed $raw
	 */
	private static function to_attachment_id($raw): int
	{
		if (is_numeric($raw)) {
			return absint($raw);
		}

		if (! is_string($raw) || $raw === '') {
			return 0;
		}

		$url = esc_url_raw($raw);

		if ($url === '') {
			return 0;
		}

		$id = attachment_url_to_postid($url);

		return $id > 0 ? $id : 0;
	}

	/**
	 * @param mixed $value
	 */
	private static function has_value($value): bool
	{
		return $value !== '' && $value !== null && $value !== false;
	}
}
