<?php

namespace Mihdan\IndexNow\SEOCore\MetaBox;

/**
 * Adds an "SEO Score" column to WordPress post list tables for all public post types.
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

	/** AJAX action name for saving inline SEO fields. */
	const AJAX_ACTION = 'crawlwp_inline_seo_save';

	public function __construct()
	{
		add_action('init', [$this, 'register_hooks'], 20);
		add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajax_save']);

		/*
		 * Persist the score to post meta whenever a post is saved via the normal
		 * metabox form.  Priority 20 ensures MetaFields::save() has already run
		 * and the fresh field values are in the database before we calculate.
		 */
		add_action('save_post', [$this, 'persist_score'], 20);
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

		/* Sort support. */
		add_filter('request', [$this, 'sort_query']);

		add_action('admin_head',           [$this, 'print_styles']);
		add_action('admin_footer',         [$this, 'print_inline_edit_js']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
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

		$seo_title = (string) MetaFields::get($post_id, MetaFields::SEO_TITLE, '');
		$seo_desc  = (string) MetaFields::get($post_id, MetaFields::SEO_DESCRIPTION, '');

		printf(
			'<span class="cwp-seo-score cwp-seo-score--%s" title="%s" aria-label="%s">' .
			'<span class="cwp-seo-score__bar" style="width:%d%%"></span>' .
			'<span class="cwp-seo-score__label">%s</span>' .
			'</span>',
			esc_attr($state),
			esc_attr($tip),
			esc_attr($label),
			(int) $score,
			esc_html($label)
		);

		/* Inline-edit trigger + hidden form. */
		printf(
			'<button type="button" class="cwp-seo-inline-edit__trigger button-link" ' .
			'data-post-id="%d" aria-expanded="false" aria-label="%s">%s</button>',
			(int) $post_id,
			esc_attr__('Edit SEO fields', 'mihdan-index-now'),
			esc_html__('Edit SEO', 'mihdan-index-now')
		);

		printf(
			'<div class="cwp-seo-inline-edit" id="cwp-seo-inline-edit-%d" hidden>' .
			'<label class="cwp-seo-inline-edit__label">%s' .
			'<input type="text" class="cwp-seo-inline-edit__title" value="%s" ' .
			'placeholder="%s" maxlength="200" />' .
			'</label>' .
			'<label class="cwp-seo-inline-edit__label">%s' .
			'<textarea class="cwp-seo-inline-edit__desc" rows="3" ' .
			'placeholder="%s" maxlength="500">%s</textarea>' .
			'</label>' .
			'<div class="cwp-seo-inline-edit__actions">' .
			'<button type="button" class="cwp-seo-inline-edit__save button button-primary button-small">%s</button>' .
			'<button type="button" class="cwp-seo-inline-edit__cancel button button-small">%s</button>' .
			'<span class="cwp-seo-inline-edit__spinner spinner"></span>' .
			'</div>' .
			'</div>',
			(int) $post_id,
			esc_html__('SEO Title', 'mihdan-index-now'),
			esc_attr($seo_title),
			esc_attr__('Leave blank to use global template', 'mihdan-index-now'),
			esc_html__('Meta Description', 'mihdan-index-now'),
			esc_attr__('Leave blank to use global template', 'mihdan-index-now'),
			esc_textarea($seo_desc),
			esc_html__('Save', 'mihdan-index-now'),
			esc_html__('Cancel', 'mihdan-index-now')
		);
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
		/* Skip autosaves, revisions and AJAX saves (the inline-edit handler
		   calls calculate+persist directly). */
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (wp_is_post_revision($post_id)) {
			return;
		}

		if (defined('DOING_AJAX') && DOING_AJAX) {
			return;
		}

		/* Verify the metabox nonce so we only act on our own form submissions. */
		$nonce = isset($_POST[MetaFields::NONCE_NAME]) ? $_POST[MetaFields::NONCE_NAME] : '';
		if (! wp_verify_nonce($nonce, MetaFields::NONCE_ACTION)) {
			return;
		}

		if (! current_user_can('edit_post', $post_id)) {
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

		return round($score, 1);
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
	// AJAX save handler
	// -------------------------------------------------------------------------

	/**
	 * Handle the AJAX request to save inline SEO fields.
	 */
	public function ajax_save(): void
	{
		check_ajax_referer('crawlwp_inline_seo_save', 'nonce');

		$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

		if (! $post_id || ! current_user_can('edit_post', $post_id)) {
			wp_send_json_error(['message' => __('Permission denied.', 'mihdan-index-now')], 403);
		}

		$seo_title = isset($_POST['seo_title']) ? sanitize_text_field(wp_unslash($_POST['seo_title'])) : '';
		$seo_desc  = isset($_POST['seo_desc'])  ? sanitize_textarea_field(wp_unslash($_POST['seo_desc'])) : '';

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

		/* Calculate, persist, and return the updated score. */
		$score = $this->calculate_score($post_id);
		update_post_meta($post_id, MetaFields::SEO_SCORE, (string) $score);
		$state = $this->score_state($score);
		$label = $this->state_label($state, $score);
		$tip   = $this->tooltip($post_id);

		wp_send_json_success([
			'score' => (int) $score,
			'state' => $state,
			'label' => $label,
			'tip'   => $tip,
		]);
	}

	/**
	 * Enqueue admin assets needed only on post list screens.
	 */
	public function enqueue_assets(): void
	{
		$screen = get_current_screen();

		if (! $screen || $screen->base !== 'edit') {
			return;
		}

		wp_localize_script('jquery', 'crawlwpInlineEdit', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce(self::AJAX_ACTION),
			'action'  => self::AJAX_ACTION,
		]);
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
	 * Print minimal inline styles for the SEO score column and inline edit UI.
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
			.column-crawlwp_seo_score { width: 120px; }

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

			/* Inline edit trigger */
			.cwp-seo-inline-edit__trigger {
				display: block;
				margin-top: 4px;
				font-size: 11px;
				color: #2271b1;
				text-decoration: none;
				cursor: pointer;
			}
			.cwp-seo-inline-edit__trigger:hover { color: #135e96; text-decoration: underline; }

			/* Inline edit panel */
			.cwp-seo-inline-edit {
				position: absolute;
				left: 0;
				z-index: 200;
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				box-shadow: 0 3px 10px rgba(0,0,0,.12);
				padding: 12px 14px;
				width: 380px;
				max-width: 90vw;
			}

			.cwp-seo-inline-edit__label {
				display: block;
				margin-bottom: 10px;
				font-size: 12px;
				font-weight: 600;
				color: #1d2327;
			}

			.cwp-seo-inline-edit__title,
			.cwp-seo-inline-edit__desc {
				display: block;
				width: 100%;
				margin-top: 4px;
				box-sizing: border-box;
				font-size: 13px;
			}

			.cwp-seo-inline-edit__actions {
				display: flex;
				align-items: center;
				gap: 8px;
				margin-top: 8px;
			}

			.cwp-seo-inline-edit__spinner {
				float: none;
				visibility: hidden;
			}
			.cwp-seo-inline-edit__spinner.is-active { visibility: visible; }
		</style>
		<?php
	}

	/**
	 * Print the inline JS for the inline-edit panel.
	 * Only emitted on post list screens.
	 */
	public function print_inline_edit_js(): void
	{
		$screen = get_current_screen();

		if (! $screen || $screen->base !== 'edit') {
			return;
		}

		?>
		<script id="cwp-seo-inline-edit-js">
		(function($) {
			'use strict';

			var cfg = window.crawlwpInlineEdit || {};
			if (!cfg.ajaxUrl) { return; }

 		/* Close any open panel. */
 		function closeAll() {
 			$('.cwp-seo-inline-edit').prop('hidden', true);
 			$('.cwp-seo-inline-edit__trigger').attr('aria-expanded', 'false');
 		}

 		/* Open / toggle the panel for a trigger button. */
 		$(document).on('click', '.cwp-seo-inline-edit__trigger', function(e) {
 			e.stopPropagation();
 			var $btn   = $(this);
 			var postId = $btn.data('post-id');
 			var $panel = $('#cwp-seo-inline-edit-' + postId);

 			/* If this panel is already open, close it. */
 			var alreadyOpen = !$panel.prop('hidden');

 			closeAll();

 			if (alreadyOpen) { return; }

 			/* Position relative to the cell. */
 			var $cell = $btn.closest('td');
 			$cell.css('position', 'relative');

 			$panel.prop('hidden', false);
 			$btn.attr('aria-expanded', 'true');
 			$panel.find('.cwp-seo-inline-edit__title').trigger('focus');
 		});

 		/* Close on outside click or Escape. */
 		$(document).on('click', function() { closeAll(); });
 		$(document).on('keydown', function(e) {
 			if (e.key === 'Escape') { closeAll(); }
 		});
 		$(document).on('click', '.cwp-seo-inline-edit', function(e) { e.stopPropagation(); });

			/* Cancel button. */
			$(document).on('click', '.cwp-seo-inline-edit__cancel', function() {
				closeAll();
			});

			/* Save button — AJAX. */
			$(document).on('click', '.cwp-seo-inline-edit__save', function() {
				var $btn    = $(this);
				var $panel  = $btn.closest('.cwp-seo-inline-edit');
				var postId  = $panel.attr('id').replace('cwp-seo-inline-edit-', '');
				var $spin   = $panel.find('.cwp-seo-inline-edit__spinner');
				var title   = $panel.find('.cwp-seo-inline-edit__title').val();
				var desc    = $panel.find('.cwp-seo-inline-edit__desc').val();

				$btn.prop('disabled', true);
				$spin.addClass('is-active');

				$.post(cfg.ajaxUrl, {
					action:    cfg.action,
					nonce:     cfg.nonce,
					post_id:   postId,
					seo_title: title,
					seo_desc:  desc
				}, function(res) {
					$btn.prop('disabled', false);
					$spin.removeClass('is-active');

					if (!res.success) {
						alert(res.data && res.data.message ? res.data.message : 'Save failed.');
						return;
					}

					/* Update the score cell without a page reload. */
					var d      = res.data;
					var $score = $panel.closest('td').find('.cwp-seo-score');

					$score
						.attr('class', 'cwp-seo-score cwp-seo-score--' + d.state)
						.attr('title', d.tip)
						.attr('aria-label', d.label);

					$score.find('.cwp-seo-score__bar').css('width', d.score + '%');
					$score.find('.cwp-seo-score__label').text(d.label);

					closeAll();
				});
			});
		}(jQuery));
		</script>
		<?php
	}
}
