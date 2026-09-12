<?php

namespace Mihdan\IndexNow\SEOCore\Importer\Sources;

use Mihdan\IndexNow\SEOCore\Importer\Source;
use Mihdan\IndexNow\SEOCore\Importer\Writer;
use Mihdan\IndexNow\SEOCore\Redirects\RedirectsManager;

class AIOSEO extends Source
{
	public function id(): string
	{
		return 'aioseo';
	}

	public function label(): string
	{
		return 'All in One SEO';
	}

	public function is_available(): bool
	{
		global $wpdb;

		return defined('AIOSEO_VERSION')
			|| get_option('aioseo_options') !== false
			|| $this->table_exists($wpdb->prefix . 'aioseo_posts')
			|| $this->has_meta('_aioseop_title');
	}

	public function counts(): array
	{
		global $wpdb;

		$posts = 0;
		$terms = 0;
		$redirects = 0;

		if ($this->table_exists($wpdb->prefix . 'aioseo_posts')) {
			$posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aioseo_posts");
		} else {
			$posts = $this->count_meta('_aioseop_title');
		}

		if ($this->table_exists($wpdb->prefix . 'aioseo_terms')) {
			$terms = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aioseo_terms");
		}

		if ($this->table_exists($wpdb->prefix . 'aioseo_redirects')) {
			$redirects = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aioseo_redirects");
		}

		return [
			'posts'     => $posts,
			'terms'     => $terms,
			'redirects' => $redirects,
		];
	}

	public function import_posts(int $offset, int $limit, bool $overwrite): array
	{
		global $wpdb;

		$table = $wpdb->prefix . 'aioseo_posts';

		if ($this->table_exists($table)) {
			$rows = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
				$limit,
				$offset
			), ARRAY_A);

			$imported = 0;
			$skipped  = 0;

			foreach ((array) $rows as $row) {
				$data = $this->row_payload($row);

				if ($data === []) {
					continue;
				}

				Writer::write_post((int) $row['post_id'], $data, $overwrite) ? $imported++ : $skipped++;
			}

			return [
				'imported'    => $imported,
				'skipped'     => $skipped,
				'done'        => count((array) $rows) < $limit,
				'next_offset' => $offset + count((array) $rows),
			];
		}

		$ids = $this->post_ids($offset, $limit);
		$imported = 0;
		$skipped  = 0;

		foreach ($ids as $post_id) {
			$data = $this->legacy_post_payload($post_id);

			if ($data === []) {
				continue;
			}

			Writer::write_post($post_id, $data, $overwrite) ? $imported++ : $skipped++;
		}

		return [
			'imported'    => $imported,
			'skipped'     => $skipped,
			'done'        => count($ids) < $limit,
			'next_offset' => $offset + count($ids),
		];
	}

	public function import_terms(int $offset, int $limit, bool $overwrite): array
	{
		global $wpdb;

		$table = $wpdb->prefix . 'aioseo_terms';

		if (! $this->table_exists($table)) {
			return ['imported' => 0, 'skipped' => 0, 'done' => true, 'next_offset' => $offset];
		}

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
			$limit,
			$offset
		), ARRAY_A);

		$imported = 0;
		$skipped  = 0;

		foreach ((array) $rows as $row) {
			$data = $this->row_payload($row);

			if ($data === []) {
				continue;
			}

			$term_id = (int) ($row['term_id'] ?? 0);

			Writer::write_term($term_id, $data, $overwrite) ? $imported++ : $skipped++;
		}

		return [
			'imported'    => $imported,
			'skipped'     => $skipped,
			'done'        => count((array) $rows) < $limit,
			'next_offset' => $offset + count((array) $rows),
		];
	}

	public function import_redirects(): int
	{
		global $wpdb;

		$table = $wpdb->prefix . 'aioseo_redirects';

		if (! $this->table_exists($table)) {
			return 0;
		}

		$rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);

		if (! is_array($rows)) {
			return 0;
		}

		$manager = new RedirectsManager();
		$count   = 0;

		foreach ($rows as $row) {
			$from = (string) ($row['source_url'] ?? $row['from_url'] ?? '');

			if ($from === '' || $manager->exists_from_url($from)) {
				continue;
			}

			$type = (int) ($row['type'] ?? $row['redirect_type'] ?? 301);
			$ok   = $manager->insert([
				'from_url'            => $from,
				'to_url'              => (string) ($row['target_url'] ?? $row['to_url'] ?? ''),
				'redirect_type'       => in_array($type, [301, 302, 307, 410, 451], true) ? $type : 301,
				'match_type'          => ! empty($row['regex']) ? 'regex' : 'exact',
				'note'                => __('Imported from All in One SEO', 'mihdan-index-now'),
				'ignore_query_string' => 1,
				'enabled'             => empty($row['enabled']) ? 1 : (int) (bool) $row['enabled'],
			]);

			if ($ok) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private function row_payload(array $row): array
	{
		$kw = '';
		if (! empty($row['keyphrases'])) {
			$decoded = json_decode((string) $row['keyphrases'], true);
			if (is_array($decoded)) {
				$kw = (string) ($decoded['focus']['keyphrase'] ?? $decoded['keyphrase'] ?? '');
			}
		}

		$data = [
			'title'            => $this->convert((string) ($row['title'] ?? '')),
			'description'      => $this->convert((string) ($row['description'] ?? '')),
			'focus_keyword'    => $kw,
			'canonical'        => (string) ($row['canonical_url'] ?? ''),
			'og_title'         => $this->convert((string) ($row['og_title'] ?? '')),
			'og_description'   => $this->convert((string) ($row['og_description'] ?? '')),
			'og_image'         => (string) ($row['og_image_url'] ?? ''),
			'x_title'          => $this->convert((string) ($row['twitter_title'] ?? '')),
			'x_description'    => $this->convert((string) ($row['twitter_description'] ?? '')),
			'x_image'          => (string) ($row['twitter_image_url'] ?? ''),
			'primary_category' => (int) ($row['primary_term'] ?? 0),
			'cornerstone'      => ! empty($row['pillar_content']),
		];

		if (! empty($row['robots_noindex'])) {
			$data['robots_index'] = 'noindex';
		}

		if (! empty($row['robots_nofollow'])) {
			$data['robots_follow'] = 'nofollow';
		}

		return array_filter($data, static function ($v) {
			return $v !== '' && $v !== null && $v !== 0 && $v !== '0' && $v !== false;
		});
	}

	/**
	 * @return array<string,mixed>
	 */
	private function legacy_post_payload(int $post_id): array
	{
		$data = [
			'title'         => $this->convert((string) get_post_meta($post_id, '_aioseop_title', true)),
			'description'   => $this->convert((string) get_post_meta($post_id, '_aioseop_description', true)),
			'focus_keyword' => (string) get_post_meta($post_id, '_aioseop_keywords', true),
			'canonical'     => (string) get_post_meta($post_id, '_aioseop_custom_link', true),
		];

		if ((string) get_post_meta($post_id, '_aioseop_noindex', true) === 'on') {
			$data['robots_index'] = 'noindex';
		}

		if ((string) get_post_meta($post_id, '_aioseop_nofollow', true) === 'on') {
			$data['robots_follow'] = 'nofollow';
		}

		return array_filter($data, static function ($v) {
			return $v !== '' && $v !== null;
		});
	}

	private function has_meta(string $key): bool
	{
		global $wpdb;

		$found = $wpdb->get_var($wpdb->prepare(
			"SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1",
			$key
		));

		return $found !== null;
	}

	private function count_meta(string $key): int
	{
		global $wpdb;

		return (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''",
			$key
		));
	}
}
