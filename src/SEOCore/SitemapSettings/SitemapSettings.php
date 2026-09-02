<?php

namespace Mihdan\IndexNow\SEOCore\SitemapSettings;

use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\Views\WPOSA;

/**
 * Registers the "Sitemap" settings section under the "Advanced" tab.
 *
 * Provides:
 *   - A button to open the WordPress sitemap index in a new tab.
 *   - News Sitemap settings: publication name and post types to include.
 *
 * Option row: crawlwp_sitemap_settings
 * All fields are stored as crawlwp_sitemap_settings[field_id].
 */
class SitemapSettings
{
	/** Option/section id (without the crawlwp_ prefix). */
	const SECTION = 'sitemap_settings';

	public function __construct()
	{
		add_action('crawlwp_setup_fields', [$this, 'settings_fields'], 40, 2);
	}

	// -------------------------------------------------------------------------
	// Settings registration
	// -------------------------------------------------------------------------

	public function settings_fields(WPOSA $wposa, $settingsInstance): void
	{
		if ($wposa->get_active_header_menu() !== Utils::get_plugin_prefix() . '_advanced_settings') {
			return;
		}

		$wposa->add_section([
			'header_menu_id' => 'advanced_settings',
			'id' => self::SECTION,
			'title' => __('Sitemap', 'mihdan-index-now'),
			'desc' => __('WordPress generates a sitemap index automatically. Use the button below to open it in a new tab and verify it is accessible to search engines.', 'mihdan-index-now')
		]);

		$this->add_sitemap_fields($wposa);
		$this->add_news_sitemap_fields($wposa);
	}

	// -------------------------------------------------------------------------
	// Fields
	// -------------------------------------------------------------------------

	/**
	 * General sitemap fields — open-sitemap button.
	 */
	private function add_sitemap_fields(WPOSA $wposa): void
	{
		$sitemap_url = get_sitemap_url('index');

		$button_html = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" class="button button-secondary">%s <span class="dashicons dashicons-external" style="vertical-align:middle;font-size:16px;line-height:1.4;"></span></a>',
			esc_url($sitemap_url),
			esc_html__('Open Sitemap', 'mihdan-index-now')
		);

		$wposa->add_field(self::SECTION, [
			'id' => 'open_sitemap',
			'type' => 'html',
			'name' => __('Sitemap URL', 'mihdan-index-now'),
			'desc' => $button_html,
		]);
	}

	/**
	 * News Sitemap fields — publication name and post type selection.
	 */
	private function add_news_sitemap_fields(WPOSA $wposa): void
	{
		$news_sitemap_url = get_sitemap_url(NewsSitemapProvider::PROVIDER_NAME);
		$news_desc = sprintf(
		/* translators: %s: URL to the news sitemap. */
			__('Enable a dedicated News Sitemap feed that Google News requires. Only recent content (published within the last 2 days) is included. Access your news sitemap at: %s', 'mihdan-index-now'),
			'<a href="' . esc_url($news_sitemap_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($news_sitemap_url) . '</a>'
		);

		$this->add_heading(
			$wposa,
			'heading_news_sitemap',
			__('Google News Sitemap', 'mihdan-index-now'),
			$news_desc
		);

		$wposa->add_field(self::SECTION, [
			'id' => 'news_enabled',
			'type' => 'switch',
			'name' => __('Enable News Sitemap', 'mihdan-index-now'),
			'desc' => esc_html__('Register a /wp-sitemap-crawlwpnews-1.xml feed for Google News indexing.', 'mihdan-index-now'),
			'default' => 'off',
		]);

		$wposa->add_field(self::SECTION, [
			'id' => 'news_publication_name',
			'type' => 'text',
			'name' => __('Publication name', 'mihdan-index-now'),
			'placeholder' => get_bloginfo('name'),
			'desc' => esc_html__('The name of the publication as it appears in Google News. Defaults to the WordPress site title.', 'mihdan-index-now'),
		]);

		$wposa->add_field(self::SECTION, [
			'id' => 'news_post_types',
			'type' => 'multicheck',
			'name' => __('Post types to include', 'mihdan-index-now'),
			'desc' => esc_html__('Select which post types should appear in the News Sitemap. Only published posts from the last 2 days are included.', 'mihdan-index-now'),
			'options' => $this->get_post_type_options(),
			'default' => ['post' => 'post'],
		]);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build an associative array of public post type options for the multicheck field.
	 *
	 * @return array<string,string>
	 */
	private function get_post_type_options(): array
	{
		$options = [];
		$post_types = get_post_types(['public' => true], 'objects');

		foreach ($post_types as $post_type) {
			/* Skip attachments — media files are not news content. */
			if ($post_type->name === 'attachment') {
				continue;
			}

			$options[$post_type->name] = $post_type->label;
		}

		return $options;
	}

	/**
	 * A full-width sub-heading inside the settings screen.
	 */
	private function add_heading(WPOSA $wposa, string $id, string $title, string $desc = ''): void
	{
		$html = sprintf('<h3 class="cwp-tm-subheading">%s</h3>', esc_html($title));

		if ($desc !== '') {
			$html .= sprintf('<p class="description">%s</p>', wp_kses_post($desc));
		}

		$wposa->add_field(self::SECTION, [
			'id' => $id,
			'type' => 'html',
			'name' => '',
			'desc' => $html,
		]);
	}

	// -------------------------------------------------------------------------
	// Option reader
	// -------------------------------------------------------------------------

	/**
	 * Read a sitemap setting value.
	 *
	 * @param string $field Field id (e.g. 'news_enabled', 'news_publication_name').
	 * @param mixed $default Default value when option is absent or empty.
	 * @return mixed
	 */
	public static function get(string $field, $default = '')
	{
		$options = get_option('crawlwp_' . self::SECTION, []);

		if (!is_array($options)) {
			return $default;
		}

		return (isset($options[$field]) && $options[$field] !== '') ? $options[$field] : $default;
	}
}
