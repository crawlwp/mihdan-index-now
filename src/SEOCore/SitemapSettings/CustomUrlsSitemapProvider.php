<?php

namespace Mihdan\IndexNow\SEOCore\SitemapSettings;

/**
 * Custom URLs Sitemap provider.
 *
 * Extends WP_Sitemaps_Provider to expose a list of arbitrary URLs
 * (not managed by WordPress) in the sitemap index.
 *
 * The sitemap is accessible at:
 *   {site}/wp-sitemap-crawlwpcustom-1.xml  (page 1)
 *   {site}/wp-sitemap-crawlwpcustom-2.xml  (page 2, etc.)
 *
 * URLs are stored in SitemapSettings as the 'custom_urls' textarea
 * (one URL per line). Empty lines and invalid URLs are silently skipped.
 *
 * The provider only registers itself (and appears in the sitemap index)
 * when at least one valid custom URL is saved.
 *
 * Developer hooks:
 *   crawlwp_custom_sitemap_urls   – filter the final array of URL strings
 *                                   before it is split into pages.
 *   crawlwp_custom_sitemap_entry  – filter/exclude a single loc array
 *                                   (return false to skip that entry).
 */
class CustomUrlsSitemapProvider extends \WP_Sitemaps_Provider
{
	/**
	 * Provider name used with wp_register_sitemap_provider().
	 *
	 * Must NOT contain hyphens — WordPress's sitemap rewrite rules would split
	 * a hyphenated name across provider and subtype capture groups.
	 */
	const PROVIDER_NAME = 'crawlwpcustom';

	protected $per_page;

	public function __construct()
	{
		$this->name = self::PROVIDER_NAME;
		$this->object_type = 'crawlwp_custom_url';

		$this->per_page = wp_sitemaps_get_max_urls($this->object_type);

		add_action('init', [$this, 'register_provider'], 20);
	}

	// -------------------------------------------------------------------------
	// WordPress sitemap provider API (abstract methods)
	// -------------------------------------------------------------------------

	/**
	 * Returns the list of URL entries for a given page.
	 *
	 * @param int $page_num Page number (1-based).
	 * @param string $object_subtype Unused; custom URLs have no subtypes.
	 * @return array<int,array{loc:string}>
	 */
	public function get_url_list($page_num, $object_subtype = ''): array
	{
		$all_urls = $this->get_urls();
		$offset = ($page_num - 1) * $this->per_page;
		$page_urls = array_slice($all_urls, $offset, $this->per_page);

		$entries = [];

		foreach ($page_urls as $url) {
			$entry = apply_filters('crawlwp_custom_sitemap_entry', ['loc' => $url], $url);

			if ($entry !== false) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Returns the maximum number of pages.
	 *
	 * Returns 0 when no custom URLs are configured, which hides the provider
	 * from the sitemap index entirely.
	 *
	 * @param string $object_subtype Unused.
	 * @return int
	 */
	public function get_max_num_pages($object_subtype = ''): int
	{
		$count = count($this->get_urls());

		if ($count === 0) {
			return 0;
		}

		return (int)ceil($count / $this->per_page);
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Register this provider with WordPress's sitemap system.
	 *
	 * Called on 'init' at priority 20 (after WordPress sets up its own
	 * sitemap providers at priority 10).
	 */
	public function register_provider(): void
	{
		wp_register_sitemap_provider(self::PROVIDER_NAME, $this);
	}

	// -------------------------------------------------------------------------
	// URL helpers
	// -------------------------------------------------------------------------

	/**
	 * Parse and sanitize the saved custom URLs textarea.
	 *
	 * @return string[] Ordered array of absolute URLs (no duplicates).
	 */
	private function get_urls(): array
	{
		$raw = SitemapSettings::get('custom_urls', '');

		if ($raw === '' || !is_string($raw)) {
			return [];
		}

		$lines = array_filter(array_map('trim', explode("\n", $raw)));
		$urls = [];
		$seen = [];

		foreach ($lines as $line) {
			$url = esc_url_raw($line);

			/* Skip empty or non-http(s) lines. */
			if ($url === '' || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
				continue;
			}

			/* Deduplicate. */
			if (isset($seen[$url])) {
				continue;
			}

			$seen[$url] = true;
			$urls[] = $url;
		}

		/**
		 * Filters the complete list of custom sitemap URLs.
		 *
		 * @param string[] $urls Sanitized, deduplicated list of URL strings.
		 */
		return (array)apply_filters('crawlwp_custom_sitemap_urls', $urls);
	}
}
