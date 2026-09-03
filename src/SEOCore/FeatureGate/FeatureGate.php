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
		add_action('crawlwp_pre_setup_fields', [$this, 'register_menu'], -1);
	}

	/**
	 * Return true when the on-page SEO features are enabled.
	 *
	 * For brand-new installs the option row does not exist yet (`get_option`
	 * returns `false`). In that case we auto-enable and persist the setting so
	 * the admin does not have to take any extra action.
	 */
	public static function is_enabled(): bool
	{
		if (self::$cache === null) {
			$raw = get_option(self::OPTION_KEY);

			// First-ever run: the option has never been saved → auto-enable.
			if ($raw === false) {
				self::enable();
				return self::$cache; // set to true by enable()
			}

			$options = is_array($raw) ? $raw : [];
			self::$cache = isset($options[self::OPTION_FIELD]) && $options[self::OPTION_FIELD] === '1';
		}

		return self::$cache;
	}

	/**
	 * Enable the on-page SEO features.
	 */
	public static function enable(): void
	{
		$options = get_option(self::OPTION_KEY, []);
		$options[self::OPTION_FIELD] = '1';
		update_option(self::OPTION_KEY, $options);
		self::$cache = true;
	}

	/**
	 * Disable the on-page SEO features.
	 */
	public static function disable(): void
	{
		$options = get_option(self::OPTION_KEY, []);
		$options[self::OPTION_FIELD] = '0';
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
				'title' => __('SEO Features', 'mihdan-index-now'),
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
 				'default' => '1',
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
				'desc' => __('Set precise SEO titles and meta descriptions for every post type, taxonomy, archive, author, and special page — with a live preview and variable tokens.', 'mihdan-index-now'),
			],
			[
				'title' => __('Open Graph & Social Cards', 'mihdan-index-now'),
				'desc' => __('Automatic og:title, og:description, og:image (with width/height), og:locale, article:section/tag and fb:app_id so your content looks perfect when shared on Facebook, LinkedIn, X/Twitter and more.', 'mihdan-index-now'),
			],
			[
				'title' => __('Schema / JSON-LD', 'mihdan-index-now'),
				'desc' => __('Rich structured data for every page — WebSite, Organization/Person, WebPage, Article, BlogPosting, BreadcrumbList and more — keeping your site eligible for Google rich results.', 'mihdan-index-now'),
			],
			[
				'title' => __('Breadcrumbs', 'mihdan-index-now'),
				'desc' => __('Semantic breadcrumb HTML via the [crawlwp_breadcrumbs] shortcode and automatic BreadcrumbList JSON-LD on every page type including archives, taxonomies and 404s.', 'mihdan-index-now'),
			],
			[
				'title' => __('Robots Directives & robots.txt', 'mihdan-index-now'),
				'desc' => __('Fine-grained noindex, nofollow and noarchive controls per post and globally per post type. Edit and fully control your robots.txt file directly from the WordPress admin.', 'mihdan-index-now'),
			],
			[
				'title' => __('Canonical URLs & Pagination', 'mihdan-index-now'),
				'desc' => __('Automatic self-referencing canonical tags and rel=prev/next pagination links to prevent duplicate content issues across paginated archives.', 'mihdan-index-now'),
			],
			[
				'title' => __('XML Sitemap Enhancements', 'mihdan-index-now'),
				'desc' => __('Noindexed content is excluded from the sitemap automatically. Add a Google News Sitemap, include custom non-WordPress URLs, and get multilingual sitemap support for Polylang, WPML and TranslatePress.', 'mihdan-index-now'),
			],
			[
				'title' => __('URL Redirects', 'mihdan-index-now'),
				'desc' => __('Manage 301, 302, 307, 410 and 451 redirects from a single admin table, with regex support, hit tracking, automatic permalink-change detection, and bulk actions.', 'mihdan-index-now'),
			],
			[
				'title' => __('RSS Feed Branding', 'mihdan-index-now'),
				'desc' => __('Prepend and append custom content to every post in your RSS feed — with variable tokens for post link, blog link and author link — to build brand recognition and discourage content scrapers.', 'mihdan-index-now'),
			],
			[
				'title' => __('Site Information & Social Profiles', 'mihdan-index-now'),
				'desc' => __('Declare your site as an Organization or Person, set your logo, and link your Facebook, X/Twitter and other social profiles so search engines build an accurate knowledge graph about your brand.', 'mihdan-index-now'),
			],
			[
				'title' => __('Author Social Profiles', 'mihdan-index-now'),
				'desc' => __('Authors can add their Facebook URL, X/Twitter handle and additional profile URLs on their profile page. These appear as article:author and sameAs properties in the JSON-LD output.', 'mihdan-index-now'),
			],
			[
				'title' => __('Critical SEO Notifications', 'mihdan-index-now'),
				'desc' => __('A compact bell-icon notification centre in the admin bar alerts you to site-wide issues like "Search engines discouraged", conflicting plugins, missing robots.txt problems and more.', 'mihdan-index-now'),
			],
		];

		$enabled = self::is_enabled();

		?>
		<div class="cwp-fg-wrap">

 		<div class="cwp-fg-hero">
 			<div class="cwp-fg-hero__text">
 				<h2 class="cwp-fg-hero__title">
 					<?php esc_html_e('Supercharge your SEO with CrawlWP', 'mihdan-index-now'); ?>
 				</h2>
 				<p class="cwp-fg-hero__intro">
 					<?php esc_html_e(
 						'A complete on-page SEO suite built into CrawlWP — title & meta templates, Open Graph, Schema/JSON-LD, breadcrumbs, canonical URLs, robots directives, a robots.txt editor, URL redirects, RSS feed branding, Google News sitemap, site information, and author social profiles.',
 						'mihdan-index-now'
 					); ?>
 				</p>
 			</div>
 			<div class="cwp-fg-hero__status">
 				<div class="cwp-fg-status cwp-fg-status--<?php echo $enabled ? 'on' : 'off'; ?>">
 					<div class="cwp-fg-status__dot"></div>
 					<div class="cwp-fg-status__body">
 						<strong class="cwp-fg-status__label">
 							<?php echo $enabled ? esc_html__('Features Active', 'mihdan-index-now') : esc_html__('Features Inactive', 'mihdan-index-now'); ?>
 						</strong>
 						<span class="cwp-fg-status__hint">
 							<?php echo $enabled
 								? esc_html__('Meta tags and structured data are being output.', 'mihdan-index-now')
 								: esc_html__('Enable below to start outputting SEO meta tags.', 'mihdan-index-now'); ?>
 						</span>
 					</div>
 				</div>
 			</div>
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

			/* Hero */
			.cwp-fg-hero {
				background: #1d2327;
				border-radius: 6px;
				padding: 32px 36px;
				margin-bottom: 24px;
				color: #fff;
				display: flex;
				align-items: center;
				gap: 40px;
				justify-content: space-between;
			}

			.cwp-fg-hero__text {
				flex: 1;
				min-width: 0;
			}

			.cwp-fg-hero__title {
				margin: 0 0 10px;
				font-size: 20px;
				font-weight: 600;
				color: #fff;
				line-height: 1.3;
			}

			.cwp-fg-hero__intro {
				margin: 0;
				font-size: 13px;
				line-height: 1.7;
				color: #8a9099;
			}

			.cwp-fg-hero__status {
				flex-shrink: 0;
			}

			/* Status indicator */
			.cwp-fg-status {
				display: flex;
				align-items: center;
				gap: 10px;
				border: 1px solid rgba(255, 255, 255, .1);
				border-radius: 4px;
				padding: 12px 16px;
			}

			.cwp-fg-status__dot {
				width: 8px;
				height: 8px;
				border-radius: 50%;
				flex-shrink: 0;
				background: #8a9099;
			}

			.cwp-fg-status--on .cwp-fg-status__dot {
				background: #fff;
			}

			.cwp-fg-status__body {
				display: flex;
				flex-direction: column;
				gap: 2px;
			}

			.cwp-fg-status__label {
				font-size: 12px;
				font-weight: 600;
				color: #fff;
				letter-spacing: .03em;
				text-transform: uppercase;
			}

			.cwp-fg-status__hint {
				font-size: 11px;
				color: #646970;
				line-height: 1.4;
			}

			/* Feature grid */
			.cwp-fg-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
				gap: 1px;
				margin-bottom: 24px;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				overflow: hidden;
			}

			.cwp-fg-card {
				background: #fff;
				padding: 18px 20px;
			}

			.cwp-fg-card__title {
				margin: 0 0 4px;
				font-size: 13px;
				font-weight: 600;
				color: #1d2327;
			}

			.cwp-fg-card__desc {
				margin: 0;
				font-size: 12px;
				color: #646970;
				line-height: 1.6;
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
