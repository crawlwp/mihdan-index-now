<?php

namespace Mihdan\IndexNow\SEOCore\RobotsSettings;

use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\Views\WPOSA;

/**
 * Registers the "Robots.txt" settings section under the "Advanced" tab.
 *
 * WordPress generates a virtual /robots.txt dynamically via the `robots_txt`
 * filter when no physical robots.txt file exists. This class lets site
 * administrators edit the full robots.txt content.
 *
 * The textarea is pre-populated with the full current robots.txt output
 * (WordPress default + all other plugins' additions via the robots_txt filter).
 * When saved, the textarea content replaces the entire /robots.txt output.
 */
class RobotsSettings
{
	/** Option/section id (without the crawlwp_ prefix). */
	const SECTION = 'robots';

	public function __construct()
	{
		add_action('crawlwp_setup_fields', [$this, 'settings_fields'], 35, 2);

		/*
		 * Replace the entire robots.txt output with the admin-saved content.
		 * Priority 99 ensures we run after all other plugins have added their
		 * directives (so the initial pre-population captured them all) and we
		 * can reliably override the final output once the admin has saved.
		 */
		add_filter('robots_txt', [$this, 'replace_robots_txt'], PHP_INT_MAX - 1, 2);
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
			'id'             => self::SECTION,
			'title'          => __('Robots.txt', 'mihdan-index-now'),
		]);

		$this->add_robots_fields($wposa);
	}

	// -------------------------------------------------------------------------
	// Fields
	// -------------------------------------------------------------------------

	private function add_robots_fields(WPOSA $wposa): void
	{
		$options = get_option('crawlwp_' . self::SECTION, []);
		$editing_enabled = ! empty($options['enable_editing']) && 'on' == $options['enable_editing'];

		/* Enable editing toggle — disabled by default. */
		$wposa->add_field(self::SECTION, [
			'id'   => 'enable_editing',
			'type' => 'checkbox',
			'name' => __('Enable robots.txt editing', 'mihdan-index-now'),
			'desc' => __('Check this box to edit the robots.txt content directly. When unchecked, the textarea below is read-only and the default WordPress output is used.', 'mihdan-index-now'),
		]);

		/*
		 * Pre-populate the textarea with the full current robots.txt output
		 * (WordPress default + every other plugin's robots_txt additions),
		 * but only when no content has been saved yet. Once the admin saves,
		 * the stored value is used as-is.
		 */
		$saved_content   = $options['robots_content'] ?? '';
		$default_content = $saved_content !== '' ? $saved_content : $this->get_full_robots_txt();

		$wposa->add_field(self::SECTION, [
			'id'         => 'robots_content',
			'type'       => 'textarea',
			'name'       => __('robots.txt content', 'mihdan-index-now'),
			'default'    => $default_content,
			'rows'       => 15,
			'attributes' => $editing_enabled ? [] : ['readonly' => 'readonly'],
			'desc'       => __('The full content of your virtual robots.txt file. Pre-filled with the current output (WordPress defaults plus any additions from other plugins). Edit as needed — whatever you save here becomes the complete /robots.txt file.', 'mihdan-index-now'),
		]);

		/* Small inline script to live-toggle the textarea readonly state. */
		add_action('admin_footer', function () {
			?>
			<script>
			(function () {
				var cb = document.getElementById('wposa-crawlwp_robots[enable_editing]');
				var ta = document.getElementById('crawlwp_robots[robots_content]');
				if (!cb || !ta) return;
				function sync() { ta.readOnly = !cb.checked; }
				cb.addEventListener('change', sync);
				sync();
			}());
			</script>
			<?php
		});
	}

	// -------------------------------------------------------------------------
	// Frontend output
	// -------------------------------------------------------------------------

	/**
	 * Replace the robots.txt output with the admin-saved content.
	 *
	 * When no content has been saved yet, the original output is returned
	 * unchanged so other plugins continue to work normally.
	 *
	 * @param string $output The current robots.txt content built by WordPress.
	 * @param bool   $public Whether the site is set to be public.
	 * @return string
	 */
	public function replace_robots_txt(string $output, bool $public): string
	{
		$options = get_option('crawlwp_' . self::SECTION, []);

		/* Only replace when the admin has explicitly enabled editing. */
		if (empty($options['enable_editing'])) {
			return $output;
		}

		$content = isset($options['robots_content']) ? trim($options['robots_content']) : '';

		/**
		 * Filter the saved robots.txt content before it is output.
		 *
		 * @param string $content The full saved robots.txt content.
		 * @param bool   $public  Whether the site is public.
		 */
		$content = apply_filters('crawlwp_robots_txt', $content, $public);

		if ($content === '') {
			return $output;
		}

		return $content . "\n";
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the full robots.txt output as WordPress would generate it —
	 * WordPress's own base content plus every other plugin's robots_txt
	 * additions — WITHOUT including our own replacement filter.
	 *
	 * We temporarily remove our filter, apply the filter chain, then restore
	 * it, so the preview never enters an infinite-loop or shows stale saved data.
	 *
	 * @return string
	 */
	private function get_full_robots_txt(): string
	{
		remove_filter('robots_txt', [$this, 'replace_robots_txt'], PHP_INT_MAX - 1);

		$public = get_option('blog_public');

		/*
		 * Replicate WordPress's do_robots() base output exactly:
		 * see wp-includes/functions.php::do_robots().
		 */
		if ('0' === $public) {
			$output = "User-agent: *\nDisallow: /\n";
		} else {
			$output  = "User-agent: *\n";
			$output .= "Disallow:\n";
		}

		/* Let every other plugin (including our own Sitemap.php) add its lines. */
		$output = apply_filters('robots_txt', $output, $public);

		add_filter('robots_txt', [$this, 'replace_robots_txt'], PHP_INT_MAX - 1, 2);

		return trim($output);
	}

	// -------------------------------------------------------------------------
	// Option reader
	// -------------------------------------------------------------------------

	/**
	 * Read a single robots option value.
	 *
	 * @param string $key     Field id.
	 * @param mixed  $default Default value when the key is absent.
	 * @return mixed
	 */
	public static function get(string $key, $default = '')
	{
		$options = get_option('crawlwp_' . self::SECTION, []);
		return $options[$key] ?? $default;
	}
}
