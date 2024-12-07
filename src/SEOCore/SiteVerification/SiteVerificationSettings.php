<?php

namespace Mihdan\IndexNow\SEOCore\SiteVerification;

use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\Views\WPOSA;

class SiteVerificationSettings
{
	public function __construct()
	{
		add_action('crawlwp_setup_fields', [$this, 'core_settings_fields'], 10, 2);

		add_filter('wposa_submitted_data', [$this, 'sanitize_site_verification_data'], 10, 2);
	}

	public function core_settings_fields(WPOSA $wposa, $settingsInstance)
	{
		if ($wposa->get_active_header_menu() === Utils::get_plugin_prefix() . '_core_settings') {

			$wposa->add_section([
				'header_menu_id' => 'core_settings',
				'id'             => 'site_verification',
				'title'          => __('Site Verification', 'mihdan-index-now'),
				'desc'           => esc_html__('To verify your website with tools such as Google Search Console, Bing Webmaster Tools, and Yandex Webmaster Tools, you need to add a verification meta tag to your site. These options will help you seamlessly integrate the required codes.', 'mihdan-index-now'),
			]);

			$providers = [
				'google' => [
					'name'     => __('Google Verification Code', 'mihdan-index-now'),
					'meta_tag' => '<meta name="google-site-verification" content="%ssite-verification-code%s"/>',
					'help_tab' => 'https://crawlwp.com/?p=763&utm_source=wp_dashboard&utm_medium=site_verification_page&utm_campaign=google',
				],
				'bing'   => [
					'name'     => __('Bing Verification Code', 'mihdan-index-now'),
					'meta_tag' => '<meta name="msvalidate.01" content="%ssite-verification-code%s" />',
					'help_tab' => 'https://crawlwp.com/?p=768&utm_source=wp_dashboard&utm_medium=site_verification_page&utm_campaign=bing',
				],
				'yandex' => [
					'name'     => __('Yandex Verification Code', 'mihdan-index-now'),
					'meta_tag' => '<meta name="yandex-verification" content="%ssite-verification-code%s" />',
					'help_tab' => 'https://crawlwp.com/?p=770&utm_source=wp_dashboard&utm_medium=site_verification_page&utm_campaign=yandex',
				],
				'baidu' => [
					'name'     => __('Baidu Verification Code', 'mihdan-index-now'),
					'meta_tag' => '<meta name="baidu-site-verification" content="%ssite-verification-code%s" />',
					'help_tab' => 'https://crawlwp.com/?p=772&utm_source=wp_dashboard&utm_medium=site_verification_page&utm_campaign=baidu',
				],
			];

			foreach ($providers as $id => $provider) {
				$wposa->add_field(
					'site_verification',
					[
						'id'       => $id,
						'type'     => 'text',
						'name'     => $provider['name'],
						'desc'     => '<code>' . sprintf(esc_html($provider['meta_tag']), '<span style="color:#a11">', '</strong>') . '</code>',
						'help_tab' => $provider['help_tab'],
					]
				);
			}
		}
	}


	public function sanitize_site_verification_data($submitted_data, $name)
	{
		if ($name === 'mihdan_index_now_site_verification') {
			$regex     = '/<meta.+content=(?:"|\')(.+)(?:"|\').+/';
			$providers = ['google', 'bing', 'yandex'];

			foreach ($providers as $provider) {
				if (isset($submitted_data[$provider])) {
					$submitted_data[$provider] = preg_replace($regex, '$1', wp_unslash($submitted_data[$provider]));
				}
			}
		}

		return $submitted_data;
	}
}
