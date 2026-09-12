<?php

namespace Mihdan\IndexNow\SEOCore\Importer;

/**
 * Base importer for a single third-party SEO plugin.
 */
abstract class Source
{
	abstract public function id(): string;

	abstract public function label(): string;

	abstract public function is_available(): bool;

	/**
	 * @return array{posts:int,terms:int,redirects:int}
	 */
	abstract public function counts(): array;

	/**
	 * @return array{imported:int,skipped:int,done:bool,next_offset:int}
	 */
	abstract public function import_posts(int $offset, int $limit, bool $overwrite): array;

	/**
	 * @return array{imported:int,skipped:int,done:bool,next_offset:int}
	 */
	abstract public function import_terms(int $offset, int $limit, bool $overwrite): array;

	public function import_redirects(): int
	{
		return 0;
	}

	/**
	 * @return int[]
	 */
	protected function post_ids(int $offset, int $limit): array
	{
		$q = new \WP_Query([
			'post_type'              => 'any',
			'post_status'            => ['publish', 'draft', 'pending', 'future', 'private'],
			'posts_per_page'         => $limit,
			'offset'                 => $offset,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'suppress_filters'       => true,
		]);

		return array_map('intval', $q->posts);
	}

	/**
	 * @return \WP_Term[]
	 */
	protected function terms(int $offset, int $limit): array
	{
		$taxonomies = get_taxonomies(['public' => true], 'names');

		if ($taxonomies === []) {
			return [];
		}

		$terms = get_terms([
			'taxonomy'   => array_values($taxonomies),
			'hide_empty' => false,
			'number'     => $limit,
			'offset'     => $offset,
			'orderby'    => 'term_id',
			'order'      => 'ASC',
		]);

		return is_array($terms) ? $terms : [];
	}

	protected function convert(string $text): string
	{
		return TokenMapper::convert($text, $this->id());
	}

	protected function table_exists(string $table): bool
	{
		global $wpdb;

		$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

		return $found === $table;
	}
}
