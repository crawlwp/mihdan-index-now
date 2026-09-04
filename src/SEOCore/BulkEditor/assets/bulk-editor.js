/* global crawlwpBulkEditor, jQuery */
(function ($) {
	'use strict';

	if (typeof crawlwpBulkEditor === 'undefined') {
		return;
	}

	var cfg  = crawlwpBulkEditor;
	var i18n = cfg.i18n || {};

	// -------------------------------------------------------------------------
	// State
	// -------------------------------------------------------------------------

	var state = {
		page:      1,
		per_page:  20,
		search:    '',
		post_type: '',
		filter:    'all'
	};

	// Dirty rows keyed by post id: { seo_title: '...', seo_description: '...' }.
	var dirty = {};

	// -------------------------------------------------------------------------
	// DOM refs (resolved after DOMContentLoaded)
	// -------------------------------------------------------------------------

	var $wrap, $tbody, $totalInfo, $pageLinks, $saveBtn, $notice;
	var searchTimer = null;

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	$(function () {
		$wrap      = $('#cwp-bulk-wrap');
		$tbody     = $('#cwp-bulk-tbody');
		$totalInfo = $('#cwp-bulk-total-info');
		$pageLinks = $('#cwp-bulk-page-links');
		$saveBtn   = $('#cwp-bulk-save-btn');
		$notice    = $('#cwp-bulk-notice');

		if (!$wrap.length) {
			return;
		}

		bindEvents();
		loadTable();
	});

	// -------------------------------------------------------------------------
	// Event bindings
	// -------------------------------------------------------------------------

	function bindEvents() {

		// Post type filter.
		$wrap.on('change', '#cwp-bulk-post-type', function () {
			state.post_type = $(this).val();
			state.page = 1;
			loadTable();
		});

		// Missing title/description filter.
		$wrap.on('change', '#cwp-bulk-filter', function () {
			state.filter = $(this).val();
			state.page = 1;
			loadTable();
		});

		// Search (debounced).
		$wrap.on('input', '#cwp-bulk-search', function () {
			var val = $(this).val();
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () {
				state.search = val;
				state.page   = 1;
				loadTable();
			}, 300);
		});

		// Per-page.
		$wrap.on('change', '#cwp-bulk-per-page', function () {
			state.per_page = parseInt($(this).val(), 10) || 20;
			state.page     = 1;
			loadTable();
		});

		// Pagination links (delegated, injected dynamically).
		$wrap.on('click', '.cwp-bulk-page-btn', function () {
			var page = parseInt($(this).data('page'), 10);
			if (page && page !== state.page) {
				state.page = page;
				loadTable();
			}
		});

		// Track edits + char counts on any inline field.
		$wrap.on('input', '.cwp-bulk-input', function () {
			updateCount($(this));
			markDirty($(this));
		});

		// Save all changes.
		$wrap.on('click', '#cwp-bulk-save-btn', saveChanges);

		// Warn on navigating away with unsaved changes.
		$(window).on('beforeunload', function (e) {
			if (hasDirty()) {
				e.preventDefault();
				return i18n.confirmLeave || 'You have unsaved changes.';
			}
		});
	}

	// -------------------------------------------------------------------------
	// AJAX: load table
	// -------------------------------------------------------------------------

	function loadTable() {
		$tbody.html('<tr class="cwp-bulk-loading-row"><td colspan="3">' + escapeHtml('Loading…') + '</td></tr>');
		dirty = {};
		toggleSaveButton();

		$.post(cfg.ajaxUrl, {
			action:    'crawlwp_bulk_editor_list',
			nonce:     cfg.nonce,
			search:    state.search,
			post_type: state.post_type,
			filter:    state.filter,
			per_page:  state.per_page,
			page:      state.page
		}, function (resp) {
			if (!resp.success) {
				$tbody.html('<tr><td colspan="3">' + escapeHtml((resp.data && resp.data.message) ? resp.data.message : 'Error') + '</td></tr>');
				return;
			}

			var data = resp.data;
			$tbody.html(data.html);

			$tbody.find('.cwp-bulk-input').each(function () {
				updateCount($(this));
			});

			$totalInfo.text('Total ' + data.total + ' item' + (data.total === 1 ? '' : 's') + '.');

			renderPagination(data.page, data.pages);
		});
	}

	// -------------------------------------------------------------------------
	// AJAX: save changes
	// -------------------------------------------------------------------------

	function saveChanges() {
		var ids = Object.keys(dirty);

		if (!ids.length) {
			return;
		}

		var rows = ids.map(function (id) {
			return {
				id:              id,
				seo_title:       dirty[id].seo_title,
				seo_description: dirty[id].seo_description
			};
		});

		$saveBtn.prop('disabled', true).text(i18n.saving || 'Saving…');
		hideNotice();

		$.post(cfg.ajaxUrl, {
			action: 'crawlwp_bulk_editor_save',
			nonce:  cfg.nonce,
			rows:   rows
		}, function (resp) {
			$saveBtn.text(i18n.saveChanges || 'Save Changes');

			if (!resp.success) {
				showNotice((resp.data && resp.data.message) ? resp.data.message : (i18n.saveError || 'Save failed.'), true);
				$saveBtn.prop('disabled', false);
				return;
			}

			// Mark saved rows as clean.
			var savedIds = (resp.data && resp.data.saved) ? resp.data.saved : [];
			savedIds.forEach(function (id) {
				delete dirty[String(id)];

				var $row = $tbody.find('tr[data-id="' + id + '"]');
				$row.find('.cwp-bulk-input').each(function () {
					$(this).data('original', $(this).val()).attr('data-original', $(this).val());
				});
				$row.removeClass('is-dirty');
			});

			toggleSaveButton();
			showNotice((resp.data && resp.data.message) ? resp.data.message : (i18n.saved || 'Saved.'), false);
		}).fail(function () {
			$saveBtn.prop('disabled', false).text(i18n.saveChanges || 'Save Changes');
			showNotice(i18n.saveError || 'Save failed.', true);
		});
	}

	// -------------------------------------------------------------------------
	// Dirty-state tracking
	// -------------------------------------------------------------------------

	function markDirty($input) {
		var $row  = $input.closest('tr');
		var id    = $row.data('id');
		var field = $input.data('field');

		if (!id || !field) {
			return;
		}

		var $title = $row.find('[data-field="seo_title"]');
		var $desc  = $row.find('[data-field="seo_description"]');

		var titleVal = $title.val();
		var descVal  = $desc.val();

		var titleChanged = titleVal !== ($title.attr('data-original') || '');
		var descChanged  = descVal !== ($desc.attr('data-original') || '');

		if (titleChanged || descChanged) {
			dirty[String(id)] = {
				seo_title:       titleVal,
				seo_description: descVal
			};
			$row.addClass('is-dirty');
		} else {
			delete dirty[String(id)];
			$row.removeClass('is-dirty');
		}

		toggleSaveButton();
	}

	function hasDirty() {
		return Object.keys(dirty).length > 0;
	}

	function toggleSaveButton() {
		var count = Object.keys(dirty).length;

		$saveBtn.prop('disabled', count === 0);

		var label = i18n.saveChanges || 'Save Changes';
		$saveBtn.text(count > 0 ? label + ' (' + count + ')' : label);
	}

	// -------------------------------------------------------------------------
	// Character count
	// -------------------------------------------------------------------------

	function updateCount($input) {
		var $count = $input.closest('td').find('.cwp-bulk-count');
		var max    = parseInt($count.data('max'), 10) || 0;
		var length = $input.val().length;

		$count.text(length + ' / ' + max);
		$count.toggleClass('is-over', max > 0 && length > max);
	}

	// -------------------------------------------------------------------------
	// Notices
	// -------------------------------------------------------------------------

	function showNotice(message, isError) {
		$notice
			.text(message)
			.toggleClass('cwp-bulk-notice--error', !!isError)
			.toggleClass('cwp-bulk-notice--success', !isError)
			.prop('hidden', false);

		setTimeout(hideNotice, 4000);
	}

	function hideNotice() {
		$notice.prop('hidden', true);
	}

	// -------------------------------------------------------------------------
	// Pagination
	// -------------------------------------------------------------------------

	/**
	 * Render numbered pagination buttons.
	 *
	 * @param {number} currentPage
	 * @param {number} totalPages
	 */
	function renderPagination(currentPage, totalPages) {
		if (totalPages <= 1) {
			$pageLinks.html('');
			return;
		}

		var html = '';

		if (currentPage > 1) {
			html += '<button class="cwp-bulk-page-btn button" data-page="' + (currentPage - 1) + '">&laquo;</button> ';
		}

		var start = Math.max(1, currentPage - 3);
		var end   = Math.min(totalPages, currentPage + 3);

		for (var p = start; p <= end; p++) {
			var active = (p === currentPage) ? ' button-primary' : '';
			html += '<button class="cwp-bulk-page-btn button' + active + '" data-page="' + p + '">' + p + '</button> ';
		}

		if (currentPage < totalPages) {
			html += '<button class="cwp-bulk-page-btn button" data-page="' + (currentPage + 1) + '">&raquo;</button>';
		}

		$pageLinks.html(html);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	function escapeHtml(str) {
		return $('<div>').text(str).html();
	}

}(jQuery));
