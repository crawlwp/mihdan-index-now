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
		$content     = '';
		$inbound     = [];

		if ($post instanceof \WP_Post) {
			$author_obj = get_userdata($post->post_author);
			$author     = $author_obj ? $author_obj->display_name : '';
			$terms      = get_the_terms($post->ID, 'category');
			if (! empty($terms) && ! is_wp_error($terms)) {
				$categories = wp_list_pluck($terms, 'name');
			}
			$content = $post->post_content;
			$inbound = $this->get_inbound_links($post->ID);
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
			'postContent' => $content,
			'inboundLinks' => $inbound,
		]);
	}

	private function get_inbound_links(int $post_id): array
	{
		$permalink = get_permalink($post_id);

		if (! $permalink) {
			return [];
		}

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content, post_date FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post','page') AND post_content LIKE %s AND ID != %d LIMIT 20",
				'%' . $wpdb->esc_like($permalink) . '%',
				$post_id
			)
		);

		$links = [];

		foreach ($results as $row) {
			$anchor = '';
			if (preg_match('/<a[^>]+href=["\']' . preg_quote($permalink, '/') . '["\'][^>]*>(.*?)<\/a>/is', $row->post_content, $m)) {
				$anchor = wp_strip_all_tags($m[1]);
			}

			$links[] = [
				'title'  => $row->post_title,
				'url'    => get_permalink($row->ID),
				'anchor' => $anchor,
				'date'   => mysql2date('j M Y', $row->post_date),
			];
		}

		return $links;
	}
}
