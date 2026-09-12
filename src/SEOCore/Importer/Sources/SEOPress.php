<?php

namespace Mihdan\IndexNow\SEOCore\Importer\Sources;

use Mihdan\IndexNow\SEOCore\Importer\Source;
use Mihdan\IndexNow\SEOCore\Importer\Writer;
use Mihdan\IndexNow\SEOCore\Redirects\RedirectsManager;

class SEOPress extends Source
{
	public function id(): string
	{
		return 'seopress';
	}

	public function label(): string
	{
		return 'SEOPress';
	}

	public function is_available(): bool
	{
		return defined('SEOPRESS_VERSION')
			|| defined('SEOPRESS_PRO_VERSION')
			|| get_option('seopress_titles_option_name') !== false
			|| $this->has_meta('_seopress_titles_title');
	}

	public function counts(): array
	{
		$redirects = 0;

		if (post_type_exists('seopress_404')) {
			$q = new \WP_Query([
				'post_type'      => 'seopress_404',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]);
			$redirects = (int) $q->found_posts;
		}

		return [
			'posts'     => $this->count_meta('_seopress_titles_title') + $this->count_meta('_seopress_titles_desc'),
			'terms'     => $this->count_term_meta('_seopress_titles_title'),
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
		if (! post_type_exists('seopress_404')) {
			return 0;
		}

		$posts = get_posts([
			'post_type'      => 'seopress_404',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		]);

		if (! is_array($posts) || $posts === []) {
			return 0;
		}

		$manager = new RedirectsManager();
		$count   = 0;

		foreach ($posts as $post) {
			$to = (string) get_post_meta($post->ID, '_seopress_redirections_value', true);

			if ($to === '' && $post->post_title === '') {
				continue;
			}

			$from = $post->post_title;

			if ($from === '' || $manager->exists_from_url($from)) {
				continue;
			}

			$type   = (int) get_post_meta($post->ID, '_seopress_redirections_type', true);
			$regex  = (string) get_post_meta($post->ID, '_seopress_redirections_enabled_regex', true);
			$on     = (string) get_post_meta($post->ID, '_seopress_redirections_enabled', true);

			$ok = $manager->insert([
				'from_url'            => $from,
				'to_url'              => $to,
				'redirect_type'       => in_array($type, [301, 302, 307, 410, 451], true) ? $type : 301,
				'match_type'          => $regex !== '' ? 'regex' : 'exact',
				'note'                => __('Imported from SEOPress', 'mihdan-index-now'),
				'ignore_query_string' => 1,
				'enabled'             => ($on !== '' && $post->post_status === 'publish') ? 1 : 0,
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
	private function object_payload(string $type, int $id): array
	{
		$get = $type === 'term' ? 'get_term_meta' : 'get_post_meta';

		$noindex  = $get($id, '_seopress_robots_index', true);
		$nofollow = $get($id, '_seopress_robots_follow', true);

		$data = [
			'title'         => $this->convert((string) $get($id, '_seopress_titles_title', true)),
			'description'   => $this->convert((string) $get($id, '_seopress_titles_desc', true)),
			'focus_keyword' => (string) $get($id, '_seopress_analysis_target_kw', true),
			'canonical'     => (string) $get($id, '_seopress_robots_canonical', true),
			'og_title'      => $this->convert((string) $get($id, '_seopress_social_fb_title', true)),
			'og_description'=> $this->convert((string) $get($id, '_seopress_social_fb_desc', true)),
			'og_image'      => (string) $get($id, '_seopress_social_fb_img', true),
			'x_title'       => $this->convert((string) $get($id, '_seopress_social_twitter_title', true)),
			'x_description' => $this->convert((string) $get($id, '_seopress_social_twitter_desc', true)),
			'x_image'       => (string) $get($id, '_seopress_social_twitter_img', true),
		];

		if ($noindex === 'yes' || $noindex === true || $noindex === '1') {
			$data['robots_index'] = 'noindex';
		}

		if ($nofollow === 'yes' || $nofollow === true || $nofollow === '1') {
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
