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
		add_action('crawlwp_setup_fields', [$this, 'settings_fields'], 42);
		add_filter('the_content', [$this, 'link_content'], 99);
	}

	public function settings_fields(WPOSA $wposa): void
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

		/**
		 * Filters the maximum number of links added per keyword.
		 *
		 * @param int $max Maximum links per keyword. Default 3, 0 for unlimited.
		 */
		$max_per_keyword = (int) apply_filters('crawlwp_autolink_max_per_keyword', 3);

		/**
		 * Filters the maximum number of links added to a single post.
		 *
		 * @param int $max Maximum links per post. Default 0 (unlimited).
		 */
		$max_total = (int) apply_filters('crawlwp_autolink_max_per_post', 0);

		return self::apply_rules($content, $rules, $max_per_keyword, $max_total);
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
	 * Link the keywords inside the content's text nodes.
	 *
	 * The content is parsed as HTML and only text nodes are rewritten, so
	 * tag names, attributes (`<img alt="keyword">`) and the contents of
	 * headings, existing links and code blocks are never touched. All
	 * keywords are matched in a single pass.
	 *
	 * Walks the post with {@see \WP_HTML_Processor} so nested structure is
	 * respected without a full DOM load. Returns the original content when
	 * the HTML API is missing or bails on unsupported markup.
	 *
	 * @param array<string,string> $rules           keyword => URL.
	 * @param int                  $max_per_keyword Maximum links per keyword, 0 for unlimited.
	 * @param int                  $max_total       Maximum links in the whole content, 0 for unlimited.
	 */
	public static function apply_rules(string $content, array $rules, int $max_per_keyword, int $max_total = 0): string
	{
		if ($content === '' || $rules === []) return $content;

		$rules = self::drop_self_links($rules);
		$rules = self::index_by_lowercase($rules);

		if ($rules === [] || ! self::contains_any($content, array_keys($rules))) return $content;

		$pattern = self::build_pattern(array_keys($rules));

		if ($pattern === '' || ! self::html_processor_available()) {
			return $content;
		}

		$processed = self::apply_rules_with_html_processor($content, $pattern, $rules, $max_per_keyword, $max_total);

		if ($processed !== null) return $processed;

		return $content;
	}

	/**
	 * Walk text tokens with WP_HTML_Processor and splice links in via
	 * placeholders. `set_modifiable_text()` only accepts plaintext (it
	 * would escape `<a>`), so each rewritten node is swapped for a
	 * unique marker and the real HTML is substituted afterwards.
	 *
	 * @param array<string,string> $rules
	 * @return string|null Processed HTML, or null when the processor bails.
	 */
	private static function apply_rules_with_html_processor(
		string $content,
		string $pattern,
		array $rules,
		int $max_per_keyword,
		int $max_total
	): ?string {
		$processor = \WP_HTML_Processor::create_fragment($content);

		if (! $processor instanceof \WP_HTML_Processor) {
			return null;
		}

		$counts       = [];
		$total        = 0;
		$replacements = [];
		$token        = 0;
		$marker       = self::placeholder_prefix();

		while ($processor->next_token()) {
			if ($max_total > 0 && $total >= $max_total) {
				break;
			}

			if ($processor->get_token_type() !== '#text' || self::is_skipped_token($processor)) {
				continue;
			}

			$html = self::link_text(
				$processor->get_modifiable_text(),
				$pattern,
				$rules,
				$counts,
				$total,
				$max_per_keyword,
				$max_total
			);

			if ($html === null) {
				continue;
			}

			$placeholder = $marker . $token . "\u{E001}";
			$token++;

			if (! $processor->set_modifiable_text($placeholder)) {
				continue;
			}

			$replacements[$placeholder] = $html;
		}

		if ($processor->get_last_error() !== null) {
			return null;
		}

		if ($replacements === []) {
			return $content;
		}

		return strtr($processor->get_updated_html(), $replacements);
	}

	private static function html_processor_available(): bool
	{
		return class_exists(\WP_HTML_Processor::class)
			&& method_exists(\WP_HTML_Processor::class, 'create_fragment')
			&& method_exists(\WP_HTML_Tag_Processor::class, 'set_modifiable_text');
	}

	/**
	 * Per-call prefix for text-node placeholders. `random_bytes()` is
	 * preferred but throws when no CSPRNG is available, so uniqid() is
	 * the fallback — uniqueness, not secrecy, is all that is required.
	 */
	private static function placeholder_prefix(): string
	{
		$entropy = str_replace('.', '', uniqid('', true));

		if (function_exists('random_bytes')) {
			try {
				$entropy = bin2hex(random_bytes(4));
			} catch (\Exception $e) {
				// CSPRNG unavailable; the uniqid() prefix already set above is enough.
			}
		}

		return "\u{E000}cwp" . $entropy;
	}

	/**
	 * Never link a page to itself.
	 *
	 * @param array<string,string> $rules
	 * @return array<string,string>
	 */
	private static function drop_self_links(array $rules): array
	{
		if (! function_exists('get_permalink')) {
			return $rules;
		}

		$permalink = get_permalink();

		if (! is_string($permalink) || $permalink === '') {
			return $rules;
		}

		$self = self::normalize_url($permalink);

		return array_filter($rules, static function ($url) use ($self) {
			return self::normalize_url((string) $url) !== $self;
		});
	}

	private static function normalize_url(string $url): string
	{
		$url = strtok($url, '#') ?: $url;
		$url = preg_replace('#^https?://#i', '', $url);

		return rtrim(strtolower((string) $url), '/');
	}

	/**
	 * Keywords are matched case-insensitively, so the lookup map has to be
	 * keyed on the lowercase keyword. Longer keywords keep their priority.
	 *
	 * @param array<string,string> $rules
	 * @return array<string,string>
	 */
	private static function index_by_lowercase(array $rules): array
	{
		$indexed = [];

		foreach ($rules as $keyword => $url) {
			$keyword = $keyword;

			if ($keyword === '') {
				continue;
			}

			$indexed[mb_strtolower($keyword)] = $url;
		}

		return $indexed;
	}

	/**
	 * @param string[] $keywords
	 */
	private static function contains_any(string $content, array $keywords): bool
	{
		foreach ($keywords as $keyword) {
			if ($keyword !== '' && mb_stripos($content, $keyword) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A single alternation of every keyword, longest first, matched on whole
	 * words only.
	 *
	 * @param string[] $keywords
	 */
	private static function build_pattern(array $keywords): string
	{
		$quoted = [];

		foreach ($keywords as $keyword) {
			if ($keyword !== '') {
				$quoted[] = preg_quote($keyword, '/');
			}
		}

		if ($quoted === []) {
			return '';
		}

		return '/(?<![\p{L}\p{N}_])(' . implode('|', $quoted) . ')(?![\p{L}\p{N}_])/iu';
	}

	/**
	 * Text inside these elements — and inside any existing link — is left
	 * alone.
	 *
	 * @return string[] Lowercase tag names.
	 */
	private static function skipped_tags(): array
	{
		$skip = [
			'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
			'script', 'style', 'code', 'pre', 'textarea',
			'select', 'option', 'button', 'iframe', 'noscript', 'nav'
		];

		/**
		 * Filters the element names whose text content is never auto-linked.
		 *
		 * @param string[] $skip Lowercase tag names.
		 */
		return array_map('strtolower', apply_filters('crawlwp_autolink_skipped_tags', $skip));
	}

	private static function is_skipped_token(\WP_HTML_Processor $processor): bool
	{
		$skip = self::skipped_tags();

		foreach ($processor->get_breadcrumbs() as $tag) {
			if (in_array(strtolower((string) $tag), $skip, true)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the HTML for a single text node, or null when nothing matched.
	 *
	 * @param array<string,string> $rules
	 * @param array<string,int>    $counts
	 */
	private static function link_text(string $text, string $pattern, array $rules, array &$counts, int &$total, int $max_per_keyword, int $max_total): ?string
	{
		if (trim($text) === '') {
			return null;
		}

		$output  = '';
		$changed = false;

		/* Shortcodes that were never parsed (e.g. inside a disabled block)
		 * must stay verbatim, so their brackets are skipped as a whole. */
		$chunks = preg_split('/(\[[^\[\]]*\])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

		foreach (is_array($chunks) ? $chunks : [$text] as $chunk) {
			if ($chunk === '') {
				continue;
			}

			if ($chunk[0] === '[') {
				$output .= self::esc($chunk);
				continue;
			}

			$parts = preg_split($pattern, $chunk, -1, PREG_SPLIT_DELIM_CAPTURE);

			if (! is_array($parts)) {
				$output .= self::esc($chunk);
				continue;
			}

			foreach ($parts as $i => $part) {
				/* The pattern holds exactly one capture group, so every odd
				 * offset is a matched keyword. */
				if ($i % 2 === 0 || $part === '') {
					$output .= self::esc($part);
					continue;
				}

				$key  = mb_strtolower($part);
				$used = $counts[$key] ?? 0;

				$allowed = isset($rules[$key])
					&& ($max_per_keyword <= 0 || $used < $max_per_keyword)
					&& ($max_total <= 0 || $total < $max_total);

				if (! $allowed) {
					$output .= self::esc($part);
					continue;
				}

				$counts[$key] = $used + 1;
				$total++;
				$changed = true;

				$output .= '<a href="' . self::esc_url($rules[$key]) . '">' . self::esc($part) . '</a>';
			}
		}

		return $changed ? $output : null;
	}

	private static function esc(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
	}

	private static function esc_url(string $url): string
	{
		if (function_exists('esc_url')) {
			$url = (string) esc_url($url);
		}

		return self::esc(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
	}

	public static function get(string $field, $default = '')
	{
		$options = get_option('crawlwp_' . self::SECTION, []);

		return is_array($options) ? ($options[$field] ?? $default) : $default;
	}
}
