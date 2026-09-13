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

		$this->per_page = function_exists('wp_sitemaps_get_max_urls') ? wp_sitemaps_get_max_urls($this->object_type) : 2000;

		add_action('init', [$this, 'register_provider'], 20);
		add_action('template_redirect', [$this, 'maybe_render'], 1);
		add_filter('wp_sitemaps_stylesheet_url', [$this, 'filter_stylesheet_url']);
	}

	/**
	 * Filter the stylesheet URL when rendering the custom sitemap.
	 *
	 * @param string $url Default sitemap stylesheet URL.
	 * @return string
	 */
	public function filter_stylesheet_url(string $url): string
	{
		static $running = false;
		if ($running) {
			return $url;
		}

		if (empty($url)) {
			return $url;
		}

		if (get_query_var('sitemap') === self::PROVIDER_NAME) {
			$running = true;
			$url = SitemapStylesheet::get_stylesheet_url('custom');
			$running = false;
		}

		return $url;
	}

	/**
	 * Intercept the sitemap request for our custom URLs provider and render XML.
	 */
	public function maybe_render(): void
	{
		if (get_query_var('sitemap') !== self::PROVIDER_NAME) {
			return;
		}

		$all_urls = $this->get_urls();
		if (empty($all_urls)) {
			global $wp_query;
			if ($wp_query instanceof \WP_Query) {
				$wp_query->set_404();
			}
			status_header(404);
			return;
		}

		$paged = absint(get_query_var('paged'));
		if ($paged <= 0) {
			$paged = 1;
		}

		$entries = $this->get_url_list($paged);
		if (empty($entries)) {
			global $wp_query;
			if ($wp_query instanceof \WP_Query) {
				$wp_query->set_404();
			}
			status_header(404);
			return;
		}

		$xml = $this->build_xml($entries);

		if (!headers_sent()) {
			header('Content-Type: application/xml; charset=UTF-8');
			header('Cache-Control: public, max-age=' . HOUR_IN_SECONDS);
			header('Expires: ' . gmdate('D, d M Y H:i:s', time() + HOUR_IN_SECONDS) . ' GMT');
		}

		echo $xml;
		exit;
	}

	/**
	 * Build the complete, fully escaped custom URLs sitemap XML document.
	 *
	 * @param array<int,array{loc:string,lastmod?:string}> $entries
	 * @return string
	 */
	public function build_xml(array $entries): string
	{
		$stylesheet_url = SitemapStylesheet::get_stylesheet_url('custom');

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		if ($stylesheet_url !== '') {
			$xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url($stylesheet_url) . '" ?>' . "\n";
		}
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ($entries as $entry) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url($entry['loc']) . "</loc>\n";
			if (!empty($entry['lastmod'])) {
				$xml .= "\t\t<lastmod>" . esc_xml($entry['lastmod']) . "</lastmod>\n";
			}
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		return $xml;
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

		/*
		 * Use the date the custom_urls option was last saved as lastmod.
		 * WordPress stores option timestamps via alloptions; we fall back to
		 * the current UTC date when no timestamp is available.
		 */
		$lastmod = $this->get_lastmod();

		$entries = [];

		foreach ($page_urls as $url) {
			$entry = apply_filters('crawlwp_custom_sitemap_entry', ['loc' => $url, 'lastmod' => $lastmod], $url);

			if ($entry !== false) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Return an ISO 8601 lastmod date for the custom URL list.
	 *
	 * Uses the WordPress-recorded timestamp of when the SitemapSettings option
	 * was last updated (stored as a companion option), falling back to today.
	 *
	 * @return string  e.g. "2026-09-03T10:03:00+00:00"
	 */
	private function get_lastmod(): string
	{
		/* SitemapSettings saves its data under the WPOSA section option key. */
		$ts = (int) get_option('crawlwp_sitemap_settings_updated', 0);

		if ($ts > 0) {
			return gmdate('Y-m-d\TH:i:s+00:00', $ts);
		}

		return gmdate('Y-m-d\TH:i:s+00:00');
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
