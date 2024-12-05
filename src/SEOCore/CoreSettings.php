<?php

namespace Mihdan\IndexNow\SEOCore;

use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\Views\WPOSA;

class CoreSettings
{
	public function __construct()
	{
		add_action('crawlwp_pre_setup_fields', [$this, 'core_settings_menu']);
		add_action('crawlwp_setup_fields', [$this, 'core_settings_fields'], 10, 2);
	}

	public function core_settings_menu($wposa)
	{
		$wposa->add_header_menu([
			'id'    => 'core_settings',
			'title' => __('Core', 'mihdan-index-now'),
		]);
	}

	public function core_settings_fields(WPOSA $wposa, $settingsInstance)
	{
		if ($wposa->get_active_header_menu() == Utils::get_plugin_prefix() . '_core_settings') {

			$wposa->add_section([
				'header_menu_id' => 'core_settings',
				'id'             => 'webmaster_tools',
				'title'          => __('Webmaster Tools', 'mihdan-index-now'),
			]);

			$wposa->add_field(
				'webmaster_tools',
				[
					'id'      => 'google',
					'type'    => 'text',
					'name'    => __('Google Search Console Verification Code', 'mihdan-index-now'),
					'desc'    => esc_html__('Select the custom post types that can be submitted to Search Engines for indexing.', 'mihdan-index-now')
				]
			);
		}
	}
}
