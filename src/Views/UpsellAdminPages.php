<?php
/**
 * Settings class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Views;

use Mihdan\IndexNow\Utils;

class UpsellAdminPages
{
	/**
	 * WP_OSA instance.
	 *
	 * @var WPOSA $wposa
	 */
	public $wposa;
	/**
	 * WP_OSA instance.
	 *
	 * @var WPOSA $wposa2
	 */
	public $wposa2;

	public function __construct()
	{
		$this->wposa = new WPOSA(
			Utils::get_plugin_name(),
			Utils::get_plugin_version(),
			Utils::get_plugin_slug(),
			'crawlwp_upsell',
			CRAWLWP_PRO_SEO_INDEX_SLUG
		);

		$this->wposa->enable_blank_mode()->setup_hooks();

		$this->wposa2 = new WPOSA(
			Utils::get_plugin_name(),
			Utils::get_plugin_version(),
			Utils::get_plugin_slug(),
			'crawlwp_upsell',
			CRAWLWP_PRO_SEO_STAT_SLUG
		);

		$this->wposa2->enable_blank_mode()->setup_hooks();

		add_action('init', [$this, 'setup_fields'], 110);
	}


	public function setup_fields()
	{
		if (wp_doing_ajax()) return;

		$upgrade_url = 'https://crawlwp.com/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=crawlwp-seo-index-upsell-page';

		$seo_index_upsell = '<div class="crawlwp-full-feature-upsell-page-wrap">';
		$seo_index_upsell .= '<div class="crawlwp-upsell-top">';
		$seo_index_upsell .= sprintf('<h2>%s</h2>', esc_html__('Improve Your Website Indexing by Search Engines', 'mihdan-index-now'));
		$seo_index_upsell .= sprintf('<p>%s</p>', esc_html__('CrawlWP Premium regularly scan your WordPress site and submit pages and content for indexing that are not indexed by search engines.', 'mihdan-index-now'));
		$seo_index_upsell .= '<div class="crawlwp-upsell-featured-image">';
		$seo_index_upsell .= '<img src="' . Utils::get_plugin_asset_url('images/crawlwp-content-indexing-stat-list.png') . '">';
		$seo_index_upsell .= '</div>';
		$seo_index_upsell .= sprintf('<a target="_blank" href="%s">%s</a>', $upgrade_url, esc_html__('Upgrade to CrawlWP Premium', 'mihdan-index-now'));
		$seo_index_upsell .= '</div>';
		$seo_index_upsell .= '<div class="crawlwp-upsell-bottom">';
		$seo_index_upsell .= '<div class="crawlwp-upsell-featured-image">';
		$seo_index_upsell .= '<img src="https://crawlwp.com/wp-content/uploads/2024/10/crawlwp-indexing-stat.png">';
		$seo_index_upsell .= '</div>';
		$seo_index_upsell .= sprintf('<h3>%s</h3>', esc_html__('Search Engine Index History', 'mihdan-index-now'));
		$seo_index_upsell .= sprintf('<p>%s</p>', esc_html__('View records of every indexing done, last submitted date, and progress over time to understand better how search engines recognize your site.', 'mihdan-index-now'));
		$seo_index_upsell .= sprintf('<a target="_blank" href="%s">%s</a>', $upgrade_url, esc_html__('Upgrade to CrawlWP Premium', 'mihdan-index-now'));
		$seo_index_upsell .= '</div>';
		$seo_index_upsell .= '</div>';

		$seo_stat_upsell = '<div class="crawlwp-full-feature-upsell-page-wrap">';
		$seo_stat_upsell .= '<div class="crawlwp-upsell-top">';
		$seo_stat_upsell .= sprintf('<h2>%s</h2>', esc_html__('Search Performance & Insights at Your Fingertips', 'mihdan-index-now'));
		$seo_stat_upsell .= sprintf('<p>%s</p>', esc_html__('Get powerful search ranking insights without leaving WordPress. Track rankings and spot growth opportunities buried in Google Search Console.', 'mihdan-index-now'));
		$seo_stat_upsell .= '<div class="crawlwp-upsell-featured-image">';
		$seo_stat_upsell .= '<img src="' . Utils::get_plugin_asset_url('images/crawlwp-google-search-console-top-section-stat.png') . '">';
		$seo_stat_upsell .= '</div>';
		$seo_stat_upsell .= sprintf('<a target="_blank" href="%s">%s</a>', $upgrade_url, esc_html__('Upgrade to CrawlWP Premium', 'mihdan-index-now'));
		$seo_stat_upsell .= '</div>';
		$seo_stat_upsell .= '<div class="crawlwp-upsell-bottom">';
		$seo_stat_upsell .= '<div class="crawlwp-upsell-featured-image">';
		$seo_stat_upsell .= '<img src="' . Utils::get_plugin_asset_url('images/crawlwp-google-search-console-main-stat.png') . '">';
		$seo_stat_upsell .= '</div>';
		$seo_stat_upsell .= sprintf('<h3>%s</h3>', esc_html__('Complete SEO Clarity in One Dashboard', 'mihdan-index-now'));
		$seo_stat_upsell .= sprintf('<p>%s</p>', esc_html__('Track your website search performance with precise keyword rankings, click-through rates, and position data directly from Google. Uncover actionable insights about your top-performing pages, user demographics, and device preferences to optimize your content strategy and boost organic traffic.', 'mihdan-index-now'));
		$seo_stat_upsell .= sprintf('<a target="_blank" href="%s">%s</a>', $upgrade_url, esc_html__('Upgrade to CrawlWP Premium', 'mihdan-index-now'));
		$seo_stat_upsell .= '</div>';
		$seo_stat_upsell .= '</div>';

		$this->wposa->sub_page_title  = esc_html__('SEO Indexing', 'mihdan-index-now');
		$this->wposa2->sub_page_title = esc_html__('SEO Stats', 'mihdan-index-now');

		$this->wposa->add_section([
			'id' => 'seo_index_upsell',
		]);

		$this->wposa->add_field(
			'seo_index_upsell',
			[
				'id'   => 'pages',
				'type' => 'html',
				'desc' => $seo_index_upsell
			]
		);

		$this->wposa2->add_section([
			'id' => 'seo_stat_upsell',
		]);

		$this->wposa2->add_field(
			'seo_stat_upsell',
			[
				'id'   => 'pages2',
				'type' => 'html',
				'desc' => $seo_stat_upsell
			]
		);
	}
}
