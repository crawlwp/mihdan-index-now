<?php

namespace Mihdan\IndexNow\SEOCore\Notifications;

use Mihdan\IndexNow\SEOCore\TitleMeta\Options;

/**
 * Critical SEO Notifications.
 *
 * Checks for site-wide SEO issues and displays dismissible admin notices.
 * Only shown to users who can manage options (site administrators).
 *
 * Checks performed:
 *  1. WordPress "Discourage search engines" setting is enabled.
 *  2. CrawlWP site-wide noindex is active.
 *  3. A conflicting SEO plugin is active alongside CrawlWP.
 *  4. Homepage has no SEO title configured.
 *  5. Homepage has no meta description configured.
 *  6. WordPress core sitemaps are disabled.
 *  7. A robots.txt file is actively blocking all crawlers.
 *  8. The site is using an HTTP URL (no SSL).
 */
class Notifications
{
	/**
	 * WordPress option key prefix for dismissed notices.
	 */
	private const DISMISSED_KEY = 'crawlwp_dismissed_notices';

	/**
	 * Known conflicting SEO plugin basenames.
	 *
	 * @var string[]
	 */
	private const CONFLICTING_PLUGINS = [
		'wordpress-seo/wp-seo.php',
		'wordpress-seo-premium/wp-seo-premium.php',
		'all-in-one-seo-pack/all_in_one_seo_pack.php',
		'all-in-one-seo-pack-pro/all_in_one_seo_pack.php',
		'seo-by-rank-math/rank-math.php',
		'seo-by-rank-math-pro/rank-math-pro.php',
		'autodescription/autodescription.php',
		'slim-seo/slim-seo.php',
		'squirrly-seo/squirrly.php',
		'seopress/seopress.php',
		'seopress-pro/seopress-pro.php',
		'wp-seopress/wp-seopress.php',
	];

	public function __construct()
	{
		add_action('admin_notices', [$this, 'display_notices']);
		add_action('wp_ajax_crawlwp_dismiss_notice', [$this, 'ajax_dismiss_notice']);
		add_action('admin_footer', [$this, 'print_dismiss_js']);
	}

	/**
	 * Collect and display all active critical notices.
	 */
	public function display_notices(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$dismissed = $this->get_dismissed();
		$notices = $this->collect_notices();

		foreach ($notices as $notice) {
			if (isset($dismissed[$notice['id']])) {
				continue;
			}

			$this->render_notice($notice);
		}
	}

	/**
	 * Collect all critical notices that are currently active.
	 *
	 * @return array[] Each item has keys: id (string), severity (error|warning), message (string), action (string).
	 */
	private function collect_notices(): array
	{
		$notices = [];

		/* 1. WordPress "Discourage search engines" setting. */
		if (!(bool)get_option('blog_public', 1)) {
			$notices[] = [
				'id' => 'blog_not_public',
				'severity' => 'error',
				'message' => sprintf(
				/* translators: 1: link opening tag, 2: link closing tag */
					__('<strong>CrawlWP SEO:</strong> Your site is set to <strong>discourage search engines from indexing</strong>. Search engines will not index your pages. %1$sChange this setting%2$s', 'mihdan-index-now'),
					'<a href="' . esc_url(admin_url('options-reading.php')) . '">',
					'</a>'
				),
			];
		}

		/* 2. CrawlWP site-wide noindex. */
		if (Options::is_on('home', 'noindex')) {
			$notices[] = [
				'id' => 'crawlwp_site_noindex',
				'severity' => 'warning',
				'message' => sprintf(
				/* translators: 1: link opening tag, 2: link closing tag */
					__('<strong>CrawlWP SEO:</strong> Your <strong>Homepage is set to noindex</strong> in CrawlWP Title & Meta settings. Search engines will not index your homepage. %1$sReview settings%2$s', 'mihdan-index-now'),
					'<a href="' . esc_url(add_query_arg(['wposa-menu' => 'crawlwp_tm_home'], CRAWLWP_SETTINGS_URL)) . '">',
					'</a>'
				),
			];
		}

		/* 3. Conflicting SEO plugin detected. */
		$conflict = $this->detect_conflicting_plugin();

		if ($conflict !== null) {
			$notices[] = [
				'id' => 'conflicting_seo_plugin',
				'severity' => 'warning',
				'message' => sprintf(
				/* translators: 1: conflicting plugin name */
					__('<strong>CrawlWP SEO:</strong> <strong>%s</strong> is also active on your site. Running two SEO plugins simultaneously can cause duplicate meta tags and conflicting settings. Please deactivate one of them.', 'mihdan-index-now'),
					esc_html($conflict)
				),
			];
		}

		/* 4. Missing homepage SEO title. */
		if (Options::get('home', 'title', '') === '') {
			$notices[] = [
				'id' => 'missing_homepage_title',
				'severity' => 'warning',
				'message' => sprintf(
				/* translators: 1: link opening tag, 2: link closing tag */
					__('<strong>CrawlWP SEO:</strong> Your homepage has <strong>no SEO title template</strong> set. A descriptive title is critical for search engine rankings. %1$sConfigure now%2$s', 'mihdan-index-now'),
				'<a href="' . esc_url(add_query_arg(['wposa-menu' => 'crawlwp_tm_home'], CRAWLWP_SETTINGS_URL)) . '">',
				'</a>'
			),
		];
	}

		/* 5. Missing homepage meta description. */
		if (Options::get('home', 'description', '') === '') {
			$notices[] = [
				'id' => 'missing_homepage_description',
				'severity' => 'warning',
				'message' => sprintf(
				/* translators: 1: link opening tag, 2: link closing tag */
					__('<strong>CrawlWP SEO:</strong> Your homepage has <strong>no meta description template</strong> set. A good description improves click-through rates from search results. %1$sConfigure now%2$s', 'mihdan-index-now'),
					'<a href="' . esc_url(add_query_arg(['wposa-menu' => 'crawlwp_tm_home'], CRAWLWP_SETTINGS_URL)) . '">',
					'</a>'
				),
			];
		}

		/* 6. WordPress core sitemaps disabled. */
		if (!$this->is_sitemap_enabled()) {
			$notices[] = [
				'id' => 'sitemap_disabled',
				'severity' => 'warning',
				'message' => __('<strong>CrawlWP SEO:</strong> The <strong>WordPress XML sitemap is disabled</strong>. Without a sitemap, search engines may have difficulty discovering all your pages.', 'mihdan-index-now'),
			];
		}

		/* 7. robots.txt blocking all crawlers. */
		if ($this->robots_txt_blocks_all()) {
			$notices[] = [
				'id' => 'robots_txt_blocking',
				'severity' => 'error',
				'message' => sprintf(
				/* translators: 1: link opening tag, 2: link closing tag */
					__('<strong>CrawlWP SEO:</strong> Your <strong>robots.txt file is blocking all search engine crawlers</strong> (Disallow: /). Search engines cannot index any of your pages. %1$sEdit robots.txt%2$s', 'mihdan-index-now'),
					'<a href="' . esc_url(CRAWLWP_ADVANCED_SETTINGS_URL . '#crawlwp_robots') . '">',
					'</a>'
				),
			];
		}

		/* 8. No SSL / HTTPS. */
		if (!is_ssl() && !$this->site_uses_https()) {
			$notices[] = [
				'id' => 'no_ssl',
				'severity' => 'warning',
				'message' => __('<strong>CrawlWP SEO:</strong> Your site is <strong>not using HTTPS</strong>. Google gives a ranking advantage to secure (HTTPS) sites. Contact your host to install an SSL certificate.', 'mihdan-index-now'),
			];
		}

		/**
		 * Filter the list of critical SEO notices before display.
		 *
		 * @param array[] $notices Array of notice definition arrays.
		 */
		return (array)apply_filters('crawlwp_seo_notices', $notices);
	}

	/**
	 * Render a single notice HTML.
	 *
	 * @param array $notice Notice definition.
	 */
	private function render_notice(array $notice): void
	{
		$severity = in_array($notice['severity'], ['error', 'warning', 'success', 'info'], true)
			? $notice['severity']
			: 'warning';

		$id = esc_attr($notice['id']);

		echo '<div class="notice notice-' . esc_attr($severity) . ' is-dismissible crawlwp-seo-notice" data-notice-id="' . $id . '">';
		echo '<p>' . wp_kses_post($notice['message']) . '</p>';
		echo '</div>';
	}

	/**
	 * AJAX handler for dismissing a notice permanently.
	 */
	public function ajax_dismiss_notice(): void
	{
		check_ajax_referer('crawlwp_dismiss_notice', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_die('', '', ['response' => 403]);
		}

		$notice_id = isset($_POST['notice_id']) ? sanitize_key($_POST['notice_id']) : '';

		if ($notice_id === '') {
			wp_send_json_error('missing notice_id');
		}

		$dismissed = $this->get_dismissed();
		$dismissed[$notice_id] = true;
		update_option(self::DISMISSED_KEY, $dismissed, false);

		wp_send_json_success();
	}

	/**
	 * Print inline JS for the dismiss buttons.
	 * Uses WordPress's native `.is-dismissible` mechanism but also persists via AJAX.
	 */
	public function print_dismiss_js(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$nonce = wp_create_nonce('crawlwp_dismiss_notice');

		?>
		<script>
			(function ($) {
				$(document).on('click', '.crawlwp-seo-notice .notice-dismiss', function () {
					var $notice = $(this).closest('.crawlwp-seo-notice');
					var noticeId = $notice.data('notice-id');
					if (!noticeId) return;
					$.post(ajaxurl, {
						action: 'crawlwp_dismiss_notice',
						nonce: '<?php echo esc_js($nonce); ?>',
						notice_id: noticeId
					});
				});
			})(jQuery);
		</script>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Retrieve the list of dismissed notice IDs for the current site.
	 *
	 * @return array<string, true>
	 */
	private function get_dismissed(): array
	{
		$stored = get_option(self::DISMISSED_KEY, []);

		return is_array($stored) ? $stored : [];
	}

	/**
	 * Detect the first active conflicting SEO plugin.
	 *
	 * @return string|null Human-readable plugin name, or null if none found.
	 */
	private function detect_conflicting_plugin(): ?string
	{
		if (!function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = (array)get_option('active_plugins', []);

		/* Include network-activated plugins for multisite. */
		if (is_multisite()) {
			$network_active = array_keys((array)get_site_option('active_sitewide_plugins', []));
			$active = array_merge($active, $network_active);
		}

		foreach (self::CONFLICTING_PLUGINS as $basename) {
			if (in_array($basename, $active, true)) {
				/* Resolve the plugin's display name. */
				$all_plugins = get_plugins();

				if (isset($all_plugins[$basename]['Name'])) {
					return $all_plugins[$basename]['Name'];
				}

				return $basename;
			}
		}

		return null;
	}

	/**
	 * Whether the WordPress core sitemap is accessible.
	 * It is disabled when the `wp_sitemaps_enabled` filter returns false,
	 * or when the `wp-sitemap.xml` route is not registered.
	 *
	 * @return bool
	 */
	private function is_sitemap_enabled(): bool
	{
		/** @global \WP_Sitemaps $wp_sitemaps */
		global $wp_sitemaps;

		return $wp_sitemaps->sitemaps_enabled();
	}

	/**
	 * Parse a physical (or virtual) robots.txt to detect a `Disallow: /` for User-agent: *.
	 *
	 * @return bool True if a catch-all Disallow is found.
	 */
	private function robots_txt_blocks_all(): bool
	{
		/* Only check once. */
		static $checked = null;

		if ($checked !== null) {
			return $checked;
		}

		$home_path = get_home_path();
		$file_path = $home_path . 'robots.txt';

		/* Physical file wins over WordPress virtual robots.txt. */
		if (file_exists($file_path)) {
			$content = @file_get_contents($file_path);
		} else {
			/* Read the virtual robots.txt generated by WordPress. */
			$robots_url = home_url('/robots.txt');
			$response = wp_remote_get($robots_url, [
				'timeout' => 5,
				'user-agent' => 'CrawlWP-SEO-Checker',
			]);

			$content = is_wp_error($response) ? '' : wp_remote_retrieve_body($response);
		}

		$checked = $this->content_blocks_all_crawlers((string)$content);

		return $checked;
	}

	/**
	 * Parse robots.txt content for a wildcard `Disallow: /`.
	 *
	 * @param string $content Raw robots.txt content.
	 * @return bool
	 */
	private function content_blocks_all_crawlers(string $content): bool
	{
		if ($content === '') {
			return false;
		}

		$lines = explode("\n", str_replace("\r\n", "\n", $content));
		$in_wildcard = false;

		foreach ($lines as $line) {
			$line = trim($line);

			if ($line === '' || $line[0] === '#') {
				continue;
			}

			if (stripos($line, 'user-agent:') === 0) {
				$agent = trim(substr($line, strlen('user-agent:')));
				$in_wildcard = ($agent === '*');
				continue;
			}

			if ($in_wildcard && stripos($line, 'disallow:') === 0) {
				$path = trim(substr($line, strlen('disallow:')));

				if ($path === '/') {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check whether the site's home URL uses https.
	 *
	 * @return bool
	 */
	private function site_uses_https(): bool
	{
		return str_starts_with(get_option('home', ''), 'https://');
	}
}
