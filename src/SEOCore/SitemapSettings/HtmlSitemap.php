<?php

namespace Mihdan\IndexNow\SEOCore\SitemapSettings;

/**
 * HTML sitemap via [crawlwp_html_sitemap] shortcode.
 */
class HtmlSitemap
{
	public function __construct()
	{
		add_shortcode('crawlwp_html_sitemap', [$this, 'shortcode']);
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
			'post_types' => 'page,post',
		], is_array($atts) ? $atts : []);

		$types = array_filter(array_map('trim', explode(',', (string) $atts['post_types'])));
		$html  = '<div class="cwp-html-sitemap">';

		foreach ($types as $type) {
			$object = get_post_type_object($type);

			if (! $object) {
				continue;
			}

			$posts = get_posts([
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'has_password'   => false,
			]);

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
}
