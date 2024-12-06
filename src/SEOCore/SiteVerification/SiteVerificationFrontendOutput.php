<?php

namespace Mihdan\IndexNow\SEOCore\SiteVerification;

use Mihdan\IndexNow\Utils;

class SiteVerificationFrontendOutput
{
	public function __construct()
	{
		add_action('wp_head', [$this, 'output']);
	}

	public function output()
	{
		if (is_home() || is_front_page()) {

			$google_verification_code = Utils::get_setting_data('site_verification', 'google');

			if ( ! empty($google_verification_code)) {
				echo sprintf('<meta name="google-site-verification" content="%s" />', esc_attr($google_verification_code));
			}
		}
	}
}
