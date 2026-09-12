<?php

namespace Mihdan\IndexNow\SEOCore\Integrations;

use Mihdan\IndexNow\SEOCore\MetaBox\MetaFields;

/**
 * CrawlWP SEO fields inside the Elementor document settings panel.
 */
class Elementor
{
	public function __construct()
	{
		add_action('elementor/documents/register_controls', [$this, 'register_controls']);
		add_action('elementor/document/after_save', [$this, 'save'], 10, 2);
	}

	public function register_controls($document): void
	{
		if (! is_object($document) || ! method_exists($document, 'get_main_id')) {
			return;
		}

		$post_id = (int) $document->get_main_id();

		if ($post_id <= 0 || ! method_exists($document, 'start_controls_section')) {
			return;
		}

		$document->start_controls_section('crawlwp_seo', [
			'label' => __('CrawlWP SEO', 'mihdan-index-now'),
			'tab'   => \Elementor\Controls_Manager::TAB_SETTINGS,
		]);

		$document->add_control(MetaFields::SEO_TITLE, [
			'label'   => __('SEO title', 'mihdan-index-now'),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => (string) MetaFields::get($post_id, MetaFields::SEO_TITLE),
		]);

		$document->add_control(MetaFields::SEO_DESCRIPTION, [
			'label'   => __('Meta description', 'mihdan-index-now'),
			'type'    => \Elementor\Controls_Manager::TEXTAREA,
			'default' => (string) MetaFields::get($post_id, MetaFields::SEO_DESCRIPTION),
		]);

		$document->add_control(MetaFields::FOCUS_KEYWORD, [
			'label'   => __('Focus keyword', 'mihdan-index-now'),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => (string) MetaFields::get($post_id, MetaFields::FOCUS_KEYWORD),
		]);

		$document->add_control(MetaFields::ROBOTS_INDEX, [
			'label'   => __('Robots', 'mihdan-index-now'),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => [
				'index'   => __('index', 'mihdan-index-now'),
				'noindex' => __('noindex', 'mihdan-index-now'),
			],
			'default' => (string) MetaFields::get($post_id, MetaFields::ROBOTS_INDEX, 'index'),
		]);

		$document->add_control(MetaFields::CANONICAL_URL, [
			'label'   => __('Canonical URL', 'mihdan-index-now'),
			'type'    => \Elementor\Controls_Manager::URL,
			'default' => ['url' => (string) MetaFields::get($post_id, MetaFields::CANONICAL_URL)],
		]);

		$document->end_controls_section();
	}

	/**
	 * @param mixed $document
	 * @param array $data
	 */
	public function save($document, $data): void
	{
		if (! is_object($document) || ! method_exists($document, 'get_main_id')) {
			return;
		}

		$post_id  = (int) $document->get_main_id();
		$settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

		if ($post_id <= 0 || $settings === []) {
			return;
		}

		if (isset($settings[MetaFields::SEO_TITLE])) {
			update_post_meta($post_id, MetaFields::SEO_TITLE, sanitize_text_field((string) $settings[MetaFields::SEO_TITLE]));
		}

		if (isset($settings[MetaFields::SEO_DESCRIPTION])) {
			update_post_meta($post_id, MetaFields::SEO_DESCRIPTION, sanitize_textarea_field((string) $settings[MetaFields::SEO_DESCRIPTION]));
		}

		if (isset($settings[MetaFields::FOCUS_KEYWORD])) {
			update_post_meta($post_id, MetaFields::FOCUS_KEYWORD, sanitize_text_field((string) $settings[MetaFields::FOCUS_KEYWORD]));
		}

		if (isset($settings[MetaFields::ROBOTS_INDEX])) {
			$value = (string) $settings[MetaFields::ROBOTS_INDEX];
			update_post_meta($post_id, MetaFields::ROBOTS_INDEX, $value === 'noindex' ? 'noindex' : 'index');
		}

		if (isset($settings[MetaFields::CANONICAL_URL])) {
			$url = $settings[MetaFields::CANONICAL_URL];
			$url = is_array($url) ? (string) ($url['url'] ?? '') : (string) $url;
			update_post_meta($post_id, MetaFields::CANONICAL_URL, MetaFields::sanitize_url($url));
		}
	}
}
