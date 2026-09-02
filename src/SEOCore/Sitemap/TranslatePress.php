<?php

namespace Mihdan\IndexNow\SEOCore\Sitemap;

/**
 * TranslatePress integration for the WordPress core sitemap.
 *
 * Outputs hreflang <link rel="alternate"> tags in <head> for the current page
 * using TranslatePress URL conversion, and ensures all language URLs are
 * reflected in the WordPress core sitemap.
 *
 * Fires two actions that third-party code may hook:
 *   crawlwp_sitemap_post  ($post)  — when a post entry is rendered.
 *   crawlwp_sitemap_term  ($term)  — when a term entry is rendered.
 */
class TranslatePress
{
	/** @var object|null TRP main instance. */
	private $trp;

	public function setup()
	{
		if (! class_exists('TRP_Translate_Press')) {
			return;
		}

		$this->trp = \TRP_Translate_Press::get_trp_instance();

		/*
		 * Emit hreflang <link> tags in <head> for the current page.
		 */
		add_action('wp_head', [$this, 'output_hreflang'], 2);

		/*
		 * Fire our own actions on each sitemap entry so third-party code can react.
		 */
		add_filter('wp_sitemaps_posts_entry', [$this, 'fire_post_action'], 10, 3);
		add_filter('wp_sitemaps_taxonomies_entry', [$this, 'fire_term_action'], 10, 3);
	}

	/**
	 * Output hreflang <link rel="alternate"> tags in <head> for the current page.
	 */
	public function output_hreflang()
	{
		$languages    = $this->get_secondary_languages();
		$url_converter = $this->get_url_converter();

		if (empty($languages) || ! $url_converter) {
			return;
		}

		$current_url = $this->get_current_url();

		foreach ($languages as $code) {
			$translated_url = $url_converter->get_url_for_language($code, $current_url, '');

			if ($translated_url && $translated_url !== $current_url) {
				printf(
					'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
					esc_attr($code),
					esc_url($translated_url)
				);
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
	 * Return secondary (non-default) publish languages from TranslatePress settings.
	 *
	 * @return string[]
	 */
	private function get_secondary_languages()
	{
		if (! $this->trp) {
			return [];
		}

		$trp_settings = $this->trp->get_component('settings');

		if (! $trp_settings) {
			return [];
		}

		$settings = $trp_settings->get_settings();

		if (empty($settings['publish-languages']) || empty($settings['default-language'])) {
			return [];
		}

		/* Exclude the default language — it's already the canonical URL. */
		return array_values(array_diff($settings['publish-languages'], [$settings['default-language']]));
	}

	/**
	 * Return the TranslatePress URL converter component.
	 *
	 * @return object|null
	 */
	private function get_url_converter()
	{
		if (! $this->trp) {
			return null;
		}

		return $this->trp->get_component('url_converter');
	}

	/**
	 * Build the canonical URL for the current request.
	 *
	 * @return string
	 */
	private function get_current_url()
	{
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
		$uri    = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

		return $scheme . '://' . $host . $uri;
	}
}
