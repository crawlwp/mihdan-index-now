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

			$verification_data = [
				'google' => [
					'meta_name' => 'google-site-verification',
					'value'     => Utils::get_setting_data('site_verification', 'google')
				],
				'bing' => [
					'meta_name' => 'msvalidate.01',
					'value'     => Utils::get_setting_data('site_verification', 'bing')
				],
				'yandex' => [
					'meta_name' => 'yandex-verification',
					'value'     => Utils::get_setting_data('site_verification', 'yandex')
				],
				'baidu' => [
					'meta_name' => 'baidu-site-verification',
					'value'     => Utils::get_setting_data('site_verification', 'baidu')
				],
				'pinterest' => [
					'meta_name' => 'p:domain_verify',
					'value'     => Utils::get_setting_data('site_verification', 'pinterest')
				],
			];

			foreach ($verification_data as $data) {
				if ( ! empty($data['value'])) {
					echo sprintf('<meta name="%s" content="%s" />%s', esc_attr($data['meta_name']), esc_attr($data['value']), PHP_EOL);
				}
			}
		}
	}
}
