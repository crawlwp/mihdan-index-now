<?php

namespace Mihdan\IndexNow\SEOCore\SitemapSettings;

use Mihdan\IndexNow\SEOCore\MetaBox\MetaFields;

/**
 * HTML sitemap via [crawlwp_html_sitemap] shortcode.
 *
 * Posts marked "noindex" in the SEO metabox are excluded, and the rendered
 * markup is cached in a transient keyed by the shortcode attributes so the
 * queries do not run again on every page load. The cache is invalidated when a
 * post is saved or deleted, or the sitemap settings change.
 */
class HtmlSitemap
{
	/** Prefix of the transients holding the rendered markup. */
	const TRANSIENT_PREFIX = 'crawlwp_html_sitemap_';

	/** Option holding the list of transient keys currently in use. */
	const TRANSIENT_INDEX_OPTION = 'crawlwp_html_sitemap_cache_keys';

	/** How long the rendered markup is cached. */
	const CACHE_TTL = HOUR_IN_SECONDS;

	public function __construct()
	{
		add_shortcode('crawlwp_html_sitemap', [$this, 'shortcode']);

		add_action('save_post', [$this, 'flush_cache']);
		add_action('deleted_post', [$this, 'flush_cache']);
		add_action('update_option_crawlwp_' . SitemapSettings::SECTION, [$this, 'flush_cache']);
		add_action('add_option_crawlwp_' . SitemapSettings::SECTION, [$this, 'flush_cache']);
	}

	/**
	 * @param array<string,string>|string $atts
	 */
	public function shortcode($atts): string
	{
		if (SitemapSettings::get('html_enabled', 'on') === 'off') {
			return '';
		}

		$atts = shortcode_atts([
			'post_types' => apply_filters('crawlwp_html_sitemap_post_types', 'page,post'),
		], is_array($atts) ? $atts : []);

		$key    = self::TRANSIENT_PREFIX . md5((string) wp_json_encode($atts));
		$cached = get_transient($key);

		if (is_string($cached) && $cached !== '') {
			return $cached;
		}

		$html = $this->render($atts);

		set_transient($key, $html, self::CACHE_TTL);
		$this->remember_cache_key($key);

		return $html;
	}

	/**
	 * Drop every cached rendering.
	 *
	 * Hooks: save_post, deleted_post, update_option_crawlwp_sitemap_settings.
	 */
	public function flush_cache(): void
	{
		$keys = get_option(self::TRANSIENT_INDEX_OPTION, []);

		if (is_array($keys)) {
			foreach ($keys as $key) {
				delete_transient((string) $key);
			}
		}

		delete_option(self::TRANSIENT_INDEX_OPTION);
	}

	/**
	 * Build the sitemap markup.
	 *
	 * @param array<string,string> $atts Parsed shortcode attributes.
	 */
	private function render(array $atts): string
	{
		$types = array_filter(array_map('trim', explode(',', (string) $atts['post_types'])));
		$html  = '<div class="cwp-html-sitemap">';

		foreach ($types as $type) {
			$object = get_post_type_object($type);

			if (! $object) {
				continue;
			}

			$posts = get_posts(apply_filters('crawlwp_html_sitemap_query', [
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'has_password'   => false,
				/* Never leak posts the editor hid from search engines. */
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => MetaFields::ROBOTS_INDEX,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => MetaFields::ROBOTS_INDEX,
						'value'   => 'noindex',
						'compare' => '!=',
					],
				],
			], $atts));

			if ($posts === []) {
				continue;
			}

			$html .= '<h2>' . esc_html($object->labels->name) . '</h2><ul>';

			foreach ($posts as $post) {
				$html .= '<li><a href="' . esc_url((string) get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a></li>';
			}

			$html .= '</ul>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Track a transient key so flush_cache() can clear every variation.
	 */
	private function remember_cache_key(string $key): void
	{
		$keys = get_option(self::TRANSIENT_INDEX_OPTION, []);
		$keys = is_array($keys) ? $keys : [];

		if (in_array($key, $keys, true)) {
			return;
		}

		$keys[] = $key;

		update_option(self::TRANSIENT_INDEX_OPTION, $keys, false);
	}
}
