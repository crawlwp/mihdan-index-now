<?php

namespace Mihdan\IndexNow\SEOCore\MetaBox;

class Assets
{
	public function __construct()
	{
		add_action('admin_enqueue_scripts', [$this, 'enqueue']);
		add_action('wp_ajax_crawlwp_load_insights', [$this, 'ajax_load_insights']);
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
			$suggested = $this->get_suggested_links($post->ID);
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
			'suggestedLinks' => $suggested ?? [],
			'isProActive' => defined('CRAWLWP_PRO_VERSION'),
			'ajaxUrl'     => admin_url('admin-ajax.php'),
			'insightsNonce' => wp_create_nonce('crawlwp_insights'),
 		'postId'      => $post instanceof \WP_Post ? $post->ID : 0,
			'featuredImageUrl' => $post instanceof \WP_Post ? (get_the_post_thumbnail_url($post->ID, 'medium') ?: '') : '',
		]);
	}

	public function ajax_load_insights(): void
	{
		check_ajax_referer('crawlwp_insights', 'nonce');

		if (! current_user_can('edit_posts')) {
			wp_send_json_error(['message' => 'Unauthorized'], 403);
		}

		$post_id   = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$days      = isset($_POST['days']) ? absint($_POST['days']) : 28;
		$permalink = $post_id ? get_permalink($post_id) : '';

		$data = [
			'engines' => [
				'google' => [
					'clicks'      => 1247,
					'impressions' => 18340,
					'position'    => 12.4,
					'ctr'         => 0.068,
					'keywords'    => [
						['keyword' => 'wordpress seo plugin',    'clicks' => 312, 'impressions' => 4520, 'position' => 8.2,  'ctr' => 0.069],
						['keyword' => 'index now wordpress',     'clicks' => 245, 'impressions' => 3100, 'position' => 5.6,  'ctr' => 0.079],
						['keyword' => 'search engine indexing',  'clicks' => 198, 'impressions' => 2980, 'position' => 11.3, 'ctr' => 0.066],
						['keyword' => 'crawlwp seo',             'clicks' => 156, 'impressions' => 2150, 'position' => 3.1,  'ctr' => 0.073],
						['keyword' => 'seo meta tags wordpress', 'clicks' => 134, 'impressions' => 1890, 'position' => 14.7, 'ctr' => 0.071],
					],
					'indexStatus'       => true,
					'lastCrawled'       => gmdate('j M Y', strtotime('-2 days')),
					'indexNowSubmitted' => gmdate('j M Y', strtotime('-1 day')),
				],
				'bing' => [
					'clicks'      => 384,
					'impressions' => 6120,
					'position'    => 9.7,
					'ctr'         => 0.063,
					'keywords'    => [
						['keyword' => 'wordpress seo plugin',   'clicks' => 98,  'impressions' => 1540, 'position' => 6.4,  'ctr' => 0.064],
						['keyword' => 'index now wordpress',    'clicks' => 76,  'impressions' => 1120, 'position' => 8.1,  'ctr' => 0.068],
						['keyword' => 'bing indexing api',       'clicks' => 65,  'impressions' => 980,  'position' => 4.3,  'ctr' => 0.066],
						['keyword' => 'instant indexing plugin', 'clicks' => 52,  'impressions' => 890,  'position' => 11.2, 'ctr' => 0.058],
					],
					'indexStatus'       => true,
					'lastCrawled'       => gmdate('j M Y', strtotime('-3 days')),
					'indexNowSubmitted' => gmdate('j M Y', strtotime('-1 day')),
				],
				'yandex' => [
					'clicks'      => 215,
					'impressions' => 4280,
					'position'    => 15.3,
					'ctr'         => 0.050,
					'keywords'    => [
						['keyword' => 'wordpress seo плагин',      'clicks' => 62, 'impressions' => 1080, 'position' => 12.1, 'ctr' => 0.057],
						['keyword' => 'yandex indexnow protocol',  'clicks' => 48, 'impressions' => 940,  'position' => 7.5,  'ctr' => 0.051],
						['keyword' => 'индексация сайта wordpress','clicks' => 41, 'impressions' => 820,  'position' => 18.4, 'ctr' => 0.050],
					],
					'indexStatus'       => false,
					'lastCrawled'       => gmdate('j M Y', strtotime('-5 days')),
					'indexNowSubmitted' => gmdate('j M Y', strtotime('-1 day')),
				],
			],
		];

		/**
		 * Allow the pro plugin to populate insights data.
		 *
		 * @param array  $data      Default insights data structure.
		 * @param int    $post_id   The post ID.
		 * @param int    $days      Number of days for the period.
		 * @param string $permalink The post permalink.
		 */
		$data = apply_filters('crawlwp_insights_data', $data, $post_id, $days, $permalink);

		wp_send_json_success($data);
	}

	private function get_suggested_links(int $post_id): array
	{
		$post = get_post($post_id);

		if (! $post instanceof \WP_Post) {
			return [];
		}

		$categories = wp_get_post_categories($post_id, ['fields' => 'ids']);
		$tags       = wp_get_post_tags($post_id, ['fields' => 'ids']);

		$args = [
			'post_type'      => $post->post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'post__not_in'   => [$post_id],
			'orderby'        => 'relevance',
		];

		/* Try keyword-based search first */
		$keyword = get_post_meta($post_id, MetaFields::FOCUS_KEYWORD, true);
		if ($keyword) {
			$args['s'] = $keyword;
		} elseif (! empty($categories)) {
			/* Fall back to same-category posts */
			unset($args['orderby']);
			$args['category__in'] = $categories;
			$args['orderby']      = 'date';
			$args['order']        = 'DESC';
		} elseif (! empty($tags)) {
			unset($args['orderby']);
			$args['tag__in'] = $tags;
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		} else {
			/* Last resort: recent posts */
			unset($args['orderby']);
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}

		$query = new \WP_Query($args);
		$links = [];

		foreach ($query->posts as $suggested) {
			$links[] = [
				'title' => $suggested->post_title,
				'url'   => get_permalink($suggested->ID),
				'date'  => mysql2date('j M Y', $suggested->post_date),
			];
		}

		wp_reset_postdata();

		return $links;
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
