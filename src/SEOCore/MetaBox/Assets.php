<?php

namespace Mihdan\IndexNow\SEOCore\MetaBox;

use Mihdan\IndexNow\SEOCore\AI\Generator;
use Mihdan\IndexNow\SEOCore\TitleMeta\Variables;

class Assets
{
	public function __construct()
	{
		add_action('admin_enqueue_scripts', [$this, 'enqueue']);
		add_action('wp_ajax_crawlwp_load_insights', [$this, 'ajax_load_insights']);
		add_action('wp_ajax_crawlwp_ai_generate', [$this, 'ajax_ai_generate']);
		add_action('wp_ajax_crawlwp_submit_indexnow', [$this, 'ajax_submit_indexnow']);
		add_action('wp_ajax_crawlwp_check_duplicate_keyword', [$this, 'ajax_check_duplicate_keyword']);
		add_action('crawlwp/index_pinged', [$this, 'store_last_pinged_time'], 10, 2);
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
			'separator'   => Variables::separator(),
			'currentYear' => gmdate('Y'),
			'author'      => $author,
			'category'    => ! empty($categories) ? $categories[0] : '',
			'permalink'   => $post instanceof \WP_Post ? get_permalink($post->ID) : '',
			'postContent' => $content,
			'inboundLinks' => $inbound,
			'suggestedLinks' => $suggested ?? [],
			'isProActive' => defined('CRAWLWP_PRO_VERSION'),
			'kwCheckNonce'  => wp_create_nonce('crawlwp_check_keyword'),
			'breadcrumbs' => $this->get_breadcrumb_trail($post),
			'ajaxUrl'     => admin_url('admin-ajax.php'),
			'insightsNonce' => wp_create_nonce('crawlwp_insights'),
			'aiNonce'       => wp_create_nonce('crawlwp_ai_generate'),
			'indexNowNonce'  => wp_create_nonce('crawlwp_submit_indexnow'),
			'postId'      => $post instanceof \WP_Post ? $post->ID : 0,
			'featuredImageUrl' => $post instanceof \WP_Post ? (get_the_post_thumbnail_url($post->ID, 'medium') ?: '') : '',
			'i18n'        => $this->get_i18n_strings(),
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

	public function ajax_ai_generate(): void
	{
		check_ajax_referer('crawlwp_ai_generate', 'nonce');

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$field   = isset($_POST['field']) ? sanitize_key($_POST['field']) : '';
		$title   = isset($_POST['post_title']) ? sanitize_text_field($_POST['post_title']) : '';
		$content = isset($_POST['post_content']) ? wp_kses_post($_POST['post_content']) : '';
		$keyword = isset($_POST['focus_keyword']) ? sanitize_text_field($_POST['focus_keyword']) : '';
		$previous = isset($_POST['previous_value']) ? sanitize_textarea_field($_POST['previous_value']) : '';

		// Check the capability against the actual post being edited, not just
		// the generic edit_posts capability.
		$allowed = $post_id ? current_user_can('edit_post', $post_id) : current_user_can('edit_posts');

		if (! $allowed) {
			wp_send_json_error(['message' => __('You are not allowed to generate SEO text for this post.', 'mihdan-index-now')], 403);
		}

		if (! in_array($field, Generator::fields(), true)) {
			wp_send_json_error(['message' => __('Unsupported field.', 'mihdan-index-now')]);
		}

		$context = [
			'post_id'  => $post_id,
			'title'    => $title,
			'content'  => $content,
			'keyword'  => $keyword,
			'previous' => $previous,
		];

		/**
		 * Filter the AI-generated SEO text.
		 *
		 * Third-party plugins or the pro add-on can hook into this filter
		 * to provide the text from their own service instead.
		 *
		 * @param string $text    The generated text (empty by default).
		 * @param string $field   The field being generated.
		 * @param array  $context Post context: post_id, title, content, keyword, previous.
		 */
		$generated = apply_filters('crawlwp_ai_generate_seo', '', $field, $context);

		if (! empty($generated)) {
			wp_send_json_success(['text' => $generated, 'source' => 'filter']);
		}

		// Ask the AI provider connected to WordPress (Settings > Connectors).
		$generated = (new Generator())->generate($field, $context);

		if (is_wp_error($generated)) {
			wp_send_json_error($this->ai_error_response($generated));
		}

		wp_send_json_success(['text' => $generated, 'source' => 'ai']);
	}

	/**
	 * Build the payload shown to the user when AI generation failed.
	 *
	 * Only errors that the user fixes by connecting a provider link to the
	 * Connectors screen; content and provider errors are shown as they are so
	 * the message stays truthful.
	 */
	private function ai_error_response(\WP_Error $error): array
	{
		$code    = $error->get_error_code();
		$message = $error->get_error_message();

		$connection_codes = ['crawlwp_ai_unavailable', 'crawlwp_ai_unsupported'];

		if (in_array($code, $connection_codes, true)) {
			return [
				'message'    => __('AI generation is unavailable. Connect an AI provider in WordPress under Settings → Connectors, then try again.', 'mihdan-index-now')
					. ($message !== '' ? "\n\n" . $message : ''),
				'connectUrl' => admin_url('options-connectors.php'),
			];
		}

		return [
			'message' => $message !== ''
				? $message
				: __('AI generation failed. Please try again.', 'mihdan-index-now'),
		];
	}

	private function get_i18n_strings(): array
	{
		return [
			/* Pixel meter labels */
			'meterTooShort'    => __('Too short', 'mihdan-index-now'),
			'meterGoodLength'  => __('Good length', 'mihdan-index-now'),
			'meterWillBeCut'   => __('Will be cut off', 'mihdan-index-now'),
			/* translators: %1$s: pixel width, %2$s: pixel limit, %3$s: character count */
			'meterDetail'      => __('%1$s / %2$s px · %3$s chars', 'mihdan-index-now'),

			/* Live preview placeholders */
			'enterTitle'       => __('Enter a title', 'mihdan-index-now'),
			'addMetaDesc'      => __('Add a meta description to control what appears here.', 'mihdan-index-now'),

			/* JSON-LD toggle */
			'hideJsonLd'       => __('Hide JSON-LD', 'mihdan-index-now'),
			'showJsonLd'       => __('Show JSON-LD', 'mihdan-index-now'),

			/* Schema preview */
			'noStructuredData' => __('// No structured data will be output for this post.', 'mihdan-index-now'),

			/* Image picker */
			'selectImage'      => __('Select Image', 'mihdan-index-now'),

			/* Show-all toggle */
			/* translators: %s: total number of links */
			'showAllLinks'     => __('Show all %s links', 'mihdan-index-now'),

			/* Link chips */
			'internal'         => __('Internal', 'mihdan-index-now'),
			'external'         => __('External', 'mihdan-index-now'),
			'suggested'        => __('Suggested', 'mihdan-index-now'),

			/* Inbound link meta */
			/* translators: %s: anchor text */
			'anchorLabel'      => __('Anchor: "%s"', 'mihdan-index-now'),
			/* translators: %s: date string */
			'publishedDate'    => __('published %s', 'mihdan-index-now'),

			/* Links notice */
			'noInternalLinks'  => __('This post links to nothing on your site. Adding two or three internal links helps crawlers reach related posts and passes ranking signals along.', 'mihdan-index-now'),

			/* Copy URL */
			'copyUrl'          => __('Copy URL', 'mihdan-index-now'),
			'copied'           => __('Copied!', 'mihdan-index-now'),

			/* Analysis notice */
			'enterFocusKw'     => __('Enter a focus keyword above to run the analysis.', 'mihdan-index-now'),
			/* translators: %s: keyword */
			'scoredAgainst'    => __('Scored against %s. Change the focus keyword above to rescore.', 'mihdan-index-now'),

			/* Analysis: 1 – Keyword in title */
			'kwInTitleGood'    => __('Keyword is in the SEO title.', 'mihdan-index-now'),
			'kwInTitleStart'   => __('It appears near the start, where it carries the most weight.', 'mihdan-index-now'),
			'kwInTitleMove'    => __('Try moving it closer to the beginning for more impact.', 'mihdan-index-now'),
			'kwInTitleBad'     => __('Keyword is missing from the SEO title.', 'mihdan-index-now'),
			'kwInTitleFix'     => __('Add it to the title so search engines and users see it immediately.', 'mihdan-index-now'),

			/* Analysis: 2 – Keyword in slug */
			'kwInSlugGood'     => __('Keyword is in the URL slug.', 'mihdan-index-now'),
			'kwInSlugBad'      => __('Keyword is missing from the URL slug.', 'mihdan-index-now'),
			'kwInSlugFix'      => __('Include it in the slug for better URL relevance.', 'mihdan-index-now'),

			/* Analysis: 3 – Title length */
			'titleLenGood'     => __('Title length fits.', 'mihdan-index-now'),
			/* translators: %s: pixel width */
			'titleLenDetail'   => __('%s px of the 580 px Google shows.', 'mihdan-index-now'),
			'titleLenLong'     => __('Title is too long.', 'mihdan-index-now'),
			/* translators: %s: pixel width */
			'titleLenLongD'    => __('%s px exceeds the 580 px limit — it will be cut off in search results.', 'mihdan-index-now'),
			'titleLenShort'    => __('Title is too short.', 'mihdan-index-now'),
			/* translators: %s: pixel width */
			'titleLenShortD'   => __('%s px of the 580 px Google shows. Aim for at least 200 px.', 'mihdan-index-now'),

			/* Analysis: 4 – Meta description keyword */
			'kwInDescGood'     => __('Keyword is in the meta description.', 'mihdan-index-now'),
			'kwInDescWarn'     => __('Meta description does not contain the keyword.', 'mihdan-index-now'),
			'kwInDescWarnD'    => __('Mentioning it helps bold the term in search results.', 'mihdan-index-now'),
			'noDescBad'        => __('No meta description set.', 'mihdan-index-now'),
			'noDescFix'        => __('Write a compelling description that includes the keyword.', 'mihdan-index-now'),

			/* Analysis: 5 – Meta description length */
			'descLenGood'      => __('Meta description length is good.', 'mihdan-index-now'),
			/* translators: %s: pixel width */
			'descLenGoodD'     => __('%s px of the 920 px limit.', 'mihdan-index-now'),
			'descLenLong'      => __('Meta description is too long.', 'mihdan-index-now'),
			/* translators: %s: pixel width */
			'descLenLongD'     => __('%s px exceeds 920 px — it may be truncated.', 'mihdan-index-now'),
			'descLenShort'     => __('Meta description is too short.', 'mihdan-index-now'),
			'descLenShortD'    => __('Aim for at least 400 px to use the available space.', 'mihdan-index-now'),

			/* Analysis: 6 – Keyword in first paragraph */
			'kwFirstParaGood'  => __('Keyword appears in the first paragraph.', 'mihdan-index-now'),
			'kwFirstParaWarn'  => __('Keyword is missing from the first paragraph.', 'mihdan-index-now'),
			'kwFirstParaFix'   => __('Introduce the topic early so readers and engines see it upfront.', 'mihdan-index-now'),

			/* Analysis: 7 – Keyword in subheadings */
			/* translators: %s: number of subheadings */
			'kwSubheadGood'    => __('Keyword appears in %s subheadings.', 'mihdan-index-now'),
			'kwSubheadOne'     => __('Only one subheading uses the keyword.', 'mihdan-index-now'),
			'kwSubheadOneFix'  => __('Work it into one or two more H2s where it reads naturally.', 'mihdan-index-now'),
			'kwSubheadBad'     => __('No subheading uses the keyword.', 'mihdan-index-now'),
			'kwSubheadFix'     => __('Add the keyword to at least one H2 or H3.', 'mihdan-index-now'),

			/* translators: %s: number of H1 tags */
			'h1Multiple'       => __('Multiple H1 tags found (%s).', 'mihdan-index-now'),
			'h1MultipleFix'    => __('Use only one H1 per page for best SEO practice.', 'mihdan-index-now'),

			/* Analysis: 9 – Images alt text */
			'noImages'         => __('No images found.', 'mihdan-index-now'),
			'noImagesFix'      => __('Adding relevant images can improve engagement and image search traffic.', 'mihdan-index-now'),
			'allImgAlt'        => __('All images have alt text.', 'mihdan-index-now'),
			/* translators: %s: number of images */
			'imgAltDetail'     => __('%s image(s) found.', 'mihdan-index-now'),
			/* translators: %s: number of images missing alt */
			'imgAltMissing'    => __('%s image(s) missing alt text.', 'mihdan-index-now'),
			'imgAltFix'        => __('Describe what each one shows for accessibility and SEO.', 'mihdan-index-now'),

			/* Analysis: 10 – Keyword in image alt */
			'kwImgAltGood'     => __('Keyword found in an image alt attribute.', 'mihdan-index-now'),
			'kwImgAltWarn'     => __('No image alt text contains the keyword.', 'mihdan-index-now'),
			'kwImgAltFix'      => __('Add the keyword to at least one relevant image alt tag.', 'mihdan-index-now'),

			/* Analysis: 11 – Internal links */
			/* translators: %s: number of internal links */
			'intLinksGood'     => __('%s internal links.', 'mihdan-index-now'),
			'intLinksGoodD'    => __('Good internal linking structure.', 'mihdan-index-now'),
			'intLinksOne'      => __('Only 1 internal link.', 'mihdan-index-now'),
			'intLinksOneFix'   => __('Add at least one more internal link to improve crawlability.', 'mihdan-index-now'),
			'intLinksNone'     => __('No internal links.', 'mihdan-index-now'),
			'intLinksNoneFix'  => __('Link to at least two related posts so crawlers can reach them from here.', 'mihdan-index-now'),

			/* Analysis: 12 – External links */
			/* translators: %s: number of external links */
			'extLinksGood'     => __('%s external link(s).', 'mihdan-index-now'),
			'extLinksGoodD'    => __('Linking to authoritative sources adds credibility.', 'mihdan-index-now'),
			'extLinksNone'     => __('No external links.', 'mihdan-index-now'),
			'extLinksNoneFix'  => __('Consider linking to a relevant authoritative source to add context.', 'mihdan-index-now'),

			/* Analysis: 13 – Content length */
			/* translators: %s: word count */
			'wordsLabel'       => __('%s words.', 'mihdan-index-now'),
			'wordsEnough'      => __('Long enough to cover the topic.', 'mihdan-index-now'),
			'wordsAim300'      => __('Aim for at least 300 words to provide enough depth.', 'mihdan-index-now'),
			'wordsThin'        => __('Content is too thin. Search engines prefer in-depth articles.', 'mihdan-index-now'),

			/* Analysis: 14 – Keyword density */
			/* translators: %s: density percentage */
			'densityLabel'     => __('Keyword density is %s%%.', 'mihdan-index-now'),
			'densityGoodD'     => __('Within the recommended 0.5–3% range.', 'mihdan-index-now'),
			'densityHighD'     => __('This may look like keyword stuffing. Aim for 0.5–3%.', 'mihdan-index-now'),
			'densityLowD'      => __('Try to mention the keyword a few more times naturally.', 'mihdan-index-now'),

			/* Analysis: 15 – Readability */
			/* translators: %s: average sentence length */
			'readability'      => __('Average sentence length is %s words.', 'mihdan-index-now'),
			'readabilityGoodD' => __('Easy to read.', 'mihdan-index-now'),
			'readabilityWarnD' => __('Some sentences may be hard to follow. Try breaking them up.', 'mihdan-index-now'),
			'readabilityBadD'  => __('Sentences are too long. Aim for under 20 words on average.', 'mihdan-index-now'),

			/* Analysis: 16 – Heading hierarchy */
			/* translators: %s: number of H2 tags */
			'h2Good'           => __('%s H2 subheadings structure the content.', 'mihdan-index-now'),
			'h2One'            => __('Only 1 H2 subheading found.', 'mihdan-index-now'),
			'h2OneFix'         => __('Adding more H2s improves readability and SEO.', 'mihdan-index-now'),
			'h2None'           => __('No H2 subheadings found.', 'mihdan-index-now'),
			'h2NoneFix'        => __('Break up long content with H2 headings for better structure.', 'mihdan-index-now'),

			/* Analysis dot */
			/* translators: %s: number of issues */
			'issueCount'       => __('%s issue(s)', 'mihdan-index-now'),

			/* Insights */
			/* translators: %s: search engine name */
			'insightsQueriesDesc' => __('Search queries where this page appeared in %s results.', 'mihdan-index-now'),
			'insightsNoKw'     => __('No keyword data available for this period.', 'mihdan-index-now'),
			/* translators: %s: engine name */
			'insightsIndex'    => __('%s Index', 'mihdan-index-now'),
			'indexed'          => __('Indexed', 'mihdan-index-now'),
			'notIndexed'       => __('Not indexed', 'mihdan-index-now'),
			'savPostFirst'     => __('Save the post first to load search performance data.', 'mihdan-index-now'),

			/* AI generate */
			'aiGenerate'       => __('Generate with AI', 'mihdan-index-now'),
			'aiGenerating'     => __('Generating…', 'mihdan-index-now'),
			'aiError'          => __('AI generation is unavailable. Connect an AI provider in WordPress under Settings → Connectors, then try again.', 'mihdan-index-now'),
			'aiOpenConnectors' => __('Open the Connectors settings page in a new tab?', 'mihdan-index-now'),
			'aiRewrite'        => __('Rewrite with AI', 'mihdan-index-now'),

			/* IndexNow submit */
			'submitIndexNow'   => __('Submit to IndexNow', 'mihdan-index-now'),
			'submitting'       => __('Submitting…', 'mihdan-index-now'),
			/* translators: %s: date string */
			'lastSubmitted'    => __('Last submitted to IndexNow on %s.', 'mihdan-index-now'),
			'notSubmittedYet'  => __('This URL has not been submitted to IndexNow yet.', 'mihdan-index-now'),
			'submitSuccess'    => __('Successfully submitted to IndexNow!', 'mihdan-index-now'),
			'submitError'      => __('Failed to submit. Please try again.', 'mihdan-index-now'),
			'savePostFirst'    => __('Please save the post first before submitting to IndexNow.', 'mihdan-index-now'),

			/* Readability badge */
			'readabilityGood'       => __('Good readability', 'mihdan-index-now'),
			'readabilityOk'         => __('Fairly readable', 'mihdan-index-now'),
			'readabilityPoor'       => __('Needs improvement', 'mihdan-index-now'),
			'readabilityNA'         => __('Readability analysis will run when content is available.', 'mihdan-index-now'),
			/* translators: %1$s: Flesch score, %2$s: avg sentence length, %3$s: percentage of long sentences */
			'readabilityDetail'     => __('Flesch score %1$s · avg. sentence %2$s words · %3$s%% long sentences', 'mihdan-index-now'),

			/* Focus keyword duplicate warning */
			/* translators: %1$s: post title, %2$s: edit link */
			'kwDuplicateWarn'       => __('This keyword is already used by "%1$s". Using the same keyword on multiple posts may cause keyword cannibalization.', 'mihdan-index-now'),
			'kwChecking'            => __('Checking…', 'mihdan-index-now'),

			/* Breadcrumb preview */
			'breadcrumbHome'        => __('Home', 'mihdan-index-now'),

			/* Social image dimensions */
			/* translators: %1$s: actual width, %2$s: actual height */
			'imgDimensions'         => __('%1$s × %2$s px', 'mihdan-index-now'),
			'imgTooSmall'           => __('Image is too small. Minimum recommended:', 'mihdan-index-now'),
			'imgSizeGood'           => __('Image meets the recommended size.', 'mihdan-index-now'),
			'imgOgMin'              => __('1200 × 630 px', 'mihdan-index-now'),
			'imgXMin'               => __('800 × 418 px', 'mihdan-index-now'),
		];
	}

	public function ajax_submit_indexnow(): void
	{
		check_ajax_referer('crawlwp_submit_indexnow', 'nonce');

		if (! current_user_can('edit_posts')) {
			wp_send_json_error(['message' => 'Unauthorized'], 403);
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		if (! $post_id) {
			wp_send_json_error(['message' => 'Invalid post ID.']);
		}

		$post = get_post($post_id);

		if (! $post instanceof \WP_Post) {
			wp_send_json_error(['message' => 'Post not found.']);
		}

		/**
		 * Trigger the same action the plugin fires when a post is updated,
		 * so all registered IndexNow providers will ping the URL.
		 */
		do_action('crawlwp/post_added', $post->ID, $post);

		$timestamp = time();
		update_post_meta($post_id, '_crawlwp_last_indexnow', $timestamp);

		wp_send_json_success([
			'message'   => 'Submitted',
			'timestamp' => $timestamp,
			'date'      => wp_date(get_option('date_format'), $timestamp),
		]);
	}

	/**
	 * Store the last pinged timestamp whenever any IndexNow provider pings a post.
	 */
	public function store_last_pinged_time(string $type, int $object_id): void
	{
		if ($type === 'post') {
			update_post_meta($object_id, '_crawlwp_last_indexnow', time());
		}
	}

	/**
	 * Build the breadcrumb trail array for a post.
	 */
	private function get_breadcrumb_trail(?\WP_Post $post): array
	{
		if (! $post instanceof \WP_Post) {
			return [];
		}

		$crumbs = [get_bloginfo('name')];

		$terms = get_the_terms($post->ID, 'category');
		if (! empty($terms) && ! is_wp_error($terms)) {
			/* Build the category hierarchy */
			$primary = $terms[0];
			$ancestors = get_ancestors($primary->term_id, 'category', 'taxonomy');
			$ancestors = array_reverse($ancestors);
			foreach ($ancestors as $anc_id) {
				$anc = get_term($anc_id, 'category');
				if ($anc && ! is_wp_error($anc)) {
					$crumbs[] = $anc->name;
				}
			}
			$crumbs[] = $primary->name;
		}

		return $crumbs;
	}

	/**
	 * AJAX: Check if a focus keyword is already used by another published post.
	 */
	public function ajax_check_duplicate_keyword(): void
	{
		check_ajax_referer('crawlwp_check_keyword', 'nonce');

		if (! current_user_can('edit_posts')) {
			wp_send_json_error(['message' => 'Unauthorized'], 403);
		}

		$keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		if (empty($keyword)) {
			wp_send_json_success(['duplicate' => false]);
		}

		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND LOWER(pm.meta_value) = LOWER(%s)
				   AND p.post_status = 'publish'
				   AND p.ID != %d
				 LIMIT 1",
				MetaFields::FOCUS_KEYWORD,
				$keyword,
				$post_id
			)
		);

		if ($row) {
			wp_send_json_success([
				'duplicate' => true,
				'postTitle' => $row->post_title,
				'editUrl'   => get_edit_post_link($row->ID, 'raw'),
			]);
		}

		wp_send_json_success(['duplicate' => false]);
	}
}
