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
			CRAWLWP_PRO_SEO_INDEX_SLUG,
			esc_html__('SEO Index', 'mihdan-index-now')
		);

		$this->wposa->enable_blank_mode()->setup_hooks();

		$this->wposa2 = new WPOSA(
			Utils::get_plugin_name(),
			Utils::get_plugin_version(),
			Utils::get_plugin_slug(),
			'crawlwp_upsell',
			CRAWLWP_PRO_SEO_STAT_SLUG,
			esc_html__('SEO Stats', 'mihdan-index-now')
		);

		$this->wposa2->enable_blank_mode()->setup_hooks();

		$this->setup_fields();
	}


	public function setup_fields()
	{
		if (wp_doing_ajax()) return;

		$upgrade_url = 'https://crawlwp.com/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=crawlwp-seo-index-upsell-page';

		$html = '<div class="crawlwp-full-feature-upsell-page-wrap">';
		$html .= '<div class="crawlwp-upsell-top">';
		$html .= sprintf('<h2>%s</h2>', esc_html__('Improve Your Website Indexing by Search Engines', 'mihdan-index-now'));
		$html .= sprintf('<p>%s</p>', esc_html__('CrawlWP Premium regularly scan your WordPress site and submit pages and content for indexing that are not indexed by search engines.', 'mihdan-index-now'));
		$html .= '<div class="crawlwp-upsell-featured-image">';
		$html .= '<img src="'.MIHDAN_INDEX_NOW_ASSETS_URL.'img/crawlwp-content-indexing-stat-list.png">';
		$html .= '</div>';
		$html .= sprintf('<a target="_blank" href="%s">%s</a>', $upgrade_url, esc_html__('Upgrade to CrawlWP Premium', 'mihdan-index-now'));
		$html .= '</div>';
		$html .= '<div class="crawlwp-upsell-bottom">';
		$html .= '<div class="crawlwp-upsell-featured-image">';
		$html .= '<img src="https://crawlwp.com/wp-content/uploads/2024/10/crawlwp-indexing-stat.png">';
		$html .= '</div>';
		$html .= sprintf('<h3>%s</h3>', esc_html__('Search Engine Index History', 'mihdan-index-now'));
		$html .= sprintf('<p>%s</p>', esc_html__('View records of every indexing done, last submitted date, and progress over time to understand better how search engines recognize your site.', 'mihdan-index-now'));
		$html .= sprintf('<a target="_blank" href="%s">%s</a>', $upgrade_url, esc_html__('Upgrade to CrawlWP Premium', 'mihdan-index-now'));
		$html .= '</div>';
		$html .= '</div>';

		$this->wposa->add_section([
			'id' => 'seo_index_upsell',
		]);

		$this->wposa->add_field(
			'seo_index_upsell',
			[
				'id'   => 'pages',
				'type' => 'html',
				'name' => 'name',
				'desc' => $html
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
				'name' => 'name',
				'desc' => function () {

					ob_start();
					echo 'hello2';

					return ob_get_clean();
				}
			]
		);
	}
}
