<?php

namespace Mihdan\IndexNow\SEOCore\Sitemap;

/**
 * WPML integration for the WordPress core sitemap.
 *
 * Ensures all language variants of posts and terms appear in the WordPress
 * core sitemap, and outputs hreflang <link rel="alternate"> tags in <head>
 * for the current page.
 *
 * Translated URLs are also cross-linked inside the sitemap XML through
 * AlternateLinks (xhtml:link rel="alternate"), which Google requires.
 *
 * Fires two actions that third-party code may hook:
 *   crawlwp_sitemap_post  ($post)  — when a post entry is rendered.
 *   crawlwp_sitemap_term  ($term)  — when a term entry is rendered.
 */
class WPML extends Integration
{
	public function setup()
	{
		/*
		 * Suppress WPML's language restriction during sitemap generation so
		 * every translated post and term is listed as its own URL.
		 */
		add_filter('wp_sitemaps_posts_query_args', [$this, 'include_all_languages']);
		add_filter('wp_sitemaps_taxonomies_query_args', [$this, 'include_all_languages']);

		/*
		 * Output hreflang <link> tags in <head> for the current page.
		 */
		add_action('wp_head', [$this, 'output_hreflang'], 2);

		/*
		 * Fire our own actions on each sitemap entry, collect the alternate URLs
		 * for the sitemap XML, and let the News Sitemap use WPML's language.
		 */
		$this->register_common_hooks();
	}

	/**
	 * Remove WPML's per-language query limitation so all translations are included.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array
	 */
	public function include_all_languages($args)
	{
		/*
		 * Setting 'suppress_filters' to false AND passing 'lang' => '' (empty)
		 * causes WPML to skip its language-narrowing filter on the query.
		 */
		$args['suppress_filters'] = false;
		$args['lang']             = '';

		return $args;
	}

	/**
	 * Output hreflang <link rel="alternate"> tags in <head> for the current page.
	 */
	public function output_hreflang()
	{
		$languages = $this->get_active_languages();

		if (empty($languages)) {
			return;
		}

		if (is_singular()) {
			$post_id   = get_queried_object_id();
			$post_type = get_post_type($post_id);

			foreach ($languages as $code) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				$translated_id = apply_filters('wpml_object_id', $post_id, $post_type, false, $code);

				if (! $translated_id) {
					continue;
				}

				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				$url = apply_filters('wpml_permalink', get_permalink($translated_id), $code, true);

				printf(
					'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
					esc_attr($code),
					esc_url($url)
				);
			}
		} elseif (is_tax() || is_category() || is_tag()) {
			$term = get_queried_object();

			if (! ($term instanceof \WP_Term)) {
				return;
			}

			foreach ($languages as $code) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				$translated_id = apply_filters('wpml_object_id', $term->term_id, $term->taxonomy, false, $code);

				if (! $translated_id) {
					continue;
				}

				$url = get_term_link((int) $translated_id, $term->taxonomy);
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				$url = apply_filters('wpml_permalink', $url, $code, true);

				if (! is_wp_error($url)) {
					printf(
						'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
						esc_attr($code),
						esc_url($url)
					);
				}
			}
		}
	}

	/**
	 * Translated post URLs keyed by WPML language code.
	 *
	 * @param \WP_Post $post Post object.
	 * @param string   $loc  The entry URL.
	 *
	 * @return array<string,string>
	 */
	protected function get_post_alternates(\WP_Post $post, string $loc): array
	{
		$alternates = [];

		foreach ($this->get_active_languages() as $code) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$translated_id = apply_filters('wpml_object_id', $post->ID, $post->post_type, false, $code);

			if (! $translated_id) {
				continue;
			}

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$url = apply_filters('wpml_permalink', get_permalink((int) $translated_id), $code, true);

			if (is_string($url) && $url !== '') {
				$alternates[(string) $code] = $url;
			}
		}

		return $alternates;
	}

	/**
	 * Translated term URLs keyed by WPML language code.
	 *
	 * @param \WP_Term $term Term object.
	 * @param string   $loc  The entry URL.
	 *
	 * @return array<string,string>
	 */
	protected function get_term_alternates(\WP_Term $term, string $loc): array
	{
		$alternates = [];

		foreach ($this->get_active_languages() as $code) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$translated_id = apply_filters('wpml_object_id', $term->term_id, $term->taxonomy, false, $code);

			if (! $translated_id) {
				continue;
			}

			$url = get_term_link((int) $translated_id, $term->taxonomy);

			if (is_wp_error($url)) {
				continue;
			}

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$url = apply_filters('wpml_permalink', $url, $code, true);

			if (is_string($url) && $url !== '') {
				$alternates[(string) $code] = $url;
			}
		}

		return $alternates;
	}

	/**
	 * WPML's current language code.
	 *
	 * @return string
	 */
	protected function get_current_language(): string
	{
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$code = apply_filters('wpml_current_language', null);

		return is_string($code) ? $code : '';
	}

	/**
	 * Return an array of active WPML language codes (skip_missing = true).
	 *
	 * @return string[]
	 */
	private function get_active_languages()
	{
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$languages = apply_filters('wpml_active_languages', null, ['skip_missing' => true]);

		if (! is_array($languages)) {
			return [];
		}

		return array_keys($languages);
	}
}
