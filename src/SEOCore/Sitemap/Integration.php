<?php

namespace Mihdan\IndexNow\SEOCore\Sitemap;

/**
 * Shared base for the multilingual sitemap integrations (Polylang, WPML,
 * TranslatePress).
 *
 * Holds the logic that used to be duplicated byte-for-byte in every
 * integration:
 *
 *   - fire_post_action() / fire_term_action() — the crawlwp_sitemap_post and
 *     crawlwp_sitemap_term actions.
 *   - Collecting the translated URLs of each entry so AlternateLinks can emit
 *     the xhtml:link rel="alternate" cross-links Google requires.
 *   - Overriding the News Sitemap language through the
 *     crawlwp_news_sitemap_language filter.
 *
 * Sub-classes implement the plugin-specific lookups by overriding
 * get_post_alternates(), get_term_alternates() and get_current_language().
 */
abstract class Integration
{
	/**
	 * Register the integration's hooks.
	 */
	abstract public function setup();

	/**
	 * Register the hooks every integration shares.
	 */
	protected function register_common_hooks(): void
	{
		AlternateLinks::boot();

		add_filter('wp_sitemaps_posts_entry', [$this, 'fire_post_action'], 10, 3);
		add_filter('wp_sitemaps_taxonomies_entry', [$this, 'fire_term_action'], 10, 3);

		/* Let the News Sitemap use the multilingual plugin's language. */
		add_filter('crawlwp_news_sitemap_language', [$this, 'filter_news_language']);
	}

	/**
	 * Fire the crawlwp_sitemap_post action for each post sitemap entry and
	 * register its translated URLs.
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

			$loc = $this->entry_loc($entry);

			if ($loc !== '') {
				$this->register_alternates($loc, $this->get_post_alternates($post, $loc));
			}
		}

		return $entry;
	}

	/**
	 * Fire the crawlwp_sitemap_term action for each taxonomy sitemap entry and
	 * register its translated URLs.
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

			$loc = $this->entry_loc($entry);

			if ($loc !== '') {
				$this->register_alternates($loc, $this->get_term_alternates($term, $loc));
			}
		}

		return $entry;
	}

	/**
	 * Override the News Sitemap language with the multilingual plugin's value.
	 *
	 * @param string $language The language code determined from the site locale.
	 *
	 * @return string
	 */
	public function filter_news_language($language)
	{
		$code = $this->get_current_language();

		return $code !== '' ? $code : (string) $language;
	}

	/**
	 * Translated URLs of a post entry, keyed by language code.
	 *
	 * @param \WP_Post $post Post object.
	 * @param string   $loc  The entry URL.
	 *
	 * @return array<string,string>
	 */
	protected function get_post_alternates(\WP_Post $post, string $loc): array
	{
		return [];
	}

	/**
	 * Translated URLs of a term entry, keyed by language code.
	 *
	 * @param \WP_Term $term Term object.
	 * @param string   $loc  The entry URL.
	 *
	 * @return array<string,string>
	 */
	protected function get_term_alternates(\WP_Term $term, string $loc): array
	{
		return [];
	}

	/**
	 * The current language code as the multilingual plugin reports it.
	 *
	 * @return string Empty string when unknown.
	 */
	protected function get_current_language(): string
	{
		return '';
	}

	/**
	 * Hand the alternates of a single entry over to the XML renderer.
	 *
	 * @param string               $loc        Entry URL.
	 * @param array<string,string> $alternates Map of language code => URL.
	 */
	protected function register_alternates(string $loc, array $alternates): void
	{
		/* A single alternate that is the entry itself adds no information. */
		if (count($alternates) < 2) {
			return;
		}

		AlternateLinks::add($loc, $alternates);
	}

	/**
	 * Read the loc value out of a sitemap entry.
	 *
	 * @param mixed $entry Sitemap entry.
	 *
	 * @return string
	 */
	private function entry_loc($entry): string
	{
		if (! is_array($entry) || empty($entry['loc'])) {
			return '';
		}

		return (string) $entry['loc'];
	}
}
