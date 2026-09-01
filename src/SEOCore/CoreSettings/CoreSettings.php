<?php

namespace Mihdan\IndexNow\SEOCore\CoreSettings;

use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\Views\WPOSA;

class CoreSettings
{

	public function __construct()
	{
		add_action('crawlwp_setup_fields', [$this, 'settings_fields'], 10, 2);

		add_filter('wposa_submitted_data', [$this, 'sanitize_site_verification_data'], 10, 2);
	}

	public function settings_fields(WPOSA $wposa, $settingsInstance)
	{
		if ($wposa->get_active_header_menu() === Utils::get_plugin_prefix() . '_core_settings') {

			do_action('crawlwp_before_core_settings_fields', $wposa);

			$wposa->add_section([
				'header_menu_id' => 'core_settings',
				'id' => 'title_meta',
				'title' => __('Title & Meta', 'mihdan-index-now'),
			]);

			do_action('crawlwp_after_advanced_settings_fields', $wposa);
		}
	}
}
