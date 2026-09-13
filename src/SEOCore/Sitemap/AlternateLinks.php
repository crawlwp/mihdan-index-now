<?php

namespace Mihdan\IndexNow\SEOCore\Sitemap;

/**
 * Injects xhtml:link rel="alternate" cross-links into the WordPress core sitemaps.
 *
 * WordPress's own renderer (WP_Sitemaps_Renderer::get_sitemap_xml()) only accepts
 * the loc/lastmod/changefreq/priority elements and offers no hook to extend a
 * <url> entry, so multilingual alternates cannot be added through the entry
 * filters alone. Instead, the multilingual integrations register the alternate
 * URLs of every entry here (while the entry filters run) and this class buffers
 * the rendered sitemap to:
 *
 *   - declare the xmlns:xhtml namespace on the <urlset> element, and
 *   - insert one <xhtml:link rel="alternate" hreflang="…" href="…"/> per
 *     translation directly after the matching <loc> element.
 *
 * This is the markup Google requires for hreflang annotations in sitemaps.
 */
class AlternateLinks
{
	/** XHTML namespace URI required for the xhtml:link element. */
	const NAMESPACE_URI = 'http://www.w3.org/1999/xhtml';

	/**
	 * Registered alternates, keyed by normalised URL.
	 *
	 * @var array<string,array<string,string>>
	 */
	private static $map = [];

	/** @var bool Whether the output buffer hook has been installed. */
	private static $booted = false;

	/**
	 * Install the sitemap output buffer once, no matter how many integrations
	 * are active.
	 */
	public static function boot(): void
	{
		if (self::$booted) {
			return;
		}

		self::$booted = true;

		add_action('template_redirect', [self::class, 'maybe_buffer'], 0);
	}

	/**
	 * Register the translated URLs of a single sitemap entry.
	 *
	 * @param string                $loc        The canonical URL of the entry.
	 * @param array<string,string>  $alternates Map of hreflang code => URL.
	 */
	public static function add(string $loc, array $alternates): void
	{
		if ($loc === '' || $alternates === []) {
			return;
		}

		$clean = [];

		foreach ($alternates as $code => $url) {
			$code = self::normalize_hreflang((string) $code);
			$url  = esc_url_raw((string) $url);

			if ($code === '' || $url === '') {
				continue;
			}

			$clean[$code] = $url;
		}

		if ($clean === []) {
			return;
		}

		$key = self::normalize_url($loc);

		self::$map[$key] = isset(self::$map[$key])
			? array_merge(self::$map[$key], $clean)
			: $clean;
	}

	/**
	 * Start buffering the output of a provider sitemap (never the index or the
	 * XSL stylesheet requests).
	 */
	public static function maybe_buffer(): void
	{
		$sitemap = get_query_var('sitemap');

		if (! is_string($sitemap) || $sitemap === '' || $sitemap === 'index') {
			return;
		}

		if (get_query_var('sitemap-stylesheet')) {
			return;
		}

		ob_start([self::class, 'inject']);
	}

	/**
	 * Rewrite the buffered sitemap XML, adding the namespace and alternates.
	 *
	 * @param string $xml The rendered sitemap XML.
	 *
	 * @return string
	 */
	public static function inject($xml): string
	{
		$xml = (string) $xml;

		if (self::$map === [] || strpos($xml, '<urlset') === false) {
			return $xml;
		}

		$xml = self::add_namespace($xml);

		return self::add_links($xml);
	}

	/**
	 * Declare xmlns:xhtml on the <urlset> element when it is missing.
	 */
	private static function add_namespace(string $xml): string
	{
		return (string) preg_replace_callback(
			'#<urlset\b([^>]*)>#',
			static function ($matches) {
				if (strpos($matches[1], 'xmlns:xhtml') !== false) {
					return $matches[0];
				}

				return '<urlset' . $matches[1] . ' xmlns:xhtml="' . self::NAMESPACE_URI . '">';
			},
			$xml,
			1
		);
	}

	/**
	 * Insert the <xhtml:link> elements right after each matching <loc>.
	 */
	private static function add_links(string $xml): string
	{
		return (string) preg_replace_callback(
			'#<url>(.*?)</url>#s',
			static function ($matches) {
				$entry = $matches[1];

				if (! preg_match('#<loc>(.*?)</loc>#s', $entry, $loc_match)) {
					return $matches[0];
				}

				$loc = html_entity_decode($loc_match[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
				$key = self::normalize_url($loc);

				if (empty(self::$map[$key])) {
					return $matches[0];
				}

				$links   = '';
				$closing = strpos($entry, '</loc>');

				if ($closing === false) {
					return $matches[0];
				}

				foreach (self::$map[$key] as $code => $url) {
					$links .= sprintf(
						"\n\t\t<xhtml:link rel=\"alternate\" hreflang=\"%s\" href=\"%s\"/>",
						esc_xml($code),
						esc_xml(esc_url_raw($url))
					);
				}

				/* substr_replace() keeps literal '$' characters in URLs intact. */
				$entry = substr_replace($entry, '</loc>' . $links, $closing, strlen('</loc>'));

				return '<url>' . $entry . '</url>';
			},
			$xml
		);
	}

	/**
	 * Normalise a URL so the registered entry and the rendered <loc> match.
	 */
	private static function normalize_url(string $url): string
	{
		$url = trim($url);

		if ($url === '') {
			return '';
		}

		return untrailingslashit($url);
	}

	/**
	 * Normalise a language code into a valid hreflang value (e.g. en_US => en-US).
	 */
	private static function normalize_hreflang(string $code): string
	{
		$code = str_replace('_', '-', trim($code));

		return (string) preg_replace('/[^A-Za-z0-9-]/', '', $code);
	}
}
