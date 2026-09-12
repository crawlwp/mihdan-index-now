<?php

namespace Mihdan\IndexNow\SEOCore\InternalLinks;

use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\Views\WPOSA;

/**
 * Site-wide keyword → URL auto-linking.
 */
class AutoLinker
{
	const SECTION = 'internal_links';

	public function __construct()
	{
		add_action('crawlwp_setup_fields', [$this, 'settings_fields'], 42, 2);
		add_filter('the_content', [$this, 'link_content'], 99);
	}

	public function settings_fields(WPOSA $wposa, $settingsInstance): void
	{
		if ($wposa->get_active_header_menu() !== Utils::get_plugin_prefix() . '_advanced_settings') {
			return;
		}

		$wposa->add_section([
			'header_menu_id' => 'advanced_settings',
			'id'             => self::SECTION,
			'title'          => __('Internal Linking', 'mihdan-index-now'),
			'desc'           => __('Automatically turn keywords in post content into internal links. One rule per line: keyword|https://example.com/page', 'mihdan-index-now'),
		]);

		$wposa->add_field(self::SECTION, [
			'id'      => 'enabled',
			'type'    => 'switch',
			'name'    => __('Enable auto-linking', 'mihdan-index-now'),
			'default' => 'off',
		]);

		$wposa->add_field(self::SECTION, [
			'id'    => 'rules',
			'type'  => 'textarea',
			'name'  => __('Keyword rules', 'mihdan-index-now'),
			'rows'  => 8,
			'desc'  => esc_html__('keyword|URL — first match wins, existing links are skipped, max 3 replacements per keyword.', 'mihdan-index-now'),
		]);
	}

	public function link_content(string $content): string
	{
		if (self::get('enabled', 'off') !== 'on' || is_admin() || ! is_singular()) {
			return $content;
		}

		$rules = self::parse_rules((string) self::get('rules', ''));

		if ($rules === []) {
			return $content;
		}

		return self::apply_rules($content, $rules, 3);
	}

	/**
	 * @return array<string,string>
	 */
	public static function parse_rules(string $raw): array
	{
		$rules = [];

		foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
			$line = trim($line);

			if ($line === '' || strpos($line, '|') === false) {
				continue;
			}

			[$keyword, $url] = array_map('trim', explode('|', $line, 2));

			if ($keyword === '' || $url === '') {
				continue;
			}

			$rules[$keyword] = $url;
		}

		uksort($rules, static function ($a, $b) {
			return mb_strlen($b) <=> mb_strlen($a);
		});

		return $rules;
	}

	/**
	 * @param array<string,string> $rules
	 */
	public static function apply_rules(string $content, array $rules, int $max_per_keyword): string
	{
		foreach ($rules as $keyword => $url) {
			$pattern = '/(?![^<]*>|[^<>]*<\/a>)(' . preg_quote($keyword, '/') . ')/iu';
			$count   = 0;
			$content = preg_replace_callback($pattern, static function ($m) use ($url, &$count, $max_per_keyword) {
				if ($count >= $max_per_keyword) {
					return $m[0];
				}
				$count++;

				return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $m[0] . '</a>';
			}, $content);
		}

		return is_string($content) ? $content : '';
	}

	public static function get(string $field, $default = '')
	{
		$options = get_option('crawlwp_' . self::SECTION, []);

		return is_array($options) ? ($options[$field] ?? $default) : $default;
	}
}
