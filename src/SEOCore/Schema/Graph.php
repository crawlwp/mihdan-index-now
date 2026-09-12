<?php

namespace Mihdan\IndexNow\SEOCore\Schema;

use Mihdan\IndexNow\SEOCore\MetaBox\MetaFields;
use Mihdan\IndexNow\SEOCore\TitleMeta\FrontendOutput;

/**
 * Extra JSON-LD nodes (FAQ, HowTo, Recipe, Event, Job, Course, Video, custom).
 *
 * Builders that take arrays are pure and unit-testable.
 */
class Graph
{
	public function __construct()
	{
		add_action('wp_head', [$this, 'output'], 3);
	}

	public function output(): void
	{
		if (is_admin() || is_feed() || ! is_singular()) {
			return;
		}

		$post = get_queried_object();

		if (! $post instanceof \WP_Post) {
			return;
		}

		$nodes = $this->nodes_for_post($post);

		foreach ($nodes as $node) {
			echo '<script type="application/ld+json">' . "\n";
			echo wp_json_encode($node, FrontendOutput::JSON_LD_FLAGS);
			echo "\n" . '</script>' . "\n";
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function nodes_for_post(\WP_Post $post): array
	{
		$extra = MetaFields::get($post->ID, MetaFields::SCHEMA_EXTRA, []);

		if (is_string($extra) && $extra !== '') {
			$decoded = json_decode($extra, true);
			$extra   = is_array($decoded) ? $decoded : [];
		}

		if (! is_array($extra)) {
			$extra = [];
		}

		$nodes = [];
		$type  = (string) ($extra['extra_type'] ?? '');

		switch ($type) {
			case 'FAQPage':
				$node = self::faq($extra['faq'] ?? []);
				break;
			case 'HowTo':
				$node = self::howto($extra['howto'] ?? []);
				break;
			case 'Recipe':
				$node = self::recipe($extra['recipe'] ?? []);
				break;
			case 'Event':
				$node = self::event($extra['event'] ?? []);
				break;
			case 'JobPosting':
				$node = self::job($extra['job'] ?? []);
				break;
			case 'Course':
				$node = self::course($extra['course'] ?? []);
				break;
			case 'VideoObject':
				$node = self::video($extra['video'] ?? []);
				break;
			default:
				$node = null;
		}

		if (is_array($node)) {
			$nodes[] = $node;
		}

		$custom = (string) MetaFields::get($post->ID, MetaFields::SCHEMA_CUSTOM, '');

		if ($custom === '' && ! empty($extra['custom'])) {
			$custom = (string) $extra['custom'];
		}

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
