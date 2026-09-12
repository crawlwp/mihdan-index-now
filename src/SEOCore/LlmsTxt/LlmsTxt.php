<?php

namespace Mihdan\IndexNow\SEOCore\LlmsTxt;

use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\Views\WPOSA;

/**
 * Virtual /llms.txt for AI crawlers.
 */
class LlmsTxt
{
	const SECTION = 'llms_txt';

	public function __construct()
	{
		add_action('crawlwp_setup_fields', [$this, 'settings_fields'], 36, 2);
		add_action('init', [$this, 'add_rewrite']);
		add_filter('query_vars', [$this, 'query_vars']);
		add_action('template_redirect', [$this, 'maybe_render'], 0);
	}

	public function add_rewrite(): void
	{
		add_rewrite_rule('^llms\.txt$', 'index.php?crawlwp_llms_txt=1', 'top');

		if (get_option('crawlwp_llms_txt_rewrite') !== '1') {
			flush_rewrite_rules(false);
			update_option('crawlwp_llms_txt_rewrite', '1', false);
		}
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public function query_vars(array $vars): array
	{
		$vars[] = 'crawlwp_llms_txt';

		return $vars;
	}

	public function maybe_render(): void
	{
		if ((int) get_query_var('crawlwp_llms_txt') !== 1) {
			return;
		}

		if (self::get('enabled', 'on') === 'off') {
			return;
		}

		nocache_headers();
		header('Content-Type: text/plain; charset=UTF-8');
		echo $this->content();
		exit;
	}

	public function settings_fields(WPOSA $wposa, $settingsInstance): void
	{
		if ($wposa->get_active_header_menu() !== Utils::get_plugin_prefix() . '_advanced_settings') {
			return;
		}

		$wposa->add_section([
			'header_menu_id' => 'advanced_settings',
			'id'             => self::SECTION,
			'title'          => __('llms.txt', 'mihdan-index-now'),
			'desc'           => sprintf(
				/* translators: %s: llms.txt URL */
				__('A plain-text file at %s that helps AI crawlers understand this site.', 'mihdan-index-now'),
				'<code>' . esc_html(home_url('/llms.txt')) . '</code>'
			),
		]);

		$wposa->add_field(self::SECTION, [
			'id'      => 'enabled',
			'type'    => 'switch',
			'name'    => __('Enable llms.txt', 'mihdan-index-now'),
			'default' => 'on',
		]);

		$wposa->add_field(self::SECTION, [
			'id'      => 'content',
			'type'    => 'textarea',
			'name'    => __('llms.txt content', 'mihdan-index-now'),
			'rows'    => 12,
			'default' => $this->default_content(),
			'desc'    => esc_html__('Leave empty to generate a default file from the site title, tagline and sitemap URL.', 'mihdan-index-now'),
		]);
	}

	public function content(): string
	{
		$saved = trim((string) self::get('content', ''));

		if ($saved !== '') {
			return $saved . "\n";
		}

		return $this->default_content();
	}

	public function default_content(): string
	{
		$lines = [
			'# ' . get_bloginfo('name'),
			'> ' . get_bloginfo('description'),
			'',
			__('This site publishes web pages that may be used as context by language models.', 'mihdan-index-now'),
			'',
			'## Sitemap',
			home_url('/wp-sitemap.xml'),
			'',
			'## Home',
			home_url('/'),
		];

		return implode("\n", $lines) . "\n";
	}

	public static function get(string $field, $default = '')
	{
		$options = get_option('crawlwp_' . self::SECTION, []);

		return is_array($options) ? ($options[$field] ?? $default) : $default;
	}
}
