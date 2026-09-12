<?php

namespace Mihdan\IndexNow\SEOCore\Importer\Sources;

use Mihdan\IndexNow\SEOCore\Importer\Source;
use Mihdan\IndexNow\SEOCore\Importer\Writer;
use Mihdan\IndexNow\SEOCore\Redirects\RedirectsManager;

class SlimSEO extends Source
{
	public function id(): string
	{
		return 'slimseo';
	}

	public function label(): string
	{
		return 'Slim SEO';
	}

	public function is_available(): bool
	{
		global $wpdb;

		return defined('SLIM_SEO_VER')
			|| get_option('slim_seo') !== false
			|| $this->has_meta('slim_seo')
			|| $this->table_exists($wpdb->prefix . 'slim_seo_redirects');
	}

	public function counts(): array
	{
		global $wpdb;

		$redirects = 0;

		if ($this->table_exists($wpdb->prefix . 'slim_seo_redirects')) {
			$redirects = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}slim_seo_redirects");
		}

		return [
			'posts'     => $this->count_meta('slim_seo'),
			'terms'     => $this->count_term_meta('slim_seo'),
			'redirects' => $redirects,
		];
	}

	public function import_posts(int $offset, int $limit, bool $overwrite): array
	{
		$ids = $this->post_ids($offset, $limit);
		$imported = 0;
		$skipped  = 0;

		foreach ($ids as $post_id) {
			$data = $this->from_bundle(get_post_meta($post_id, 'slim_seo', true));

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
			$data = $this->from_bundle(get_term_meta((int) $term->term_id, 'slim_seo', true));

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

		$table = $wpdb->prefix . 'slim_seo_redirects';

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
			$from = (string) ($row['from'] ?? $row['from_url'] ?? '');

			if ($from === '' || $manager->exists_from_url($from)) {
				continue;
			}

			$type = (int) ($row['type'] ?? $row['redirect_type'] ?? 301);
			$cond = (string) ($row['condition'] ?? $row['match_type'] ?? 'exact');
			$match = in_array($cond, ['regex', 'exact-match', 'exact'], true) && $cond === 'regex' ? 'regex' : 'exact';

			$ok = $manager->insert([
				'from_url'            => $from,
				'to_url'              => (string) ($row['to'] ?? $row['to_url'] ?? ''),
				'redirect_type'       => in_array($type, [301, 302, 307, 410, 451], true) ? $type : 301,
				'match_type'          => $match,
				'note'                => __('Imported from Slim SEO', 'mihdan-index-now'),
				'ignore_query_string' => empty($row['ignoreParameters']) ? 1 : (int) (bool) $row['ignoreParameters'],
				'enabled'             => isset($row['enable']) ? (int) (bool) $row['enable'] : 1,
			]);

			if ($ok) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * @param mixed $bundle
	 * @return array<string,mixed>
	 */
	private function from_bundle($bundle): array
	{
		if (! is_array($bundle) || $bundle === []) {
			return [];
		}

		$data = [
			'title'         => $this->convert((string) ($bundle['title'] ?? '')),
			'description'   => $this->convert((string) ($bundle['description'] ?? '')),
			'canonical'     => (string) ($bundle['canonical'] ?? $bundle['canonical_url'] ?? ''),
			'og_image'      => (string) ($bundle['facebook_image'] ?? $bundle['og_image'] ?? ''),
			'x_image'       => (string) ($bundle['twitter_image'] ?? ''),
			'og_title'      => $this->convert((string) ($bundle['facebook_title'] ?? '')),
			'og_description'=> $this->convert((string) ($bundle['facebook_description'] ?? '')),
			'x_title'       => $this->convert((string) ($bundle['twitter_title'] ?? '')),
			'x_description' => $this->convert((string) ($bundle['twitter_description'] ?? '')),
		];

		$noindex = $bundle['noindex'] ?? $bundle['robots_index'] ?? null;

		if ($noindex === 1 || $noindex === '1' || $noindex === true || $noindex === 'noindex') {
			$data['robots_index'] = 'noindex';
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

	private function count_term_meta(string $key): int
	{
		global $wpdb;

		return (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value <> ''",
			$key
		));
	}
}
