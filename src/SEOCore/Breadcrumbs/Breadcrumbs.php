<?php

namespace Mihdan\IndexNow\SEOCore\Breadcrumbs;

/**
 * Builds and renders HTML breadcrumbs for every public front-end context.
 *
 * Usage:
 *   [crawlwp_breadcrumbs]                       — shortcode with defaults
 *   [crawlwp_breadcrumbs separator="/" label_home="Start" display_current="false"]
 *
 * Developers can filter the link list:
 *   add_filter('crawlwp_breadcrumbs_links', function($links) { … return $links; });
 *
 * Or adjust the args before parsing:
 *   add_filter('crawlwp_breadcrumbs_args', function($args) { … return $args; });
 */
class Breadcrumbs
{
	/** @var array Default arguments for the breadcrumb trail. */
	private $args = [];

	/** @var array Resolved ancestor link items [['url'=>…,'text'=>…], …] */
	private $links = [];

	/** @var string Display text for the current (last) crumb — not linked. */
	private $current = '';

	/** @var bool Whether parse() has run for the current request. */
	private $is_parsed = false;

	public function __construct()
	{
		$this->args = [
			'separator'       => '&rsaquo;',
			'taxonomy'        => 'category',
			'display_current' => 'true',
			'label_home'      => __('Home', 'mihdan-index-now'),
			/* translators: %s = search query */
			'label_search'    => __('Search results for &#8220;%s&#8221;', 'mihdan-index-now'),
			'label_404'       => __('Page not found', 'mihdan-index-now'),
		];
	}

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	public function setup(): void
	{
		add_shortcode('crawlwp_breadcrumbs', [$this, 'render_shortcode']);
	}

	// -------------------------------------------------------------------------
	// Public render API
	// -------------------------------------------------------------------------

	/**
	 * Shortcode callback — [crawlwp_breadcrumbs].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function render_shortcode($atts): string
	{
		$this->args = wp_parse_args((array) $atts, $this->args);
		$this->parse();

		$links = $this->get_links();

		if (empty($links) && $this->current === '') {
			return '';
		}

		$sep = '<span class="cwp-bc__sep" aria-hidden="true">' . $this->args['separator'] . '</span>';

		$output = '<nav class="cwp-bc" aria-label="' . esc_attr__('Breadcrumbs', 'mihdan-index-now') . '">';

		$items = [];

		foreach ($links as $i => $link) {
			$class   = $i === 0 ? ' cwp-bc__item--first' : '';
			$items[] = sprintf(
				'<a href="%s" class="cwp-bc__item%s">%s</a>',
				esc_url($link['url']),
				$class,
				esc_html($link['text'])
			);
		}

		if ($this->current !== '' && $this->args['display_current'] !== 'false') {
			$items[] = sprintf(
				'<span class="cwp-bc__item cwp-bc__item--current" aria-current="page">%s</span>',
				esc_html($this->current)
			);
		}

		$output .= implode(' ' . $sep . ' ', $items);
		$output .= '</nav>';

		return $output;
	}

	// -------------------------------------------------------------------------
	// BreadcrumbList JSON-LD
	// -------------------------------------------------------------------------

	/**
	 * Returns a BreadcrumbList schema node for inclusion in the site graph,
	 * or null when there is nothing to emit (e.g. the homepage).
	 *
	 * @return array|null
	 */
	public function get_schema_node()
	{
		$this->parse();

		$links = $this->get_links();

		/* Include the current page as the last item when it has a URL. */
		$current_url = $this->current !== '' ? (string) get_permalink() : '';

		if (empty($links) && $current_url === '') {
			return null;
		}

		$list     = [];
		$position = 1;

		foreach ($links as $link) {
			$list[] = [
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $link['text'],
				'item'     => $link['url'],
			];
		}

		if ($this->current !== '') {
			/* Determine the canonical URL of the current page. */
			if (is_singular()) {
				$cur_url = (string) get_permalink();
			} elseif (is_home()) {
				$cur_url = (string) get_permalink(get_option('page_for_posts'));
			} elseif (is_post_type_archive()) {
				$cur_url = (string) get_post_type_archive_link(get_query_var('post_type'));
			} elseif (is_tax() || is_category() || is_tag()) {
				$term    = get_queried_object();
				$cur_url = $term ? (string) get_term_link($term) : '';
			} elseif (is_author()) {
				$cur_url = (string) get_author_posts_url(get_queried_object_id());
			} else {
				$cur_url = '';
			}

			if ($cur_url !== '') {
				$list[] = [
					'@type'    => 'ListItem',
					'position' => $position,
					'name'     => $this->current,
					'item'     => $cur_url,
				];
			}
		}

		if (empty($list)) {
			return null;
		}

		return [
			'@type'           => 'BreadcrumbList',
			'@id'             => home_url('/') . '#breadcrumb',
			'itemListElement' => $list,
		];
	}

	// -------------------------------------------------------------------------
	// Link list accessors
	// -------------------------------------------------------------------------

	/**
	 * Returns the filtered link array (ancestor items only, NOT the current page).
	 *
	 * @return array
	 */
	public function get_links(): array
	{
		return apply_filters('crawlwp_breadcrumbs_links', $this->links);
	}

	// -------------------------------------------------------------------------
	// Parsing — build $links and $current
	// -------------------------------------------------------------------------

	public function parse(): void
	{
		if ($this->is_parsed) {
			return;
		}

		/* Allow developers to change args before we start building. */
		$this->args = apply_filters('crawlwp_breadcrumbs_args', $this->args);

		if (is_front_page()) {
			/* Nothing to show on the actual homepage. */
			$this->is_parsed = true;
			return;
		}

		/* Every trail starts with Home. */
		$this->add_link(home_url('/'), $this->args['label_home']);

		if (is_home()) {
			/* Static blog page. */
			$this->current = (string) single_post_title('', false);
		} elseif (is_post_type_archive()) {
			$this->current = (string) post_type_archive_title('', false);
		} elseif (is_singular()) {
			$this->add_singular();
		} elseif (is_tax() || is_category() || is_tag()) {
			$term = get_queried_object();
			if ($term instanceof \WP_Term) {
				$taxonomy = get_taxonomy($term->taxonomy);
				if ($taxonomy && ! empty($taxonomy->object_type) && count($taxonomy->object_type) === 1) {
					$this->add_post_type_archive_link(reset($taxonomy->object_type));
				}
				$this->add_term_ancestors($term);
			}
			$this->current = (string) single_term_title('', false);
		} elseif (is_search()) {
			$this->current = sprintf($this->args['label_search'], get_search_query());
		} elseif (is_404()) {
			$this->current = $this->args['label_404'];
		} elseif (is_author()) {
			$author        = get_queried_object();
			$this->current = $author instanceof \WP_User ? $author->display_name : '';
		} elseif (is_date()) {
			$this->add_date_links();
		}

		$this->is_parsed = true;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function add_singular(): void
	{
		$post          = get_queried_object();
		$this->current = (string) single_post_title('', false);

		$this->add_post_type_archive_link($post->post_type);

		/* Hierarchical post types (pages): add ancestor pages. */
		if (is_post_type_hierarchical($post->post_type)) {
			$ancestors = array_reverse(get_post_ancestors($post));
			foreach ($ancestors as $ancestor_id) {
				$this->add_link((string) get_permalink($ancestor_id), (string) get_the_title($ancestor_id));
			}
		} else {
			/* Non-hierarchical: add the primary term and its ancestors. */
			$terms = get_the_terms($post, $this->args['taxonomy']);
			if (is_array($terms) && ! empty($terms)) {
				$term = reset($terms);
				$this->add_term_ancestors($term);
				$this->add_link((string) get_term_link($term), $term->name);
			}
		}
	}

	private function add_post_type_archive_link(string $post_type): void
	{
		if ($post_type === 'post') {
			/* Use the "Blog" page when a static front page is configured. */
			$blog_page_id = (int) get_option('page_for_posts');
			if (get_option('show_on_front') === 'page' && $blog_page_id) {
				$this->add_link((string) get_permalink($blog_page_id), (string) get_the_title($blog_page_id));
			}
			return;
		}

		$link = get_post_type_archive_link($post_type);
		$pto  = get_post_type_object($post_type);

		if ($link && $pto) {
			$this->add_link($link, $pto->labels->name);
		}
	}

	private function add_term_ancestors(\WP_Term $term): void
	{
		$ancestors = array_reverse(get_ancestors($term->term_id, $term->taxonomy, 'taxonomy'));
		foreach ($ancestors as $ancestor_id) {
			$ancestor = get_term($ancestor_id, $term->taxonomy);
			if ($ancestor instanceof \WP_Term) {
				$this->add_link((string) get_term_link($ancestor), $ancestor->name);
			}
		}
	}

	private function add_date_links(): void
	{
		global $wp_locale;

		$year  = (int) get_query_var('year');
		$month = (int) get_query_var('monthnum');
		$day   = (int) get_query_var('day');

		if (is_year()) {
			$this->current = (string) $year;
			return;
		}

		$month_label = $wp_locale->get_month($month);

		if (is_month()) {
			$this->add_link((string) get_year_link($year), (string) $year);
			$this->current = $month_label;
			return;
		}

		/* Daily archive. */
		$this->add_link((string) get_year_link($year), (string) $year);
		$this->add_link((string) get_month_link($year, $month), $month_label);
		$this->current = zeroise($day, 2);
	}

	private function add_link(string $url, string $text): void
	{
		$this->links[] = [
			'url'  => $url,
			'text' => $text,
		];
	}
}
