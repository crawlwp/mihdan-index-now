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

			$google_verification_code    = Utils::get_setting_data('site_verification', 'google');
			$bing_verification_code      = Utils::get_setting_data('site_verification', 'bing');
			$yandex_verification_code    = Utils::get_setting_data('site_verification', 'yandex');
			$baidu_verification_code     = Utils::get_setting_data('site_verification', 'baidu');
			$pinterest_verification_code = Utils::get_setting_data('site_verification', 'pinterest');

			if ( ! empty($google_verification_code)) {
				echo sprintf('<meta name="google-site-verification" content="%s" />', esc_attr($google_verification_code));
			}

			if ( ! empty($bing_verification_code)) {
				echo sprintf('<meta name="msvalidate.01" content="%s" />', esc_attr($bing_verification_code));
			}

			if ( ! empty($yandex_verification_code)) {
				echo sprintf('<meta name="yandex-verification" content="%s" />', esc_attr($yandex_verification_code));
			}

			if ( ! empty($baidu_verification_code)) {
				echo sprintf('<meta name="baidu-site-verification" content="%s" />', esc_attr($baidu_verification_code));
			}

			if ( ! empty($pinterest_verification_code)) {
				echo sprintf('<meta name="p:domain_verify" content="%s" />', esc_attr($pinterest_verification_code));
			}
		}
	}
}
