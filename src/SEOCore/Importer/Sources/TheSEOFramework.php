<?php

namespace Mihdan\IndexNow\SEOCore\Importer\Sources;

use Mihdan\IndexNow\SEOCore\Importer\Source;
use Mihdan\IndexNow\SEOCore\Importer\Writer;

class TheSEOFramework extends Source
{
	public function id(): string
	{
		return 'tsf';
	}

	public function label(): string
	{
		return 'The SEO Framework';
	}

	public function is_available(): bool
	{
		return defined('THE_SEO_FRAMEWORK_VERSION')
			|| get_option('autodescription-site-settings') !== false
			|| $this->has_meta('_genesis_title')
			|| $this->has_meta('_genesis_description');
	}

	public function counts(): array
	{
		return [
			'posts'     => $this->count_meta('_genesis_title') + $this->count_meta('_genesis_description'),
			'terms'     => $this->count_term_meta('autodescription-term-settings'),
			'redirects' => 0,
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
		$terms = $this->terms($offset, $limit);
		$imported = 0;
		$skipped  = 0;

		foreach ($terms as $term) {
			$meta = get_term_meta((int) $term->term_id, 'autodescription-term-settings', true);

			if (! is_array($meta) || $meta === []) {
				continue;
			}

			$data = [
				'title'       => (string) ($meta['doctitle'] ?? $meta['title'] ?? ''),
				'description' => (string) ($meta['description'] ?? ''),
				'canonical'   => (string) ($meta['canonical'] ?? ''),
				'og_image'    => (string) ($meta['social_image_url'] ?? ''),
				'og_title'    => (string) ($meta['og_title'] ?? ''),
				'x_title'     => (string) ($meta['twitter_title'] ?? ''),
			];

			if (! empty($meta['noindex'])) {
				$data['robots_index'] = 'noindex';
			}

			if (! empty($meta['nofollow'])) {
				$data['robots_follow'] = 'nofollow';
			}

			$data = array_filter($data, static function ($v) {
				return $v !== '' && $v !== null;
			});

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

	/**
	 * @return array<string,mixed>
	 */
	private function post_payload(int $post_id): array
	{
		$noindex  = (int) get_post_meta($post_id, '_genesis_noindex', true);
		$nofollow = (int) get_post_meta($post_id, '_genesis_nofollow', true);

		$data = [
			'title'         => (string) get_post_meta($post_id, '_genesis_title', true),
			'description'   => (string) get_post_meta($post_id, '_genesis_description', true),
			'canonical'     => (string) get_post_meta($post_id, '_genesis_canonical_uri', true),
			'og_image'      => (string) get_post_meta($post_id, '_social_image_id', true)
				?: (string) get_post_meta($post_id, '_social_image_url', true),
			'og_title'      => (string) get_post_meta($post_id, '_open_graph_title', true),
			'og_description'=> (string) get_post_meta($post_id, '_open_graph_description', true),
			'x_title'       => (string) get_post_meta($post_id, '_twitter_title', true),
			'x_description' => (string) get_post_meta($post_id, '_twitter_description', true),
			'redirect_url'  => (string) get_post_meta($post_id, '_genesis_redirect', true)
				?: (string) get_post_meta($post_id, '_redirect_url', true),
		];

		if ($noindex === 1) {
			$data['robots_index'] = 'noindex';
		}

		if ($nofollow === 1) {
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

	private function count_term_meta(string $key): int
	{
		global $wpdb;

		return (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value <> ''",
			$key
		));
	}
}
