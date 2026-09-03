<?php

namespace Mihdan\IndexNow\SEOCore\Notifications;

use Mihdan\IndexNow\SEOCore\FeatureGate\FeatureGate;
use Mihdan\IndexNow\SEOCore\TitleMeta\Options;

/**
 * CrawlWP SEO Notification Center.
 *
 * Replaces individual admin_notices with a single admin-bar notification bell
 * that shows a badge count and a dropdown panel listing all active SEO issues.
 * Each notice can be dismissed permanently per-site.
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
	 * WordPress option key for dismissed notices.
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

	/**
	 * Cached list of active (non-dismissed) notices for the current request.
	 *
	 * @var array[]|null
	 */
	private $active_notices = null;

	public function __construct()
	{
		add_action('admin_bar_menu', [$this, 'add_admin_bar_node'], 999);
		add_action('admin_head', [$this, 'print_styles']);
		add_action('admin_footer', [$this, 'print_panel_html']);
		add_action('admin_footer', [$this, 'print_scripts']);
		add_action('wp_ajax_crawlwp_dismiss_notice', [$this, 'ajax_dismiss_notice']);
	}

	// -------------------------------------------------------------------------
	// Admin Bar Node
	// -------------------------------------------------------------------------

	/**
	 * Add the CrawlWP notification bell to the WP admin bar.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar
	 */
	public function add_admin_bar_node(\WP_Admin_Bar $wp_admin_bar): void
	{
		if (!current_user_can('manage_options') || !is_admin()) {
			return;
		}

		$notices = $this->get_active_notices();

		if (empty($notices)) {
			return;
		}

		$count = count($notices);
		$has_error = !empty(array_filter($notices, fn($n) => $n['severity'] === 'error'));
		$bell_class = $has_error ? 'cwp-nc-bell cwp-nc-bell--error' : 'cwp-nc-bell cwp-nc-bell--warning';

		/* The bell icon SVG. */
		$bell_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false">'
			. '<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>'
			. '</svg>';

		$title = '<span class="' . esc_attr($bell_class) . '">'
			. $bell_svg
			. '<span class="cwp-nc-badge" aria-label="' . esc_attr(sprintf(
			/* translators: %d number of SEO issues */
				_n('%d SEO issue', '%d SEO issues', $count, 'mihdan-index-now'),
				$count
			)) . '">' . esc_html($count) . '</span>'
			. '</span>';

		$wp_admin_bar->add_node([
			'id' => 'crawlwp-notifications',
			'title' => $title,
			'href' => '#',
			'meta' => [
				'class' => 'cwp-nc-menu',
				'tabindex' => '0',
			],
		]);
	}

	// -------------------------------------------------------------------------
	// Panel HTML (rendered in admin footer, toggled by JS)
	// -------------------------------------------------------------------------

	/**
	 * Render the notification center dropdown panel into the page footer.
	 * Visibility is controlled via JS.
	 */
	public function print_panel_html(): void
	{
		if (!current_user_can('manage_options') || !is_admin()) {
			return;
		}

		$notices = $this->get_active_notices();

		if (empty($notices)) {
			return;
		}

		$nonce = wp_create_nonce('crawlwp_dismiss_notice');
		?>
		<div id="cwp-nc-panel" class="cwp-nc-panel" role="dialog"
		     aria-label="<?php esc_attr_e('CrawlWP SEO Notifications', 'mihdan-index-now'); ?>" hidden>
			<div class="cwp-nc-panel__header">
				<span class="cwp-nc-panel__title">
					<?php
					$count = count($notices);
					printf(
					/* translators: %d number of SEO issues */
						esc_html(_n('%d SEO Issue', '%d SEO Issues', $count, 'mihdan-index-now')),
						(int)$count
					);
					?>
				</span>
				<button type="button" class="cwp-nc-panel__close"
				        aria-label="<?php esc_attr_e('Close notification panel', 'mihdan-index-now'); ?>">&#10005;
				</button>
			</div>
			<ul class="cwp-nc-panel__list">
				<?php foreach ($notices as $notice) : ?>
					<li class="cwp-nc-item cwp-nc-item--<?php echo esc_attr($notice['severity']); ?>"
					    data-notice-id="<?php echo esc_attr($notice['id']); ?>">
						<span class="cwp-nc-item__icon" aria-hidden="true">
							<?php echo $notice['severity'] === 'error' ? '&#9888;' : '&#9432;'; ?>
						</span>
						<span class="cwp-nc-item__message"><?php echo wp_kses_post($notice['message']); ?></span>
						<button type="button"
						        class="cwp-nc-item__dismiss"
						        data-notice-id="<?php echo esc_attr($notice['id']); ?>"
						        data-nonce="<?php echo esc_attr($nonce); ?>"
						        aria-label="<?php esc_attr_e('Dismiss this notice', 'mihdan-index-now'); ?>">
							&#10005;
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Inline Styles
	// -------------------------------------------------------------------------

	/**
	 * Print scoped CSS for the notification center.
	 * Loaded only in the WP admin.
	 */
	public function print_styles(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		if (empty($this->get_active_notices())) {
			return;
		}
		?>
		<style id="cwp-nc-styles">
			/* ---- Admin-bar bell ---- */
			#wp-admin-bar-crawlwp-notifications > .ab-item {
				display: flex !important;
				align-items: center;
				padding: 0 8px;
				cursor: pointer;
			}

			.cwp-nc-bell {
				display: flex;
				align-items: center;
				gap: 4px;
				line-height: 1;
			}

			.cwp-nc-bell svg {
				display: block;
				width: 18px;
				height: 18px;
			}

			/* Error = red tint, Warning = amber tint */
			.cwp-nc-bell--error svg {
				color: #ff8b8b;
			}

			.cwp-nc-bell--warning svg {
				color: #f0c040;
			}

			.cwp-nc-badge {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				min-width: 18px;
				height: 18px;
				padding: 0 5px;
				border-radius: 9px;
				font-size: 11px;
				font-weight: 700;
				line-height: 1;
				color: #fff;
			}

			.cwp-nc-bell--error .cwp-nc-badge {
				background: #cc1818;
			}

			.cwp-nc-bell--warning .cwp-nc-badge {
				background: #b57800;
			}

			/* ---- Dropdown panel ---- */
			.cwp-nc-panel {
				position: fixed;
				top: 32px; /* below the admin bar */
				right: 16px;
				z-index: 99999;
				width: 420px;
				max-width: calc(100vw - 32px);
				max-height: calc(100vh - 60px);
				overflow-y: auto;
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				box-shadow: 0 4px 16px rgba(0, 0, 0, .18);
				font-size: 13px;
			}

			.cwp-nc-panel[hidden] {
				display: none;
			}

			.cwp-nc-panel__header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: 12px 14px 10px;
				border-bottom: 1px solid #e0e0e0;
				background: #f6f7f7;
			}

			.cwp-nc-panel__title {
				font-weight: 600;
				font-size: 13px;
				color: #1d2327;
			}

			.cwp-nc-panel__close {
				background: none;
				border: none;
				cursor: pointer;
				color: #646970;
				font-size: 16px;
				line-height: 1;
				padding: 0 2px;
				border-radius: 3px;
			}

			.cwp-nc-panel__close:hover,
			.cwp-nc-panel__close:focus {
				color: #1d2327;
				background: #e0e0e0;
				outline: none;
			}

			.cwp-nc-panel__list {
				margin: 0;
				padding: 0;
				list-style: none;
			}

			/* ---- Individual notice items ---- */
			.cwp-nc-item {
				display: flex;
				align-items: flex-start;
				gap: 10px;
				padding: 12px 14px;
				border-bottom: 1px solid #f0f0f1;
				transition: background .1s;
			}

			.cwp-nc-item:last-child {
				border-bottom: none;
			}

			.cwp-nc-item:hover {
				background: #fafafa;
			}

			/* Left-border accent by severity */
			.cwp-nc-item--error {
				border-left: 3px solid #d63638;
			}

			.cwp-nc-item--warning {
				border-left: 3px solid #dba617;
			}

			.cwp-nc-item--success {
				border-left: 3px solid #00a32a;
			}

			.cwp-nc-item--info {
				border-left: 3px solid #72aee6;
			}

			.cwp-nc-item__icon {
				flex-shrink: 0;
				font-size: 15px;
				line-height: 1.4;
			}

			.cwp-nc-item--error .cwp-nc-item__icon {
				color: #d63638;
			}

			.cwp-nc-item--warning .cwp-nc-item__icon {
				color: #b57800;
			}

			.cwp-nc-item__message {
				flex: 1;
				line-height: 1.5;
				color: #1d2327;
			}

			.cwp-nc-item__message strong {
				font-weight: 600;
			}

			.cwp-nc-item__message a {
				color: #2271b1;
				text-decoration: none;
			}

			.cwp-nc-item__message a:hover {
				text-decoration: underline;
			}

			.cwp-nc-item__dismiss {
				flex-shrink: 0;
				background: none;
				border: none;
				cursor: pointer;
				color: #c3c4c7;
				font-size: 14px;
				line-height: 1;
				padding: 2px 4px;
				border-radius: 3px;
				align-self: center;
			}

			.cwp-nc-item__dismiss:hover,
			.cwp-nc-item__dismiss:focus {
				color: #646970;
				background: #e0e0e0;
				outline: none;
			}

			/* Slide-out animation on dismiss */
			.cwp-nc-item.is-dismissing {
				opacity: 0;
				max-height: 0;
				padding-top: 0;
				padding-bottom: 0;
				overflow: hidden;
				transition: opacity .2s, max-height .25s .05s, padding .25s .05s;
			}
		</style>
		<?php
	}

	// -------------------------------------------------------------------------
	// Scripts
	// -------------------------------------------------------------------------

	/**
	 * Print inline JS that wires up the bell toggle and dismiss buttons.
	 */
	public function print_scripts(): void
	{
		if (!current_user_can('manage_options') || !is_admin()) {
			return;
		}

		if (empty($this->get_active_notices())) {
			return;
		}
		?>
		<script id="cwp-nc-scripts">
			(function () {
				'use strict';

				var bellBtn = document.getElementById('wp-admin-bar-crawlwp-notifications');
				var panel = document.getElementById('cwp-nc-panel');
				var closeBtn = panel ? panel.querySelector('.cwp-nc-panel__close') : null;

				if (!bellBtn || !panel) return;

				/* Toggle the panel when the bell is clicked. */
				bellBtn.addEventListener('click', function (e) {
					e.preventDefault();
					e.stopPropagation();
					togglePanel();
				});

				/* Close via the × button. */
				if (closeBtn) {
					closeBtn.addEventListener('click', function () {
						closePanel();
					});
				}

				/* Close when clicking outside. */
				document.addEventListener('click', function (e) {
					if (!panel.hidden && !panel.contains(e.target) && !bellBtn.contains(e.target)) {
						closePanel();
					}
				});

				/* Close on Escape. */
				document.addEventListener('keydown', function (e) {
					if (e.key === 'Escape' && !panel.hidden) {
						closePanel();
						bellBtn.querySelector('a') && bellBtn.querySelector('a').focus();
					}
				});

				/* Dismiss individual notices. */
				panel.addEventListener('click', function (e) {
					var btn = e.target.closest('.cwp-nc-item__dismiss');
					if (!btn) return;

					var item = btn.closest('.cwp-nc-item');
					var noticeId = btn.dataset.noticeId;
					var nonce = btn.dataset.nonce;

					if (!item || !noticeId) return;

					/* Animate out. */
					item.classList.add('is-dismissing');

					setTimeout(function () {
						item.remove();
						updateBadge();

						/* If no items remain, close and hide the bell. */
						var remaining = panel.querySelectorAll('.cwp-nc-item');
						if (remaining.length === 0) {
							closePanel();
							if (bellBtn) bellBtn.style.display = 'none';
						}
					}, 300);

					/* Persist via AJAX. */
					var data = new FormData();
					data.append('action', 'crawlwp_dismiss_notice');
					data.append('nonce', nonce);
					data.append('notice_id', noticeId);

					fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
						method: 'POST',
						body: data,
						credentials: 'same-origin'
					});
				});

				/* ---------- helpers ---------- */

				function togglePanel() {
					if (panel.hidden) {
						openPanel();
					} else {
						closePanel();
					}
				}

				function openPanel() {
					panel.hidden = false;
					panel.removeAttribute('hidden');
					bellBtn.setAttribute('aria-expanded', 'true');
					if (closeBtn) closeBtn.focus();
				}

				function closePanel() {
					panel.hidden = true;
					panel.setAttribute('hidden', '');
					bellBtn.setAttribute('aria-expanded', 'false');
				}

				function updateBadge() {
					var badge = bellBtn.querySelector('.cwp-nc-badge');
					var count = panel.querySelectorAll('.cwp-nc-item').length;
					if (badge) badge.textContent = count;
				}
			}());
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX Dismiss
	// -------------------------------------------------------------------------

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

	// -------------------------------------------------------------------------
	// Notice Collection
	// -------------------------------------------------------------------------

	/**
	 * Return the cached list of active (non-dismissed) notices.
	 *
	 * @return array[]
	 */
	private function get_active_notices(): array
	{
		if ($this->active_notices !== null) {
			return $this->active_notices;
		}

		$dismissed = $this->get_dismissed();
		$notices = $this->collect_notices();

		$this->active_notices = array_values(
			array_filter($notices, fn($n) => !isset($dismissed[$n['id']]))
		);

		return $this->active_notices;
	}

	/**
	 * Collect all critical SEO notices that are currently triggered.
	 *
	 * @return array[] Each item: id, severity (error|warning|success|info), message.
	 */
	private function collect_notices(): array
	{
		$notices = [];

		/* 1. WordPress "Discourage search engines" setting. */
		if (!get_option('blog_public', 1)) {
			$notices[] = [
				'id' => 'blog_not_public',
				'severity' => 'error',
				'message' => sprintf(
				/* translators: 1: link opening tag, 2: link closing tag */
					__('Your site is set to <strong>discourage search engines from indexing</strong>. Search engines will not index your pages. %1$sChange this setting%2$s', 'mihdan-index-now'),
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
					__('Your <strong>Homepage is set to noindex</strong> in CrawlWP Title &amp; Meta settings. Search engines will not index your homepage. %1$sReview settings%2$s', 'mihdan-index-now'),
					'<a href="' . esc_url(add_query_arg(['wposa-menu' => 'crawlwp_tm_home'], CRAWLWP_SETTINGS_URL)) . '">',
					'</a>'
				),
			];
		}

		/* 3. Conflicting SEO plugin detected (only relevant when our output is active). */
		$conflict = $this->detect_conflicting_plugin();

		if ($conflict !== null) {
			$notices[] = [
				'id' => 'conflicting_seo_plugin',
				'severity' => 'warning',
				'message' => sprintf(
				/* translators: %s: conflicting plugin name */
					__('<strong>%s</strong> is also active on your site. Running two SEO plugins simultaneously can cause duplicate meta tags and conflicting settings. Please deactivate one of them.', 'mihdan-index-now'),
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
					__('Your homepage has <strong>no SEO title template</strong> set. A descriptive title is critical for search engine rankings. %1$sConfigure now%2$s', 'mihdan-index-now'),
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
					__('Your homepage has <strong>no meta description template</strong> set. A good description improves click-through rates from search results. %1$sConfigure now%2$s', 'mihdan-index-now'),
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
				'message' => __('The <strong>WordPress XML sitemap is disabled</strong>. Without a sitemap, search engines may have difficulty discovering all your pages.', 'mihdan-index-now'),
			];
		}

		/* 7. robots.txt blocking all crawlers. */
		if ($this->robots_txt_blocks_all()) {
			$notices[] = [
				'id' => 'robots_txt_blocking',
				'severity' => 'error',
				'message' => sprintf(
				/* translators: 1: link opening tag, 2: link closing tag */
					__('Your <strong>robots.txt file is blocking all search engine crawlers</strong> (Disallow: /). Search engines cannot index any of your pages. %1$sEdit robots.txt%2$s', 'mihdan-index-now'),
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
				'message' => __('Your site is <strong>not using HTTPS</strong>. Google gives a ranking advantage to secure (HTTPS) sites. Contact your host to install an SSL certificate.', 'mihdan-index-now'),
			];
		}

		/**
		 * Filter the list of critical SEO notices before display.
		 *
		 * @param array[] $notices Array of notice definition arrays.
		 */
		return (array)apply_filters('crawlwp_seo_notices', $notices);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

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
	 *
	 * @return bool
	 */
	private function is_sitemap_enabled(): bool
	{
		/** @global \WP_Sitemaps $wp_sitemaps */
		global $wp_sitemaps;

		return is_a($wp_sitemaps, \WP_Sitemaps::class) && $wp_sitemaps->sitemaps_enabled();
	}

	/**
	 * Parse a physical (or virtual) robots.txt to detect a `Disallow: /` for User-agent: *.
	 *
	 * @return bool True if a catch-all Disallow is found.
	 */
	private function robots_txt_blocks_all(): bool
	{
		static $checked = null;

		if ($checked !== null) {
			return $checked;
		}

		$home_path = get_home_path();
		$file_path = $home_path . 'robots.txt';

		if (file_exists($file_path)) {
			$content = @file_get_contents($file_path);
		} else {
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
	 * Check whether the site's home URL uses HTTPS.
	 *
	 * @return bool
	 */
	private function site_uses_https(): bool
	{
		return str_starts_with(get_option('home', ''), 'https://');
	}
}
