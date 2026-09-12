<?php

namespace Mihdan\IndexNow\SEOCore\Schema;

/**
 * Gutenberg FAQ, HowTo and Table of Contents blocks.
 */
class Blocks
{
	public function __construct()
	{
		add_action('init', [$this, 'register']);
	}

	public function register(): void
	{
		if (! function_exists('register_block_type')) {
			return;
		}

		wp_register_script(
			'crawlwp-blocks',
			CRAWLWP_PLUGIN_URL . 'src/SEOCore/Schema/assets/blocks.js',
			['wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-components'],
			CRAWLWP_VERSION,
			true
		);

		register_block_type('crawlwp/faq', [
			'api_version'     => 2,
			'editor_script'   => 'crawlwp-blocks',
			'render_callback' => [$this, 'render_faq'],
			'attributes'      => [
				'items' => [
					'type'    => 'array',
					'default' => [],
				],
			],
		]);

		register_block_type('crawlwp/howto', [
			'api_version'     => 2,
			'editor_script'   => 'crawlwp-blocks',
			'render_callback' => [$this, 'render_howto'],
			'attributes'      => [
				'name'  => ['type' => 'string', 'default' => ''],
				'steps' => ['type' => 'array', 'default' => []],
			],
		]);

		register_block_type('crawlwp/toc', [
			'api_version'     => 2,
			'editor_script'   => 'crawlwp-blocks',
			'render_callback' => [$this, 'render_toc'],
			'attributes'      => [
				'title' => ['type' => 'string', 'default' => ''],
			],
		]);
	}

	/**
	 * @param array<string,mixed> $attrs
	 */
	public function render_faq(array $attrs): string
	{
		$items = is_array($attrs['items'] ?? null) ? $attrs['items'] : [];
		$html  = '<div class="cwp-faq">';

		foreach ($items as $item) {
			$q = isset($item['question']) ? (string) $item['question'] : '';
			$a = isset($item['answer']) ? (string) $item['answer'] : '';

			if ($q === '') {
				continue;
			}

			$html .= '<details class="cwp-faq-item"><summary>' . esc_html($q) . '</summary><div>' . wp_kses_post(wpautop($a)) . '</div></details>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * @param array<string,mixed> $attrs
	 */
	public function render_howto(array $attrs): string
	{
		$name  = (string) ($attrs['name'] ?? '');
		$steps = is_array($attrs['steps'] ?? null) ? $attrs['steps'] : [];
		$html  = '<div class="cwp-howto">';

		if ($name !== '') {
			$html .= '<h3>' . esc_html($name) . '</h3>';
		}

		$html .= '<ol>';

		foreach ($steps as $step) {
			$text = is_array($step) ? (string) ($step['text'] ?? $step['name'] ?? '') : (string) $step;
			if ($text === '') {
				continue;
			}
			$html .= '<li>' . esc_html($text) . '</li>';
		}

		$html .= '</ol></div>';

		return $html;
	}

	/**
	 * @param array<string,mixed> $attrs
	 */
	public function render_toc(array $attrs): string
	{
		$post = get_post();

		if (! $post) {
			return '';
		}

		$title   = (string) ($attrs['title'] ?? '');
		$heading = $title !== '' ? $title : __('Table of contents', 'mihdan-index-now');
		$items   = $this->headings_from_content((string) $post->post_content);

		if ($items === []) {
			return '';
		}

		$html = '<nav class="cwp-toc" aria-label="' . esc_attr($heading) . '"><strong>' . esc_html($heading) . '</strong><ol>';

		foreach ($items as $item) {
			$html .= '<li><a href="#' . esc_attr($item['id']) . '">' . esc_html($item['text']) . '</a></li>';
		}

		$html .= '</ol></nav>';

		return $html;
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function headings_from_content(string $content): array
	{
		if ($content === '' || ! preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $content, $matches)) {
			return [];
		}

		$items = [];

		foreach ($matches[1] as $i => $text) {
			$plain = wp_strip_all_tags($text);
			$id    = sanitize_title($plain);

			if ($id === '') {
				$id = 'section-' . ($i + 1);
			}

			$items[] = ['id' => $id, 'text' => $plain];
		}

		return $items;
	}
}
