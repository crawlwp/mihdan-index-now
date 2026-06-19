<?php

namespace Mihdan\IndexNow\SEOCore\SEOMetaFields;

class SEOMetaFieldsFrontendOutput
{
	public function __construct()
	{
		add_action('wp_head', [$this, 'output_meta_tags'], 1);
		add_filter('pre_get_document_title', [$this, 'filter_document_title'], 100);
		add_filter('document_title_parts', [$this, 'filter_document_title_parts'], 100);
		add_action('wp', [$this, 'maybe_set_robots_headers']);
	}

	public function filter_document_title(string $title): string
	{
		if (! is_singular()) {
			return $title;
		}

		$post       = get_queried_object();
		$meta_title = get_post_meta($post->ID, '_crawlwp_meta_title', true);

		if (! empty($meta_title)) {
			return $meta_title;
		}

		return $title;
	}

	public function filter_document_title_parts(array $title_parts): array
	{
		if (! is_singular()) {
			return $title_parts;
		}

		$post       = get_queried_object();
		$meta_title = get_post_meta($post->ID, '_crawlwp_meta_title', true);

		if (! empty($meta_title)) {
			$title_parts['title'] = $meta_title;
			unset($title_parts['site'], $title_parts['tagline']);
		}

		return $title_parts;
	}

	public function maybe_set_robots_headers(): void
	{
		if (! is_singular()) {
			return;
		}

		$post = get_queried_object();

		if (! $post instanceof \WP_Post) {
			return;
		}

		$noindex  = get_post_meta($post->ID, '_crawlwp_robots_noindex', true);
		$nofollow = get_post_meta($post->ID, '_crawlwp_robots_nofollow', true);

		if ($noindex) {
			add_filter('wp_robots', static function (array $robots) {
				$robots['noindex'] = true;
				unset($robots['max-image-preview']);
				return $robots;
			});
		}

		if ($nofollow) {
			add_filter('wp_robots', static function (array $robots) {
				$robots['nofollow'] = true;
				return $robots;
			});
		}
	}

	public function output_meta_tags(): void
	{
		if (! is_singular()) {
			return;
		}

		$post = get_queried_object();

		if (! $post instanceof \WP_Post) {
			return;
		}

		$prefix = '_crawlwp_';
		$id     = $post->ID;

		$meta_description = get_post_meta($id, $prefix . 'meta_description', true);
		$canonical_url    = get_post_meta($id, $prefix . 'canonical_url', true);
		$og_title         = get_post_meta($id, $prefix . 'og_title', true);
		$og_description   = get_post_meta($id, $prefix . 'og_description', true);
		$og_image         = get_post_meta($id, $prefix . 'og_image', true);
		$og_type          = get_post_meta($id, $prefix . 'og_type', true);
		$twitter_card     = get_post_meta($id, $prefix . 'twitter_card', true);
		$twitter_title    = get_post_meta($id, $prefix . 'twitter_title', true);
		$twitter_desc     = get_post_meta($id, $prefix . 'twitter_description', true);
		$twitter_image    = get_post_meta($id, $prefix . 'twitter_image', true);

		// Fallbacks
		$title       = get_the_title($id);
		$og_title    = ! empty($og_title) ? $og_title : $title;
		$og_type     = ! empty($og_type) ? $og_type : 'article';
		$twitter_card = ! empty($twitter_card) ? $twitter_card : 'summary_large_image';

		echo "\n<!-- CrawlWP SEO -->\n";

		if (! empty($meta_description)) {
			printf('<meta name="description" content="%s" />' . "\n", esc_attr($meta_description));
		}

		if (! empty($canonical_url)) {
			printf('<link rel="canonical" href="%s" />' . "\n", esc_url($canonical_url));
		}

		// Open Graph
		printf('<meta property="og:title" content="%s" />' . "\n", esc_attr($og_title));
		printf('<meta property="og:type" content="%s" />' . "\n", esc_attr($og_type));
		printf('<meta property="og:url" content="%s" />' . "\n", esc_url(get_permalink($id)));

		if (! empty($og_description)) {
			printf('<meta property="og:description" content="%s" />' . "\n", esc_attr($og_description));
		}

		if (! empty($og_image)) {
			printf('<meta property="og:image" content="%s" />' . "\n", esc_url($og_image));
		}

		// Twitter Card
		printf('<meta name="twitter:card" content="%s" />' . "\n", esc_attr($twitter_card));

		$twitter_title = ! empty($twitter_title) ? $twitter_title : $og_title;
		printf('<meta name="twitter:title" content="%s" />' . "\n", esc_attr($twitter_title));

		if (! empty($twitter_desc)) {
			printf('<meta name="twitter:description" content="%s" />' . "\n", esc_attr($twitter_desc));
		}

		$twitter_image = ! empty($twitter_image) ? $twitter_image : $og_image;
		if (! empty($twitter_image)) {
			printf('<meta name="twitter:image" content="%s" />' . "\n", esc_url($twitter_image));
		}

		echo "<!-- /CrawlWP SEO -->\n";
	}
}
