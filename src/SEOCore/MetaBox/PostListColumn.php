<?php

namespace Mihdan\IndexNow\SEOCore\MetaBox;

/**
 * Adds an "SEO Score" column to WordPress post list tables for all public post types.
 * Also injects SEO title + meta description fields into the native Quick Edit panel.
 *
 * Score is calculated from:
 *  - SEO title length (0–60 chars ideal)
 *  - SEO description length (0–160 chars ideal)
 *  - Presence of a focus keyword
 *  - Whether the post is set to noindex
 */
class PostListColumn
{
	/** Title length thresholds (characters). */
	private const TITLE_MIN = 30;
	private const TITLE_MAX = 60;

	/** Description length thresholds (characters). */
	private const DESC_MIN  = 50;
	private const DESC_MAX  = 160;

	/** Nonce action for Quick Edit saves. */
	const QUICK_EDIT_NONCE = 'crawlwp_quick_edit_seo';

	public function __construct()
	{
		add_action('init', [$this, 'register_hooks'], 20);

		/*
		 * Persist the score to post meta whenever a post is saved.
		 * Priority 20 ensures MetaFields::save() (priority 10)
		 * has already written the fresh meta values.
		 */
		add_action('save_post', [$this, 'persist_score'], 20);

		/* Save SEO fields submitted via Quick Edit. */
		add_action('save_post', [$this, 'save_quick_edit'], 15);
	}

	/**
	 * Register column hooks after post types are registered.
	 */
	public function register_hooks(): void
	{
		foreach ($this->get_public_post_types() as $post_type) {
			add_filter("manage_{$post_type}_posts_columns",       [$this, 'add_column']);
			add_action("manage_{$post_type}_posts_custom_column", [$this, 'render_column'], 10, 2);
		}

		/* Inject our fields into the Quick Edit panel (fires once per column). */
		add_action('quick_edit_custom_box', [$this, 'render_quick_edit_fields'], 10, 2);

		/* Sort support. */
		add_filter('request', [$this, 'sort_query']);

		add_action('admin_head',   [$this, 'print_styles']);
		add_action('admin_footer', [$this, 'print_quick_edit_js']);
	}

	/**
	 * Return slugs of all public post types.
	 *
	 * @return string[]
	 */
	private function get_public_post_types(): array
	{
		$types = get_post_types(['public' => true], 'names');

		/* Remove attachments — they don't have SEO metaboxes. */
		unset($types['attachment']);

		return array_values($types);
	}

	// -------------------------------------------------------------------------
	// Column registration
	// -------------------------------------------------------------------------

	/**
	 * Append the SEO score column after the title column.
	 *
	 * @param string[] $columns
	 * @return string[]
	 */
	public function add_column(array $columns): array
	{
		$result = [];

		foreach ($columns as $key => $label) {
			$result[$key] = $label;

			/* Insert after the title column. */
			if ($key === 'title') {
				$result['crawlwp_seo_score'] = esc_html__('SEO', 'mihdan-index-now');
			}
		}

		/* Fallback: if there was no title column, append at end. */
		if (! isset($result['crawlwp_seo_score'])) {
			$result['crawlwp_seo_score'] = esc_html__('SEO', 'mihdan-index-now');
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Column rendering
	// -------------------------------------------------------------------------

	/**
	 * Output the SEO score cell for a given post.
	 *
	 * @param string $column_name
	 * @param int    $post_id
	 */
	public function render_column(string $column_name, int $post_id): void
	{
		if ($column_name !== 'crawlwp_seo_score') {
			return;
		}

		$cached = get_post_meta($post_id, MetaFields::SEO_SCORE, true);
		$score  = ($cached !== '' && $cached !== false) ? (float) $cached : $this->calculate_score($post_id);
		$state  = $this->score_state($score);
		$label  = $this->state_label($state, $score);
		$tip    = $this->tooltip($post_id);

		/* Store current SEO values in data attributes so Quick Edit JS can pre-fill the fields. */
		$seo_title = (string) MetaFields::get($post_id, MetaFields::SEO_TITLE, '');
		$seo_desc  = (string) MetaFields::get($post_id, MetaFields::SEO_DESCRIPTION, '');

		printf(
			'<span class="cwp-seo-score cwp-seo-score--%s" title="%s" aria-label="%s"' .
			' data-cwp-seo-title="%s" data-cwp-seo-desc="%s">' .
			'<span class="cwp-seo-score__bar" style="width:%d%%"></span>' .
			'<span class="cwp-seo-score__label">%s</span>' .
			'</span>',
			esc_attr($state),
			esc_attr($tip),
			esc_attr($label),
			esc_attr($seo_title),
			esc_attr($seo_desc),
			(int) $score,
			esc_html($label)
		);
	}

	// -------------------------------------------------------------------------
	// Quick Edit — render fields
	// -------------------------------------------------------------------------

	/**
	 * Inject SEO title + meta description fields into the Quick Edit panel.
	 *
	 * @param string $column_name
	 * @param string $post_type
	 */
	public function render_quick_edit_fields(string $column_name, string $post_type): void
	{
		if ($column_name !== 'crawlwp_seo_score') {
			return;
		}

		/* Only for registered public post types. */
		if (! in_array($post_type, $this->get_public_post_types(), true)) {
			return;
		}

		wp_nonce_field(self::QUICK_EDIT_NONCE, 'cwp_quick_edit_nonce');
		?>
		<fieldset class="inline-edit-col-right cwp-quick-edit-seo">
			<div class="inline-edit-col">
				<h4 class="cwp-quick-edit-seo__heading"><?php esc_html_e('CrawlWP SEO', 'mihdan-index-now'); ?></h4>
				<label class="cwp-quick-edit-seo__label">
					<span class="title"><?php esc_html_e('SEO Title', 'mihdan-index-now'); ?></span>
					<input type="text"
						name="cwp_quick_edit_seo_title"
						class="cwp-quick-edit-seo__title"
						value=""
						maxlength="200"
						placeholder="<?php esc_attr_e('Leave blank to use global template', 'mihdan-index-now'); ?>" />
				</label>
				<label class="cwp-quick-edit-seo__label">
					<span class="title"><?php esc_html_e('Meta Description', 'mihdan-index-now'); ?></span>
					<textarea
						name="cwp_quick_edit_seo_desc"
						class="cwp-quick-edit-seo__desc"
						rows="3"
						maxlength="500"
						placeholder="<?php esc_attr_e('Leave blank to use global template', 'mihdan-index-now'); ?>"></textarea>
				</label>
			</div>
		</fieldset>
		<?php
	}

	// -------------------------------------------------------------------------
	// Quick Edit — save fields
	// -------------------------------------------------------------------------

	/**
	 * Save SEO fields submitted via the Quick Edit panel.
	 *
	 * WordPress fires save_post for Quick Edit submissions; the nonce we embed
	 * in the panel lets us distinguish our submission from other save_post calls.
	 *
	 * @param int $post_id
	 */
	public function save_quick_edit(int $post_id): void
	{
		/* Only act when our nonce is present (i.e. Quick Edit was used). */
		$nonce = isset($_POST['cwp_quick_edit_nonce']) ? $_POST['cwp_quick_edit_nonce'] : '';
		if (! wp_verify_nonce($nonce, self::QUICK_EDIT_NONCE)) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		$seo_title = isset($_POST['cwp_quick_edit_seo_title'])
			? sanitize_text_field(wp_unslash($_POST['cwp_quick_edit_seo_title']))
			: '';

		$seo_desc = isset($_POST['cwp_quick_edit_seo_desc'])
			? sanitize_textarea_field(wp_unslash($_POST['cwp_quick_edit_seo_desc']))
			: '';

		if ($seo_title !== '') {
			update_post_meta($post_id, MetaFields::SEO_TITLE, $seo_title);
		} else {
			delete_post_meta($post_id, MetaFields::SEO_TITLE);
		}

		if ($seo_desc !== '') {
			update_post_meta($post_id, MetaFields::SEO_DESCRIPTION, $seo_desc);
		} else {
			delete_post_meta($post_id, MetaFields::SEO_DESCRIPTION);
		}
	}

	// -------------------------------------------------------------------------
	// Score persistence
	// -------------------------------------------------------------------------

	/**
	 * Calculate and persist the SEO score whenever a post is saved.
	 *
	 * Runs at priority 20 on save_post so MetaFields::save() (priority 10)
	 * has already written the fresh meta values.
	 *
	 * @param int $post_id
	 */
	public function persist_score(int $post_id): void
	{
		/* Skip autosaves and revisions. */
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (wp_is_post_revision($post_id)) {
			return;
		}

		/*
		 * Only persist the JS-calculated score (richer analysis: keyword density,
		 * readability, heading structure, etc.).  When the JS score is absent the
		 * cached meta is deleted so the post list always shows a fresh PHP-computed
		 * approximation rather than a stale value from a previous save.
		 */
		$submitted = isset($_POST[MetaFields::SEO_SCORE]) ? trim($_POST[MetaFields::SEO_SCORE]) : '';

		if ($submitted !== '' && is_numeric($submitted)) {
			/* Verify the metabox nonce — only our own form submissions carry this. */
			$nonce = isset($_POST[MetaFields::NONCE_NAME]) ? $_POST[MetaFields::NONCE_NAME] : '';
			if (! wp_verify_nonce($nonce, MetaFields::NONCE_ACTION)) {
				return;
			}

			if (! current_user_can('edit_post', $post_id)) {
				return;
			}

			$score = (float) $submitted;
			// Clamp to [0, 100] so a tampered value can't break the display.
			$score = max(0.0, min(100.0, $score));
			update_post_meta($post_id, MetaFields::SEO_SCORE, (string) $score);
		} else {
			/* No JS score submitted — remove any stale cached value so the list
			   always falls back to the live PHP approximation. */
			delete_post_meta($post_id, MetaFields::SEO_SCORE);
		}
	}

	// -------------------------------------------------------------------------
	// Score calculation
	// -------------------------------------------------------------------------

	/**
	 * Calculate a 0–100 SEO score for a post.
	 * The score is the average of three sub-scores:
	 *  1. SEO title quality (40 pts max)
	 *  2. SEO description quality (40 pts max)
	 *  3. Focus keyword present (20 pts max)
	 * Noindex posts are capped at 10.
	 *
	 * @param int $post_id
	 * @return float 0–100
	 */
	private function calculate_score(int $post_id): float
	{
		/* Noindex: bail early with a low score. */
		$robots_index = MetaFields::get($post_id, MetaFields::ROBOTS_INDEX, 'index');

		if ($robots_index === 'noindex') {
			return 10.0;
		}

		$title       = (string) MetaFields::get($post_id, MetaFields::SEO_TITLE, '');
		$description = (string) MetaFields::get($post_id, MetaFields::SEO_DESCRIPTION, '');
		$keyword     = (string) MetaFields::get($post_id, MetaFields::FOCUS_KEYWORD, '');

		$title_score = $this->length_score(mb_strlen($title), self::TITLE_MIN, self::TITLE_MAX);
		$desc_score  = $this->length_score(mb_strlen($description), self::DESC_MIN, self::DESC_MAX);
		$kw_score    = ($keyword !== '') ? 100.0 : 0.0;

		/* Weighted: title 40%, description 40%, keyword 20%. */
		$score = ($title_score * 0.4) + ($desc_score * 0.4) + ($kw_score * 0.2);
		$score = round($score, 1);

		/**
		 * Filter the calculated SEO score for a post.
		 *
		 * @param float $score   The computed score (0–100).
		 * @param int   $post_id The post ID.
		 */
		return (float) apply_filters('crawlwp_post_seo_score', $score, $post_id);
	}

	/**
	 * Map a character count to a 0–100 quality score for a length-sensitive field.
	 *
	 * @param int $length  Actual character count (0 when empty / global template in use).
	 * @param int $min     Minimum recommended length.
	 * @param int $max     Maximum recommended length.
	 * @return float 0–100
	 */
	private function length_score(int $length, int $min, int $max): float
	{
		if ($length === 0) {
			/*
			 * Empty means the global Title & Meta template is used, not that it
			 * is blank.  Award a neutral 50 so it does not drag the score down
			 * severely, but signal there is room to customise.
			 */
			return 50.0;
		}

		if ($length >= $min && $length <= $max) {
			return 100.0;
		}

		if ($length < $min) {
			/* Linearly scale from 0 (length=0) to 100 (length=min). */
			return round(($length / $min) * 100, 1);
		}

		/* Too long: scale down from 100 (length=max) toward 0 as length grows. */
		$over   = $length - $max;
		$score  = max(0, 100 - ($over / $max) * 100);

		return round($score, 1);
	}

	/**
	 * Map a numeric score to a named state.
	 *
	 * @param float $score 0–100
	 * @return string  'good' | 'ok' | 'poor' | 'noindex'
	 */
	private function score_state(float $score): string
	{
		if ($score <= 10) {
			return 'noindex';
		}

		if ($score >= 70) {
			return 'good';
		}

		if ($score >= 40) {
			return 'ok';
		}

		return 'poor';
	}

	/**
	 * Human-readable label for a state.
	 *
	 * @param string $state
	 * @param float  $score
	 * @return string
	 */
	private function state_label(string $state, float $score): string
	{
		switch ($state) {
			case 'noindex':
				return __('No Index', 'mihdan-index-now');
			case 'good':
				return __('Good', 'mihdan-index-now');
			case 'ok':
				return __('OK', 'mihdan-index-now');
			default:
				return __('Poor', 'mihdan-index-now');
		}
	}

	/**
	 * Build a descriptive tooltip showing what is missing.
	 *
	 * @param int $post_id
	 * @return string
	 */
	private function tooltip(int $post_id): string
	{
		$robots_index = MetaFields::get($post_id, MetaFields::ROBOTS_INDEX, 'index');

		if ($robots_index === 'noindex') {
			return __('This post is set to noindex and will not appear in search results.', 'mihdan-index-now');
		}

		$parts = [];

		$title  = (string) MetaFields::get($post_id, MetaFields::SEO_TITLE, '');
		$desc   = (string) MetaFields::get($post_id, MetaFields::SEO_DESCRIPTION, '');
		$kw     = (string) MetaFields::get($post_id, MetaFields::FOCUS_KEYWORD, '');

		if ($title === '') {
			$parts[] = __('SEO title: using global template.', 'mihdan-index-now');
		} else {
			$len = mb_strlen($title);

			if ($len < self::TITLE_MIN) {
				/* translators: %d = character count */
				$parts[] = sprintf(__('SEO title: too short (%d chars).', 'mihdan-index-now'), $len);
			} elseif ($len > self::TITLE_MAX) {
				/* translators: %d = character count */
				$parts[] = sprintf(__('SEO title: too long (%d chars).', 'mihdan-index-now'), $len);
			} else {
				/* translators: %d = character count */
				$parts[] = sprintf(__('SEO title: good (%d chars).', 'mihdan-index-now'), $len);
			}
		}

		if ($desc === '') {
			$parts[] = __('Meta description: using global template.', 'mihdan-index-now');
		} else {
			$len = mb_strlen($desc);

			if ($len < self::DESC_MIN) {
				/* translators: %d = character count */
				$parts[] = sprintf(__('Meta description: too short (%d chars).', 'mihdan-index-now'), $len);
			} elseif ($len > self::DESC_MAX) {
				/* translators: %d = character count */
				$parts[] = sprintf(__('Meta description: too long (%d chars).', 'mihdan-index-now'), $len);
			} else {
				/* translators: %d = character count */
				$parts[] = sprintf(__('Meta description: good (%d chars).', 'mihdan-index-now'), $len);
			}
		}

		if ($kw === '') {
			$parts[] = __('Focus keyword: not set.', 'mihdan-index-now');
		} else {
			$parts[] = __('Focus keyword: set.', 'mihdan-index-now');
		}

		return implode(' ', $parts);
	}

	// -------------------------------------------------------------------------
	// Sort support
	// -------------------------------------------------------------------------

	/**
	 * Allow sorting by the SEO score column via a meta_key query.
	 * Note: because the score is computed, we sort by presence of the SEO title
	 * as a practical approximation.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function sort_query(array $vars): array
	{
		if (
			isset($vars['orderby']) &&
			$vars['orderby'] === 'crawlwp_seo_score'
		) {
			$vars['meta_key'] = MetaFields::SEO_TITLE;
			$vars['orderby']  = 'meta_value';
		}

		return $vars;
	}

	// -------------------------------------------------------------------------
	// Inline CSS + JS
	// -------------------------------------------------------------------------

	/**
	 * Print minimal inline styles for the SEO score column and Quick Edit fields.
	 * Only emitted on post list screens.
	 */
	public function print_styles(): void
	{
		$screen = get_current_screen();

		if (! $screen || $screen->base !== 'edit') {
			return;
		}

		?>
		<style id="cwp-seo-score-styles">
			/* SEO Score column — CrawlWP */
			.column-crawlwp_seo_score { width: 80px; }

			.cwp-seo-score {
				display: inline-flex;
				flex-direction: column;
				align-items: flex-start;
				gap: 3px;
				cursor: default;
			}

			/* Progress bar track */
			.cwp-seo-score__bar {
				display: block;
				height: 6px;
				border-radius: 3px;
				min-width: 4px;
				max-width: 72px;
				background: #c3c4c7; /* default / empty */
				transition: width .2s ease;
			}

			.cwp-seo-score--good  .cwp-seo-score__bar { background: #00a32a; }
			.cwp-seo-score--ok    .cwp-seo-score__bar { background: #dba617; }
			.cwp-seo-score--poor  .cwp-seo-score__bar { background: #d63638; }
			.cwp-seo-score--noindex .cwp-seo-score__bar { background: #8c8f94; width: 8px !important; }

			/* Text label */
			.cwp-seo-score__label {
				font-size: 11px;
				line-height: 1.3;
				color: #646970;
			}

			.cwp-seo-score--good  .cwp-seo-score__label { color: #00a32a; }
			.cwp-seo-score--ok    .cwp-seo-score__label { color: #a07000; }
			.cwp-seo-score--poor  .cwp-seo-score__label { color: #d63638; }
			.cwp-seo-score--noindex .cwp-seo-score__label { color: #8c8f94; }

			/* Quick Edit SEO fields */
			.cwp-quick-edit-seo__heading {
				margin-bottom: 2px;
				padding: 0;
			}

			.cwp-quick-edit-seo__label {
				display: flex;
				align-items: baseline;
				margin-bottom: 8px;
			}

			.cwp-quick-edit-seo__label .title {
				min-width: 110px;
				padding-right: 8px;
				font-size: 12px;
				color: #646970;
			}

			.cwp-quick-edit-seo__title,
			.cwp-quick-edit-seo__desc {
				flex: 1;
				width: 100%;
				box-sizing: border-box;
				font-size: 13px;
			}
		</style>
		<?php
	}

	/**
	 * Print the JS that pre-fills our Quick Edit fields from the data attributes
	 * stored on the score span in the list row.
	 * Only emitted on post list screens.
	 */
	public function print_quick_edit_js(): void
	{
		$screen = get_current_screen();

		if (! $screen || $screen->base !== 'edit') {
			return;
		}

		?>
		<script id="cwp-seo-quick-edit-js">
		(function($) {
			'use strict';

			$(document).ready(function() {
				if (typeof inlineEditPost === 'undefined') { return; }

				/* Store a reference to the original open() method. */
				var _originalOpen = inlineEditPost.open;

				/* Override open() so we can pre-fill our fields each time the row expands. */
				inlineEditPost.open = function(id) {
					/* Call the original first so WP sets up the row. */
					_originalOpen.apply(this, arguments);

					/* Resolve the numeric post ID (WP may pass the TR id string). */
					var postId = (typeof id === 'string') ? id.replace(/[^0-9]/g, '') : String(id);
					if (!postId) { return; }

					/* Read stored values from the data attributes on the score span. */
					var $score = $('#post-' + postId + ' .cwp-seo-score');
					if (!$score.length) { return; }

					var seoTitle = $score.data('cwp-seo-title') || '';
					var seoDesc  = $score.data('cwp-seo-desc')  || '';

					/* Find the Quick Edit row that WP just revealed and fill our fields. */
					var $editRow = $('#edit-' + postId);
					$editRow.find('.cwp-quick-edit-seo__title').val(seoTitle);
					$editRow.find('.cwp-quick-edit-seo__desc').val(seoDesc);
				};
			});
		}(jQuery));
		</script>
		<?php
	}
}
