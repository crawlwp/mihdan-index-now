<?php

namespace Mihdan\IndexNow\SEOCore\CoreSettings;

use Mihdan\IndexNow\SEOCore\TitleMeta\Entities;
use Mihdan\IndexNow\SEOCore\TitleMeta\Variables;
use Mihdan\IndexNow\Utils;

/**
 * Loads the live preview script for the Title & Meta settings screens.
 */
class Assets
{
	public function __construct()
	{
		add_action('admin_enqueue_scripts', [$this, 'enqueue']);
	}

	public function enqueue(string $hook): void
	{
		if (strpos($hook, Utils::get_plugin_slug()) === false) {
			return;
		}

		$assets_url = CRAWLWP_PLUGIN_URL . 'src/SEOCore/CoreSettings/assets/';
		$version    = CRAWLWP_VERSION;

		wp_enqueue_style(
			'crawlwp-title-meta',
			$assets_url . 'crawlwp-title-meta.css',
			[],
			$version
		);

		wp_enqueue_media();

		wp_enqueue_script(
			'crawlwp-title-meta',
			$assets_url . 'crawlwp-title-meta.js',
			[],
			$version,
			true
		);

		wp_localize_script('crawlwp-title-meta', 'crawlwpTitleMeta', [
			'separator' => Variables::separator(),
			'limits'    => [
				'title'       => ['min' => 30, 'max' => 60],
				'description' => ['min' => 50, 'max' => 160],
			],
			'variables' => $this->variables(),
			'samples'   => $this->samples(),
			'i18n'      => [
				'previewLabel'     => __('Preview:', 'mihdan-index-now'),
				/* translators: %s: number of characters. */
				'charCount'        => __('Character count: %s.', 'mihdan-index-now'),
				'insertVariable'   => __('Insert variable', 'mihdan-index-now'),
				'searchVariables'  => __('Search variables…', 'mihdan-index-now'),
				'noVariables'      => __('No matching variables.', 'mihdan-index-now'),
				'emptyPreview'     => __('Nothing will be output.', 'mihdan-index-now'),
			],
		]);
	}

	/**
	 * Variable reference for the insert-variable dropdown.
	 */
	private function variables(): array
	{
		$groups = [];

		foreach (Variables::definitions() as $group) {
			$items = [];

			foreach ($group['variables'] as $token => $description) {
				$items[] = [
					'token' => $token,
					'desc'  => $description,
				];
			}

			$groups[] = [
				'label' => $group['label'],
				'items' => $items,
			];
		}

		return $groups;
	}

	/**
	 * Representative values so the preview reflects real content.
	 */
	private function samples(): array
	{
		$samples = [
			'global' => [
				'sep'                  => Variables::separator(),
				'page'                 => '',
				'site.title'           => get_bloginfo('name'),
				'site.description'     => get_bloginfo('description'),
				'site.url'             => home_url('/'),
				'current.year'         => gmdate('Y'),
				'current.month'        => gmdate('F'),
				'current.date'         => wp_date((string) get_option('date_format')) ?: gmdate('Y-m-d'),
				'date.archive_title'   => gmdate('F Y'),
				'search.query'         => __('example search', 'mihdan-index-now'),
				'search.results_count' => '12',
			],
		];

		foreach (Entities::post_types() as $post_type) {
			$samples[Entities::post_type_key($post_type->name)] = $this->post_type_samples($post_type);
		}

		foreach (Entities::taxonomies() as $taxonomy) {
			$samples[Entities::taxonomy_key($taxonomy->name)] = $this->taxonomy_samples($taxonomy);
		}

		$samples['author'] = $this->author_samples();

		return $samples;
	}

	private function post_type_samples(\WP_Post_Type $post_type): array
	{
		$samples = [
			'post_type.name'        => (string) $post_type->labels->singular_name,
			'post_type.plural_name' => (string) $post_type->label,
			'post_type.description' => (string) $post_type->description,
		];

		$posts = get_posts([
			'post_type'          => $post_type->name,
			'post_status'        => 'publish',
			'posts_per_page'     => 1,
			'orderby'            => 'date',
			'order'              => 'DESC',
			'no_found_rows'      => true,
			'suppress_filters'   => false,
			'ignore_sticky_posts' => true,
		]);

		if (empty($posts)) {
			$samples['post.title']            = (string) $post_type->labels->singular_name;
			$samples['post.auto_description'] = '';

			return $samples;
		}

		$post = $posts[0];

		$context = ['post' => $post, 'post_type' => $post_type];

		foreach (['title', 'auto_description', 'excerpt', 'author', 'category', 'tag', 'date', 'modified', 'url'] as $key) {
			$samples['post.' . $key] = Variables::replace('{{ post.' . $key . ' }}', $context);
		}

		return $samples;
	}

	private function taxonomy_samples(\WP_Taxonomy $taxonomy): array
	{
		$terms = get_terms([
			'taxonomy'   => $taxonomy->name,
			'number'     => 1,
			'hide_empty' => false,
		]);

		if (is_wp_error($terms) || empty($terms)) {
			return [
				'term.title'            => (string) $taxonomy->labels->singular_name,
				'term.auto_description' => '',
			];
		}

		$context = ['term' => $terms[0]];
		$samples = [];

		foreach (['title', 'auto_description', 'description', 'count'] as $key) {
			$samples['term.' . $key] = Variables::replace('{{ term.' . $key . ' }}', $context);
		}

		return $samples;
	}

	private function author_samples(): array
	{
		$user = wp_get_current_user();

		if (! $user instanceof \WP_User || ! $user->exists()) {
			return [];
		}

		$context = ['user' => $user];
		$samples = [];

		foreach (['display_name', 'auto_description', 'first_name', 'last_name', 'posts_count'] as $key) {
			$samples['author.' . $key] = Variables::replace('{{ author.' . $key . ' }}', $context);
		}

		return $samples;
	}
}
