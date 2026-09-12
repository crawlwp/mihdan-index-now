<?php

namespace Mihdan\IndexNow\SEOCore\Importer\Sources;

use Mihdan\IndexNow\SEOCore\Importer\Source;
use Mihdan\IndexNow\SEOCore\Importer\Writer;
use Mihdan\IndexNow\SEOCore\Redirects\RedirectsManager;

class RankMath extends Source
{
	public function id(): string
	{
		return 'rankmath';
	}

	public function label(): string
	{
		return 'Rank Math';
	}

	public function is_available(): bool
	{
		return defined('RANK_MATH_VERSION')
			|| get_option('rank-math-options-general') !== false
			|| $this->has_meta('rank_math_title');
	}

	public function counts(): array
	{
		global $wpdb;

		$redirects = 0;

		if ($this->table_exists($wpdb->prefix . 'rank_math_redirections')) {
			$redirects = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rank_math_redirections");
		}

		return [
			'posts'     => $this->count_meta('rank_math_title') + $this->count_meta('rank_math_description'),
			'terms'     => $this->count_term_meta('rank_math_title'),
			'redirects' => $redirects,
		];
	}

	public function import_posts(int $offset, int $limit, bool $overwrite): array
	{
		$ids = $this->post_ids($offset, $limit);
		$imported = 0;
		$skipped  = 0;

		foreach ($ids as $post_id) {
			$data = $this->object_payload('post', $post_id);

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
		$terms = $this->terms($offset, $limit);
		$imported = 0;
		$skipped  = 0;

		foreach ($terms as $term) {
			$data = $this->object_payload('term', (int) $term->term_id);

			if ($data === []) {
				continue;
			}

			Writer::write_term((int) $term->term_id, $data, $overwrite) ? $imported++ : $skipped++;
		}

		return [
			'imported'    => $imported,
			'skipped'     => $skipped,
			'done'        => count($terms) < $limit,
			'next_offset' => $offset + count($terms),
		];
	}

	public function import_redirects(): int
	{
		global $wpdb;

		$table = $wpdb->prefix . 'rank_math_redirections';

		if (! $this->table_exists($table)) {
			return 0;
		}

		$rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);

		if (! is_array($rows) || $rows === []) {
			return 0;
		}

		$manager = new RedirectsManager();
		$count   = 0;

		foreach ($rows as $row) {
			$sources = maybe_unserialize($row['sources'] ?? '');

			if (! is_array($sources)) {
				continue;
			}

			foreach ($sources as $source) {
				$from = is_array($source) ? (string) ($source['pattern'] ?? '') : (string) $source;

				if ($from === '' || $manager->exists_from_url($from)) {
					continue;
				}

				$comparison = is_array($source) ? (string) ($source['comparison'] ?? 'exact') : 'exact';
				$match      = $comparison === 'regex' ? 'regex' : 'exact';
				$type       = (int) ($row['header_code'] ?? 301);
				$enabled    = (($row['status'] ?? 'active') === 'active') ? 1 : 0;

				$ok = $manager->insert([
					'from_url'            => $from,
					'to_url'              => (string) ($row['url_to'] ?? ''),
					'redirect_type'       => in_array($type, [301, 302, 307, 410, 451], true) ? $type : 301,
					'match_type'          => $match,
					'note'                => __('Imported from Rank Math', 'mihdan-index-now'),
					'ignore_query_string' => 1,
					'enabled'             => $enabled,
				]);

				if ($ok) {
					$count++;
				}
			}
		}

		return $count;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function object_payload(string $type, int $id): array
	{
		$get = $type === 'term' ? 'get_term_meta' : 'get_post_meta';

		$title = $this->convert((string) $get($id, 'rank_math_title', true));
		$desc  = $this->convert((string) $get($id, 'rank_math_description', true));
		$kw    = (string) $get($id, 'rank_math_focus_keyword', true);
		$robots = $get($id, 'rank_math_robots', true);
		$noindex = is_array($robots) && in_array('noindex', $robots, true);
		$nofollow = is_array($robots) && in_array('nofollow', $robots, true);

		$twitter_use_fb = (string) $get($id, 'rank_math_twitter_use_facebook', true);

		$data = [
			'title'            => $title,
			'description'      => $desc,
			'focus_keyword'    => $kw,
			'canonical'        => (string) $get($id, 'rank_math_canonical_url', true),
			'og_title'         => $this->convert((string) $get($id, 'rank_math_facebook_title', true)),
			'og_description'   => $this->convert((string) $get($id, 'rank_math_facebook_description', true)),
			'og_image'         => (string) $get($id, 'rank_math_facebook_image_id', true)
				?: (string) $get($id, 'rank_math_facebook_image', true),
			'x_title'          => $this->convert((string) $get($id, 'rank_math_twitter_title', true)),
			'x_description'    => $this->convert((string) $get($id, 'rank_math_twitter_description', true)),
			'x_image'          => $twitter_use_fb === 'on'
				? ''
				: ((string) $get($id, 'rank_math_twitter_image', true)),
			'primary_category' => (int) $get($id, 'rank_math_primary_category', true),
			'cornerstone'      => (string) $get($id, 'rank_math_pillar_content', true),
		];

		if ($noindex) {
			$data['robots_index'] = 'noindex';
		}

		if ($nofollow) {
			$data['robots_follow'] = 'nofollow';
		}

		return array_filter($data, static function ($v) {
			return $v !== '' && $v !== null && $v !== 0 && $v !== '0';
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

	private function count_term_meta(string $key): int
	{
		global $wpdb;

		return (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value <> ''",
			$key
		));
	}
}
