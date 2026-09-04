<?php
/**
 * FeatureGate — master on/off switch for all on-page SEO features.
 *
 * New installs (option never saved) automatically have the feature enabled.
 * Existing installs that have explicitly saved the option respect whatever
 * value was stored, so upgrading users who turned it off are not affected.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\SEOCore\FeatureGate;

use Mihdan\IndexNow\Utils;

class FeatureGate
{
	const OPTION_KEY = 'crawlwp_seo_features'; // Full WP option name (what get_option/update_option use directly)
	const SECTION_ID = 'seo_features';         // Bare id passed to add_section(); WPOSA prepends 'crawlwp_' → 'crawlwp_seo_features'
	const OPTION_FIELD = 'enabled';

	/**
	 * In-memory cache so we only read the DB once per request.
	 *
	 * @var bool|null
	 */
	private static $cache = null;

	/**
	 * Register the admin promo/settings section.
	 */
	public function __construct()
	{
		if (!self::is_enabled()) {
			add_action('crawlwp_pre_setup_fields', [$this, 'register_menu'], -1);
		} else {
			if (Utils::_GET_var('wposa-menu') == 'crawlwp_seo_features') {
				Utils::content_http_redirect(CRAWLWP_SETTINGS_URL);
			}
		}
	}

	/**
	 * Return true when the on-page SEO features are enabled.
	 *
	 * For brand-new installs (the feature-gate option has never been saved AND
	 * no pre-existing plugin data exists) we auto-enable so a fresh user does
	 * not have to take any extra action.
	 *
	 * For users who are upgrading from a version before the feature gate was
	 * introduced, the gate option is also absent — but their pre-existing plugin
	 * options (e.g. crawlwp_index_now) ARE present. In that case we leave
	 * features disabled so they see the gate page and can opt-in consciously.
	 */
	public static function is_enabled(): bool
	{
		if (self::$cache === null) {

			$raw = get_option(self::OPTION_KEY);

			if ($raw === false) {
				// Option never written yet. Decide based on whether the plugin
				// was already in use: if the core IndexNow option exists this is
				// an upgrade, not a fresh install — leave features OFF.
				$is_fresh_install = empty(get_option('crawlwp_index_now', ''));

				if ($is_fresh_install) {
					self::enable();
					return self::$cache; // set to true by enable()
				}

				// Upgrade path: persist disabled state so we don't re-check every request.
				self::disable();
				return self::$cache; // set to false by disable()
			}

			$options = is_array($raw) ? $raw : [];

			self::$cache = isset($options[self::OPTION_FIELD]) && $options[self::OPTION_FIELD] === 'on';
		}

		return apply_filters('crawlwp_seo_features_is_enabled', self::$cache);
	}

	/**
	 * Enable the on-page SEO features.
	 */
	public static function enable(): void
	{
		$options = get_option(self::OPTION_KEY, []);
		$options[self::OPTION_FIELD] = 'on';
		update_option(self::OPTION_KEY, $options);
		self::$cache = true;
	}

	/**
	 * Disable the on-page SEO features.
	 */
	public static function disable(): void
	{
		$options = get_option(self::OPTION_KEY, []);
		$options[self::OPTION_FIELD] = 'off';
		update_option(self::OPTION_KEY, $options);
		self::$cache = false;
	}

	/**
	 * Register the "SEO Features" header menu and its promo section.
	 *
	 * @param \Mihdan\IndexNow\Views\WPOSA $wposa
	 */
	public function register_menu($wposa): void
	{
		$wposa->add_header_menu([
			'id' => 'seo_features',
			'title' => __('SEO Features', 'mihdan-index-now'),
		]);

		if ($wposa->get_active_header_menu() == Utils::get_plugin_prefix() . '_' . self::SECTION_ID) {

			$wposa->add_section([
				'header_menu_id' => self::SECTION_ID,
				'id' => self::SECTION_ID,
				'title' => '',
				'desc' => '',
				'callback' => [$this, 'render_promo'],
			]);
			$wposa->add_field(
				self::SECTION_ID,
				[
					'id' => self::OPTION_FIELD,
					'type' => 'checkbox',
					// callback_checkbox() renders `desc` as the label text next to the box.
					'desc' => __('Enable all on-page SEO features on this website', 'mihdan-index-now'),
				]
			);
		}
	}

	/**
	 * Render the promotional HTML block above the enable checkbox.
	 */
	public function render_promo(): void
	{
		$features = [
			[
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
				'title' => __('Title &amp; Meta Tags', 'mihdan-index-now'),
				'desc'  => __('Global title &amp; description templates across post types, archives, taxonomies and special pages.', 'mihdan-index-now'),
			],
			[
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>',
				'title' => __('Open Graph &amp; Social Cards', 'mihdan-index-now'),
				'desc'  => __('og:title, og:image, og:locale, article tags and fb:app_id for great link previews on every social network.', 'mihdan-index-now'),
			],
			[
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>',
				'title' => __('Schema / JSON-LD', 'mihdan-index-now'),
				'desc'  => __('WebSite, Organization/Person, WebPage, Article &amp; BreadcrumbList structured data for Google rich results.', 'mihdan-index-now'),
			],
			[
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>',
				'title' => __('Breadcrumbs', 'mihdan-index-now'),
				'desc'  => __('[crawlwp_breadcrumbs] shortcode with automatic BreadcrumbList JSON-LD schema included.', 'mihdan-index-now'),
			],
			[
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
				'title' => __('Robots &amp; robots.txt', 'mihdan-index-now'),
				'desc'  => __('Per-post and global noindex / nofollow / noarchive controls, plus a full robots.txt editor.', 'mihdan-index-now'),
			],
			[
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>',
				'title' => __('Canonical &amp; Pagination', 'mihdan-index-now'),
				'desc'  => __('Self-referencing canonical tags and rel=prev/next links to prevent duplicate-content penalties.', 'mihdan-index-now'),
			],
			[
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>',
				'title' => __('XML Sitemap Enhancements', 'mihdan-index-now'),
				'desc'  => __('Google News sitemap, custom URL lists, noindex filtering and Polylang / WPML / TranslatePress hreflang.', 'mihdan-index-now'),
			],
			[
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>',
				'title' => __('URL Redirects', 'mihdan-index-now'),
				'desc'  => __('301/302/307/410/451 redirects with regex support, hit tracking and automatic permalink-change detection.', 'mihdan-index-now'),
			],
			[
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>',
				'title' => __('RSS Feed Branding', 'mihdan-index-now'),
				'desc'  => __('Prepend and append custom text (with tokens) to feed items — deters scrapers and builds brand recognition.', 'mihdan-index-now'),
			],
			[
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
				'title' => __('Site &amp; Author Profiles', 'mihdan-index-now'),
				'desc'  => __('Declare your Organization or Person identity; authors can set Facebook URL, X handle and extra profile URLs.', 'mihdan-index-now'),
			],
			[
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>',
				'title' => __('SEO Notifications', 'mihdan-index-now'),
				'desc'  => __('Admin bar notification centre alerts you to discouraged indexing, conflicting plugins and robots.txt problems.', 'mihdan-index-now'),
			],
			[
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
				'title' => __('Post List SEO Column', 'mihdan-index-now'),
				'desc'  => __('Per-post SEO score column with Quick Edit support for SEO title and meta description directly from the list table.', 'mihdan-index-now'),
			],
		];

		?>
		<div class="cwp-fg-wrap">

			<div class="cwp-fg-hero">
				<span class="cwp-fg-hero__eyebrow"><?php esc_html_e('On-page SEO', 'mihdan-index-now'); ?></span>
				<h2 class="cwp-fg-hero__title">
					<?php esc_html_e('Complete on-page SEO, built into CrawlWP', 'mihdan-index-now'); ?>
				</h2>
				<p class="cwp-fg-hero__intro">
					<?php esc_html_e(
						'Everything you need to rank — title &amp; meta templates, Open Graph, Schema/JSON-LD, breadcrumbs, sitemaps, redirects and robots.txt — without installing a separate SEO plugin.',
						'mihdan-index-now'
					); ?>
				</p>
				<div class="cwp-fg-hero__badges">
					<span class="cwp-fg-badge"><?php esc_html_e('Zero extra plugins needed', 'mihdan-index-now'); ?></span>
					<span class="cwp-fg-badge"><?php esc_html_e('Multilingual ready', 'mihdan-index-now'); ?></span>
				</div>
			</div>

			<div class="cwp-fg-hero__illustration" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1360 800" role="img" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif">
						<defs><clipPath id="cwp-fg-clip"><rect x="1" y="1" width="1358" height="798" rx="10"></rect></clipPath></defs>
						<rect x="0.5" y="0.5" width="1359" height="799" rx="10" fill="#ffffff" stroke="#dcdcde"></rect>
						<g clip-path="url(#cwp-fg-clip)">
						<rect x="1" y="1" width="1358" height="63" fill="#f6f7f7"></rect>
						<line x1="0" y1="64" x2="1360" y2="64" stroke="#dcdcde"></line>
						<rect x="28" y="22" width="20" height="20" rx="5" fill="#2688c0"></rect>
						<path d="M34 32.5 l3.5 3.5 l6 -7" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path>
						<text x="60" y="39" font-size="16" font-weight="600" fill="#1d2327">CrawlWP SEO</text>
						<circle cx="1306" cy="32" r="17" fill="none" stroke="#e6e7e8" stroke-width="5"></circle>
						<circle cx="1306" cy="32" r="17" fill="none" stroke="#00a32a" stroke-width="5" stroke-linecap="round" stroke-dasharray="88 107" transform="rotate(-90 1306 32)"></circle>
						<text x="1306" y="37" font-size="14" font-weight="600" fill="#1d2327" text-anchor="middle">82</text>
						<text x="1272" y="37" font-size="12" fill="#646970" text-anchor="end">SEO score</text>
						<line x1="0" y1="108" x2="1360" y2="108" stroke="#e0e0e2"></line>
						<text x="28" y="93" font-size="13" font-weight="600" fill="#2688c0">General</text>
						<rect x="28" y="104" width="53" height="3" fill="#2688c0"></rect>
						<text x="109" y="93" font-size="13" fill="#646970">Social</text>
						<text x="169" y="93" font-size="13" fill="#646970">Advanced</text>
						<text x="253" y="93" font-size="13" fill="#646970">Schema</text>
						<text x="325" y="93" font-size="13" fill="#646970">Analysis</text>
						<text x="399" y="93" font-size="13" fill="#646970">Insights</text>
						<text x="470" y="93" font-size="13" fill="#646970">Links</text>
						<text x="28" y="150" font-size="12" font-weight="600" fill="#1d2327">Search title</text>
						<rect x="28" y="160" width="640" height="42" rx="5" fill="#fff" stroke="#8c8f94"></rect>
						<text x="42" y="186" font-size="14" fill="#1d2327">A Practical Guide to WordPress SEO in 2026</text>
						<rect x="28" y="214" width="640" height="6" rx="3" fill="#e6e7e8"></rect>
						<rect x="28" y="214" width="565" height="6" rx="3" fill="#00a32a"></rect>
						<text x="28" y="238" font-size="11" fill="#646970">Fits on desktop and mobile</text>
						<text x="668" y="238" font-size="11" fill="#646970" text-anchor="end">512 of 580 px</text>
						<text x="28" y="274" font-size="12" font-weight="600" fill="#1d2327">Meta description</text>
						<rect x="28" y="284" width="640" height="88" rx="5" fill="#fff" stroke="#8c8f94"></rect>
						<text x="42" y="310" font-size="14" fill="#1d2327">Learn how to structure, optimize and index a</text>
						<text x="42" y="332" font-size="14" fill="#1d2327">WordPress site so search engines find your content</text>
						<text x="42" y="354" font-size="14" fill="#1d2327">within hours rather than weeks.</text>
						<rect x="28" y="384" width="640" height="6" rx="3" fill="#e6e7e8"></rect>
						<rect x="28" y="384" width="592" height="6" rx="3" fill="#00a32a"></rect>
						<text x="668" y="408" font-size="11" fill="#646970" text-anchor="end">148 of 160 characters</text>
						<text x="28" y="444" font-size="12" font-weight="600" fill="#1d2327">URL slug</text>
						<rect x="28" y="454" width="640" height="42" rx="5" fill="#fff" stroke="#8c8f94"></rect>
						<text x="42" y="480" font-size="14" fill="#1d2327">wordpress-seo</text>
						<text x="28" y="530" font-size="12" font-weight="600" fill="#1d2327">Focus keyword</text>
						<rect x="28" y="540" width="640" height="42" rx="5" fill="#fff" stroke="#8c8f94"></rect>
						<text x="42" y="566" font-size="14" fill="#1d2327">wordpress seo</text>
						<rect x="540" y="550" width="116" height="22" rx="11" fill="#e6f4ec"></rect>
						<text x="598" y="565" font-size="11" font-weight="600" fill="#166534" text-anchor="middle">In title &#xb7; URL &#xb7; H2</text>
						<text x="28" y="622" font-size="12" font-weight="600" fill="#1d2327">Insert variable</text>
						<rect x="28" y="634" width="120" height="26" rx="13" fill="#f0f0f1" stroke="#dcdcde"></rect>
						<text x="88" y="651" font-size="11.5" fill="#50575e" text-anchor="middle">{{ post.title }}</text>
						<rect x="156" y="634" width="112" height="26" rx="13" fill="#f0f0f1" stroke="#dcdcde"></rect>
						<text x="212" y="651" font-size="11.5" fill="#50575e" text-anchor="middle">{{ site.title }}</text>
						<rect x="276" y="634" width="104" height="26" rx="13" fill="#f0f0f1" stroke="#dcdcde"></rect>
						<text x="328" y="651" font-size="11.5" fill="#50575e" text-anchor="middle">{{ category }}</text>
						<rect x="388" y="634" width="96" height="26" rx="13" fill="#f0f0f1" stroke="#dcdcde"></rect>
						<text x="436" y="651" font-size="11.5" fill="#50575e" text-anchor="middle">{{ sep }}</text>
						<rect x="28" y="694" width="640" height="76" rx="8" fill="#fff" stroke="#dcdcde"></rect>
						<circle cx="60" cy="732" r="12" fill="#e6f4ec"></circle>
						<path d="M54.5 732 l4 4 l7 -8" fill="none" stroke="#166534" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
						<text x="84" y="727" font-size="13" font-weight="600" fill="#1d2327">Article schema attached</text>
						<text x="84" y="746" font-size="12" fill="#646970">JSON-LD validates &#xb7; headline, author, section, breadcrumb</text>
						<text x="656" y="737" font-size="12" fill="#2688c0" text-anchor="end">View output</text>
						<line x1="700" y1="130" x2="700" y2="770" stroke="#e6e7e8"></line>
						<text x="732" y="152" font-size="12" font-weight="600" fill="#1d2327">Google preview</text>
						<rect x="1156" y="136" width="152" height="26" rx="13" fill="#f0f0f1" stroke="#dcdcde"></rect>
						<rect x="1158" y="138" width="74" height="22" rx="11" fill="#ffffff" stroke="#c3c4c7"></rect>
						<text x="1195" y="153" font-size="11" font-weight="600" fill="#1d2327" text-anchor="middle">Desktop</text>
						<text x="1270" y="153" font-size="11" fill="#646970" text-anchor="middle">Mobile</text>
						<rect x="732" y="180" width="576" height="212" rx="8" fill="#fbfbfc" stroke="#dcdcde"></rect>
						<circle cx="765" cy="222" r="13" fill="#2688c0"></circle>
						<path d="M759.5 222 l4 4 l7 -8" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
						<text x="788" y="218" font-size="12" font-family="Arial, Helvetica, sans-serif" fill="#202124">yoursite.com</text>
						<text x="788" y="233" font-size="12" font-family="Arial, Helvetica, sans-serif" fill="#4d5156">https://yoursite.com &#x203a; guides &#x203a; wordpress-seo</text>
						<text x="752" y="272" font-size="20" font-family="Arial, Helvetica, sans-serif" fill="#1a0dab">A Practical Guide to WordPress SEO in 2026</text>
						<text x="752" y="298" font-size="14" font-family="Arial, Helvetica, sans-serif" fill="#4d5156">Learn how to structure, optimize and index a WordPress site so</text>
						<text x="752" y="318" font-size="14" font-family="Arial, Helvetica, sans-serif" fill="#4d5156">search engines find your content within hours rather than weeks.</text>
						<rect x="752" y="340" width="164" height="24" rx="12" fill="#e6f4ec"></rect>
						<text x="834" y="356" font-size="11" font-weight="600" fill="#166534" text-anchor="middle">Indexed &#xb7; 3h after publish</text>
						<rect x="926" y="340" width="122" height="24" rx="12" fill="#eef6fb"></rect>
						<text x="987" y="356" font-size="11" font-weight="600" fill="#1c6a94" text-anchor="middle">Canonical: self</text>
						<text x="732" y="432" font-size="12" font-weight="600" fill="#1d2327">Facebook preview</text>
						<rect x="732" y="448" width="576" height="180" rx="8" fill="#ffffff" stroke="#dcdcde"></rect>
						<rect x="733" y="449" width="574" height="96" rx="0" fill="#eef2f7"></rect>
						<path d="M960 480 l26 26 l-52 0 z" fill="#c9d4e2"></path>
						<circle cx="1030" cy="486" r="12" fill="#c9d4e2"></circle>
						<text x="752" y="574" font-size="11" font-family="Arial, Helvetica, sans-serif" fill="#606770">YOURSITE.COM</text>
						<text x="752" y="596" font-size="14" font-weight="600" font-family="Arial, Helvetica, sans-serif" fill="#1d2129">A Practical Guide to WordPress SEO in 2026</text>
						<text x="752" y="616" font-size="12" font-family="Arial, Helvetica, sans-serif" fill="#606770">Structure, optimize and index a WordPress site properly.</text>
						<text x="732" y="676" font-size="12" font-weight="600" fill="#1d2327">Robots directives</text>
						<rect x="732" y="690" width="92" height="28" rx="14" fill="#eef6fb" stroke="#2688c0"></rect>
						<text x="778" y="708" font-size="11.5" font-weight="600" fill="#1c6a94" text-anchor="middle">index</text>
						<rect x="832" y="690" width="92" height="28" rx="14" fill="#eef6fb" stroke="#2688c0"></rect>
						<text x="878" y="708" font-size="11.5" font-weight="600" fill="#1c6a94" text-anchor="middle">follow</text>
						<rect x="932" y="690" width="108" height="28" rx="14" fill="#f6f7f7" stroke="#dcdcde"></rect>
						<text x="986" y="708" font-size="11.5" fill="#646970" text-anchor="middle">noarchive</text>
						<rect x="1048" y="690" width="120" height="28" rx="14" fill="#f6f7f7" stroke="#dcdcde"></rect>
						<text x="1108" y="708" font-size="11.5" fill="#646970" text-anchor="middle">nosnippet</text>
						<text x="732" y="752" font-size="12" fill="#646970">Canonical URL: https://yoursite.com/guides/wordpress-seo</text>
						</g>
					</svg>
			</div>

			<div class="cwp-fg-section-head">
				<h3 class="cwp-fg-section-head__title"><?php esc_html_e('What you get', 'mihdan-index-now'); ?></h3>
			</div>

			<div class="cwp-fg-grid">
				<?php foreach ($features as $feature): ?>
					<div class="cwp-fg-card">
						<span class="cwp-fg-card__icon" aria-hidden="true"><?php echo $feature['icon']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<div class="cwp-fg-card__body">
							<h3 class="cwp-fg-card__title"><?php echo wp_kses_post($feature['title']); ?></h3>
							<p class="cwp-fg-card__desc"><?php echo esc_html($feature['desc']); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="cwp-fg-note">
				<svg class="cwp-fg-note__icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
				<div>
					<strong><?php esc_html_e('Migrating from another SEO plugin?', 'mihdan-index-now'); ?></strong>
					<?php esc_html_e(
						'Enable the features below first — this unlocks the Title &amp; Meta, Social Networks, Site Information and all other settings tabs. Configure everything to your liking, then safely deactivate your old SEO plugin.',
						'mihdan-index-now'
					); ?>
				</div>
			</div>

			<div class="cwp-fg-cta">
				<span class="cwp-fg-cta__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
				</span>
				<div class="cwp-fg-cta__body">
					<h3 class="cwp-fg-cta__title"><?php esc_html_e('Turn on the SEO features', 'mihdan-index-now'); ?></h3>
					<p class="cwp-fg-cta__text">
						<?php esc_html_e(
							'Tick the box below and save to activate everything listed above. Nothing is rewritten in your content.',
							'mihdan-index-now'
						); ?>
					</p>
				</div>
			</div>

		</div>

		<style>
			.cwp-fg-wrap {
				margin: 0 0 0;
			}

			.wposa-form-table__row_crawlwp_seo_features_enabled th {
				padding: 0;
			}

			/* ── Hero ── */
			.cwp-fg-hero {
				position: relative;
				overflow: hidden;
				background: radial-gradient(circle at 88% -30%, #2c3b47 0%, #1d2327 55%);
				border-radius: 8px 8px 0 0;
				padding: 34px 36px 30px;
			}

			.cwp-fg-hero__eyebrow {
				display: inline-block;
				margin-bottom: 12px;
				font-size: 11px;
				font-weight: 600;
				letter-spacing: .1em;
				text-transform: uppercase;
				color: #7fb5dd;
			}

			.cwp-fg-hero__title {
				margin: 0 0 10px;
				font-size: 23px;
				font-weight: 700;
				color: #fff;
				line-height: 1.25;
				letter-spacing: -.01em;
			}

			.cwp-fg-hero__intro {
				margin: 0 0 18px;
				max-width: 760px;
				font-size: 13.5px;
				line-height: 1.7;
				color: #b6bcc3;
			}

			.cwp-fg-hero__badges {
				display: flex;
				flex-wrap: wrap;
				gap: 6px;
			}

			.cwp-fg-badge {
				display: inline-block;
				padding: 4px 11px;
				background: rgba(255,255,255,.06);
				border: 1px solid rgba(255,255,255,.14);
				border-radius: 20px;
				font-size: 11px;
				font-weight: 500;
				color: #d7dbdf;
				letter-spacing: .02em;
			}

			.cwp-fg-hero__illustration {
				border: 1px solid #dcdcde;
				border-top: none;
				border-radius: 0 0 8px 8px;
				overflow: hidden;
				margin-bottom: 28px;
				background: #fff;
			}

			.cwp-fg-hero__illustration svg {
				display: block;
				width: 100%;
				height: auto;
			}

			/* ── Section heading ── */
			.cwp-fg-section-head {
				display: flex;
				align-items: baseline;
				justify-content: space-between;
				gap: 12px;
				margin-bottom: 12px;
			}

			.cwp-fg-section-head__title {
				margin: 0;
				font-size: 13px;
				font-weight: 600;
				letter-spacing: .06em;
				text-transform: uppercase;
				color: #1d2327;
			}

			.cwp-fg-section-head__meta {
				font-size: 12px;
				color: #646970;
			}

			/* ── Feature grid ── */
			.cwp-fg-grid {
				display: grid;
				grid-template-columns: repeat(4, 1fr);
				gap: 12px;
				margin-bottom: 20px;
			}

			.cwp-fg-card {
				display: flex;
				align-items: flex-start;
				gap: 11px;
				background: #fff;
				border: 1px solid #e2e4e7;
				border-radius: 6px;
				padding: 16px 18px;
				transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
			}

			.cwp-fg-card:hover {
				border-color: #c3c4c7;
				box-shadow: 0 2px 6px rgba(0,0,0,.05);
				transform: translateY(-1px);
			}

			.cwp-fg-card__icon {
				display: flex;
				align-items: center;
				justify-content: center;
				flex: 0 0 30px;
				width: 30px;
				height: 30px;
				border-radius: 6px;
				background: #f0f6fc;
				color: #2271b1;
				line-height: 1;
			}

			.cwp-fg-card__icon svg {
				display: block;
			}

			.cwp-fg-card__body {
				flex: 1 1 auto;
				min-width: 0;
			}

			.cwp-fg-card__title {
				margin: 1px 0 4px;
				font-size: 13px;
				font-weight: 600;
				color: #1d2327;
				line-height: 1.4;
			}

			.cwp-fg-card__desc {
				margin: 0;
				font-size: 12.5px;
				color: #646970;
				line-height: 1.55;
			}

			/* ── Migration note ── */
			.cwp-fg-note {
				display: flex;
				align-items: flex-start;
				gap: 10px;
				background: #fbfbfc;
				border: 1px solid #e2e4e7;
				border-radius: 6px;
				padding: 15px 18px;
				font-size: 12.5px;
				color: #50575e;
				line-height: 1.6;
				margin-bottom: 24px;
			}

			.cwp-fg-note__icon {
				flex: 0 0 16px;
				margin-top: 2px;
				color: #8c8f94;
			}

			.cwp-fg-note strong {
				display: block;
				margin-bottom: 2px;
				color: #1d2327;
				font-weight: 600;
			}

			/* ──────────────────────────────────────────────────────────
			   Call-to-action panel.
			   The CTA block below is the visual header of a single card;
			   the WPOSA settings row that renders the enable checkbox is
			   restyled as that same card's body so the two read as one
			   highlighted panel.
			   ────────────────────────────────────────────────────────── */
			.cwp-fg-cta {
				display: flex;
				align-items: flex-start;
				gap: 12px;
				background: #f0f6fc;
				border: 1px solid #9ec9e8;
				border-bottom: none;
				border-radius: 8px 8px 0 0;
				padding: 20px 22px 16px;
				box-shadow: 0 1px 0 rgba(34,113,177,.06);
			}

			.cwp-fg-cta__icon {
				display: flex;
				align-items: center;
				justify-content: center;
				flex: 0 0 32px;
				width: 32px;
				height: 32px;
				border-radius: 50%;
				background: #2271b1;
				color: #fff;
				line-height: 1;
			}

			.cwp-fg-cta__body {
				flex: 1 1 auto;
				min-width: 0;
			}

			.cwp-fg-cta__title {
				margin: 3px 0 4px;
				font-size: 15px;
				font-weight: 600;
				color: #0a4b78;
				line-height: 1.3;
			}

			.cwp-fg-cta__text {
				margin: 0;
				font-size: 12.5px;
				color: #40607a;
				line-height: 1.6;
			}

			/* ── Enable checkbox row, styled as the CTA card body ── */
			#crawlwp_seo_features .form-table {
				margin-top: 0;
			}

			.wposa-form-table__row_crawlwp_seo_features_enabled {
				display: block;
				background: #f0f6fc;
				border: 1px solid #9ec9e8;
				border-top: none;
				border-radius: 0 0 8px 8px;
				padding: 4px 22px 20px 66px;
			}

			.wposa-form-table__row_crawlwp_seo_features_enabled th {
				display: none;
			}

			.wposa-form-table__row_crawlwp_seo_features_enabled td {
				display: block;
				width: auto;
				padding: 0;
			}

			.wposa-form-table__row_crawlwp_seo_features_enabled label {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				font-size: 13.5px;
				font-weight: 600;
				color: #0a4b78;
			}

			.wposa-form-table__row_crawlwp_seo_features_enabled input[type="checkbox"] {
				width: 18px;
				height: 18px;
				margin: 0;
				border-color: #2271b1;
			}

			.wposa-form-table__row_crawlwp_seo_features_enabled fieldset {
				margin: 0;
			}

			@media screen and (max-width: 960px) {
				.cwp-fg-grid {
					grid-template-columns: repeat(2, 1fr);
				}

				.wposa-form-table__row_crawlwp_seo_features_enabled {
					padding-left: 22px;
				}
			}
		</style>
		<?php
	}
}
