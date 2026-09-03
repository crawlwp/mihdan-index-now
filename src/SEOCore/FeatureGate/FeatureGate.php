<?php
/**
 * FeatureGate — master on/off switch for all on-page SEO features.
 *
 * When a user upgrades from an older version (or already has another SEO
 * plugin active) the option will not exist, so `is_enabled()` returns false
 * and no frontend output is emitted until the admin explicitly enables it.
 * New installs that have never saved any option will also start with the
 * feature OFF so the admin can review and choose to enable it.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\SEOCore\FeatureGate;

class FeatureGate
{
	const OPTION_KEY   = 'crawlwp_seo_features';
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
		add_action('crawlwp_pre_setup_fields', [$this, 'register_menu'], 5);
	}

	/**
	 * Return true when the on-page SEO features are enabled.
	 */
	public static function is_enabled(): bool
	{
		if (self::$cache === null) {
			$options      = get_option(self::OPTION_KEY, []);
			self::$cache  = !empty($options[self::OPTION_FIELD]);
		}

		return self::$cache;
	}

	/**
	 * Enable the on-page SEO features.
	 */
	public static function enable(): void
	{
		$options                    = get_option(self::OPTION_KEY, []);
		$options[self::OPTION_FIELD] = '1';
		update_option(self::OPTION_KEY, $options);
		self::$cache = true;
	}

	/**
	 * Disable the on-page SEO features.
	 */
	public static function disable(): void
	{
		$options                    = get_option(self::OPTION_KEY, []);
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
			'id'    => 'seo_features',
			'title' => __('SEO Features', 'mihdan-index-now'),
		]);

		$wposa->add_section([
			'header_menu_id' => 'seo_features',
			'id'             => self::OPTION_KEY,
			'title'          => __('On-Page SEO Features', 'mihdan-index-now'),
			'desc'           => '',
			'callback'       => [$this, 'render_promo'],
		]);

		$wposa->add_field(
			self::OPTION_KEY,
			[
				'id'      => self::OPTION_FIELD,
				'type'    => 'checkbox',
				'name'    => __('Enable on-page SEO features', 'mihdan-index-now'),
				'label'   => __('Activate CrawlWP\'s complete on-page SEO suite for this website.', 'mihdan-index-now'),
				'desc'    => __('Check this box to enable meta tags, Open Graph, Twitter cards, Schema/JSON-LD, breadcrumbs, sitemap enhancements, and critical SEO notifications. Leave it unchecked if you prefer to keep using your current SEO plugin.', 'mihdan-index-now'),
				'default' => '0',
			]
		);
	}

	/**
	 * Render the promotional HTML block above the enable checkbox.
	 */
	public function render_promo(): void
	{
		$features = [
			[
				'icon'  => '🏷️',
				'title' => __('Title & Meta Tags', 'mihdan-index-now'),
				'desc'  => __('Set precise SEO titles and meta descriptions for every post type, taxonomy, archive, author, and special page — with a live preview and variable tokens.', 'mihdan-index-now'),
			],
			[
				'icon'  => '📣',
				'title' => __('Open Graph & Social Cards', 'mihdan-index-now'),
				'desc'  => __('Automatic og:title, og:description, og:image (with width/height), og:locale, article:section/tag and fb:app_id so your content looks perfect when shared on Facebook, LinkedIn, X/Twitter and more.', 'mihdan-index-now'),
			],
			[
				'icon'  => '🔷',
				'title' => __('Schema / JSON-LD', 'mihdan-index-now'),
				'desc'  => __('Rich structured data for every page — WebSite, Organization/Person, WebPage, Article, BlogPosting, BreadcrumbList and more — keeping your site eligible for Google rich results.', 'mihdan-index-now'),
			],
			[
				'icon'  => '🗺️',
				'title' => __('Breadcrumbs', 'mihdan-index-now'),
				'desc'  => __('Semantic breadcrumb HTML via the [crawlwp_breadcrumbs] shortcode and automatic BreadcrumbList JSON-LD on every page type including archives, taxonomies and 404s.', 'mihdan-index-now'),
			],
			[
				'icon'  => '🤖',
				'title' => __('Robots Directives', 'mihdan-index-now'),
				'desc'  => __('Fine-grained noindex, nofollow and noarchive controls per post, globally per post type, and via a full robots.txt editor — keeping thin content out of the index.', 'mihdan-index-now'),
			],
			[
				'icon'  => '📐',
				'title' => __('Canonical URLs & Pagination', 'mihdan-index-now'),
				'desc'  => __('Automatic self-referencing canonical tags and rel=prev/next pagination links to prevent duplicate content issues across paginated archives.', 'mihdan-index-now'),
			],
			[
				'icon'  => '🗂️',
				'title' => __('XML Sitemap Enhancements', 'mihdan-index-now'),
				'desc'  => __('Noindexed content is automatically excluded from the WordPress sitemap. Add a Google News Sitemap and custom URLs — all without a separate plugin.', 'mihdan-index-now'),
			],
			[
				'icon'  => '🔔',
				'title' => __('Critical SEO Notifications', 'mihdan-index-now'),
				'desc'  => __('A compact bell-icon notification centre in the admin bar alerts you to site-wide issues like "Search engines discouraged", conflicting plugins, missing robots.txt problems and more.', 'mihdan-index-now'),
			],
		];

		$enabled = self::is_enabled();
		$badge   = $enabled
			? '<span class="cwp-fg-badge cwp-fg-badge--on">' . esc_html__('Active', 'mihdan-index-now') . '</span>'
			: '<span class="cwp-fg-badge cwp-fg-badge--off">' . esc_html__('Inactive', 'mihdan-index-now') . '</span>';

		?>
		<div class="cwp-fg-wrap">

			<div class="cwp-fg-hero">
				<div class="cwp-fg-hero__text">
					<h2 class="cwp-fg-hero__title">
						<?php esc_html_e('Supercharge your SEO with CrawlWP', 'mihdan-index-now'); ?>
						<?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</h2>
					<p class="cwp-fg-hero__intro">
						<?php esc_html_e(
							'CrawlWP includes a full on-page SEO suite — title & meta templates, Open Graph, Schema/JSON-LD, breadcrumbs, robots directives, sitemap enhancements, and a notification centre for critical issues. If you are migrating from another SEO plugin, simply review your settings and flip the switch below.',
							'mihdan-index-now'
						); ?>
					</p>
					<?php if (!$enabled): ?>
					<p class="cwp-fg-hero__cta-note">
						<?php esc_html_e(
							'⚠️ On-page SEO features are currently INACTIVE. Enable them below to start outputting optimised meta tags, structured data and more on your website.',
							'mihdan-index-now'
						); ?>
					</p>
					<?php endif; ?>
				</div>
			</div>

			<div class="cwp-fg-grid">
				<?php foreach ($features as $feature): ?>
				<div class="cwp-fg-card">
					<div class="cwp-fg-card__icon" aria-hidden="true"><?php echo esc_html($feature['icon']); ?></div>
					<div class="cwp-fg-card__body">
						<h3 class="cwp-fg-card__title"><?php echo esc_html($feature['title']); ?></h3>
						<p class="cwp-fg-card__desc"><?php echo esc_html($feature['desc']); ?></p>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

			<div class="cwp-fg-note">
				<strong><?php esc_html_e('Switching from another SEO plugin?', 'mihdan-index-now'); ?></strong>
				<?php esc_html_e(
					'Deactivate your current SEO plugin first, configure the Title & Meta and Social settings in CrawlWP, then enable the features below. Your existing per-post meta will be honoured automatically.',
					'mihdan-index-now'
				); ?>
			</div>

		</div>

		<style>
		.cwp-fg-wrap { max-width: 900px; margin-bottom: 24px; }

		/* Hero */
		.cwp-fg-hero {
			background: linear-gradient(135deg, #1d2327 0%, #2c3338 100%);
			border-radius: 8px;
			padding: 32px 36px;
			margin-bottom: 24px;
			color: #fff;
		}
		.cwp-fg-hero__title {
			margin: 0 0 12px;
			font-size: 22px;
			font-weight: 600;
			color: #fff;
			display: flex;
			align-items: center;
			gap: 12px;
			flex-wrap: wrap;
		}
		.cwp-fg-hero__intro {
			margin: 0 0 12px;
			font-size: 14px;
			line-height: 1.7;
			color: #e8eaed;
			max-width: 680px;
		}
		.cwp-fg-hero__cta-note {
			margin: 0;
			font-size: 13px;
			color: #ffd700;
			font-weight: 500;
		}

		/* Status badge */
		.cwp-fg-badge {
			display: inline-block;
			padding: 3px 10px;
			border-radius: 20px;
			font-size: 12px;
			font-weight: 600;
			letter-spacing: .03em;
			text-transform: uppercase;
			vertical-align: middle;
		}
		.cwp-fg-badge--on  { background: #00a32a; color: #fff; }
		.cwp-fg-badge--off { background: #d63638; color: #fff; }

		/* Feature grid */
		.cwp-fg-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
			gap: 16px;
			margin-bottom: 24px;
		}
		.cwp-fg-card {
			background: #fff;
			border: 1px solid #e0e0e0;
			border-radius: 6px;
			padding: 18px 20px;
			display: flex;
			gap: 14px;
			align-items: flex-start;
			transition: box-shadow .15s ease;
		}
		.cwp-fg-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
		.cwp-fg-card__icon {
			font-size: 24px;
			line-height: 1;
			flex-shrink: 0;
			margin-top: 2px;
		}
		.cwp-fg-card__title {
			margin: 0 0 6px;
			font-size: 14px;
			font-weight: 600;
			color: #1d2327;
		}
		.cwp-fg-card__desc {
			margin: 0;
			font-size: 13px;
			color: #50575e;
			line-height: 1.6;
		}

		/* Migration note */
		.cwp-fg-note {
			background: #f0f6fc;
			border-left: 4px solid #2271b1;
			padding: 14px 18px;
			border-radius: 0 4px 4px 0;
			font-size: 13px;
			color: #1d2327;
			line-height: 1.6;
		}
		.cwp-fg-note strong { color: #2271b1; }
		</style>
		<?php
	}
}
