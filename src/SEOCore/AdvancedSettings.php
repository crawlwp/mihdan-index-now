<?php

namespace Mihdan\IndexNow\SEOCore;

class AdvancedSettings
{
	public function __construct()
	{
		add_action('crawlwp_setup_fields_before_log', [$this, 'advanced_settings_menu'], 1);
	}

	public function advanced_settings_menu($wposa)
	{
		$wposa->add_header_menu([
			'id'    => 'advanced_settings',
			'title' => __('Advanced', 'mihdan-index-now'),
		]);
	}
}
