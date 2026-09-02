<?php

namespace Mihdan\IndexNow\SEOCore\Sitemap;

/**
 * Polylang integration for the WordPress core sitemap.
 *
 * Adds hreflang <link rel="alternate"> tags to the frontend <head> for the
 * current page, and ensures all language variants of posts and terms are
 * included in the WordPress core sitemap by disabling Polylang's per-language
 * query filtering during sitemap generation.
 *
 * Fires two actions that third-party code may hook:
 *   crawlwp_sitemap_post  ($post)  — when a post entry is rendered.
 *   crawlwp_sitemap_term  ($term)  — when a term entry is rendered.
 */
class Polylang
{
	public function setup()
	{
		/*
		 * Include all languages in the sitemap queries so every translation
		 * appears as its own URL row (Polylang normally limits queries to the
		 * current language).
		 */
		add_filter('wp_sitemaps_posts_query_args', [$this, 'include_all_languages']);
		add_filter('wp_sitemaps_taxonomies_query_args', [$this, 'include_all_languages']);

		/*
		 * Emit hreflang <link> tags in <head> for the current singular/archive
		 * page so crawlers know about the translated equivalents.
		 */
		add_action('wp_head', [$this, 'output_hreflang'], 2);

		/*
		 * Fire our own actions on each sitemap entry so that third-party code
		 * can react (e.g. to inject extra sitemap XML in a custom sitemap).
		 */
		add_filter('wp_sitemaps_posts_entry', [$this, 'fire_post_action'], 10, 3);
		add_filter('wp_sitemaps_taxonomies_entry', [$this, 'fire_term_action'], 10, 3);
	}

	/**
	 * Remove Polylang's language restriction from sitemap WP_Query / get_terms() args
	 * so all translated posts and terms appear in the sitemap.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array
	 */
	public function include_all_languages($args)
	{
		/* Setting 'lang' to an empty string tells Polylang to return all languages. */
		$args['lang'] = '';

		return $args;
	}

	/**
	 * Output hreflang <link rel="alternate"> tags in <head> for the current page.
	 */
	public function output_hreflang()
	{
		if (! function_exists('pll_get_post_translations') || ! function_exists('pll_get_term_translations')) {
			return;
		}

		$translations = [];

		if (is_singular()) {
			$translations = pll_get_post_translations(get_queried_object_id());

			foreach ($translations as $code => $post_id) {
				printf(
					'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
					esc_attr($code),
					esc_url(get_permalink($post_id))
				);
			}
		} elseif (is_tax() || is_category() || is_tag()) {
			$term = get_queried_object();

			if ($term instanceof \WP_Term) {
				$translations = pll_get_term_translations($term->term_id);

				foreach ($translations as $code => $term_id) {
					$url = get_term_link((int) $term_id);

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
}
