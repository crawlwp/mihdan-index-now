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
					'name' => __('Enable on-page SEO features', 'mihdan-index-now'),
					'label' => __('Activate CrawlWP\'s complete on-page SEO suite for this website.', 'mihdan-index-now'),
					'desc' => __('Check this box to enable all on-page SEO features. Leave it unchecked if you prefer to keep using your current SEO plugin.', 'mihdan-index-now'),
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
				'title' => __('Title & Meta Tags', 'mihdan-index-now'),
				'desc' => __('Global templates for SEO title and meta description across all post types, archives, taxonomies and special pages.', 'mihdan-index-now'),
			],
			[
				'title' => __('Open Graph & Social Cards', 'mihdan-index-now'),
				'desc' => __('Outputs og:title, og:image, og:locale, article tags and fb:app_id so links look great on Facebook, LinkedIn and X/Twitter.', 'mihdan-index-now'),
			],
			[
				'title' => __('Schema / JSON-LD', 'mihdan-index-now'),
				'desc' => __('Structured data for every page — WebSite, Organization/Person, WebPage, Article, BreadcrumbList — for Google rich results.', 'mihdan-index-now'),
			],
			[
				'title' => __('Breadcrumbs', 'mihdan-index-now'),
				'desc' => __('Semantic breadcrumb trail via [crawlwp_breadcrumbs] shortcode with automatic BreadcrumbList JSON-LD schema.', 'mihdan-index-now'),
			],
			[
				'title' => __('Robots Directives & robots.txt', 'mihdan-index-now'),
				'desc' => __('Per-post and global noindex / nofollow / noarchive controls, plus a full robots.txt editor in the admin.', 'mihdan-index-now'),
			],
			[
				'title' => __('Canonical URLs & Pagination', 'mihdan-index-now'),
				'desc' => __('Self-referencing canonical tags and rel=prev/next links to prevent duplicate content on paginated archives.', 'mihdan-index-now'),
			],
			[
				'title' => __('XML Sitemap Enhancements', 'mihdan-index-now'),
				'desc' => __('Excludes noindexed content, adds a Google News sitemap, custom URLs, and multilingual support (Polylang, WPML, TranslatePress).', 'mihdan-index-now'),
			],
			[
				'title' => __('URL Redirects', 'mihdan-index-now'),
				'desc' => __('Manage 301/302/307/410/451 redirects with regex support, hit tracking and automatic permalink-change detection.', 'mihdan-index-now'),
			],
			[
				'title' => __('RSS Feed Branding', 'mihdan-index-now'),
				'desc' => __('Prepend and append custom text (with tokens) to feed items to build brand recognition and deter content scrapers.', 'mihdan-index-now'),
			],
			[
				'title' => __('Site Information & Social Profiles', 'mihdan-index-now'),
				'desc' => __('Declare your site as an Organization or Person, set a logo, and link social profiles for the knowledge graph.', 'mihdan-index-now'),
			],
			[
				'title' => __('Author Social Profiles', 'mihdan-index-now'),
				'desc' => __('Authors can set their Facebook URL, X handle and extra profile URLs, surfaced as article:author and sameAs in JSON-LD.', 'mihdan-index-now'),
			],
			[
				'title' => __('Critical SEO Notifications', 'mihdan-index-now'),
				'desc' => __('Admin bar notification centre alerts you to issues like discouraged indexing, conflicting plugins and robots.txt problems.', 'mihdan-index-now'),
			],
		];

		?>
		<div class="cwp-fg-wrap">

			<div class="cwp-fg-hero">
				<h2 class="cwp-fg-hero__title">
					<?php esc_html_e('Supercharge your SEO with CrawlWP', 'mihdan-index-now'); ?>
				</h2>
				<p class="cwp-fg-hero__intro">
					<?php esc_html_e(
						'A complete on-page SEO suite — title & meta, Open Graph, Schema/JSON-LD, breadcrumbs, sitemaps, redirects, robots.txt and more — built directly into CrawlWP.',
						'mihdan-index-now'
					); ?>
				</p>
			</div>

			<div class="cwp-fg-grid">
				<?php foreach ($features as $feature): ?>
					<div class="cwp-fg-card">
						<h3 class="cwp-fg-card__title"><?php echo esc_html($feature['title']); ?></h3>
						<p class="cwp-fg-card__desc"><?php echo esc_html($feature['desc']); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="cwp-fg-note">
				<strong><?php esc_html_e('Migrating from another SEO plugin?', 'mihdan-index-now'); ?></strong>
				<?php esc_html_e(
					'Enable the features below first — this unlocks the Title & Meta, Social Networks, Site Information and all other settings tabs. Configure everything to your liking, then safely deactivate your old SEO plugin. Your existing per-post SEO meta will be honoured automatically.',
					'mihdan-index-now'
				); ?>
			</div>

		</div>

		<style>
			.cwp-fg-wrap {
				margin-bottom: 24px;
			}

			/* Hero — matches WP admin card style */
			.cwp-fg-hero {
				background: #1d2327;
				border-radius: 4px;
				padding: 28px 32px;
				margin-bottom: 20px;
			}

			.cwp-fg-hero__title {
				margin: 0 0 8px;
				font-size: 18px;
				font-weight: 600;
				color: #fff;
				line-height: 1.3;
			}

			.cwp-fg-hero__intro {
				margin: 0;
				font-size: 13px;
				line-height: 1.65;
				color: #8a9099;
			}

			/* Feature grid */
			.cwp-fg-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
				gap: 1px;
				margin-bottom: 20px;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				overflow: hidden;
				background: #dcdcde;
			}

			.cwp-fg-card {
				background: #fff;
				padding: 16px 18px;
			}

			.cwp-fg-card__title {
				margin: 0 0 5px;
				font-size: 13px;
				font-weight: 600;
				color: #1d2327;
			}

			.cwp-fg-card__desc {
				margin: 0;
				font-size: 13px;
				color: #50575e;
				line-height: 1.55;
			}

			/* Migration note */
			.cwp-fg-note {
				border: 1px solid #dcdcde;
				border-radius: 4px;
				padding: 14px 18px;
				font-size: 13px;
				color: #50575e;
				line-height: 1.6;
			}

			.cwp-fg-note strong {
				display: block;
				margin-bottom: 4px;
				color: #1d2327;
				font-weight: 600;
			}
		</style>
		<?php
	}
}
