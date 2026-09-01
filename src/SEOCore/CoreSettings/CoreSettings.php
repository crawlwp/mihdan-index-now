<?php

namespace Mihdan\IndexNow\SEOCore\CoreSettings;

use Mihdan\IndexNow\SEOCore\TitleMeta\Entities;
use Mihdan\IndexNow\SEOCore\TitleMeta\Options;
use Mihdan\IndexNow\SEOCore\TitleMeta\Variables;
use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\Views\WPOSA;

class CoreSettings
{

	public function __construct()
	{
		add_action('crawlwp_setup_fields', [$this, 'settings_fields'], 10, 2);
	}

	public function settings_fields(WPOSA $wposa, $settingsInstance)
	{
		if ($wposa->get_active_header_menu() === Utils::get_plugin_prefix() . '_title_meta') {

			do_action('crawlwp_before_title_meta_settings_fields', $wposa);

			foreach (Entities::all() as $entity) {
				$this->register_entity($wposa, $entity);
			}

			do_action('crawlwp_after_title_meta_settings_fields', $wposa);
		}
	}

	/**
	 * Register one sidebar item, its section and every field on that screen.
	 */
	private function register_entity(WPOSA $wposa, array $entity): void
	{
		$key     = $entity['key'];
		$section = Options::section_id($key);
		$groups  = Entities::groups();

		$wposa->add_section([
			'header_menu_id'  => 'title_meta',
			'id'              => $section,
			'title'           => $entity['label'],
			'nav_group'       => $entity['group'],
			'nav_group_label' => $groups[$entity['group']] ?? '',
		]);

		if ($key === 'home') {
			$this->add_separator_field($wposa, $section);
		} else {
			$this->add_noindex_field($wposa, $section, $entity);
		}

		$this->add_heading($wposa, $section, 'heading_main', $entity['heading']);
		$this->add_template_fields($wposa, $section, $entity, '');
		$this->add_social_image_fields($wposa, $section, $entity, '');

		$this->add_social_override_fields($wposa, $section, $entity);
		$this->add_robots_fields($wposa, $section, $entity);

		if ($entity['type'] === Entities::TYPE_POST_TYPE) {
			$this->add_schema_field($wposa, $section, $entity);

			if (! empty($entity['archive'])) {
				$this->add_archive_fields($wposa, $section, $entity);
			}
		}
	}

	/**
	 * Site-wide title separator, surfaced on the Homepage screen.
	 */
	private function add_separator_field(WPOSA $wposa, string $section): void
	{
		$wposa->add_field($section, [
			'id'      => 'separator',
			'type'    => 'select',
			'name'    => __('Title separator', 'mihdan-index-now'),
			'options' => Variables::separator_choices(),
			'default' => '-',
			'desc'    => esc_html__('The character used wherever the {{ sep }} variable appears in a title.', 'mihdan-index-now'),
		]);
	}

	/**
	 * "Hide from search results" toggle.
	 */
	private function add_noindex_field(WPOSA $wposa, string $section, array $entity): void
	{
		$wposa->add_field($section, [
			'id'      => 'noindex',
			'type'    => 'switch',
			'name'    => __('Hide from search results', 'mihdan-index-now'),
			'default' => Entities::default_value($entity['key'], 'noindex', 'off'),
			'desc'    => $this->description($this->noindex_description($entity)),
		]);
	}

	/**
	 * Meta title + meta description template fields.
	 *
	 * @param string $prefix Field id prefix, e.g. `archive_`.
	 */
	private function add_template_fields(WPOSA $wposa, string $section, array $entity, string $prefix): void
	{
		$wposa->add_field($section, [
			'id'         => $prefix . 'title',
			'type'       => 'text',
			'name'       => __('Meta title', 'mihdan-index-now'),
			'default'    => Entities::default_value($entity['key'], $prefix . 'title'),
			'attributes' => $this->template_attributes($entity['key'], $prefix . 'title'),
		]);

		$wposa->add_field($section, [
			'id'         => $prefix . 'description',
			'type'       => 'textarea',
			'name'       => __('Meta description', 'mihdan-index-now'),
			'default'    => Entities::default_value($entity['key'], $prefix . 'description'),
			'rows'       => 4,
			'attributes' => $this->template_attributes($entity['key'], $prefix . 'description'),
		]);
	}

	/**
	 * Facebook + X share images.
	 */
	private function add_social_image_fields(WPOSA $wposa, string $section, array $entity, string $prefix): void
	{
		$featured_note = $entity['type'] === Entities::TYPE_POST_TYPE
			? ' ' . esc_html__('Leave empty to use the featured image.', 'mihdan-index-now')
			: '';

		$wposa->add_field($section, [
			'id'   => $prefix . 'og_image',
			'type' => 'image',
			'name' => __('Facebook image', 'mihdan-index-now'),
			'desc' => esc_html__('Recommended size: 1200x630 px. Should have a 1.91:1 aspect ratio with width of at least 600 px.', 'mihdan-index-now') . $featured_note,
		]);

		$wposa->add_field($section, [
			'id'   => $prefix . 'x_image',
			'type' => 'image',
			'name' => __('X image', 'mihdan-index-now'),
			'desc' => esc_html__('Recommended size: 1200x600 px. Should have a 2:1 aspect ratio with width between 300 px and 4096 px.', 'mihdan-index-now') . $featured_note,
		]);
	}

	/**
	 * Unified social title/description overrides (used for both OG and X/Twitter).
	 */
	private function add_social_override_fields(WPOSA $wposa, string $section, array $entity): void
	{
		$this->add_heading(
			$wposa,
			$section,
			'heading_social',
			__('Social overrides', 'mihdan-index-now'),
			__('Leave these empty to reuse the meta title and meta description above. Applied to both Facebook (OG) and X/Twitter cards.', 'mihdan-index-now')
		);

		$wposa->add_field($section, [
			'id'         => 'social_title',
			'type'       => 'text',
			'name'       => __('Social title', 'mihdan-index-now'),
			'attributes' => $this->template_attributes($entity['key'], 'social_title'),
		]);

		$wposa->add_field($section, [
			'id'         => 'social_description',
			'type'       => 'textarea',
			'name'       => __('Social description', 'mihdan-index-now'),
			'rows'       => 3,
			'attributes' => $this->template_attributes($entity['key'], 'social_description'),
		]);
	}

	/**
	 * Advanced robots directives.
	 */
	private function add_robots_fields(WPOSA $wposa, string $section, array $entity): void
	{
		$this->add_heading(
			$wposa,
			$section,
			'heading_robots',
			__('Advanced robots directives', 'mihdan-index-now')
		);

		$toggles = [
			'nofollow'     => [
				'name' => __('No follow', 'mihdan-index-now'),
				'desc' => __('Tell search engines not to follow links on these pages.', 'mihdan-index-now'),
			],
			'noarchive'    => [
				'name' => __('No archive', 'mihdan-index-now'),
				'desc' => __('Prevent search engines from showing a cached copy of these pages.', 'mihdan-index-now'),
			],
			'nosnippet'    => [
				'name' => __('No snippet', 'mihdan-index-now'),
				'desc' => __('Prevent search engines from showing a text snippet for these pages.', 'mihdan-index-now'),
			],
			'noimageindex' => [
				'name' => __('No image index', 'mihdan-index-now'),
				'desc' => __('Prevent search engines from indexing images on these pages.', 'mihdan-index-now'),
			],
			'notranslate'  => [
				'name' => __('No translate', 'mihdan-index-now'),
				'desc' => __('Prevent search engines from offering a translation of these pages.', 'mihdan-index-now'),
			],
		];

		foreach ($toggles as $id => $toggle) {
			$wposa->add_field($section, [
				'id'      => $id,
				'type'    => 'switch',
				'name'    => $toggle['name'],
				'default' => 'off',
				'desc'    => $this->description($toggle['desc']),
			]);
		}

		$wposa->add_field($section, [
			'id'      => 'max_snippet',
			'type'    => 'select',
			'name'    => __('Max snippet length', 'mihdan-index-now'),
			'default' => '',
			'options' => [
				''    => __('Default (no limit)', 'mihdan-index-now'),
				'0'   => __('No snippet', 'mihdan-index-now'),
				'50'  => __('50 characters', 'mihdan-index-now'),
				'100' => __('100 characters', 'mihdan-index-now'),
				'160' => __('160 characters', 'mihdan-index-now'),
			],
		]);

		$wposa->add_field($section, [
			'id'      => 'max_image_preview',
			'type'    => 'select',
			'name'    => __('Max image preview', 'mihdan-index-now'),
			'default' => 'large',
			'options' => [
				''         => __('Default', 'mihdan-index-now'),
				'none'     => __('None', 'mihdan-index-now'),
				'standard' => __('Standard', 'mihdan-index-now'),
				'large'    => __('Large', 'mihdan-index-now'),
			],
		]);

		$wposa->add_field($section, [
			'id'      => 'max_video_preview',
			'type'    => 'select',
			'name'    => __('Max video preview', 'mihdan-index-now'),
			'default' => '',
			'options' => [
				''   => __('Default (no limit)', 'mihdan-index-now'),
				'0'  => __('No video preview', 'mihdan-index-now'),
				'15' => __('15 seconds', 'mihdan-index-now'),
				'30' => __('30 seconds', 'mihdan-index-now'),
			],
		]);
	}

	/**
	 * Default schema.org type for a post type.
	 */
	private function add_schema_field(WPOSA $wposa, string $section, array $entity): void
	{
		$this->add_heading($wposa, $section, 'heading_schema', __('Structured data', 'mihdan-index-now'));

		$wposa->add_field($section, [
			'id'      => 'schema_type',
			'type'    => 'select',
			'name'    => __('Schema type', 'mihdan-index-now'),
			'default' => Entities::default_value($entity['key'], 'schema_type', 'WebPage'),
			'options' => [
				'none'         => __('None', 'mihdan-index-now'),
				'Article'      => __('Article', 'mihdan-index-now'),
				'BlogPosting'  => __('Blog Posting', 'mihdan-index-now'),
				'NewsArticle'  => __('News Article', 'mihdan-index-now'),
				'WebPage'      => __('Web Page', 'mihdan-index-now'),
				'Product'      => __('Product', 'mihdan-index-now'),
				'Recipe'       => __('Recipe', 'mihdan-index-now'),
				'Course'       => __('Course', 'mihdan-index-now'),
				'Event'        => __('Event', 'mihdan-index-now'),
			],
			'desc'    => esc_html__('Applied to posts of this type that do not define their own schema type.', 'mihdan-index-now'),
		]);
	}

	/**
	 * Archive listing defaults for a post type that has an archive.
	 */
	private function add_archive_fields(WPOSA $wposa, string $section, array $entity): void
	{
		$post_type = $entity['object'];
		$label     = $post_type instanceof \WP_Post_Type ? $post_type->label : $entity['label'];

		$this->add_heading(
			$wposa,
			$section,
			'heading_archive',
			sprintf(
			/* translators: %s: post type plural label. */
				__('%s archive page', 'mihdan-index-now'),
				$label
			)
		);

		$wposa->add_field($section, [
			'id'      => 'archive_noindex',
			'type'    => 'switch',
			'name'    => __('Hide from search results', 'mihdan-index-now'),
			'default' => 'off',
			'desc'    => $this->description(__('This setting will apply the noindex robots tag to the archive listing page only.', 'mihdan-index-now')),
		]);

		$this->add_template_fields($wposa, $section, $entity, 'archive_');
		$this->add_social_image_fields($wposa, $section, $entity, 'archive_');
	}

	/**
	 * A full-width sub-heading inside a settings screen.
	 */
	private function add_heading(WPOSA $wposa, string $section, string $id, string $title, string $desc = ''): void
	{
		$html = sprintf('<h3 class="cwp-tm-subheading">%s</h3>', esc_html($title));

		if ($desc !== '') {
			$html .= sprintf('<p class="description">%s</p>', esc_html($desc));
		}

		$wposa->add_field($section, [
			'id'    => $id,
			'type'  => 'html',
			'name'  => '',
			'desc'  => $html,
			'class' => 'wposa-form-table__row cwp-tm-heading-row',
		]);
	}

	/**
	 * Data attributes consumed by the live preview script.
	 */
	private function template_attributes(string $entity_key, string $field): array
	{
		return [
			'data-cwp-tm'     => $field,
			'data-cwp-entity' => $entity_key,
		];
	}

	/**
	 * Wrap helper copy so it renders below a switch instead of beside it.
	 */
	private function description(string $text): string
	{
		return sprintf('<p class="description">%s</p>', esc_html($text));
	}

	/**
	 * Context-aware copy for the "Hide from search results" toggle.
	 */
	private function noindex_description(array $entity): string
	{
		switch ($entity['type']) {
			case Entities::TYPE_POST_TYPE:
				return __('This setting will apply the noindex robots tag to all posts of this post type and exclude the post type from the sitemap.', 'mihdan-index-now');

			case Entities::TYPE_TAXONOMY:
				return __('This setting will apply the noindex robots tag to every term archive of this taxonomy.', 'mihdan-index-now');

			case Entities::TYPE_AUTHOR:
				return __('This setting will apply the noindex robots tag to author pages.', 'mihdan-index-now');

			case Entities::TYPE_DATE:
				return __('This setting will apply the noindex robots tag to date based archives.', 'mihdan-index-now');

			case Entities::TYPE_SEARCH:
				return __('This setting will apply the noindex robots tag to search results pages. Keeping this enabled is recommended.', 'mihdan-index-now');

			case Entities::TYPE_NOT_FOUND:
				return __('This setting will apply the noindex robots tag to the 404 page. Keeping this enabled is recommended.', 'mihdan-index-now');
		}

		return __('This setting will apply the noindex robots tag to these pages.', 'mihdan-index-now');
	}
}
