(function () {

	'use strict';

	/**
	 * CrawlWP SEO "Title & Meta" settings enhancer.
	 *
	 * Progressive enhancement for every [data-cwp-tm] input/textarea rendered by
	 * the WordPress Settings API. For each field we add:
	 *
	 *   - a live resolved preview of the template,
	 *   - an "insert variable" dropdown anchored inside the field.
	 *
	 * The script never throws: a missing or malformed window.crawlwpTitleMeta
	 * simply means no enhancement happens.
	 */

	var DATA_ATTR  = 'data-cwp-tm';
	var INIT_FLAG  = 'cwpTmReady';
	var openPanel  = null;

	/* ---------- configuration ---------- */

	/** Read the localized payload defensively; every key is optional. */
	function getConfig() {
		var raw = window.crawlwpTitleMeta;

		if (!raw || typeof raw !== 'object') {
			return null;
		}

		return {
			separator: typeof raw.separator === 'string' && raw.separator !== '' ? raw.separator : '-',
			variables: Array.isArray(raw.variables) ? raw.variables : [],
			entityVariables: raw.entityVariables && typeof raw.entityVariables === 'object' ? raw.entityVariables : {},
			samples: raw.samples && typeof raw.samples === 'object' ? raw.samples : {},
			i18n: raw.i18n && typeof raw.i18n === 'object' ? raw.i18n : {}
		};
	}

	function text(config, key, fallback) {
		var value = config.i18n[key];

		return typeof value === 'string' && value !== '' ? value : fallback;
	}

	/* ---------- template resolution ---------- */

	function escapeRegExp(value) {
		return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}

	/**
	 * Resolve a template into its preview string.
	 *
	 * Tokens are written as {{ token }} with optional inner spaces. A token is
	 * looked up in samples[entity] first, then in samples.global; anything still
	 * unknown resolves to an empty string.
	 *
	 * Dropping tokens leaves debris behind ("A - - B", " - B", "A  B"), so the
	 * substituted string is cleaned up afterwards:
	 *   1. runs of 2+ separators (and the spaces around them) become one,
	 *   2. a leading or trailing separator is stripped,
	 *   3. repeated whitespace collapses to a single space.
	 */
	function resolve(config, template, entity) {
		var entitySamples = config.samples[entity] && typeof config.samples[entity] === 'object' ? config.samples[entity] : {};
		var globalSamples = config.samples.global && typeof config.samples.global === 'object' ? config.samples.global : {};

		var resolved = String(template == null ? '' : template).replace(/\{\{\s*([^{}]+?)\s*\}\}/g, function (match, token) {
			var key = token.trim();

			if (Object.prototype.hasOwnProperty.call(entitySamples, key)) {
				return entitySamples[key] == null ? '' : String(entitySamples[key]);
			}

			if (Object.prototype.hasOwnProperty.call(globalSamples, key)) {
				return globalSamples[key] == null ? '' : String(globalSamples[key]);
			}

			return '';
		});

		return cleanup(config, resolved);
	}

	function cleanup(config, value) {
		var sep = escapeRegExp(config.separator);

		/* "A - - B" -> "A - B" */
		value = value.replace(new RegExp('(?:\\s*' + sep + '\\s*){2,}', 'g'), ' ' + config.separator + ' ');

		/* " - B" / "A - " -> "B" / "A" */
		value = value.replace(new RegExp('^\\s*(?:' + sep + '\\s*)+'), '');
		value = value.replace(new RegExp('(?:\\s*' + sep + ')+\\s*$'), '');

		/* "A  B" -> "A B" */
		value = value.replace(/[ \t]{2,}/g, ' ');

		return value.trim();
	}

	/* ---------- DOM helpers ---------- */

	function el(tag, className) {
		var node = document.createElement(tag);

		if (className) {
			node.className = className;
		}

		return node;
	}

	/* ---------- field controller ---------- */

	function initField(config, control) {
		/* guard against double initialisation (refresh() may be called again) */
		if (control[INIT_FLAG] || control.getAttribute('data-cwp-tm-ready') === '1') {
			return;
		}

		control[INIT_FLAG] = true;
		control.setAttribute('data-cwp-tm-ready', '1');

		var entity = control.getAttribute('data-cwp-entity') || '';

		/* 1. wrap the control so the trigger button can be positioned inside it */
		var field  = el('div', 'cwp-tm-field');
		var parent = control.parentNode;

		if (!parent) {
			return;
		}

		parent.insertBefore(field, control);
		field.appendChild(control);

		var trigger = el('button', 'cwp-tm-vars');
		trigger.type = 'button';
		trigger.setAttribute('aria-haspopup', 'true');
		trigger.setAttribute('aria-expanded', 'false');
		trigger.setAttribute('title', text(config, 'insertVariable', 'Insert variable'));
		trigger.appendChild(document.createTextNode('\u2026'));
		field.appendChild(trigger);

		/* 2. readout goes right after the field, before any p.description hint */
		var readout = el('div', 'cwp-tm-readout');
		var preview = el('div', 'cwp-tm-preview');

		readout.appendChild(preview);

		if (field.nextSibling) {
			parent.insertBefore(readout, field.nextSibling);
		} else {
			parent.appendChild(readout);
		}

		function recompute() {
			var resolved = resolve(config, control.value, entity);
			var length   = resolved.length;

			preview.innerHTML = '';

			var label = el('span', 'cwp-tm-preview__label');
			label.appendChild(document.createTextNode(text(config, 'previewLabel', 'Preview:') + ' '));
			preview.appendChild(label);

			if (length === 0) {
				var empty = el('span', 'cwp-tm-preview__empty');
				empty.appendChild(document.createTextNode(text(config, 'emptyPreview', 'Nothing will be output.')));
				preview.appendChild(empty);
			} else {
				preview.appendChild(document.createTextNode(resolved));
			}
		}

		control.addEventListener('input', recompute);
		control.addEventListener('change', recompute);

		trigger.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			togglePanel(config, field, control, trigger, entity, recompute);
		});

		recompute();
	}

	/* ---------- variables dropdown ---------- */

	function togglePanel(config, field, control, trigger, entity, recompute) {
		if (openPanel && openPanel.trigger === trigger) {
			closePanel();

			return;
		}

		closePanel();

		var panel = trigger[INIT_FLAG + 'Panel'];

		if (!panel) {
			panel = buildPanel(config, control, trigger, entity, recompute);
			trigger[INIT_FLAG + 'Panel'] = panel;
			field.appendChild(panel.root);
		}

		panel.root.style.display = 'block';
		trigger.setAttribute('aria-expanded', 'true');
		openPanel = panel;
		panel.activeIndex = -1;
		panel.search.value = '';
		panel.filter('');

		if (panel.search.focus) {
			panel.search.focus();
		}
	}

	function closePanel(returnFocus) {
		if (!openPanel) {
			return;
		}

		var panel = openPanel;
		openPanel = null;

		panel.root.style.display = 'none';
		panel.trigger.setAttribute('aria-expanded', 'false');

		if (returnFocus && panel.trigger.focus) {
			panel.trigger.focus();
		}
	}

	function buildPanel(config, control, trigger, entity, recompute) {
		var root = el('div', 'cwp-tm-panel');
		root.style.display = 'none';

		var search = el('input', 'cwp-tm-panel__search');
		search.type = 'search';
		search.setAttribute('placeholder', text(config, 'searchVariables', 'Search variables\u2026'));
		root.appendChild(search);

		var empty = el('div', 'cwp-tm-panel__empty');
		empty.appendChild(document.createTextNode(text(config, 'noVariables', 'No matching variables.')));
		empty.style.display = 'none';

		var groups = [];
		var items  = [];

		/* Use entity-specific variable groups when available; fall back to the global list. */
		var variableList = (entity && config.entityVariables[entity] && Array.isArray(config.entityVariables[entity]))
			? config.entityVariables[entity]
			: config.variables;

		variableList.forEach(function (group) {
			if (!group || typeof group !== 'object') {
				return;
			}

			var groupRoot  = el('div', 'cwp-tm-panel__group');
			var groupLabel = el('div', 'cwp-tm-panel__label');
			groupLabel.appendChild(document.createTextNode(group.label == null ? '' : String(group.label)));
			groupRoot.appendChild(groupLabel);

			var groupItems = [];

			(Array.isArray(group.items) ? group.items : []).forEach(function (item) {
				if (!item || typeof item !== 'object' || item.token == null) {
					return;
				}

				var token = String(item.token);
				var desc  = item.desc == null ? '' : String(item.desc);

				var button = el('button', 'cwp-tm-panel__item');
				button.type = 'button';
				button.setAttribute('data-token', token);

				var code = document.createElement('code');
				code.appendChild(document.createTextNode('{{ ' + token + ' }}'));
				button.appendChild(code);

				var descNode = document.createElement('span');
				descNode.appendChild(document.createTextNode(desc));
				button.appendChild(descNode);

				button.addEventListener('click', function (event) {
					event.preventDefault();
					insertToken(control, token, recompute);
					closePanel();
				});

				groupRoot.appendChild(button);
				groupItems.push({ node: button, haystack: (token + ' ' + desc).toLowerCase() });
				items.push({ node: button, haystack: (token + ' ' + desc).toLowerCase() });
			});

			root.appendChild(groupRoot);
			groups.push({ root: groupRoot, items: groupItems });
		});

		root.appendChild(empty);

		var panel = {
			root: root,
			search: search,
			trigger: trigger,
			items: items,
			visible: [],
			activeIndex: -1
		};

		/* filter items by token AND description, hiding empty groups */
		panel.filter = function (query) {
			var needle = String(query).toLowerCase().trim();
			var shown  = [];

			groups.forEach(function (group) {
				var groupShown = 0;

				group.items.forEach(function (item) {
					var match = needle === '' || item.haystack.indexOf(needle) !== -1;

					item.node.style.display = match ? '' : 'none';
					item.node.classList.remove('is-active');

					if (match) {
						groupShown++;
						shown.push(item.node);
					}
				});

				group.root.style.display = groupShown > 0 ? '' : 'none';
			});

			empty.style.display = shown.length === 0 ? 'block' : 'none';
			panel.visible = shown;
			panel.activeIndex = -1;
		};

		panel.setActive = function (index) {
			if (!panel.visible.length) {
				return;
			}

			if (index < 0) {
				index = panel.visible.length - 1;
			}

			if (index >= panel.visible.length) {
				index = 0;
			}

			panel.visible.forEach(function (node) {
				node.classList.remove('is-active');
			});

			panel.activeIndex = index;

			var active = panel.visible[index];
			active.classList.add('is-active');

			if (active.focus) {
				active.focus();
			}
		};

		search.addEventListener('input', function () {
			panel.filter(search.value);
		});

		root.addEventListener('click', function (event) {
			event.stopPropagation();
		});

		root.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' || event.keyCode === 27) {
				event.preventDefault();
				closePanel(true);

				return;
			}

			if (event.key === 'ArrowDown' || event.keyCode === 40) {
				event.preventDefault();
				panel.setActive(panel.activeIndex + 1);

				return;
			}

			if (event.key === 'ArrowUp' || event.keyCode === 38) {
				event.preventDefault();
				panel.setActive(panel.activeIndex - 1);

				return;
			}

			if ((event.key === 'Enter' || event.keyCode === 13) && event.target !== search) {
				event.preventDefault();

				if (event.target.getAttribute && event.target.getAttribute('data-token')) {
					insertToken(control, event.target.getAttribute('data-token'), recompute);
					closePanel(true);
				}
			}
		});

		return panel;
	}

	/** Insert "{{ token }}" at the caret, keeping focus and caret position sane. */
	function insertToken(control, token, recompute) {
		var snippet = '{{ ' + token + ' }}';
		var value   = control.value == null ? '' : String(control.value);
		var start   = typeof control.selectionStart === 'number' ? control.selectionStart : null;
		var end     = typeof control.selectionEnd === 'number' ? control.selectionEnd : null;
		var caret;

		if (start === null || end === null) {
			control.value = value + snippet;
			caret = control.value.length;
		} else {
			control.value = value.slice(0, start) + snippet + value.slice(end);
			caret = start + snippet.length;
		}

		if (control.focus) {
			control.focus();
		}

		if (control.setSelectionRange) {
			try {
				control.setSelectionRange(caret, caret);
			} catch (e) {
				/* some controls disallow selection ranges; harmless */
			}
		}

		recompute();

		control.dispatchEvent(new Event('input', { bubbles: true }));
	}

	/* ---------- bootstrap ---------- */

	function initAll() {
		var config = getConfig();

		if (!config) {
			return;
		}

		var controls = document.querySelectorAll('[' + DATA_ATTR + ']');

		for (var i = 0; i < controls.length; i++) {
			try {
				initField(config, controls[i]);
			} catch (e) {
				/* never let one broken row break the rest of the screen */
			}
		}
	}

	document.addEventListener('click', function () {
		closePanel();
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' || event.keyCode === 27) {
			closePanel(true);
		}
	});

	document.addEventListener('DOMContentLoaded', function () {
		initAll();

		if (window.crawlwpTitleMeta && typeof window.crawlwpTitleMeta === 'object') {
			window.crawlwpTitleMeta.refresh = initAll;
		}
	});

}());
