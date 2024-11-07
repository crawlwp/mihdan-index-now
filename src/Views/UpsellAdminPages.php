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

		$content = <<<CONTENT
<div class="crawlwp-full-feature-upsell-page-wrap">
<div class="crawlwp-upsell-top">
<h2>See All Your Important Store Metrics in One Place</h2>
<h4>Get an Answer to All Your Top Ecommerce Questions From a Single Report</h4>
<div class="crawlwp-upsell-featured-image">
<img src="https://upsell-design-inspo.instawp.xyz/wp-content/plugins/google-analytics-for-wordpress/lite/assets/vue/img/monsterinsights-report-ecommerce.png">
</div>
</div>
<div class="crawlwp-upsell-bottom">
<a target="_blank" href="#" class="btn-higher-up">Upgrade to MonsterInsights Pro</a>
<div class="crawlwp-upsell-featured-image">
<img src="https://upsell-design-inspo.instawp.xyz/wp-content/plugins/google-analytics-for-wordpress/lite/assets/vue/img/monsterinsights-report-ecommerce.png">
</div>
<h3>Enable Ecommerce Tracking and Grow Your Business with Confidence</h3>
<p>MonsterInsights Ecommerce Addon makes it easy to setup enhanced eCommerce tracking, so you can see all your important eCommerce metrics like total revenue, conversion rate, average order value, top products, top referral sources, and more in a single report right inside your WordPress dashboard.</p>
<a target="_blank" href="#">Upgrade to MonsterInsights Pro</a>
</div>
</div>
CONTENT;

		$this->wposa->add_section([
			'id' => 'seo_index_upsell',
		]);

		$this->wposa->add_field(
			'seo_index_upsell',
			[
				'id'   => 'pages',
				'type' => 'html',
				'name' => 'name',
				'desc' => $content
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
