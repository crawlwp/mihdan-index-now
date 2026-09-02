<?php

namespace Mihdan\IndexNow\SEOCore\SitemapSettings;

/**
 * Google News Sitemap provider.
 *
 * Registers a custom WordPress sitemap endpoint at /wp-sitemap-news.xml
 * that outputs a Google News-compatible sitemap with news:news metadata.
 *
 * Only posts published within the last 2 days are included, per the
 * Google News Sitemap specification.
 *
 * The provider reads its configuration from SitemapSettings:
 *   news_enabled          – whether the news sitemap is active.
 *   news_publication_name – the <news:name> value (defaults to site title).
 *   news_post_types       – multicheck array of post types to include.
 *
 * The output URL is:  {site}/wp-sitemap-news.xml
 */
class NewsSitemapProvider
{
	/** Rewrite tag used for the custom sitemap endpoint. */
	const QUERY_VAR = 'crawlwp_news_sitemap';

	public function __construct()
	{
		add_action('init', [$this, 'add_rewrite_rule']);
		add_action('template_redirect', [$this, 'maybe_render']);
		add_filter('query_vars', [$this, 'add_query_var']);

		/* Flush rewrite rules once after the rule is first registered. */
		add_action('admin_init', [$this, 'maybe_flush_rewrite_rules']);
	}

	// -------------------------------------------------------------------------
	// Rewrite / routing
	// -------------------------------------------------------------------------

	/**
	 * Register the /wp-sitemap-news.xml rewrite rule.
	 */
	public function add_rewrite_rule(): void
	{
		add_rewrite_rule('^wp-sitemap-news\.xml$', 'index.php?' . self::QUERY_VAR . '=1', 'top');
	}

	/**
	 * Flush rewrite rules once after first registration so the new endpoint
	 * is immediately accessible without a manual Permalinks save.
	 */
	public function maybe_flush_rewrite_rules(): void
	{
		if (get_option('crawlwp_news_sitemap_flushed')) {
			return;
		}

		flush_rewrite_rules(false);
		update_option('crawlwp_news_sitemap_flushed', true, false);
	}

	/**
	 * Expose the query var to WordPress.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function add_query_var(array $vars): array
	{
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Render the news sitemap when our query var is present.
	 */
	public function maybe_render(): void
	{
		if (!get_query_var(self::QUERY_VAR)) {
			return;
		}

		/* Respect the news_enabled toggle. */
		if (SitemapSettings::get('news_enabled', 'off') !== 'on') {
			wp_die(
				esc_html__('The News Sitemap is disabled. Enable it under CrawlWP → Settings → Advanced → Sitemap.', 'mihdan-index-now'),
				esc_html__('News Sitemap disabled', 'mihdan-index-now'),
				['response' => 404]
			);
		}

		$this->render();
		exit;
	}

	// -------------------------------------------------------------------------
	// Sitemap output
	// -------------------------------------------------------------------------

	/**
	 * Stream the Google News sitemap XML to the browser.
	 */
	private function render(): void
	{
		$entries = $this->get_entries();

		header('Content-Type: application/xml; charset=UTF-8');
		header('X-Robots-Tag: noindex, follow');

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		echo '        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

		$publication_name = SitemapSettings::get('news_publication_name', '') ?: get_bloginfo('name');
		$language = substr(get_locale(), 0, 2);

		foreach ($entries as $entry) {
			echo "\t<url>\n";
			echo "\t\t<loc>" . esc_url($entry['loc']) . "</loc>\n";
			echo "\t\t<news:news>\n";
			echo "\t\t\t<news:publication>\n";
			echo "\t\t\t\t<news:name>" . esc_xml($publication_name) . "</news:name>\n";
			echo "\t\t\t\t<news:language>" . esc_xml($language) . "</news:language>\n";
			echo "\t\t\t</news:publication>\n";
			echo "\t\t\t<news:publication_date>" . esc_xml($entry['publication_date']) . "</news:publication_date>\n";
			echo "\t\t\t<news:title>" . esc_xml($entry['title']) . "</news:title>\n";
			echo "\t\t</news:news>\n";
			echo "\t</url>\n";
		}

		echo '</urlset>';
	}

	// -------------------------------------------------------------------------
	// Data
	// -------------------------------------------------------------------------

	/**
	 * Retrieve recent posts to include in the news sitemap.
	 *
	 * @return array<int,array{loc:string,title:string,publication_date:string}>
	 */
	private function get_entries(): array
	{
		$post_types = $this->get_enabled_post_types();

		if (empty($post_types)) {
			return [];
		}

		/* Google News only accepts content published within the last 2 days. */
		$args = [
			'post_type' => $post_types,
			'post_status' => 'publish',
			'posts_per_page' => 1000,
			'date_queryz' => [
				[
					'after' => '2 days ago',
					'inclusive' => true,
				],
			],
			'orderby' => 'date',
			'order' => 'DESC',
			'no_found_rows' => true,
		];

		/**
		 * Filter the WP_Query arguments used to fetch News Sitemap entries.
		 *
		 * @param array $args WP_Query arguments.
		 */
		$args = apply_filters('crawlwp_news_sitemap_query_args', $args);

		$query = new \WP_Query($args);
		$entries = [];

		foreach ($query->posts as $post) {
			if (!($post instanceof \WP_Post)) {
				continue;
			}

			$entry = [
				'loc' => get_permalink($post),
				'title' => $post->post_title,
				'publication_date' => mysql2date('c', $post->post_date_gmt, false),
			];

			/**
			 * Filter a single News Sitemap entry.
			 *
			 * Return false to exclude the entry.
			 *
			 * @param array $entry News sitemap entry data.
			 * @param \WP_Post $post Post object.
			 */
			$entry = apply_filters('crawlwp_news_sitemap_entry', $entry, $post);

			if ($entry) {
				$entries[] = $entry;
			}
		}

		wp_reset_postdata();

		return $entries;
	}

	/**
	 * Return the list of enabled post types, validated against registered types.
	 *
	 * @return string[]
	 */
	private function get_enabled_post_types(): array
	{
		$saved = SitemapSettings::get('news_post_types', []);

		if (!is_array($saved) || empty($saved)) {
			/* Default to 'post' when nothing has been saved yet. */
			return ['post'];
		}

		$registered = get_post_types(['public' => true]);
		$enabled = [];

		foreach (array_keys($saved) as $name) {
			if ($saved[$name] && isset($registered[$name])) {
				$enabled[] = $name;
			}
		}

		return $enabled;
	}
}
