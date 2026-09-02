<?php

namespace Mihdan\IndexNow\SEOCore\Sitemap;

/**
 * WPML integration for the WordPress core sitemap.
 *
 * Ensures all language variants of posts and terms appear in the WordPress
 * core sitemap, and outputs hreflang <link rel="alternate"> tags in <head>
 * for the current page.
 *
 * Fires two actions that third-party code may hook:
 *   crawlwp_sitemap_post  ($post)  — when a post entry is rendered.
 *   crawlwp_sitemap_term  ($term)  — when a term entry is rendered.
 */
class WPML
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
		 * Fire our own actions on each sitemap entry.
		 */
		add_filter('wp_sitemaps_posts_entry', [$this, 'fire_post_action'], 10, 3);
		add_filter('wp_sitemaps_taxonomies_entry', [$this, 'fire_term_action'], 10, 3);
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
	 * Fire the crawlwp_sitemap_post action for each post sitemap entry.
	 *
	 * @param array    $entry     Sitemap entry data.
	 * @param \WP_Post $post      Post object.
	 * @param string   $post_type Post type name.
	 *
	 * @return array Unmodified entry.
	 */
	public function fire_post_action($entry, $post, $post_type)
	{
		if ($post instanceof \WP_Post) {
			/**
			 * Fires when a post entry is about to be included in the CrawlWP sitemap.
			 *
			 * @param \WP_Post $post Post object.
			 */
			do_action('crawlwp_sitemap_post', $post);
		}

		return $entry;
	}

	/**
	 * Fire the crawlwp_sitemap_term action for each taxonomy sitemap entry.
	 *
	 * @param array    $entry    Sitemap entry data.
	 * @param \WP_Term $term     Term object.
	 * @param string   $taxonomy Taxonomy name.
	 *
	 * @return array Unmodified entry.
	 */
	public function fire_term_action($entry, $term, $taxonomy)
	{
		if ($term instanceof \WP_Term) {
			/**
			 * Fires when a term entry is about to be included in the CrawlWP sitemap.
			 *
			 * @param \WP_Term $term Term object.
			 */
			do_action('crawlwp_sitemap_term', $term);
		}

		return $entry;
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
