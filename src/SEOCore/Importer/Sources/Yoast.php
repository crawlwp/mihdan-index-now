<?php

namespace Mihdan\IndexNow\SEOCore\Importer\Sources;

use Mihdan\IndexNow\SEOCore\Importer\Source;
use Mihdan\IndexNow\SEOCore\Importer\Writer;
use Mihdan\IndexNow\SEOCore\Redirects\RedirectsManager;

class Yoast extends Source
{
	public function id(): string
	{
		return 'yoast';
	}

	public function label(): string
	{
		return 'Yoast SEO';
	}

	public function is_available(): bool
	{
		return get_option('wpseo') !== false
			|| defined('WPSEO_VERSION')
			|| $this->has_meta('_yoast_wpseo_title');
	}

	public function counts(): array
	{
		$redirects = get_option('wpseo-premium-redirects-base', []);

		return [
			'posts'     => $this->count_meta('_yoast_wpseo_title') + $this->count_meta('_yoast_wpseo_metadesc'),
			'terms'     => $this->count_yoast_terms(),
			'redirects' => is_array($redirects) ? count($redirects) : 0,
		];
	}

	public function import_posts(int $offset, int $limit, bool $overwrite): array
	{
		$ids = $this->post_ids($offset, $limit);
		$imported = 0;
		$skipped  = 0;

		foreach ($ids as $post_id) {
			$data = $this->post_payload($post_id);

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
		$all = $this->yoast_terms();
		$slice = array_slice($all, $offset, $limit, true);
		$imported = 0;
		$skipped  = 0;

		foreach ($slice as $term_id => $meta) {
			if (! is_array($meta)) {
				continue;
			}

			$data = [
				'title'       => $this->convert((string) ($meta['wpseo_title'] ?? '')),
				'description' => $this->convert((string) ($meta['wpseo_desc'] ?? '')),
				'canonical'   => (string) ($meta['wpseo_canonical'] ?? ''),
				'og_image'    => (string) ($meta['wpseo_opengraph-image'] ?? ''),
				'x_image'     => (string) ($meta['wpseo_twitter-image'] ?? ''),
				'robots_index'=> (! empty($meta['wpseo_noindex']) && $meta['wpseo_noindex'] === 'noindex') ? 'noindex' : '',
			];

			$data = array_filter($data, static function ($v) {
				return $v !== '' && $v !== null;
			});

			if ($data === []) {
				continue;
			}

			Writer::write_term((int) $term_id, $data, $overwrite) ? $imported++ : $skipped++;
		}

		return [
			'imported'    => $imported,
			'skipped'     => $skipped,
			'done'        => count($slice) < $limit,
			'next_offset' => $offset + count($slice),
		];
	}

	public function import_redirects(): int
	{
		$results = get_option('wpseo-premium-redirects-base', []);

		if (! is_array($results) || $results === []) {
			return 0;
		}

		$manager = new RedirectsManager();
		$count   = 0;

		foreach ($results as $row) {
			if (! is_array($row) || empty($row['origin'])) {
				continue;
			}

			$from = (string) $row['origin'];

			if ($manager->exists_from_url($from)) {
				continue;
			}

			$type = (int) ($row['type'] ?? 301);
			$ok   = $manager->insert([
				'from_url'             => $from,
				'to_url'               => (string) ($row['url'] ?? ''),
				'redirect_type'        => in_array($type, [301, 302, 307, 410, 451], true) ? $type : 301,
				'match_type'           => (($row['format'] ?? '') === 'regex') ? 'regex' : 'exact',
				'note'                 => __('Imported from Yoast SEO', 'mihdan-index-now'),
				'ignore_query_string'  => 1,
				'enabled'              => 1,
			]);

			if ($ok) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function post_payload(int $post_id): array
	{
		$title = $this->convert((string) get_post_meta($post_id, '_yoast_wpseo_title', true));
		$desc  = $this->convert((string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true));
		$kw    = (string) get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
		$canon = (string) get_post_meta($post_id, '_yoast_wpseo_canonical', true);
		$noindex = (int) get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
		$nofollow = (int) get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true);

		$data = [
			'title'            => $title,
			'description'      => $desc,
			'focus_keyword'    => $kw,
			'canonical'        => $canon,
			'og_title'         => $this->convert((string) get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true)),
			'og_description'   => $this->convert((string) get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true)),
			'og_image'         => (string) get_post_meta($post_id, '_yoast_wpseo_opengraph-image-id', true)
				?: (string) get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true),
			'x_title'          => $this->convert((string) get_post_meta($post_id, '_yoast_wpseo_twitter-title', true)),
			'x_description'    => $this->convert((string) get_post_meta($post_id, '_yoast_wpseo_twitter-description', true)),
			'x_image'          => (string) get_post_meta($post_id, '_yoast_wpseo_twitter-image', true),
			'primary_category' => (int) get_post_meta($post_id, '_yoast_wpseo_primary_category', true),
			'cornerstone'      => (string) get_post_meta($post_id, '_yoast_wpseo_is_cornerstone', true),
			'redirect_url'     => (string) get_post_meta($post_id, '_yoast_wpseo_redirect', true),
		];

		if ($noindex === 1) {
			$data['robots_index'] = 'noindex';
		}

		if ($nofollow === 1) {
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

	private function count_yoast_terms(): int
	{
		return count($this->yoast_terms());
	}

	/**
	 * @return array<int,array>
	 */
	private function yoast_terms(): array
	{
		$option = get_option('wpseo_taxonomy_meta');

		if (! is_array($option)) {
			return [];
		}

		$flat = [];

		foreach ($option as $terms) {
			if (! is_array($terms)) {
				continue;
			}

			foreach ($terms as $term_id => $meta) {
				$flat[(int) $term_id] = $meta;
			}
		}

		return $flat;
	}
}
