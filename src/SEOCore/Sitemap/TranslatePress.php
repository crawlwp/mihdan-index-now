<?php

namespace Mihdan\IndexNow\SEOCore\Sitemap;

/**
 * TranslatePress integration.
 *
 * Outputs hreflang <link rel="alternate"> tags in <head> for the current page
 * using TranslatePress URL conversion. It does not add alternate-language
 * entries to the WordPress core sitemap itself; TranslatePress serves
 * translated URLs from the same post/term entries via its URL converter.
 *
 * The translated URLs are cross-linked inside the sitemap XML through
 * AlternateLinks (xhtml:link rel="alternate"), which Google requires.
 *
 * Fires two actions that third-party code may hook:
 *   crawlwp_sitemap_post  ($post)  — when a post entry is rendered.
 *   crawlwp_sitemap_term  ($term)  — when a term entry is rendered.
 */
class TranslatePress extends Integration
{
	/** @var object|null TRP main instance. */
	private $trp;

	public function setup()
	{
		if (! class_exists('TRP_Translate_Press')) {
			return;
		}

		$this->trp = \TRP_Translate_Press::get_trp_instance();

		/*
		 * Emit hreflang <link> tags in <head> for the current page.
		 */
		add_action('wp_head', [$this, 'output_hreflang'], 2);

		/*
		 * Fire our own actions on each sitemap entry so third-party code can react,
		 * collect the alternate URLs for the sitemap XML, and let the News Sitemap
		 * use TranslatePress's language.
		 */
		$this->register_common_hooks();
	}

	/**
	 * Output hreflang <link rel="alternate"> tags in <head> for the current page.
	 */
	public function output_hreflang()
	{
		$languages    = $this->get_secondary_languages();
		$url_converter = $this->get_url_converter();

		if (empty($languages) || ! $url_converter) {
			return;
		}

		$current_url = $this->get_current_url();

		foreach ($languages as $code) {
			$translated_url = $url_converter->get_url_for_language($code, $current_url, '');

			if ($translated_url && $translated_url !== $current_url) {
				printf(
					'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
					esc_attr($code),
					esc_url($translated_url)
				);
			}
		}
	}

	/**
	 * Translated post URLs keyed by TranslatePress language code.
	 *
	 * @param \WP_Post $post Post object.
	 * @param string   $loc  The entry URL.
	 *
	 * @return array<string,string>
	 */
	protected function get_post_alternates(\WP_Post $post, string $loc): array
	{
		return $this->get_url_alternates($loc);
	}

	/**
	 * Translated term URLs keyed by TranslatePress language code.
	 *
	 * @param \WP_Term $term Term object.
	 * @param string   $loc  The entry URL.
	 *
	 * @return array<string,string>
	 */
	protected function get_term_alternates(\WP_Term $term, string $loc): array
	{
		return $this->get_url_alternates($loc);
	}

	/**
	 * TranslatePress's current language code.
	 *
	 * @return string
	 */
	protected function get_current_language(): string
	{
		if (defined('TRP_LANGUAGE') && is_string(TRP_LANGUAGE)) {
			return TRP_LANGUAGE;
		}

		return $this->get_default_language();
	}

	/**
	 * Build the per-language variants of a single URL via the URL converter.
	 *
	 * @param string $url The canonical (default-language) URL.
	 *
	 * @return array<string,string>
	 */
	private function get_url_alternates(string $url): array
	{
		$url_converter = $this->get_url_converter();

		if ($url === '' || ! $url_converter) {
			return [];
		}

		$alternates = [];

		foreach ($this->get_publish_languages() as $code) {
			$translated = $url_converter->get_url_for_language($code, $url, '');

			if (is_string($translated) && $translated !== '') {
				$alternates[(string) $code] = $translated;
			}
		}

		return $alternates;
	}

	/**
	 * Return every published language, including the default one.
	 *
	 * @return string[]
	 */
	private function get_publish_languages(): array
	{
		$settings = $this->get_settings();

		if (empty($settings['publish-languages']) || ! is_array($settings['publish-languages'])) {
			return [];
		}

		return array_values($settings['publish-languages']);
	}

	/**
	 * The TranslatePress default language code.
	 *
	 * @return string
	 */
	private function get_default_language(): string
	{
		$settings = $this->get_settings();

		return isset($settings['default-language']) ? (string) $settings['default-language'] : '';
	}

	/**
	 * Read the TranslatePress settings array.
	 *
	 * @return array
	 */
	private function get_settings(): array
	{
		if (! $this->trp) {
			return [];
		}

		$trp_settings = $this->trp->get_component('settings');

		if (! $trp_settings) {
			return [];
		}

		$settings = $trp_settings->get_settings();

		return is_array($settings) ? $settings : [];
	}

	/**
	 * Return secondary (non-default) publish languages from TranslatePress settings.
	 *
	 * @return string[]
	 */
	private function get_secondary_languages()
	{
		$settings = $this->get_settings();

		if (empty($settings['publish-languages']) || empty($settings['default-language'])) {
			return [];
		}

		/* Exclude the default language — it's already the canonical URL. */
		return array_values(array_diff($settings['publish-languages'], [$settings['default-language']]));
	}

	/**
	 * Return the TranslatePress URL converter component.
	 *
	 * @return object|null
	 */
	private function get_url_converter()
	{
		if (! $this->trp) {
			return null;
		}

		return $this->trp->get_component('url_converter');
	}

	/**
	 * Build the canonical URL for the current request.
	 *
	 * @return string
	 */
	private function get_current_url()
	{
		/* home_url() supplies the trusted scheme and host — never rely on HTTP_HOST. */
		$uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';

		if ($uri === '' || $uri[0] !== '/') {
			$uri = '/' . ltrim($uri, '/');
		}

		// REQUEST_URI already contains any sub-directory path of the site,
		// so only the scheme/host/port of home_url() are used.
		$home = wp_parse_url(home_url());

		$origin = ($home['scheme'] ?? 'https') . '://' . ($home['host'] ?? '');

		if (! empty($home['port'])) {
			$origin .= ':' . $home['port'];
		}

		return $origin . $uri;
	}
}
