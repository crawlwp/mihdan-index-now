<?php

namespace Mihdan\IndexNow\SEOCore\TitleMeta;

/**
 * Keeps the core sitemap in sync with the "Hide from search results" toggles.
 */
class Sitemap
{
	public function __construct()
	{
		add_filter('wp_sitemaps_post_types', [$this, 'filter_post_types']);
		add_filter('wp_sitemaps_taxonomies', [$this, 'filter_taxonomies']);
		add_filter('wp_sitemaps_add_provider', [$this, 'filter_providers'], 10, 2);
	}

	/**
	 * Drop post types that are hidden from search results.
	 *
	 * @param array $post_types Post type objects keyed by name.
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
}
