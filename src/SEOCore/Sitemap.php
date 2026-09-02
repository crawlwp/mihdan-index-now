<?php

namespace Mihdan\IndexNow\SEOCore;

use Mihdan\IndexNow\SEOCore\TitleMeta\Entities;
use Mihdan\IndexNow\SEOCore\TitleMeta\FrontendOutput;

/**
 * Keeps the WordPress core sitemap in sync with the "Hide from search results" toggles
 * and adds hreflang alternate-link support for Polylang, WPML, and TranslatePress.
 */
class Sitemap
{
	public function __construct()
	{
		add_filter('wp_sitemaps_post_types', [$this, 'filter_post_types']);
		add_filter('wp_sitemaps_taxonomies', [$this, 'filter_taxonomies']);
		add_filter('wp_sitemaps_add_provider', [$this, 'filter_providers'], 10, 2);

		/* Boot whichever multilingual integration is active. */
		$this->setup_multilingual();
	}

	// -------------------------------------------------------------------------
	// Noindex filters (unchanged from original)
	// -------------------------------------------------------------------------

	/**
	 * Drop post types that are hidden from search results.
	 *
	 * @param array $post_types Post type objects keyed by name.
	 *
	 * @return array
	 */
	public function filter_post_types($post_types)
	{
		if (! is_array($post_types)) {
			return $post_types;
		}

		foreach (array_keys($post_types) as $name) {
			if (FrontendOutput::is_noindexed(Entities::post_type_key((string) $name))) {
				unset($post_types[$name]);
			}
		}

		return $post_types;
	}

	/**
	 * Drop taxonomies that are hidden from search results.
	 *
	 * @param array $taxonomies Taxonomy objects keyed by name.
	 *
	 * @return array
	 */
	public function filter_taxonomies($taxonomies)
	{
		if (! is_array($taxonomies)) {
			return $taxonomies;
		}

		foreach (array_keys($taxonomies) as $name) {
			if (FrontendOutput::is_noindexed(Entities::taxonomy_key((string) $name))) {
				unset($taxonomies[$name]);
			}
		}

		return $taxonomies;
	}

	/**
	 * Drop the users provider when author archives are hidden.
	 *
	 * @param mixed  $provider The sitemap provider.
	 * @param string $name     Provider name.
	 *
	 * @return mixed
	 */
	public function filter_providers($provider, $name)
	{
		if ($name === 'users' && FrontendOutput::is_noindexed('author')) {
			return false;
		}

		return $provider;
	}

	// -------------------------------------------------------------------------
	// Multilingual integration bootstrap
	// -------------------------------------------------------------------------

	/**
	 * Detect active multilingual plugin and boot the appropriate integration.
	 */
	private function setup_multilingual()
	{
		if (defined('POLYLANG_VERSION')) {
			(new Sitemap\Polylang())->setup();
		} elseif (defined('ICL_SITEPRESS_VERSION')) {
			(new Sitemap\WPML())->setup();
		} elseif (defined('TRP_PLUGIN_VERSION')) {
			(new Sitemap\TranslatePress())->setup();
		}
	}
}
