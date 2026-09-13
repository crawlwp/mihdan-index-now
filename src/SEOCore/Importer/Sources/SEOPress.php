<?php

namespace Mihdan\IndexNow\SEOCore\Importer\Sources;

use Mihdan\IndexNow\SEOCore\Importer\Source;
use Mihdan\IndexNow\SEOCore\Importer\Writer;
use Mihdan\IndexNow\SEOCore\Redirects\RedirectsManager;
use Mihdan\IndexNow\SEOCore\TitleMeta\Entities;
use Mihdan\IndexNow\SEOCore\TitleMeta\Variables;

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
		return [
			'posts'     => $this->count_meta('_seopress_titles_title') + $this->count_meta('_seopress_titles_desc'),
			'terms'     => $this->count_term_meta('_seopress_titles_title'),
			'users'     => $this->count_user_meta('_seopress_titles_title') + $this->count_user_meta('seopress_titles_title'),
			'redirects' => $this->count_redirects(),
		];
	}

	public function import_posts(int $offset, int $limit, bool $overwrite): array
	{
		$ids      = $this->post_ids($offset, $limit);
		$imported = 0;
		$skipped  = 0;

		foreach ($ids as $post_id) {
			$data = $this->object_payload('post', $post_id);

			if ($data === []) {
				continue;
			}

			Writer::write_post($post_id, $data, $overwrite) ? $imported++ : $skipped++;
		}

		return $this->batch_result($imported, $skipped, $offset, count($ids), $limit);
	}

	public function import_terms(int $offset, int $limit, bool $overwrite): array
	{
		$terms    = $this->terms($offset, $limit);
		$imported = 0;
		$skipped  = 0;

		foreach ($terms as $term) {
			$data = $this->object_payload('term', (int) $term->term_id);

			if ($data === []) {
				continue;
			}

			Writer::write_term((int) $term->term_id, $data, $overwrite) ? $imported++ : $skipped++;
		}

		return $this->batch_result($imported, $skipped, $offset, count($terms), $limit);
	}

	public function import_users(int $offset, int $limit, bool $overwrite): array
	{
		$ids      = $this->user_ids($offset, $limit);
		$imported = 0;
		$skipped  = 0;

		foreach ($ids as $user_id) {
			$data = array_filter([
				'title'       => $this->convert($this->user_meta($user_id, ['_seopress_titles_title', 'seopress_titles_title'])),
				'description' => $this->convert($this->user_meta($user_id, ['_seopress_titles_desc', 'seopress_titles_desc'])),
			], static function ($v) {
				return $v !== '' && $v !== null;
			});

			$noindex = $this->user_meta($user_id, ['_seopress_robots_index', 'seopress_robots_index']);

			if ($noindex === 'yes' || $noindex === '1') {
				$data['robots_index'] = 'noindex';
			}

			if ($data === []) {
				continue;
			}

			Writer::write_user($user_id, $data, $overwrite) ? $imported++ : $skipped++;
		}

		return $this->batch_result($imported, $skipped, $offset, count($ids), $limit);
	}

	public function import_redirects(int $offset, int $limit): array
	{
		if (! post_type_exists('seopress_404')) {
			return $this->batch_result(0, 0, $offset, 0, $limit);
		}

		$posts = get_posts([
			'post_type'        => 'seopress_404',
			'post_status'      => 'any',
			'posts_per_page'   => $limit,
			'offset'           => $offset,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
		]);

		if (! is_array($posts) || $posts === []) {
			return $this->batch_result(0, 0, $offset, 0, $limit);
		}

		$this->prime_meta('post', array_map(static function ($post) {
			return (int) $post->ID;
		}, $posts));

		$manager  = new RedirectsManager();
		$imported = 0;
		$skipped  = 0;

		foreach ($posts as $post) {
			$to = (string) get_post_meta($post->ID, '_seopress_redirections_value', true);

			$from = $post->post_title;

			if ($from === '' || $manager->exists_from_url($from)) {
				$skipped++;
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

			$ok ? $imported++ : $skipped++;
		}

		return $this->batch_result($imported, $skipped, $offset, count($posts), $limit);
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function settings_payload(): array
	{
		$titles = get_option('seopress_titles_option_name');
		$social = get_option('seopress_social_option_name');

		if (! is_array($titles)) {
			$titles = [];
		}

		if (! is_array($social)) {
			$social = [];
		}

		if ($titles === [] && $social === []) {
			return [];
		}

		$singles  = is_array($titles['seopress_titles_single_titles'] ?? null) ? $titles['seopress_titles_single_titles'] : [];
		$archives = is_array($titles['seopress_titles_archive_titles'] ?? null) ? $titles['seopress_titles_archive_titles'] : [];
		$taxes    = is_array($titles['seopress_titles_tax_titles'] ?? null) ? $titles['seopress_titles_tax_titles'] : [];

		$entities = [];

		$entities['home'] = $this->entity_fields([
			'title'       => $titles['seopress_titles_home_site_title'] ?? '',
			'description' => $titles['seopress_titles_home_site_desc'] ?? '',
		]);

		foreach (Entities::post_types() as $post_type) {
			$fields = $this->entity_fields(is_array($singles[ $post_type->name ] ?? null) ? $singles[ $post_type->name ] : []);

			$archive = is_array($archives[ $post_type->name ] ?? null) ? $archives[ $post_type->name ] : [];

			if (($archive['title'] ?? '') !== '') {
				$fields['archive_title'] = $this->convert((string) $archive['title']);
			}

			if (($archive['description'] ?? '') !== '') {
				$fields['archive_description'] = $this->convert((string) $archive['description']);
			}

			if (isset($archive['noindex'])) {
				$fields['archive_noindex'] = ! empty($archive['noindex']) ? 'on' : 'off';
			}

			$entities[ Entities::post_type_key($post_type->name) ] = $fields;
		}

		foreach (Entities::taxonomies() as $taxonomy) {
			$entities[ Entities::taxonomy_key($taxonomy->name) ] = $this->entity_fields(
				is_array($taxes[ $taxonomy->name ] ?? null) ? $taxes[ $taxonomy->name ] : []
			);
		}

		$entities['author'] = $this->entity_fields([
			'title'       => $titles['seopress_titles_archives_author_title'] ?? '',
			'description' => $titles['seopress_titles_archives_author_desc'] ?? '',
			'noindex'     => $titles['seopress_titles_archives_author_noindex'] ?? null,
		]);

		$entities['date'] = $this->entity_fields([
			'title'       => $titles['seopress_titles_archives_date_title'] ?? '',
			'description' => $titles['seopress_titles_archives_date_desc'] ?? '',
			'noindex'     => $titles['seopress_titles_archives_date_noindex'] ?? null,
		]);

		$entities['search'] = $this->entity_fields([
			'title'       => $titles['seopress_titles_archives_search_title'] ?? '',
			'description' => $titles['seopress_titles_archives_search_desc'] ?? '',
			'noindex'     => $titles['seopress_titles_archives_search_noindex'] ?? null,
		]);

		return array_filter([
			'separator' => $this->separator((string) ($titles['seopress_titles_sep'] ?? '')),
			'entities'  => array_filter($entities),
			'site_info' => $this->site_info($social),
			'social'    => $this->social($social),
			'sitemap'   => $this->sitemap(),
		]);
	}

	/**
	 * @param array<string,mixed> $node
	 *
	 * @return array<string,string>
	 */
	private function entity_fields(array $node): array
	{
		$fields = [];

		$title = (string) ($node['title'] ?? '');
		$desc  = (string) ($node['description'] ?? '');

		if ($title !== '') {
			$fields['title'] = $this->convert($title);
		}

		if ($desc !== '') {
			$fields['description'] = $this->convert($desc);
		}

		if (isset($node['noindex']) && $node['noindex'] !== null) {
			$fields['noindex'] = ! empty($node['noindex']) ? 'on' : 'off';
		}

		if (isset($node['nofollow']) && $node['nofollow'] !== null) {
			$fields['nofollow'] = ! empty($node['nofollow']) ? 'on' : 'off';
		}

		return $fields;
	}

	/**
	 * @param array<string,mixed> $social
	 *
	 * @return array<string,mixed>
	 */
	private function site_info(array $social): array
	{
		$type = (string) ($social['seopress_social_knowledge_type'] ?? '');

		$info = [
			'site_type' => strtolower($type) === 'person' ? 'person' : 'organization',
			'site_name' => (string) ($social['seopress_social_knowledge_name'] ?? ''),
		];

		return array_filter($info, static function ($v) {
			return $v !== '';
		});
	}

	/**
	 * @param array<string,mixed> $social
	 *
	 * @return array<string,mixed>
	 */
	private function social(array $social): array
	{
		$handle = (string) ($social['seopress_social_accounts_twitter'] ?? '');

		$fields = [
			'facebook_author' => (string) ($social['seopress_social_accounts_facebook'] ?? ''),
			'twitter_creator' => $handle === '' ? '' : '@' . ltrim($handle, '@'),
			'fb_app_id'       => (string) ($social['seopress_social_facebook_app_id'] ?? ''),
		];

		if (! empty($social['seopress_social_twitter_card_summary'])) {
			$fields['twitter_card'] = 'summary';
		}

		return array_filter($fields, static function ($v) {
			return $v !== '';
		});
	}

	/**
	 * @return array<string,string>
	 */
	private function sitemap(): array
	{
		$sitemap = get_option('seopress_xml_sitemap_option_name');

		if (! is_array($sitemap)) {
			return [];
		}

		$fields = [];

		if (! empty($sitemap['seopress_xml_sitemap_news_enable'])) {
			$fields['news_enabled'] = 'on';
		}

		if (! empty($sitemap['seopress_xml_sitemap_video_enable'])) {
			$fields['video_enabled'] = 'on';
		}

		return $fields;
	}

	private function separator(string $stored): string
	{
		$stored = trim($stored);

		return array_key_exists($stored, Variables::separator_choices()) ? $stored : '';
	}

	/**
	 * First non-empty value among several possible user meta keys.
	 *
	 * @param string[] $keys
	 */
	private function user_meta(int $user_id, array $keys): string
	{
		foreach ($keys as $key) {
			$value = (string) get_user_meta($user_id, $key, true);

			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}

	private function count_redirects(): int
	{
		if (! post_type_exists('seopress_404')) {
			return 0;
		}

		$q = new \WP_Query([
			'post_type'      => 'seopress_404',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		]);

		return (int) $q->found_posts;
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
