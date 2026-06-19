<?php

namespace Mihdan\IndexNow\SEOCore\SEOMetaFields;

class SEOMetaFields
{
	private const META_PREFIX = '_crawlwp_';

	private const META_KEYS = [
		'meta_title',
		'meta_description',
		'og_title',
		'og_description',
		'og_image',
		'og_type',
		'twitter_card',
		'twitter_title',
		'twitter_description',
		'twitter_image',
		'canonical_url',
		'robots_noindex',
		'robots_nofollow',
	];

	public function __construct()
	{
		add_action('init', [$this, 'register_meta']);
		add_action('add_meta_boxes', [$this, 'add_meta_box']);
		add_action('save_post', [$this, 'save_meta_box'], 10, 2);
		add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
	}

	public static function get_meta_prefix(): string
	{
		return self::META_PREFIX;
	}

	public static function get_meta_keys(): array
	{
		return self::META_KEYS;
	}

	public function register_meta(): void
	{
		$post_types = get_post_types(['public' => true]);

		foreach ($post_types as $post_type) {
			foreach (self::META_KEYS as $key) {
				$type = 'string';

				if (in_array($key, ['robots_noindex', 'robots_nofollow'], true)) {
					$type = 'boolean';
				}

				register_post_meta($post_type, self::META_PREFIX . $key, [
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => $type,
					'default'       => $type === 'boolean' ? false : '',
					'auth_callback' => static function () {
						return current_user_can('edit_posts');
					},
				]);
			}
		}
	}

	public function add_meta_box(): void
	{
		$post_types = get_post_types(['public' => true]);

		add_meta_box(
			'crawlwp-seo-meta-fields',
			__('CrawlWP SEO Settings', 'mihdan-index-now'),
			[$this, 'render_meta_box'],
			$post_types,
			'normal',
			'high'
		);
	}

	public function render_meta_box(\WP_Post $post): void
	{
		wp_nonce_field('crawlwp_seo_meta_nonce', 'crawlwp_seo_meta_nonce');

		$values = [];
		foreach (self::META_KEYS as $key) {
			$values[$key] = get_post_meta($post->ID, self::META_PREFIX . $key, true);
		}

		?>
		<style>
			.crawlwp-seo-meta-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
			.crawlwp-seo-meta-fields .crawlwp-field { display: flex; flex-direction: column; gap: 4px; }
			.crawlwp-seo-meta-fields .crawlwp-field.full-width { grid-column: 1 / -1; }
			.crawlwp-seo-meta-fields label { font-weight: 600; }
			.crawlwp-seo-meta-fields input[type="text"],
			.crawlwp-seo-meta-fields input[type="url"],
			.crawlwp-seo-meta-fields textarea,
			.crawlwp-seo-meta-fields select { width: 100%; }
			.crawlwp-seo-meta-fields textarea { min-height: 60px; }
			.crawlwp-seo-meta-fields .crawlwp-field-desc { color: #666; font-size: 12px; }
			.crawlwp-seo-meta-fields .crawlwp-section-title { grid-column: 1 / -1; margin: 8px 0 0; padding: 8px 0 4px; border-bottom: 1px solid #ddd; font-size: 14px; font-weight: 700; }
			.crawlwp-seo-meta-fields .crawlwp-checkbox-field { flex-direction: row; align-items: center; gap: 8px; }
		</style>
		<div class="crawlwp-seo-meta-fields">

			<div class="crawlwp-section-title"><?php esc_html_e('General', 'mihdan-index-now'); ?></div>

			<div class="crawlwp-field full-width">
				<label for="crawlwp_meta_title"><?php esc_html_e('Meta Title', 'mihdan-index-now'); ?></label>
				<input type="text" id="crawlwp_meta_title" name="crawlwp_meta_title" value="<?php echo esc_attr($values['meta_title']); ?>" />
				<span class="crawlwp-field-desc"><?php esc_html_e('Custom title tag for this page. Leave empty to use the default.', 'mihdan-index-now'); ?></span>
			</div>

			<div class="crawlwp-field full-width">
				<label for="crawlwp_meta_description"><?php esc_html_e('Meta Description', 'mihdan-index-now'); ?></label>
				<textarea id="crawlwp_meta_description" name="crawlwp_meta_description" rows="3"><?php echo esc_textarea($values['meta_description']); ?></textarea>
				<span class="crawlwp-field-desc"><?php esc_html_e('Custom meta description for search engines.', 'mihdan-index-now'); ?></span>
			</div>

			<div class="crawlwp-field full-width">
				<label for="crawlwp_canonical_url"><?php esc_html_e('Canonical URL', 'mihdan-index-now'); ?></label>
				<input type="url" id="crawlwp_canonical_url" name="crawlwp_canonical_url" value="<?php echo esc_url($values['canonical_url']); ?>" />
				<span class="crawlwp-field-desc"><?php esc_html_e('Set a custom canonical URL for this page.', 'mihdan-index-now'); ?></span>
			</div>

			<div class="crawlwp-section-title"><?php esc_html_e('Robots', 'mihdan-index-now'); ?></div>

			<div class="crawlwp-field crawlwp-checkbox-field">
				<input type="checkbox" id="crawlwp_robots_noindex" name="crawlwp_robots_noindex" value="1" <?php checked($values['robots_noindex']); ?> />
				<label for="crawlwp_robots_noindex"><?php esc_html_e('Set this page to noindex', 'mihdan-index-now'); ?></label>
			</div>

			<div class="crawlwp-field crawlwp-checkbox-field">
				<input type="checkbox" id="crawlwp_robots_nofollow" name="crawlwp_robots_nofollow" value="1" <?php checked($values['robots_nofollow']); ?> />
				<label for="crawlwp_robots_nofollow"><?php esc_html_e('Set this page to nofollow', 'mihdan-index-now'); ?></label>
			</div>

			<div class="crawlwp-section-title"><?php esc_html_e('Open Graph', 'mihdan-index-now'); ?></div>

			<div class="crawlwp-field">
				<label for="crawlwp_og_title"><?php esc_html_e('OG Title', 'mihdan-index-now'); ?></label>
				<input type="text" id="crawlwp_og_title" name="crawlwp_og_title" value="<?php echo esc_attr($values['og_title']); ?>" />
			</div>

			<div class="crawlwp-field">
				<label for="crawlwp_og_type"><?php esc_html_e('OG Type', 'mihdan-index-now'); ?></label>
				<select id="crawlwp_og_type" name="crawlwp_og_type">
					<option value="" <?php selected($values['og_type'], ''); ?>><?php esc_html_e('Default (article)', 'mihdan-index-now'); ?></option>
					<option value="article" <?php selected($values['og_type'], 'article'); ?>>article</option>
					<option value="website" <?php selected($values['og_type'], 'website'); ?>>website</option>
					<option value="product" <?php selected($values['og_type'], 'product'); ?>>product</option>
					<option value="profile" <?php selected($values['og_type'], 'profile'); ?>>profile</option>
				</select>
			</div>

			<div class="crawlwp-field full-width">
				<label for="crawlwp_og_description"><?php esc_html_e('OG Description', 'mihdan-index-now'); ?></label>
				<textarea id="crawlwp_og_description" name="crawlwp_og_description" rows="2"><?php echo esc_textarea($values['og_description']); ?></textarea>
			</div>

			<div class="crawlwp-field full-width">
				<label for="crawlwp_og_image"><?php esc_html_e('OG Image URL', 'mihdan-index-now'); ?></label>
				<input type="url" id="crawlwp_og_image" name="crawlwp_og_image" value="<?php echo esc_url($values['og_image']); ?>" />
				<span class="crawlwp-field-desc"><?php esc_html_e('Recommended size: 1200×630 pixels.', 'mihdan-index-now'); ?></span>
			</div>

			<div class="crawlwp-section-title"><?php esc_html_e('Twitter Card', 'mihdan-index-now'); ?></div>

			<div class="crawlwp-field">
				<label for="crawlwp_twitter_card"><?php esc_html_e('Card Type', 'mihdan-index-now'); ?></label>
				<select id="crawlwp_twitter_card" name="crawlwp_twitter_card">
					<option value="" <?php selected($values['twitter_card'], ''); ?>><?php esc_html_e('Default (summary_large_image)', 'mihdan-index-now'); ?></option>
					<option value="summary" <?php selected($values['twitter_card'], 'summary'); ?>>summary</option>
					<option value="summary_large_image" <?php selected($values['twitter_card'], 'summary_large_image'); ?>>summary_large_image</option>
				</select>
			</div>

			<div class="crawlwp-field">
				<label for="crawlwp_twitter_title"><?php esc_html_e('Twitter Title', 'mihdan-index-now'); ?></label>
				<input type="text" id="crawlwp_twitter_title" name="crawlwp_twitter_title" value="<?php echo esc_attr($values['twitter_title']); ?>" />
			</div>

			<div class="crawlwp-field full-width">
				<label for="crawlwp_twitter_description"><?php esc_html_e('Twitter Description', 'mihdan-index-now'); ?></label>
				<textarea id="crawlwp_twitter_description" name="crawlwp_twitter_description" rows="2"><?php echo esc_textarea($values['twitter_description']); ?></textarea>
			</div>

			<div class="crawlwp-field full-width">
				<label for="crawlwp_twitter_image"><?php esc_html_e('Twitter Image URL', 'mihdan-index-now'); ?></label>
				<input type="url" id="crawlwp_twitter_image" name="crawlwp_twitter_image" value="<?php echo esc_url($values['twitter_image']); ?>" />
			</div>

		</div>
		<?php
	}

	public function save_meta_box(int $post_id, \WP_Post $post): void
	{
		if (
			! isset($_POST['crawlwp_seo_meta_nonce']) ||
			! wp_verify_nonce($_POST['crawlwp_seo_meta_nonce'], 'crawlwp_seo_meta_nonce')
		) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		$text_keys = ['meta_title', 'meta_description', 'og_title', 'og_description', 'og_type', 'twitter_card', 'twitter_title', 'twitter_description'];
		$url_keys  = ['og_image', 'twitter_image', 'canonical_url'];
		$bool_keys = ['robots_noindex', 'robots_nofollow'];

		foreach ($text_keys as $key) {
			$field_name = 'crawlwp_' . $key;
			$value      = isset($_POST[$field_name]) ? sanitize_text_field(wp_unslash($_POST[$field_name])) : '';
			update_post_meta($post_id, self::META_PREFIX . $key, $value);
		}

		foreach ($url_keys as $key) {
			$field_name = 'crawlwp_' . $key;
			$value      = isset($_POST[$field_name]) ? esc_url_raw(wp_unslash($_POST[$field_name])) : '';
			update_post_meta($post_id, self::META_PREFIX . $key, $value);
		}

		foreach ($bool_keys as $key) {
			$field_name = 'crawlwp_' . $key;
			$value      = ! empty($_POST[$field_name]);
			update_post_meta($post_id, self::META_PREFIX . $key, $value);
		}
	}

	public function enqueue_block_editor_assets(): void
	{
		$asset_url = plugin_dir_url(MIHDAN_INDEX_NOW_FILE) . 'src/SEOCore/SEOMetaFields/';

		wp_enqueue_script(
			'crawlwp-seo-meta-fields-block-editor',
			$asset_url . 'seo-meta-fields-block-editor.js',
			['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-compose', 'wp-i18n'],
			MIHDAN_INDEX_NOW_VERSION,
			true
		);
	}
}
