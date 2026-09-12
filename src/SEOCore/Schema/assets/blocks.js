/* global wp */
(function () {
	if (!wp || !wp.blocks) {
		return;
	}

	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var __ = wp.i18n.__;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var Button = wp.components.Button;
	var Fragment = wp.element.Fragment;

	registerBlockType('crawlwp/faq', {
		title: __('CrawlWP FAQ', 'mihdan-index-now'),
		icon: 'editor-help',
		category: 'widgets',
		attributes: {
			items: { type: 'array', default: [] }
		},
		edit: function (props) {
			var items = props.attributes.items || [];

			function update(i, key, value) {
				var next = items.slice();
				next[i] = Object.assign({}, next[i] || {}, {});
				next[i][key] = value;
				props.setAttributes({ items: next });
			}

			function add() {
				props.setAttributes({ items: items.concat([{ question: '', answer: '' }]) });
			}

			return el(Fragment, {},
				el('p', {}, __('FAQ items (also output as FAQPage schema).', 'mihdan-index-now')),
				items.map(function (item, i) {
					return el('div', { key: i, style: { marginBottom: '12px' } },
						el(TextControl, {
							label: __('Question', 'mihdan-index-now'),
							value: item.question || '',
							onChange: function (v) { update(i, 'question', v); }
						}),
						el(TextareaControl, {
							label: __('Answer', 'mihdan-index-now'),
							value: item.answer || '',
							onChange: function (v) { update(i, 'answer', v); }
						})
					);
				}),
				el(Button, { isSecondary: true, onClick: add }, __('Add question', 'mihdan-index-now'))
			);
		},
		save: function () {
			return null;
		}
	});

	registerBlockType('crawlwp/howto', {
		title: __('CrawlWP HowTo', 'mihdan-index-now'),
		icon: 'list-view',
		category: 'widgets',
		attributes: {
			name: { type: 'string', default: '' },
			steps: { type: 'array', default: [] }
		},
		edit: function (props) {
			var steps = props.attributes.steps || [];

			function updateStep(i, value) {
				var next = steps.slice();
				next[i] = { text: value };
				props.setAttributes({ steps: next });
			}

			return el(Fragment, {},
				el(TextControl, {
					label: __('How-to title', 'mihdan-index-now'),
					value: props.attributes.name || '',
					onChange: function (v) { props.setAttributes({ name: v }); }
				}),
				steps.map(function (step, i) {
					return el(TextControl, {
						key: i,
						label: __('Step', 'mihdan-index-now') + ' ' + (i + 1),
						value: (step && step.text) || '',
						onChange: function (v) { updateStep(i, v); }
					});
				}),
				el(Button, {
					isSecondary: true,
					onClick: function () {
						props.setAttributes({ steps: steps.concat([{ text: '' }]) });
					}
				}, __('Add step', 'mihdan-index-now'))
			);
		},
		save: function () {
			return null;
		}
	});

	registerBlockType('crawlwp/toc', {
		title: __('CrawlWP Table of Contents', 'mihdan-index-now'),
		icon: 'list-view',
		category: 'widgets',
		attributes: {
			title: { type: 'string', default: '' }
		},
		edit: function (props) {
			return el(TextControl, {
				label: __('Heading', 'mihdan-index-now'),
				value: props.attributes.title || '',
				placeholder: __('Table of contents', 'mihdan-index-now'),
				onChange: function (v) { props.setAttributes({ title: v }); }
			});
		},
		save: function () {
			return null;
		}
	});
}());
