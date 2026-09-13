<?php

namespace Mihdan\IndexNow\SEOCore\Schema;

use Mihdan\IndexNow\SEOCore\MetaBox\MetaFields;

/**
 * The JSON-LD graph of the current request.
 *
 * Every producer (title & meta output, breadcrumbs, integrations, the FAQ /
 * HowTo / Recipe / Event / Job / Course / Video / custom nodes built here)
 * pushes its nodes in with {@see self::add_node()}. They are printed once, in
 * a single `<script type="application/ld+json">` holding one `@graph`, so the
 * nodes can reference each other by `@id`.
 *
 * Builders that take arrays are pure and unit-testable.
 */
class Graph
{
	/**
	 * Encoding flags for every JSON-LD payload the plugin prints.
	 *
	 * The `JSON_HEX_*` flags keep `<`, `&`, `'` and `"` out of the script body so
	 * no value can break out of the `<script>` element. Lives here, on the class
	 * that owns the printing, so producers cannot drift from it.
	 */
	public const JSON_LD_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

	/**
	 * Nodes collected for the current request, keyed by `@id` when they have one.
	 *
	 * @var array<int|string, array<string,mixed>>
	 */
	private static array $nodes = [];

	public function __construct()
	{
		add_action('wp_head', [$this, 'output'], 3);
	}

	/**
	 * Add one node to the request graph.
	 *
	 * The `@context` of a standalone node is dropped — the printed graph carries
	 * a single one. Nodes sharing an `@id` are merged, so a later producer can
	 * extend a node another one already registered. Lists of nodes and payloads
	 * wrapped in their own `@graph` are unwrapped.
	 *
	 * @param array<string,mixed> $node
	 */
	public static function add_node(array $node): void
	{
		unset($node['@context']);

		if ($node === []) {
			return;
		}

		if (isset($node['@graph']) && is_array($node['@graph'])) {
			self::add_nodes($node['@graph']);

			return;
		}

		if (array_is_list($node)) {
			self::add_nodes($node);

			return;
		}

		$id = isset($node['@id']) ? (string) $node['@id'] : '';

		if ($id === '') {
			self::$nodes[] = $node;

			return;
		}

		self::$nodes[$id] = isset(self::$nodes[$id])
			? array_merge(self::$nodes[$id], $node)
			: $node;
	}

	/**
	 * Add several nodes to the request graph.
	 *
	 * @param array<int,mixed> $nodes
	 */
	public static function add_nodes(array $nodes): void
	{
		foreach ($nodes as $node) {
			if (is_array($node)) {
				self::add_node($node);
			}
		}
	}

	/**
	 * Every node collected so far.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_nodes(): array
	{
		return array_values(self::$nodes);
	}

	/**
	 * Drop the collected nodes. Mainly useful for tests.
	 */
	public static function reset_nodes(): void
	{
		self::$nodes = [];
	}

	public function output(): void
	{
		if (is_admin() || is_feed() || is_trackback() || is_robots()) {
			return;
		}

		if (is_singular()) {
			$post = get_queried_object();

			if ($post instanceof \WP_Post) {
				self::add_nodes($this->nodes_for_post($post));
			}
		}

		self::print_graph();
	}

	/**
	 * Print the collected nodes as one `@graph`.
	 */
	public static function print_graph(): void
	{
		/**
		 * Filter every JSON-LD node collected for the current request.
		 *
		 * Each entry is one node of the printed `@graph`; nodes reference each
		 * other through their `@id`. Return an empty array to print nothing.
		 *
		 * @param array<int,array<string,mixed>> $nodes The collected nodes.
		 */
		$nodes = (array) apply_filters('crawlwp_schema_graph', self::get_nodes());
		$nodes = array_values(array_filter($nodes, 'is_array'));

		if ($nodes === []) {
			return;
		}

		$flags = self::JSON_LD_FLAGS;

		/* Readable markup while debugging, compact <head> in production. */
		if (defined('WP_DEBUG') && WP_DEBUG) {
			$flags |= JSON_PRETTY_PRINT;
		}

		$json = wp_json_encode(
			[
				'@context' => 'https://schema.org',
				'@graph'   => $nodes,
			],
			$flags
		);

		if (! is_string($json) || $json === '') {
			return;
		}

		echo '<script type="application/ld+json">' . "\n";
		echo $json;
		echo "\n" . '</script>' . "\n";
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function nodes_for_post(\WP_Post $post): array
	{
		$nodes = [];

		$custom = (string) MetaFields::get($post->ID, MetaFields::SCHEMA_CUSTOM, '');

		if ($custom !== '') {
			$decoded = json_decode($custom, true);
			if (is_array($decoded)) {
				$nodes[] = $decoded;
			}
		}

		$from_blocks = self::nodes_from_blocks((string) $post->post_content);

		return array_values(array_filter(array_merge($nodes, $from_blocks)));
	}

	/**
	 * @param array<int,array{question?:string,answer?:string}> $items
	 * @return array<string,mixed>|null
	 */
	public static function faq(array $items): ?array
	{
		$entities = [];

		foreach ($items as $item) {
			$q = trim((string) ($item['question'] ?? ''));
			$a = trim((string) ($item['answer'] ?? ''));

			if ($q === '' || $a === '') {
				continue;
			}

			$entities[] = [
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $a,
				],
			];
		}

		if ($entities === []) {
			return null;
		}

		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		];
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|null
	 */
	public static function howto(array $data): ?array
	{
		$name  = trim((string) ($data['name'] ?? ''));
		$steps = is_array($data['steps'] ?? null) ? $data['steps'] : [];
		$list  = [];

		foreach ($steps as $i => $step) {
			$text = trim((string) (is_array($step) ? ($step['text'] ?? $step['name'] ?? '') : $step));

			if ($text === '') {
				continue;
			}

			$list[] = [
				'@type'    => 'HowToStep',
				'position' => $i + 1,
				'name'     => trim((string) (is_array($step) ? ($step['name'] ?? $text) : $text)),
				'text'     => $text,
			];
		}

		if ($name === '' || $list === []) {
			return null;
		}

		return [
			'@context' => 'https://schema.org',
			'@type'    => 'HowTo',
			'name'     => $name,
			'step'     => $list,
		];
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|null
	 */
	public static function recipe(array $data): ?array
	{
		$name = trim((string) ($data['name'] ?? ''));

		if ($name === '') {
			return null;
		}

		$node = [
			'@context' => 'https://schema.org',
			'@type'    => 'Recipe',
			'name'     => $name,
		];

		if (! empty($data['description'])) {
			$node['description'] = (string) $data['description'];
		}

		if (! empty($data['prep_time'])) {
			$node['prepTime'] = self::duration((string) $data['prep_time']);
		}

		if (! empty($data['cook_time'])) {
			$node['cookTime'] = self::duration((string) $data['cook_time']);
		}

		if (! empty($data['ingredients']) && is_array($data['ingredients'])) {
			$node['recipeIngredient'] = array_values(array_filter(array_map('strval', $data['ingredients'])));
		}

		return $node;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|null
	 */
	public static function event(array $data): ?array
	{
		$name = trim((string) ($data['name'] ?? ''));

		if ($name === '') {
			return null;
		}

		$node = [
			'@context'  => 'https://schema.org',
			'@type'     => 'Event',
			'name'      => $name,
			'startDate' => (string) ($data['start_date'] ?? ''),
		];

		if (! empty($data['end_date'])) {
			$node['endDate'] = (string) $data['end_date'];
		}

		if (! empty($data['location'])) {
			$node['location'] = [
				'@type' => 'Place',
				'name'  => (string) $data['location'],
			];
		}

		return $node;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|null
	 */
	public static function job(array $data): ?array
	{
		$title = trim((string) ($data['title'] ?? ''));

		if ($title === '') {
			return null;
		}

		$node = [
			'@context'  => 'https://schema.org',
			'@type'     => 'JobPosting',
			'title'     => $title,
			'datePosted'=> (string) ($data['date_posted'] ?? ''),
		];

		if (! empty($data['description'])) {
			$node['description'] = (string) $data['description'];
		}

		if (! empty($data['organization'])) {
			$node['hiringOrganization'] = [
				'@type' => 'Organization',
				'name'  => (string) $data['organization'],
			];
		}

		return $node;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|null
	 */
	public static function course(array $data): ?array
	{
		$name = trim((string) ($data['name'] ?? ''));

		if ($name === '') {
			return null;
		}

		return [
			'@context'    => 'https://schema.org',
			'@type'       => 'Course',
			'name'        => $name,
			'description' => (string) ($data['description'] ?? ''),
			'provider'    => [
				'@type' => 'Organization',
				'name'  => (string) ($data['provider'] ?? ''),
			],
		];
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|null
	 */
	public static function video(array $data): ?array
	{
		$name = trim((string) ($data['name'] ?? ''));
		$url  = trim((string) ($data['url'] ?? $data['content_url'] ?? ''));

		if ($name === '' || $url === '') {
			return null;
		}

		$node = [
			'@context'   => 'https://schema.org',
			'@type'      => 'VideoObject',
			'name'       => $name,
			'contentUrl' => $url,
		];

		if (! empty($data['description'])) {
			$node['description'] = (string) $data['description'];
		}

		if (! empty($data['thumbnail'])) {
			$node['thumbnailUrl'] = (string) $data['thumbnail'];
		}

		if (! empty($data['upload_date'])) {
			$node['uploadDate'] = (string) $data['upload_date'];
		}

		return $node;
	}

	public static function duration(string $minutes): string
	{
		$m = (int) $minutes;

		return 'PT' . max(0, $m) . 'M';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function nodes_from_blocks(string $content): array
	{
		if ($content === '' || ! function_exists('parse_blocks')) {
			return [];
		}

		$nodes  = [];
		$blocks = parse_blocks($content);
		self::walk_blocks($blocks, $nodes);

		return $nodes;
	}

	/**
	 * @param array<int,array<string,mixed>> $blocks
	 * @param array<int,array<string,mixed>> $nodes
	 */
	private static function walk_blocks(array $blocks, array &$nodes): void
	{
		foreach ($blocks as $block) {
			$name = (string) ($block['blockName'] ?? '');
			$attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

			if ($name === 'crawlwp/faq') {
				$node = self::faq($attrs['items'] ?? []);
				if ($node) {
					$nodes[] = $node;
				}
			}

			if ($name === 'crawlwp/howto') {
				$node = self::howto($attrs);
				if ($node) {
					$nodes[] = $node;
				}
			}

			if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
				self::walk_blocks($block['innerBlocks'], $nodes);
			}
		}
	}
}
