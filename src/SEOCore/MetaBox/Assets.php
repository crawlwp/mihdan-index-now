<?php

namespace Mihdan\IndexNow\SEOCore\MetaBox;

class Assets
{
	public function __construct()
	{
		add_action('admin_enqueue_scripts', [$this, 'enqueue']);
	}

	public function enqueue(string $hook): void
	{
		if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
			return;
		}

		$assets_url = CRAWLWP_PLUGIN_URL . 'src/SEOCore/MetaBox/assets/';
		$version    = CRAWLWP_VERSION;

		wp_enqueue_style(
			'crawlwp-seo-metabox',
			$assets_url . 'crawlwp-metabox.css',
			[],
			$version
		);

		wp_enqueue_media();

		wp_enqueue_script(
			'crawlwp-seo-metabox',
			$assets_url . 'crawlwp-metabox.js',
			['jquery'],
			$version,
			true
		);

		global $post;

		$post_title = '';
		$excerpt    = '';

		if ($post instanceof \WP_Post) {
			$post_title = $post->post_title;
			$excerpt    = $post->post_excerpt ?: wp_trim_words(wp_strip_all_tags($post->post_content), 30, '...');
		}

		$author      = '';
		$categories  = [];

		if ($post instanceof \WP_Post) {
			$author_obj = get_userdata($post->post_author);
			$author     = $author_obj ? $author_obj->display_name : '';
			$terms      = get_the_terms($post->ID, 'category');
			if (! empty($terms) && ! is_wp_error($terms)) {
				$categories = wp_list_pluck($terms, 'name');
			}
		}

		wp_localize_script('crawlwp-seo-metabox', 'crawlwpSEO', [
			'siteName'    => get_bloginfo('name'),
			'siteUrl'     => home_url('/'),
			'postTitle'   => $post_title,
			'excerpt'     => $excerpt,
			'separator'   => '—',
			'currentYear' => gmdate('Y'),
			'author'      => $author,
			'category'    => ! empty($categories) ? $categories[0] : '',
			'permalink'   => $post instanceof \WP_Post ? get_permalink($post->ID) : '',
		]);
	}
}
