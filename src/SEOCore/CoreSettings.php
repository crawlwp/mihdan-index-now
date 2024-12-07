<?php

namespace Mihdan\IndexNow\SEOCore;

class CoreSettings
{
	public function __construct()
	{
		add_action('crawlwp_setup_fields_before_log', [$this, 'core_settings_menu'], 1);
	}

	public function core_settings_menu($wposa)
	{
		$wposa->add_header_menu([
			'id'    => 'core_settings',
			'title' => __('Core', 'mihdan-index-now'),
		]);
	}
}
