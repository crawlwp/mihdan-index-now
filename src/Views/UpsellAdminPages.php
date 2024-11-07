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

		$this->wposa->add_section([
			'id' => 'seo_index_upsell',
		]);

		$this->wposa->add_field(
			'seo_index_upsell',
			[
				'id'   => 'pages',
				'type' => 'html',
				'name' => 'name',
				'desc' => function () {

					ob_start();
					echo 'hello';

					return ob_get_clean();
				}
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
