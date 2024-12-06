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
		if ($wposa->get_active_header_menu() == Utils::get_plugin_prefix() . '_core_settings') {

			$wposa->add_section([
				'header_menu_id' => 'core_settings',
				'id'             => 'site_verification',
				'title'          => __('Site Verification', 'mihdan-index-now'),
				'desc'           => esc_html__('To verify your website with tools such as Google Search Console, Bing Webmaster Tools, and Yandex Webmaster Tools, you need to add a verification meta tag to your site. These options will help you seamlessly integrate the required codes.', 'mihdan-index-now')
			]);

			$wposa->add_field(
				'site_verification',
				[
					'id'       => 'google',
					'type'     => 'text',
					'name'     => __('Google Verification Code', 'mihdan-index-now'),
					'desc'     => '<code>' . sprintf(esc_html('<meta name="google-site-verification" content="%ssite-verification-code%s"/>'), '<span style="color:#a11">', '</strong>') . '</code>',
					'help_tab' => 'https://crawlwp.com/?p=763&utm_source=wp_dashboard&utm_medium=site_verification_page&utm_campaign=google',
				]
			);

			$wposa->add_field(
				'site_verification',
				[
					'id'       => 'bing',
					'type'     => 'text',
					'name'     => __('Bing Verification Code', 'mihdan-index-now'),
					'desc'     => '<code>' . sprintf(esc_html('<meta name="msvalidate.01" content="%ssite-verification-code%s" />'), '<span style="color:#a11">', '</strong>') . '</code>',
					'help_tab' => 'https://crawlwp.com/?p=763&utm_source=wp_dashboard&utm_medium=site_verification_page&utm_campaign=bing',
				]
			);
		}
	}

	public function sanitize_site_verification_data($submitted_data, $name)
	{
		if ($name == 'mihdan_index_now_site_verification' && isset($submitted_data['google'])) {
			$regex = '/<meta.+content=(?:"|\')(.+)(?:"|\').+/';

			$submitted_data['google'] = preg_replace($regex, '$1', wp_unslash($submitted_data['google']));
			$submitted_data['bing']   = preg_replace($regex, '$1', wp_unslash($submitted_data['bing']));
		}

		return $submitted_data;
	}
}
