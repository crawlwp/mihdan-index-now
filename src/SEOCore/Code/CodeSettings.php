<?php

namespace Mihdan\IndexNow\SEOCore\Code;

use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\Views\WPOSA;

/**
 * Header / footer scripts and basic tracking IDs (GA4, GTM).
 */
class CodeSettings
{
	const SECTION = 'code';

	public function __construct()
	{
		add_action('crawlwp_setup_fields', [$this, 'settings_fields'], 45, 2);
		add_action('wp_head', [$this, 'output_head'], 99);
		add_action('wp_body_open', [$this, 'output_body'], 1);
		add_action('wp_footer', [$this, 'output_footer'], 99);
	}

	public function settings_fields(WPOSA $wposa, $settingsInstance): void
	{
		if ($wposa->get_active_header_menu() !== Utils::get_plugin_prefix() . '_advanced_settings') {
			return;
		}

		$wposa->add_section([
			'header_menu_id' => 'advanced_settings',
			'id'             => self::SECTION,
			'title'          => __('Header &amp; Footer Code', 'mihdan-index-now'),
			'desc'           => __('Insert tracking scripts (GA4, GTM) or arbitrary HTML in the site head, after &lt;body&gt;, or in the footer.', 'mihdan-index-now'),
		]);

		$wposa->add_field(self::SECTION, [
			'id'   => 'ga4_id',
			'type' => 'text',
			'name' => __('GA4 measurement ID', 'mihdan-index-now'),
			'desc' => esc_html__('e.g. G-XXXXXXXXXX. Leave empty to skip the Google tag.', 'mihdan-index-now'),
		]);

		$wposa->add_field(self::SECTION, [
			'id'   => 'gtm_id',
			'type' => 'text',
			'name' => __('Google Tag Manager ID', 'mihdan-index-now'),
			'desc' => esc_html__('e.g. GTM-XXXXXXX.', 'mihdan-index-now'),
		]);

		$wposa->add_field(self::SECTION, [
			'id'   => 'head',
			'type' => 'textarea',
			'name' => __('Scripts in &lt;head&gt;', 'mihdan-index-now'),
			'rows' => 6,
		]);

		$wposa->add_field(self::SECTION, [
			'id'   => 'body',
			'type' => 'textarea',
			'name' => __('Scripts after &lt;body&gt;', 'mihdan-index-now'),
			'rows' => 4,
		]);

		$wposa->add_field(self::SECTION, [
			'id'   => 'footer',
			'type' => 'textarea',
			'name' => __('Scripts in footer', 'mihdan-index-now'),
			'rows' => 6,
		]);
	}

	public function output_head(): void
	{
		$gtm = trim((string) self::get('gtm_id', ''));
		$ga4 = trim((string) self::get('ga4_id', ''));

		if ($gtm !== '' && preg_match('/^GTM-[A-Z0-9]+$/i', $gtm)) {
			echo "<!-- CrawlWP GTM -->\n";
			echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . esc_js($gtm) . "');</script>\n";
		}

		if ($ga4 !== '' && preg_match('/^G-[A-Z0-9]+$/i', $ga4)) {
			echo "<!-- CrawlWP GA4 -->\n";
			echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr($ga4) . '"></script>' . "\n";
			echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . esc_js($ga4) . "');</script>\n";
		}

		echo (string) self::get('head', '');
	}

	public function output_body(): void
	{
		$gtm = trim((string) self::get('gtm_id', ''));

		if ($gtm !== '' && preg_match('/^GTM-[A-Z0-9]+$/i', $gtm)) {
			echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr($gtm) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
		}

		echo (string) self::get('body', '');
	}

	public function output_footer(): void
	{
		echo (string) self::get('footer', '');
	}

	public static function get(string $field, $default = '')
	{
		$options = get_option('crawlwp_' . self::SECTION, []);

		return is_array($options) ? ($options[$field] ?? $default) : $default;
	}
}
