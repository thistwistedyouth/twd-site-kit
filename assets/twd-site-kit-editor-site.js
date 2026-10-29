/* TWD Site Kit: front-end editor, the Site tab. Vanilla JS, no build step.
 *
 * Loaded only for administrators, after the main editor script, and never in safe mode.
 * It changes the look of the site: which style pack is used, and colour, font and corner
 * changes on top of it. Changes show on this page at once by setting the --twd-site-*
 * custom properties on the page's root element. Nothing is saved until Save is pressed,
 * and the server checks every value again and refuses unreadable button or band text. */
(function () {
	'use strict';

	var ED = window.TWD_SK_ED;
	if (!ED) {
		return;
	}
	var el = ED.el;
	var W = ED.W || function (k, f) {
		return f;
	};
	var WORDS = (ED.cfg && ED.cfg.words) || {};
	var FACTS = W('facts', 'practice facts');
	var FACTS_TITLE = W('Facts', 'Practice facts');
	var clear = ED.clear;
	var show = ED.show;
	var button = ED.button;
	var message = ED.message;
	var listInto = ED.listInto;
	var api = ED.api;

	var PREFIX = '--twd-site-';
	var S = { data: null, pack: '', draft: {}, applied: [] };
	var ui = {};

	/* contrast:start */
	function parseColor(v) {
		if (typeof v !== 'string') {
			return null;
		}
		v = v.trim();
		var m = /^#([0-9a-f]{3})$/i.exec(v);
		if (m) {
			return [parseInt(m[1].charAt(0) + m[1].charAt(0), 16), parseInt(m[1].charAt(1) + m[1].charAt(1), 16), parseInt(m[1].charAt(2) + m[1].charAt(2), 16)];
		}
		m = /^#([0-9a-f]{6})$/i.exec(v);
		if (m) {
			return [parseInt(m[1].substr(0, 2), 16), parseInt(m[1].substr(2, 2), 16), parseInt(m[1].substr(4, 2), 16)];
		}
		m = /^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/.exec(v);
		if (m) {
			return [Math.min(255, +m[1]), Math.min(255, +m[2]), Math.min(255, +m[3])];
		}
		return null;
	}
	function luminance(rgb) {
		var c = rgb.map(function (v) {
			v = v / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		});
		return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
	}
	function contrastRatio(a, b) {
		var x = parseColor(a);
		var y = parseColor(b);
		if (!x || !y) {
			return null;
		}
		var lx = luminance(x);
		var ly = luminance(y);
		if (lx < ly) {
			var t = lx;
			lx = ly;
			ly = t;
		}
		return (lx + 0.05) / (ly + 0.05);
	}
	/* contrast:end */

	function toHex(value) {
		var rgb = parseColor(value);
		if (!rgb) {
			return null;
		}
		return '#' + rgb.map(function (n) {
			var h = n.toString(16);
			return h.length < 2 ? '0' + h : h;
		}).join('');
	}

	function packTokens(slug) {
		var found = {};
		S.data.packs.forEach(function (p) {
			if (p.slug === slug) {
				found = p.tokens;
			}
		});
		return found;
	}

	// The pack's own values with the draft changes on top.
	function effective() {
		var out = {};
		var base = packTokens(S.pack);
		Object.keys(base).forEach(function (k) {
			out[k] = base[k];
		});
		Object.keys(S.draft).forEach(function (k) {
			out[k] = S.draft[k];
		});
		return out;
	}

	/* ---- live preview ---- */

	function cssValue(name, value) {
		if (name === 'font-heading' || name === 'font-body' || name === 'menu-font') {
			return (S.data.font_css && S.data.font_css[value]) || '';
		}
		return value;
	}

	function previewAll() {
		var root = document.documentElement;
		var tokens = effective();
		Object.keys(tokens).forEach(function (name) {
			var value = cssValue(name, tokens[name]);
			if (value) {
				root.style.setProperty(PREFIX + name, value);
				if (S.applied.indexOf(name) < 0) {
					S.applied.push(name);
				}
			}
		});
	}

	function clearPreview() {
		var root = document.documentElement;
		S.applied.forEach(function (name) {
			root.style.removeProperty(PREFIX + name);
		});
		S.applied = [];
	}

	/* ---- contrast feedback ---- */

	// A see-through colour (rgba, 8-digit hex, transparent) laid over an opaque one: the colour a visitor sees (0.15.0).
	function flattenColor(top, under) {
		var u = parseColor(under);
		if (!u) {
			return null;
		}
		var t = parseColor(top);
		if (t) {
			return 'rgb(' + t.join(',') + ')';
		}
		var a = null;
		var m = /^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(0|1|0?\.\d{1,3}|1\.0+)\s*\)$/.exec(String(top || '').trim());
		if (m) {
			t = [Math.min(255, +m[1]), Math.min(255, +m[2]), Math.min(255, +m[3])];
			a = parseFloat(m[4]);
		} else if ((m = /^#([0-9a-fA-F]{8})$/.exec(String(top || '').trim()))) {
			t = [parseInt(m[1].slice(0, 2), 16), parseInt(m[1].slice(2, 4), 16), parseInt(m[1].slice(4, 6), 16)];
			a = parseInt(m[1].slice(6, 8), 16) / 255;
		} else if (String(top || '').trim() === 'transparent') {
			t = [0, 0, 0];
			a = 0;
		}
		if (a === null) {
			return null;
		}
		return 'rgb(' + t.map(function (c, i) {
			return Math.round(c * a + u[i] * (1 - a));
		}).join(',') + ')';
	}

	// Layers from the bottom up (a literal colour or a token name each) as one opaque colour, or null.
	function stackColors(layers, tokens) {
		var base = null;
		for (var i = 0; i < layers.length; i++) {
			var v = tokens[layers[i]] !== undefined ? tokens[layers[i]] : layers[i];
			base = base === null ? flattenColor(v, '#000000') : flattenColor(v, base);
			if (base === null) {
				return null;
			}
		}
		return base;
	}

	function evaluate() {
		var tokens = effective();
		var blocking = [];
		var warnings = [];
		S.data.pairs.forEach(function (p) {
			var bg = tokens[p.bg];
			if (p.under) {
				var under = stackColors(p.under, tokens);
				bg = under === null ? null : flattenColor(bg, under);
			}
			var r = contrastRatio(tokens[p.fg], bg);
			if (r === null || r >= 4.5) {
				return;
			}
			(p.level === 'block' ? blocking : warnings).push({ label: p.label, ratio: Math.round(r * 100) / 100 });
		});
		return { blocking: blocking, warnings: warnings };
	}

	function renderContrast() {
		var res = evaluate();
		clear(ui.contrast);
		var lines = function (items) {
			return items.map(function (i) {
				return i.label + ' (' + i.ratio + ':1, needs 4.5:1)';
			});
		};
		if (res.blocking.length) {
			ui.contrast.appendChild(el('strong', { text: 'You cannot save yet. This text would be hard to read:' }));
			listInto(ui.contrast, lines(res.blocking));
		}
		if (res.warnings.length) {
			ui.contrast.appendChild(el('strong', { text: 'Worth fixing (saving is still allowed):' }));
			listInto(ui.contrast, lines(res.warnings));
		}
		if (!res.blocking.length && !res.warnings.length) {
			ui.contrast.appendChild(document.createTextNode('All the text and button pairs are easy to read.'));
		}
		show(ui.contrast, true);
		ui.saveBtn.disabled = res.blocking.length > 0;
	}

	/* ---- the controls ---- */

	function setToken(name, value) {
		S.draft[name] = value;
		previewAll();
		renderContrast();
		message(ui.msg, 'This is a preview. Press Save style to keep it.', true);
	}

	function colourControl(item, value) {
		var hex = toHex(value);
		var id = 'twd-sk-site-' + item.token;
		var input = el('input', { id: id, type: 'color', className: 'twd-sk-ed__color' });
		var readout = el('code', { className: 'twd-sk-ed__hex', text: hex || String(value) });
		if (hex) {
			input.value = hex;
			input.addEventListener('input', function () {
				readout.textContent = input.value;
				setToken(item.token, input.value);
			});
		} else {
			input.disabled = true;
		}
		return el('div', { className: 'twd-sk-ed__field' }, [
			el('label', { className: 'twd-sk-ed__label', 'for': id, text: item.label }),
			el('div', { className: 'twd-sk-ed__inline' }, [input, readout])
		]);
	}

	function fontControl(item, value) {
		var id = 'twd-sk-site-' + item.token;
		var select = el('select', { id: id, className: 'twd-sk-ed__select' });
		S.data.fonts.forEach(function (f) {
			var opt = el('option', { value: f, text: f });
			if (f === value) {
				opt.setAttribute('selected', '');
			}
			select.appendChild(opt);
		});
		select.value = value;
		select.addEventListener('change', function () {
			setToken(item.token, select.value);
		});
		return el('div', { className: 'twd-sk-ed__field' }, [
			el('label', { className: 'twd-sk-ed__label', 'for': id, text: item.label }),
			select
		]);
	}

	function radiusControl(item, value) {
		var id = 'twd-sk-site-' + item.token;
		var m = /^(\d{1,3}(?:\.\d+)?)px$/.exec(String(value));
		// Corners go up to 100 pixels; a logo size (0.14.0) up to 400.
		var top = item.kind === 'size' ? 400 : 100;
		var input = el('input', { id: id, type: 'number', min: '0', max: String(top), step: '1', className: 'twd-sk-ed__input' });
		if (m) {
			input.value = String(Math.round(parseFloat(m[1])));
			input.addEventListener('input', function () {
				var n = Math.max(0, Math.min(top, parseInt(input.value, 10) || 0));
				setToken(item.token, n + 'px');
			});
		} else {
			input.disabled = true;
			input.value = String(value);
		}
		return el('div', { className: 'twd-sk-ed__field' }, [
			el('label', { className: 'twd-sk-ed__label', 'for': id, text: item.label + ' (pixels)' }),
			input
		]);
	}

	// A short list of ready-made choices (the picture colour treatment and tint, 0.15.0). A value from the style
	// itself that is not on the list is kept as the first choice, so nothing is lost by opening this.
	function choiceControl(item, value) {
		var id = 'twd-sk-site-' + item.token;
		var list = ((S.data.presets || {})[item.token] || []).slice();
		var known = list.some(function (p) {
			return p[0] === value;
		});
		if (!known) {
			list.unshift([value, 'The style\'s own setting']);
		}
		var select = el('select', { id: id, className: 'twd-sk-ed__input' });
		list.forEach(function (p) {
			select.appendChild(el('option', { value: p[0], text: p[1] }));
		});
		select.value = value;
		select.addEventListener('change', function () {
			setToken(item.token, select.value);
		});
		return el('div', { className: 'twd-sk-ed__field' }, [
			el('label', { className: 'twd-sk-ed__label', 'for': id, text: item.label }),
			select
		]);
	}

	function renderControls() {
		clear(ui.controls);
		var tokens = effective();
		var groups = { colours: 'Colours', fonts: 'Fonts', text: 'Text (pixels)', shape: 'Corners', pictures: 'Pictures', logo: 'Logo sizes (pixels high)', header: 'Header (pixels)' };
		['colours', 'fonts', 'text', 'shape', 'pictures', 'logo'].forEach(function (group) {
			var grid = el('div', { className: 'twd-sk-ed__grid' });
			S.data.editable.forEach(function (item) {
				if (item.group !== group) {
					return;
				}
				var value = tokens[item.token];
				grid.appendChild(item.kind === 'color' ? colourControl(item, value) : item.kind === 'font' ? fontControl(item, value) : item.kind === 'choice' ? choiceControl(item, value) : radiusControl(item, value));
			});
			ui.controls.appendChild(el('h4', { className: 'twd-sk-ed__subtitle', text: groups[group] }));
			ui.controls.appendChild(grid);
		});
		renderContrast();
	}

	function renderPacks() {
		clear(ui.packs);
		S.data.packs.forEach(function (p) {
			var id = 'twd-sk-site-pack-' + p.slug;
			var radio = el('input', { id: id, type: 'radio', name: 'twd-sk-site-pack', value: p.slug });
			if (p.slug === S.pack) {
				radio.checked = true;
			}
			radio.addEventListener('change', function () {
				S.pack = p.slug;
				S.draft = {};
				clearPreview();
				previewAll();
				renderControls();
				message(ui.msg, p.slug === S.data.active ? 'Back to the saved style.' : 'This is a preview of the ' + p.name + ' style. Press Save style to keep it. Your colour changes are cleared when the style changes.', true);
			});
			ui.packs.appendChild(el('label', { className: 'twd-sk-ed__check', 'for': id }, [
				radio,
				el('span', {}, [el('strong', { text: p.name }), el('span', { className: 'twd-sk-ed__version-note', text: p.description })])
			]));
		});
	}

	function draw() {
		S.pack = S.data.active;
		S.draft = {};
		var o = S.data.overrides || {};
		Object.keys(o).forEach(function (k) {
			S.draft[k] = o[k];
		});
		renderPacks();
		renderControls();
	}

	function load() {
		return api('GET', '/site').then(function (data) {
			S.data = data;
			draw();
		}, function (err) {
			message(ui.msg, err.message, false);
		});
	}

	function save() {
		var body = { tokens: S.draft };
		if (S.pack !== S.data.active) {
			body.pack = S.pack;
		}
		message(ui.msg, 'Saving...', true);
		ui.saveBtn.disabled = true;
		api('POST', '/site/style', body).then(function (data) {
			loadHistory();
			S.data = data;
			clearPreview();
			draw();
			previewAll();
			message(ui.msg, 'Saved. Reload the page to see it everywhere.', true);
			show(ui.reloadBtn, true);
		}, function (err) {
			message(ui.msg, err.message, false);
			renderContrast();
		});
	}

	function discard() {
		clearPreview();
		draw();
		message(ui.msg, 'Changes discarded. This is the saved style again.', true);
	}

	function resetAll() {
		ED.confirmInline(ui.resetHost, {
			text: 'Reset every colour, font and corner change to the style\'s own look? This saves straight away.',
			yesLabel: 'Yes, reset',
			onYes: function () {
				message(ui.msg, 'Resetting...', true);
				api('POST', '/site/style/reset', {}).then(function (data) {
					S.data = data;
					clearPreview();
					draw();
					message(ui.msg, 'Reset. Reload the page to see it everywhere.', true);
					show(ui.reloadBtn, true);
				}, function (err) {
					message(ui.msg, err.message, false);
				});
			}
		});
	}

	function buildStyleSection(panel) {
		ui.packs = el('div', { className: 'twd-sk-ed__packs', role: 'radiogroup', 'aria-label': 'Style' });
		ui.controls = el('div', {});
		ui.contrast = el('div', { className: 'twd-sk-ed__banner', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.saveBtn = button('Save style', 'primary', save);
		ui.saveBtn.disabled = true;
		ui.discardBtn = button('Discard changes', 'secondary', discard);
		ui.resetBtn = button('Reset to the style\'s own look', 'secondary', resetAll);
		ui.reloadBtn = button('Reload the page to see it', 'secondary', function () {
			window.location.reload();
		});
		show(ui.reloadBtn, false);
		ui.resetHost = el('div', {});
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Style' }),
			el('p', { className: 'twd-sk-ed__help', text: 'Pick a style, then change colours, fonts and corners if you need to. Changes show on this page straight away. Nothing is kept until you press Save style.' })
		].concat(aiBlocks(['style'])).concat([brandBlock()]).concat([
			ui.packs,
			ui.controls,
			ui.contrast,
			el('div', { className: 'twd-sk-ed__actions' }, [ui.saveBtn, ui.discardBtn, ui.resetBtn, ui.reloadBtn]),
			ui.resetHost,
			ui.msg
		])));
		load();
	}

	/* ---- site details (the profile) and the header and footer ---- */

	var P = { data: null };
	var pui = {};

	function field(labelText, control, help) {
		var id = control.getAttribute('id');
		var kids = [el('label', { className: 'twd-sk-ed__label', 'for': id, text: labelText })];
		if (help) {
			kids.push(el('p', { className: 'twd-sk-ed__help', text: help }));
		}
		kids.push(control);
		return el('div', { className: 'twd-sk-ed__field' }, kids);
	}

	function textInput(id, maxlength) {
		return el('input', { id: id, type: 'text', className: 'twd-sk-ed__input', maxlength: String(maxlength), autocomplete: 'off' });
	}

	function textArea(id, rows) {
		return el('textarea', { id: id, className: 'twd-sk-ed__textarea', rows: String(rows), spellcheck: 'false' });
	}

	/* ---- footer text (0.15.0): up to three lines with bold, italic and a link ----
	 * A small toolbar wraps the selection in the box with the tiny allowed set (strong, em, a) or puts in a new line;
	 * the server keeps only that set (TWD_SK_Profile::rich) and prints line breaks. The footer on the page shows the
	 * words as typed: the preview builds nodes from the same marks (DOM calls only) and the saved words come back
	 * on Save or when the change is thrown away. */
	var FT = { orig: null, node: null };

	function footerTextNode() {
		return document.querySelector('.twd-sk-chrome .twd-sk-footer__text, .twd-sk-chrome .twd-sk-footer__tagline');
	}

	function decodeEntities(text) {
		return String(text).replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'");
	}

	// Nodes for light text: strong, em, a (with its address) and line breaks; anything else is plain words.
	function richNodes(text) {
		var frag = document.createDocumentFragment();
		var stack = [frag];
		var parts = String(text || '').replace(/\r\n?/g, '\n').replace(/<br\s*\/?>/gi, '\n').split(/(<\/?(?:strong|em|a|b|i)\b[^>]*>|\n)/i);
		parts.forEach(function (part) {
			if (!part) {
				return;
			}
			var top = stack[stack.length - 1];
			if (part === '\n') {
				top.appendChild(el('br'));
				return;
			}
			var m = /^<(\/?)(strong|em|a|b|i)\b([^>]*)>$/i.exec(part);
			if (!m) {
				top.appendChild(document.createTextNode(decodeEntities(part)));
				return;
			}
			var tag = m[2].toLowerCase();
			tag = tag === 'b' ? 'strong' : (tag === 'i' ? 'em' : tag);
			if (m[1]) {
				if (stack.length > 1) {
					stack.pop();
				}
				return;
			}
			var node = el(tag);
			if (tag === 'a') {
				var h = /href\s*=\s*"([^"]*)"/i.exec(m[3]) || /href\s*=\s*'([^']*)'/i.exec(m[3]);
				if (h && /^(https?:|mailto:|tel:|\/|#)/i.test(h[1].trim())) {
					node.setAttribute('href', h[1].trim());
				}
			}
			top.appendChild(node);
			stack.push(node);
		});
		return frag;
	}

	function previewFooterText() {
		var node = footerTextNode();
		if (!node) {
			return;
		}
		if (FT.orig === null || FT.node !== node) {
			FT.node = node;
			FT.orig = [];
			Array.prototype.forEach.call(node.childNodes, function (n) {
				FT.orig.push(n.cloneNode(true));
			});
		}
		clear(node);
		node.appendChild(richNodes(pui.footerText.value));
	}

	function previewTextStyle() {
		var node = footerTextNode();
		if (!node || !cui.textStyle) {
			return;
		}
		var piece = node.classList.contains('twd-sk-footer__tagline') ? 'twd-sk-footer__tagline--lead' : 'twd-sk-footer__text--lead';
		node.classList.toggle(piece, cui.textStyle.value === 'lead');
	}

	// Put the saved words back in the footer (the change was thrown away, or saved and the page will be reloaded).
	function restoreFooterText() {
		if (FT.node && FT.orig) {
			clear(FT.node);
			FT.orig.forEach(function (n) {
				FT.node.appendChild(n);
			});
		}
		FT.orig = null;
		FT.node = null;
	}

	function wrapSelection(box, before, after) {
		var start = box.selectionStart;
		var end = box.selectionEnd;
		var v = box.value;
		var picked = v.slice(start, end);
		box.value = v.slice(0, start) + before + picked + after + v.slice(end);
		box.focus();
		box.setSelectionRange(start + before.length, start + before.length + picked.length);
		box.dispatchEvent(new Event('input', { bubbles: true }));
	}

	function footerTextField() {
		var box = pui.footerText;
		box.setAttribute('maxlength', '400');
		var linkRow = el('div', { className: 'twd-sk-ed__inline', hidden: '' });
		var linkInput = el('input', { type: 'text', id: 'twd-sk-pf-footerlink', className: 'twd-sk-ed__input', placeholder: '/contact, or a full web address', autocomplete: 'off', 'aria-label': 'Link address' });
		var linkSet = button('Set the link', 'secondary', function () {
			var href = linkInput.value.trim();
			if (!href) {
				linkInput.focus();
				return;
			}
			wrapSelection(box, '<a href="' + href.replace(/"/g, '') + '">', '</a>');
			show(linkRow, false);
		});
		linkRow.appendChild(linkInput);
		linkRow.appendChild(linkSet);
		var tool = function (label, title, run) {
			var b = el('button', { type: 'button', className: 'twd-sk-ed__tool', text: label, title: title, 'aria-label': title });
			b.addEventListener('click', run);
			return b;
		};
		var bar = el('div', { className: 'twd-sk-ed__inlinetools twd-sk-ed__texttools', role: 'group', 'aria-label': 'Footer text formatting' }, [
			tool('B', 'Bold: select some words first', function () {
				wrapSelection(box, '<strong>', '</strong>');
			}),
			tool('I', 'Italic: select some words first', function () {
				wrapSelection(box, '<em>', '</em>');
			}),
			tool('Link', 'Link the selected words to an address', function () {
				show(linkRow, linkRow.hasAttribute('hidden'));
				if (!linkRow.hasAttribute('hidden')) {
					linkInput.focus();
				}
			}),
			tool('New line', 'Start a new line here (up to three lines)', function () {
				wrapSelection(box, '\n', '');
			})
		]);
		box.addEventListener('input', previewFooterText);
		var node = field('Footer text', box, 'Up to three lines. Select words, then press B, I or Link. The footer below shows it as you type; nothing is saved until you press Save.');
		node.insertBefore(bar, box);
		node.appendChild(linkRow);
		return node;
	}

	// One line per link: "Label | /link". In the menu a line that starts with a dash is a sub-menu link under the line above.
	function linksToText(list, withChildren) {
		var lines = [];
		(list || []).forEach(function (item) {
			lines.push(item.label + ' | ' + item.url);
			if (withChildren) {
				(item.children || []).forEach(function (c) {
					lines.push('- ' + c.label + ' | ' + c.url);
				});
			}
		});
		return lines.join('\n');
	}

	function parseLinks(text, withChildren) {
		var list = [];
		var error = '';
		String(text).split('\n').forEach(function (raw) {
			var line = raw.trim();
			if (!line || error) {
				return;
			}
			var child = false;
			if (line.charAt(0) === '-') {
				child = true;
				line = line.substr(1).trim();
			}
			var bar = line.indexOf('|');
			if (bar < 1 || !line.substr(bar + 1).trim()) {
				error = 'Each line needs a label, a bar and a link, like: About | /about';
				return;
			}
			var item = { label: line.substr(0, bar).trim(), url: line.substr(bar + 1).trim() };
			if (child) {
				if (!withChildren || !list.length) {
					error = 'A sub-menu line (starting with a dash) must come under a menu line.';
					return;
				}
				list[list.length - 1].children.push(item);
			} else {
				if (withChildren) {
					item.children = [];
				}
				list.push(item);
			}
		});
		return { list: list, error: error };
	}

	function lines(text) {
		return String(text).split('\n').map(function (l) {
			return l.trim();
		}).filter(function (l) {
			return l;
		});
	}

	function pickMedia(done) {
		if (!(window.wp && window.wp.media)) {
			message(pui.msg, 'The media library is not available here. Type the picture number instead.', false);
			return;
		}
		var frame = window.wp.media({ title: 'Choose a logo', button: { text: 'Use this picture' }, multiple: false, library: { type: 'image' } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			done(a.id);
		});
		frame.open();
	}

	function fillProfile() {
		var p = P.data.profile;
		pui.name.value = p.site_name;
		pui.logo.value = p.logo_id ? String(p.logo_id) : '';
		pui.logoMark.value = p.logo_mark_id ? String(p.logo_mark_id) : '';
		pui.social.value = p.social_image_id ? String(p.social_image_id) : '';
		pui.logoLine.value = p.logo_line || '';
		pui.ctaLabel.value = p.cta_label;
		pui.ctaUrl.value = p.cta_url;
		pui.phone.value = p.phone;
		pui.email.value = p.email;
		pui.address.value = (p.address || []).join('\n');
		pui.area.value = p.area_served;
		pui.footerText.value = p.footer_text;
		pui.registration.value = (p.registration || []).join('\n');
		pui.menu.value = linksToText(p.menu, true);
		pui.legal.value = linksToText(p.legal, false);
		pui.personName.value = p.person_name;
		pui.personJob.value = p.person_job;
		pui.sameAs.value = (p.same_as || []).join('\n');
		renderChecks();
		pui.snap = profileSnap();
	}

	function renderChecks() {
		clear(pui.checks);
		var d = P.data;
		var must = d.leftovers.must || [];
		var check = d.leftovers.check || [];
		if (d.missing && d.missing.length) {
			pui.checks.appendChild(el('strong', { text: 'Still missing:' }));
			listInto(pui.checks, d.missing);
		}
		if (must.length) {
			pui.checks.appendChild(el('strong', { text: 'Example text still in the site details (replace it before the site goes live):' }));
			listInto(pui.checks, must.map(function (i) {
				return i.marker + ' (' + i.count + '): ' + i.meaning;
			}));
		}
		if (check.length) {
			pui.checks.appendChild(el('strong', { text: 'To check:' }));
			listInto(pui.checks, check.map(function (i) {
				return i.marker + ' (' + i.count + '): ' + i.meaning;
			}));
		}
		if (!(d.missing && d.missing.length) && !must.length && !check.length) {
			pui.checks.appendChild(document.createTextNode('The site details are complete.'));
		}
		show(pui.checks, true);
	}

	function collectProfile() {
		var menu = parseLinks(pui.menu.value, true);
		if (menu.error) {
			return { error: 'Menu: ' + menu.error };
		}
		var legal = parseLinks(pui.legal.value, false);
		if (legal.error) {
			return { error: 'Legal links: ' + legal.error };
		}
		var logo = pui.logo.value.trim();
		if (logo && !/^\d+$/.test(logo)) {
			return { error: 'The logo must be the number of a picture in the media library.' };
		}
		var mark = pui.logoMark.value.trim();
		if (mark && !/^\d+$/.test(mark)) {
			return { error: 'The small logo mark must be the number of a picture in the media library.' };
		}
		var social = pui.social.value.trim();
		if (social && !/^\d+$/.test(social)) {
			return { error: 'The default social picture must be the number of a picture in the media library.' };
		}
		return {
			profile: {
				site_name: pui.name.value,
				logo_id: logo ? parseInt(logo, 10) : 0,
				logo_mark_id: mark ? parseInt(mark, 10) : 0,
				social_image_id: social ? parseInt(social, 10) : 0,
				logo_line: pui.logoLine.value,
				menu: menu.list,
				cta_label: pui.ctaLabel.value,
				cta_url: pui.ctaUrl.value,
				phone: pui.phone.value,
				email: pui.email.value,
				address: lines(pui.address.value),
				area_served: pui.area.value,
				footer_text: pui.footerText.value,
				legal: legal.list,
				registration: lines(pui.registration.value),
				person_name: pui.personName.value,
				person_job: pui.personJob.value,
				same_as: lines(pui.sameAs.value)
			}
		};
	}

	function saveProfile() {
		var got = collectProfile();
		if (got.error) {
			message(pui.msg, got.error, false);
			return;
		}
		message(pui.msg, 'Saving...', true);
		api('POST', '/site/profile', { profile: got.profile }).then(function (data) {
			loadHistory();
			P.data = data;
			fillProfile();
			message(pui.msg, 'Saved. Reload the page to see the header and footer change.', true);
			show(pui.reloadBtn, true);
		}, function (err) {
			message(pui.msg, err.message, false);
		});
	}

	function buildProfileSection(panel) {
		pui.name = textInput('twd-sk-pf-name', 80);
		pui.logo = textInput('twd-sk-pf-logo', 12);
		pui.logoBtn = button('Choose from the media library', 'secondary', function () {
			pickMedia(function (id) {
				pui.logo.value = String(id);
			});
		});
		pui.logoMark = textInput('twd-sk-pf-logomark', 12);
		pui.logoMarkBtn = button('Choose from the media library', 'secondary', function () {
			pickMedia(function (id) {
				pui.logoMark.value = String(id);
			});
		});
		// Section 23d: the picture shared with a page that has no social picture of its own (empty: the logo).
		pui.social = textInput('twd-sk-pf-social', 12);
		pui.socialBtn = button('Choose from the media library', 'secondary', function () {
			pickMedia(function (id) {
				pui.social.value = String(id);
			});
		});
		pui.ctaLabel = textInput('twd-sk-pf-ctalabel', 30);
		pui.logoLine = textInput('twd-sk-pf-logoline', 40);
		pui.ctaUrl = textInput('twd-sk-pf-ctaurl', 300);
		pui.phone = textInput('twd-sk-pf-phone', 40);
		pui.email = textInput('twd-sk-pf-email', 100);
		pui.address = textArea('twd-sk-pf-address', 3);
		pui.area = textInput('twd-sk-pf-area', 120);
		pui.footerText = textArea('twd-sk-pf-footertext', 3);
		pui.registration = textArea('twd-sk-pf-registration', 3);
		pui.menu = textArea('twd-sk-pf-menu', 7);
		pui.legal = textArea('twd-sk-pf-legal', 3);
		pui.personName = textInput('twd-sk-pf-personname', 80);
		pui.personJob = textInput('twd-sk-pf-personjob', 80);
		pui.sameAs = textArea('twd-sk-pf-sameas', 3);
		// Business mode hides the job title; a value already saved is kept and still sent back unchanged.
		pui.personJobField = field(W('person_job_label', 'Therapist job title'), pui.personJob);
		show(pui.personJobField, WORDS.show_person_job !== false);
		pui.checks = el('div', { className: 'twd-sk-ed__banner', role: 'status', hidden: '' });
		pui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		pui.saveBtn = button('Save site details', 'primary', saveProfile);
		pui.reloadBtn = button('Reload the page to see it', 'secondary', function () {
			window.location.reload();
		});
		show(pui.reloadBtn, false);
		var logoRow = el('div', { className: 'twd-sk-ed__inline' }, [pui.logo, pui.logoBtn]);
		logoRow.setAttribute('id', 'twd-sk-pf-logo-row');
		var markRow = el('div', { className: 'twd-sk-ed__inline' }, [pui.logoMark, pui.logoMarkBtn]);
		markRow.setAttribute('id', 'twd-sk-pf-logomark-row');
		var socialRow = el('div', { className: 'twd-sk-ed__inline' }, [pui.social, pui.socialBtn]);
		socialRow.setAttribute('id', 'twd-sk-pf-social-row');
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Site details' }),
			el('p', { className: 'twd-sk-ed__help', text: 'These feed the header, the footer and the search engine details. Only add what the ' + W('person', 'therapist') + ' has given you. Leave a box empty rather than guess.' }),
			pui.checks,
			el('div', { className: 'twd-sk-ed__grid' }, [
				field('Site name', pui.name, 'Shown as text if there is no logo.'),
				field('Logo picture number', logoRow, 'Optional. A picture from the media library.'),
				field('Small logo mark picture number', markRow, 'Optional, a symbol-only version. Used beside the site name on every page but the front page when "Bigger logo on the front page" is on below.'),
				field('Default social picture number', socialRow, 'Optional. Shown when a page is shared on social media and has no social picture of its own in the Search tab. Empty means the logo is used.'),
				field('Second line under the name', pui.logoLine, 'Optional, a few words such as the kind of work. Shown under the name in the large front page header when "Text wordmark" is ticked in Header and footer. To show it in the smaller header too (other pages, and the front page once scrolled), also tick "Show the second line under the name in the smaller header too" there.'),
				field('Header button text', pui.ctaLabel),
				field('Header button link', pui.ctaUrl, 'A page on this site, such as /contact.'),
				field('Phone number', pui.phone),
				field('Email address', pui.email),
				field(W('area_label', 'Where you work'), pui.area, 'For example a town, or Online.'),
				field(W('person_name_label', 'Therapist name'), pui.personName, 'For the search engine details.'),
				pui.personJobField
			]),
			field('Address lines', pui.address, W('address_help', 'Up to three lines. Leave empty for an online practice.')),
			footerTextField(),
			field(W('registration_label', 'Registration lines'), pui.registration, W('registration_help', 'Only exactly what the therapist has supplied. One line each, up to four.')),
			field('Menu', pui.menu, 'One link per line: Label | /link. A line starting with a dash is a sub-menu link under the line above. Up to 8 menu items.'),
			field('Legal links', pui.legal, 'One per line: Label | /link. Shown in the footer.'),
			field('Professional profile links', pui.sameAs, 'Full https addresses, one per line, up to four. Optional.'),
			el('div', { className: 'twd-sk-ed__actions' }, [pui.saveBtn, pui.reloadBtn]),
			pui.msg
		]));
		P.ready = api('GET', '/site/profile').then(function (data) {
			P.data = data;
			fillProfile();
		}, function (err) {
			message(pui.msg, err.message, false);
		});
	}

	var cui = {};

	// The split header keeps the menu in a third of the bar: with six or more top-level items the bar header fits better (0.15.0, SK-017).
	function splitNote() {
		var items = P.data && P.data.profile && P.data.profile.menu ? P.data.profile.menu.length : 0;
		var split = cui.header.value === 'split' || (cui.header.value === '' && P.data && P.data.chrome && P.data.chrome.effective && P.data.chrome.effective.header === 'split');
		return (split && items >= 6 && !cui.wordmark.checked) ? ' With ' + items + ' menu items the split header runs out of room; the bar header fits them.' : '';
	}

	function fillChrome() {
		var c = P.data.chrome;
		cui.header.value = c.settings.header_variant;
		cui.footer.value = c.settings.footer_variant;
		cui.cols.value = String(c.settings.footer_columns);
		cui.textStyle.value = c.settings.footer_text_style || 'plain';
		previewTextStyle();
		cui.contactBar.checked = !!c.settings.contact_bar;
		cui.exit.checked = !!c.settings.quick_exit;
		cui.exitUrl.value = c.settings.quick_exit_url || '';
		cui.sticky.checked = !!c.settings.sticky;
		cui.shrink.checked = !!c.settings.shrink;
		cui.front.checked = !!c.settings.front_logo;
		cui.over.checked = !!c.settings.overlay;
		cui.wordmark.checked = !!c.settings.wordmark;
		cui.wmname.checked = !!c.settings.wordmark_scrolled_name;
		cui.wmline.checked = !!c.settings.wordmark_small_line;
		cui.clientFooter.checked = !!c.settings.client_footer_text;
		cui.marker.value = c.settings.menu_marker;
		cui.button.checked = !!c.settings.show_button;
		cui.strip.checked = !!c.settings.show_strip;
		cui.placement.value = c.settings.placement;
		var e = c.effective;
		cui.snap = chromeSnap();
		cui.note.textContent = 'Now showing: ' + (e.wordmark ? 'the text wordmark header (stacked on the front page, one row elsewhere)' : c.header_variants[e.header]) + ' (header), ' + c.footer_variants[e.footer] + ' (footer).' + (e.sticky_note ? ' The centred header cannot be fixed to the top, so that setting is ignored.' : '') + splitNote();
		fillPlacementNote();
	}

	/* Who places the header and footer, and whether the Theme Builder download is needed at all. */
	function fillPlacementNote() {
		var c = P.data.chrome;
		var kit = cui.placement.value === 'kit';
		var text = 'Placed now by: ' + c.placed_by + '.';
		if (kit && !c.hello) {
			text += ' Switch the theme to Hello (Appearance, Themes) or choose the Elementor option below.';
		} else if (kit) {
			text += ' Nothing to set up in Elementor. An Elementor Pro header or footer template, if you ever add one, takes over on the pages it covers.';
		} else {
			text += ' Use the download below for Elementor Pro\'s Theme Builder.';
		}
		cui.placedNote.textContent = text;
		show(cui.exportBtn, !kit);
	}

	function selectWith(id, options, emptyLabel) {
		var select = el('select', { id: id, className: 'twd-sk-ed__select' });
		if (emptyLabel) {
			select.appendChild(el('option', { value: '', text: emptyLabel }));
		}
		Object.keys(options).forEach(function (k) {
			select.appendChild(el('option', { value: k, text: options[k] }));
		});
		return select;
	}

	function checkbox(id, labelText) {
		var input = el('input', { id: id, type: 'checkbox' });
		return { input: input, label: el('label', { className: 'twd-sk-ed__check', 'for': id }, [input, el('span', { text: labelText })]) };
	}

	function saveChrome() {
		message(cui.msg, 'Saving...', true);
		loadHistory();
		api('POST', '/site/chrome', {
			settings: {
				header_variant: cui.header.value,
				footer_variant: cui.footer.value,
				footer_columns: parseInt(cui.cols.value, 10),
				footer_text_style: cui.textStyle.value,
				contact_bar: cui.contactBar.checked,
				quick_exit: cui.exit.checked,
				quick_exit_url: cui.exitUrl.value.trim(),
				sticky: cui.sticky.checked,
				shrink: cui.shrink.checked,
				front_logo: cui.front.checked,
				overlay: cui.over.checked,
				wordmark: cui.wordmark.checked,
				wordmark_scrolled_name: cui.wmname.checked,
				wordmark_small_line: cui.wmline.checked,
				client_footer_text: cui.clientFooter.checked,
				menu_marker: cui.marker.value,
				show_button: cui.button.checked,
				show_strip: cui.strip.checked,
				placement: cui.placement.value
			}
		}).then(function (data) {
			P.data = data;
			fillChrome();
			message(cui.msg, 'Saved. Reload the page to see the new header and footer.', true);
			show(cui.reloadBtn, true);
		}, function (err) {
			message(cui.msg, err.message, false);
		});
	}

	function downloadTemplates() {
		message(cui.msg, 'Preparing the files...', true);
		api('GET', '/site/templates').then(function (data) {
			Object.keys(data.files).forEach(function (name) {
				var blob = new Blob([data.files[name]], { type: 'application/json' });
				var url = window.URL.createObjectURL(blob);
				var a = el('a', { href: url, download: name });
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				window.setTimeout(function () {
					window.URL.revokeObjectURL(url);
				}, 1000);
			});
			message(cui.msg, 'Downloaded twd-header.json and twd-footer.json. In Elementor: Templates, Theme Builder, import each file, then set its display condition to Entire Site.', true);
		}, function (err) {
			message(cui.msg, err.message, false);
		});
	}

	function buildChromeSection(panel) {
		cui.header = selectWith('twd-sk-ch-header', {}, 'Use the style\'s own layout');
		cui.footer = selectWith('twd-sk-ch-footer', {}, 'Use the style\'s own layout');
		cui.cols = selectWith('twd-sk-ch-cols', { '1': 'One column', '2': 'Two columns', '3': 'Three columns' }, '');
		cui.textStyle = selectWith('twd-sk-ch-textstyle', { plain: 'As before', lead: 'First line larger, the rest quieter' }, '');
		cui.textStyle.addEventListener('change', previewTextStyle);
		var sticky = checkbox('twd-sk-ch-sticky', 'Keep the header fixed at the top of the screen');
		var btn = checkbox('twd-sk-ch-button', 'Show the header button');
		var strip = checkbox('twd-sk-ch-strip', 'Show a thin strip with the phone number and email above the header');
		var cbar = checkbox('twd-sk-ch-contactbar', 'On a phone, a slim Email and Call bar fixed to the bottom of the screen (from the email and phone in Site details; nothing on a computer)');
		var exit = checkbox('twd-sk-ch-exit', 'A small "Leave quickly" link in the top corner of every page, for visitors who may need to hide the page fast');
		cui.contactBar = cbar.input;
		cui.exit = exit.input;
		cui.exitUrl = textInput('twd-sk-ch-exiturl', 300);
		var shrink = checkbox('twd-sk-ch-shrink', 'Make the fixed header smaller when scrolling (only works with the fixed header above)');
		var front = checkbox('twd-sk-ch-front', 'Bigger logo on the front page: the full logo, larger and centred above the menu, on the front page only. Other pages show the small logo mark (if one is set in Site details) with the site name. Sizes are in the Style section.');
		var over = checkbox('twd-sk-ch-over', 'Transparent over the picture: on a page that opens with a full-width picture hero, the header sits over the picture with no background and light words. Every other page keeps the normal header. With the fixed header, it turns solid again once the page is scrolled.');
		var wordmark = checkbox('twd-sk-ch-wordmark', 'Text wordmark: the logo as live text. On the front page a centred stack: a small symbol (the Small logo mark in Site details), the site name in capitals, an optional second line, and the menu centred below. On every other page one row: the symbol and the name at the left, the menu at the right. The menu gets its own smaller look. Replaces the header layout above while it is ticked. Sizes are in the Style section.');
		var wmname = checkbox('twd-sk-ch-wmname', 'Keep the name when the front page header is scrolled (otherwise only the small symbol stays, beside the menu). Only works with the fixed header and the smaller-when-scrolling option above.');
		var wmline = checkbox('twd-sk-ch-wmline', 'Show the second line under the name in the smaller header too: on every page except the large front page header, and on the front page once it is scrolled (this also keeps the name beside the symbol). The second line is set in Site details; keep it short so it fits beside the symbol on a phone.');
		var clientFooter = checkbox('twd-sk-ch-clientfooter', 'Clients may edit the footer text: a "Footer words" box in their editor (up to three lines, bold and italic). Nothing else in the header or footer; contact details stay in Site details.');
		cui.clientFooter = clientFooter.input;
		cui.marker = selectWith('twd-sk-ch-marker', {}, '');
		cui.wordmark = wordmark.input;
		cui.wmname = wmname.input;
		cui.wmline = wmline.input;
		cui.sticky = sticky.input;
		cui.shrink = shrink.input;
		cui.front = front.input;
		cui.over = over.input;
		cui.button = btn.input;
		cui.strip = strip.input;
		cui.placement = selectWith('twd-sk-ch-placement', {}, '');
		cui.placement.addEventListener('change', function () {
			if (P.data) {
				fillPlacementNote();
			}
		});
		cui.placedNote = el('p', { className: 'twd-sk-ed__help' });
		cui.note = el('p', { className: 'twd-sk-ed__help' });
		cui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		cui.saveBtn = button('Save header and footer', 'primary', saveChrome);
		cui.reloadBtn = button('Reload the page to see it', 'secondary', function () {
			window.location.reload();
		});
		show(cui.reloadBtn, false);
		cui.exportBtn = button('Download the Theme Builder templates', 'secondary', downloadTemplates);
		// Undo from here too (session 7, section 21b): the last site change, whatever it was, with the result shown here.
		cui.undoBtn = button('Undo the last site change', 'secondary', function () {
			historyAction('/site/history/undo', {}, 'Went back one site change.', cui.msg);
		});
		// The words live in Site details: two plain ways there, so "Header and footer" in Your Edits is one place to start.
		var toMenu = button('Change the menu', 'secondary', function () {
			ED.openAt('site', 'twd-sk-pf-menu');
		});
		var toFooter = button('Change the footer text', 'secondary', function () {
			ED.openAt('site', 'twd-sk-pf-footertext');
		});
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Header and footer' }),
			el('p', { className: 'twd-sk-ed__help', text: 'The header and footer print from the site details above. On the Hello theme Site Kit puts them on every page itself, so Elementor Pro is not needed. Choose how they look here.' }),
			el('p', { className: 'twd-sk-ed__help', text: 'The words (the site name, logo, menu, button, footer text, legal links and contact details) are in Site details above. The layout and options are here. Save, then reload the page to see it; Undo the last site change steps back one Save.' }),
			el('div', { className: 'twd-sk-ed__actions', id: 'twd-sk-chrome-ways' }, [toMenu, toFooter])
		].concat(aiBlocks(['header', 'footer'])).concat([
			field('Who places them', cui.placement),
			cui.placedNote,
			cui.note,
			el('div', { className: 'twd-sk-ed__grid' }, [
				field('Header layout', cui.header),
				field('Footer layout', cui.footer),
				field('Footer columns', cui.cols, 'Used by the column footers.'),
				field('Footer text style', cui.textStyle, 'With two or three lines of footer text, the first line can stand out.')
			]),
			sticky.label,
			shrink.label,
			wordmark.label,
			wmname.label,
			wmline.label,
			field('Marking the page you are on', cui.marker, 'In the text wordmark header\'s menu.'),
			front.label,
			over.label,
			btn.label,
			strip.label,
			cbar.label,
			exit.label,
			field('Where Leave quickly goes', cui.exitUrl, 'A full https address of a neutral site, such as a weather or news page. Empty means no link.'),
			clientFooter.label,
			el('div', { className: 'twd-sk-ed__actions' }, [cui.saveBtn, cui.exportBtn, cui.reloadBtn, cui.undoBtn]),
			cui.msg
		])));
		// The layout names come with the profile, so fill the lists once it has arrived.
		P.ready.then(function () {
			if (!P.data) {
				return;
			}
			var c = P.data.chrome;
			Object.keys(c.placements).forEach(function (k) {
				cui.placement.appendChild(el('option', { value: k, text: c.placements[k] }));
			});
			Object.keys(c.header_variants).forEach(function (k) {
				cui.header.appendChild(el('option', { value: k, text: c.header_variants[k] }));
			});
			Object.keys(c.footer_variants).forEach(function (k) {
				cui.footer.appendChild(el('option', { value: k, text: c.footer_variants[k] }));
			});
			Object.keys(c.menu_markers || {}).forEach(function (k) {
				cui.marker.appendChild(el('option', { value: k, text: c.menu_markers[k] }));
			});
			fillChrome();
		});
	}

	/* ---- set up a new site from the starters ---- */

	var sui = {};

	function runSetup(pack, frontPage) {
		message(sui.msg, 'Setting up...', true);
		api('POST', '/site/setup', { confirm: true, pack: pack || undefined, front_page: frontPage }).then(function (data) {
			clear(sui.result);
			var lines = [];
			data.created.forEach(function (p) {
				lines.push('Created the draft page "' + p.title + '".');
			});
			data.skipped.forEach(function (p) {
				lines.push('"' + p.title + '" already existed, so it was left alone.');
			});
			if (data.pack) {
				lines.push('The style is now ' + data.pack + '.');
			}
			if (data.profile === 'filled') {
				lines.push('The empty site details were filled with placeholders.');
			}
			(data.notes || []).forEach(function (n) {
				lines.push(n);
			});
			listInto(sui.result, lines);
			data.created.concat(data.skipped).forEach(function (p) {
				if (typeof p.url === 'string' && (p.url.indexOf(window.location.origin + '/') === 0 || p.url.charAt(0) === '/')) {
					sui.result.appendChild(el('p', {}, [el('a', { className: 'twd-sk-ed__link', href: p.url, text: 'Open ' + p.title })]));
				}
			});
			show(sui.result, true);
			message(sui.msg, 'Done. Nothing is published. Replace every [PLACEHOLDER], then publish each page from the Pages tab.', true);
			if (data.profile_state) {
				P.data = data.profile_state;
				if (pui.name) {
					fillProfile();
				}
			}
		}, function (err) {
			message(sui.msg, err.message, false);
		});
	}

	function askSetup() {
		message(sui.msg, '', true);
		api('GET', '/site/setup').then(function (data) {
			var front = !sui.skipFront.checked;
			var box = el('div', { className: 'twd-sk-ed__card', role: 'group', 'aria-label': 'Please confirm' });
			box.appendChild(el('p', { text: 'This is what will happen:' }));
			listInto(box, data.plan.filter(function (line) {
				return front || line.indexOf('front page') < 0;
			}));
			var yes = button('Yes, set the site up', 'primary', function () {
				sui.confirmHost.removeChild(box);
				runSetup(sui.pack.value, front);
			});
			var no = button('Cancel', 'secondary', function () {
				sui.confirmHost.removeChild(box);
			});
			box.appendChild(el('div', { className: 'twd-sk-ed__actions' }, [yes, no]));
			clear(sui.confirmHost);
			sui.confirmHost.appendChild(box);
			yes.focus();
		}, function (err) {
			message(sui.msg, err.message, false);
		});
	}

	function buildSetupSection(panel) {
		sui.pack = el('select', { id: 'twd-sk-su-pack', className: 'twd-sk-ed__select' });
		sui.pack.appendChild(el('option', { value: '', text: 'Keep the current style' }));
		sui.skipFront = el('input', { id: 'twd-sk-su-skipfront', type: 'checkbox' });
		sui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		sui.result = el('div', { className: 'twd-sk-ed__banner', hidden: '' });
		sui.confirmHost = el('div', {});
		sui.btn = button('Set this site up from the starters', 'primary', askSetup);
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Set up a new site' }),
			el('p', { className: 'twd-sk-ed__help', text: 'For a brand new site. It makes Home, About and Contact as draft pages with placeholder text, fills empty site details with placeholders, and can make Home the front page. It never publishes anything and never overwrites site details that already have content. Safe to run twice.' }),
			field('Style', sui.pack),
			el('label', { className: 'twd-sk-ed__check', 'for': 'twd-sk-su-skipfront' }, [sui.skipFront, el('span', { text: 'Do not change the front page setting' })]),
			el('div', { className: 'twd-sk-ed__actions' }, [sui.btn]),
			sui.confirmHost,
			sui.result,
			sui.msg
		]));
		api('GET', '/site/setup').then(function (data) {
			data.packs.forEach(function (slug) {
				sui.pack.appendChild(el('option', { value: slug, text: slug }));
			});
		}, function () {});
	}

	/* ---- unsaved changes and the edit pills ---- */

	function sameMap(a, b) {
		var ka = Object.keys(a || {});
		var kb = Object.keys(b || {});
		if (ka.length !== kb.length) {
			return false;
		}
		return ka.every(function (k) {
			return Object.prototype.hasOwnProperty.call(b, k) && String(a[k]) === String(b[k]);
		});
	}

	function profileSnap() {
		var got = collectProfile();
		return JSON.stringify(got.error ? { error: got.error, raw: [pui.menu.value, pui.legal.value, pui.logo.value] } : got.profile);
	}

	function chromeSnap() {
		return JSON.stringify([cui.header.value, cui.footer.value, cui.cols.value, cui.textStyle.value, cui.contactBar.checked, cui.exit.checked, cui.exitUrl.value, cui.sticky.checked, cui.shrink.checked, cui.front.checked, cui.over.checked, cui.wordmark.checked, cui.wmname.checked, cui.wmline.checked, cui.clientFooter.checked, cui.marker.value, cui.button.checked, cui.strip.checked, cui.placement.value]);
	}

	ED.addDirtyCheck(function () {
		if (!S.data) {
			return '';
		}
		return (S.pack !== S.data.active || !sameMap(S.draft, S.data.overrides || {})) ? 'style changes (colours, fonts or corners)' : '';
	}, function () {
		discard();
	});

	ED.addDirtyCheck(function () {
		return (P.data && pui.snap !== undefined && profileSnap() !== pui.snap) ? 'site details (menu, contact lines, footer text and so on)' : '';
	}, function () {
		restoreFooterText();
		if (P.data) {
			fillProfile();
		}
	});

	ED.addDirtyCheck(function () {
		return (P.data && cui.snap !== undefined && chromeSnap() !== cui.snap) ? 'header and footer layout settings' : '';
	}, function () {
		if (P.data) {
			fillChrome();
		}
	});

	// Edit pills: small buttons laid over the corner of the header and the footer. They appear once Edit with AI has
	// been opened on this page, and stay until the page is reloaded. They sit in the editor wrapper, never inside the
	// header or footer, so the site's own layout is untouched. They only exist where the kit prints the header or footer.
	var PILLS = [
		{ selector: '.twd-sk-chrome .twd-sk-header', label: 'Edit header', aria: 'Edit the header: menu and site details', field: 'twd-sk-pf-menu', host: null, node: null },
		{ selector: '.twd-sk-chrome .twd-sk-footer', label: 'Edit footer', aria: 'Edit the footer: text and legal links', field: 'twd-sk-pf-footertext', host: null, node: null }
	];
	var pillQueued = false;

	function placePills() {
		pillQueued = false;
		PILLS.forEach(function (pill) {
			if (!pill.node || !pill.host) {
				return;
			}
			var r = pill.host.getBoundingClientRect();
			var visible = r.height > 0 && r.bottom > 40 && r.top < window.innerHeight - 40;
			if (!visible) {
				show(pill.node, false);
				return;
			}
			show(pill.node, true);
			pill.node.style.top = Math.max(r.top + 6, 6) + 'px';
			pill.node.style.left = Math.max(r.left + 6, 6) + 'px';
		});
	}

	function queuePills() {
		if (!pillQueued) {
			pillQueued = true;
			window.requestAnimationFrame(placePills);
		}
	}

	function addPills() {
		var any = false;
		PILLS.forEach(function (pill) {
			if (pill.node) {
				any = true;
				return;
			}
			var host = document.querySelector(pill.selector);
			if (!host) {
				return;
			}
			any = true;
			pill.host = host;
			pill.node = el('button', { type: 'button', className: 'twd-sk-ed__pill', text: pill.label, 'aria-label': pill.aria });
			pill.node.addEventListener('click', function () {
				ED.openAt('site', pill.field);
			});
			document.getElementById('twd-sk-ed').appendChild(pill.node);
		});
		if (any && !addPills.bound) {
			addPills.bound = true;
			window.addEventListener('scroll', queuePills, { passive: true });
			window.addEventListener('resize', queuePills);
		}
		queuePills();
	}

	ED.onOpen.push(addPills);

	/* ---- the facts (the master document) ---- */

	var fui = { data: null, snap: null };

	function updateFactsCount() {
		var n = fui.text.value.length;
		var max = fui.data ? fui.data.max : 20000;
		fui.count.textContent = n + ' of ' + max + ' characters.' + (n > max ? ' Too long to save.' : '');
		fui.saveBtn.disabled = n > max;
		show(fui.templateBtn, fui.text.value.trim() === '');
	}

	function fillFacts() {
		fui.text.value = fui.data.text || '';
		fui.snap = fui.text.value;
		updateFactsCount();
		fui.updated.textContent = fui.data.updated ? 'Last saved ' + fui.data.updated + ' (UTC).' : 'Not saved yet.';
	}

	function saveFacts() {
		message(fui.msg, 'Saving...', true);
		api('POST', '/site/facts', { text: fui.text.value }).then(function (data) {
			fui.data = data;
			fillFacts();
			message(fui.msg, 'Saved. Long dashes and tags are removed on saving.', true);
		}, function (err) {
			message(fui.msg, err.message, false);
		});
	}

	function buildFactsSection(panel) {
		fui.text = el('textarea', { id: 'twd-sk-facts', className: 'twd-sk-ed__textarea twd-sk-ed__tall', spellcheck: 'true' });
		fui.text.addEventListener('input', updateFactsCount);
		fui.count = el('p', { className: 'twd-sk-ed__help', 'aria-live': 'polite' });
		fui.updated = el('p', { className: 'twd-sk-ed__help' });
		fui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		fui.saveBtn = button('Save ' + FACTS, 'primary', saveFacts);
		fui.templateBtn = button('Start from the outline', 'secondary', function () {
			fui.text.value = fui.data ? fui.data.template : '';
			updateFactsCount();
			fui.text.focus();
		});
		show(fui.templateBtn, false);
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: FACTS_TITLE }),
			el('p', { className: 'twd-sk-ed__help', text: W('facts_help', '') }),
			field(FACTS_TITLE, fui.text),
			fui.count,
			fui.updated,
			el('div', { className: 'twd-sk-ed__actions' }, [fui.saveBtn, fui.templateBtn]),
			fui.msg
		]));
		api('GET', '/site/facts').then(function (data) {
			fui.data = data;
			fillFacts();
		}, function (err) {
			message(fui.msg, err.message, false);
		});
	}

	ED.addDirtyCheck(function () {
		return (fui.data && fui.snap !== null && fui.text.value !== fui.snap) ? FACTS : '';
	}, function () {
		if (fui.data) {
			fillFacts();
		}
	});

	/* ---- AI suggestions for the header, the footer and the style ---- */

	var AI_TEXT = {
		header: { title: 'Ask the AI to change the header', help: 'For example: add an About and a Contact link, or make the button say Book a call.', done: 'The suggestions are filled into the Site details boxes (menu and button) and the Header and footer settings below. Look them over, then press Save site details and Save header and footer to keep them.' },
		footer: { title: 'Ask the AI to change the footer', help: 'For example: shorten the footer text, or add a link to the cookie policy.', done: 'The suggestions are filled into the Site details boxes (footer text and legal links) and the Header and footer settings below. Look them over, then press Save site details and Save header and footer to keep them.' },
		style: { title: 'Ask the AI to change the style', help: 'For example: make it calmer and more earthy, or use squarer buttons. Colours always stay easy to read.', done: 'The suggestion is previewed on this page now. Nothing is kept until you press Save style.' }
	};

	function setValue(box, value) {
		box.value = value === null || value === undefined ? '' : String(value);
	}

	function fillProposal(kind, v) {
		if (kind === 'style') {
			if (v.pack) {
				S.pack = v.pack;
				S.draft = {};
			}
			Object.keys(v.tokens || {}).forEach(function (name) {
				S.draft[name] = v.tokens[name];
			});
			clearPreview();
			previewAll();
			renderPacks();
			renderControls();
			return;
		}
		if (kind === 'header') {
			if (v.menu) {
				pui.menu.value = linksToText(v.menu, true);
			}
			if ('cta_label' in v) {
				setValue(pui.ctaLabel, v.cta_label);
			}
			if ('cta_url' in v) {
				setValue(pui.ctaUrl, v.cta_url);
			}
			if ('header_variant' in v) {
				cui.header.value = v.header_variant;
			}
			if ('sticky' in v) {
				cui.sticky.checked = !!v.sticky;
			}
			if ('show_button' in v) {
				cui.button.checked = !!v.show_button;
			}
			if ('show_strip' in v) {
				cui.strip.checked = !!v.show_strip;
			}
			return;
		}
		if ('footer_text' in v) {
			setValue(pui.footerText, v.footer_text);
		}
		if (v.legal) {
			pui.legal.value = linksToText(v.legal, false);
		}
		if ('footer_variant' in v) {
			cui.footer.value = v.footer_variant;
		}
		if ('footer_text_style' in v) {
			cui.textStyle.value = v.footer_text_style;
			previewTextStyle();
		}
		if ('footer_columns' in v) {
			cui.cols.value = String(v.footer_columns);
		}
	}

	function undoProposal(kind) {
		if (kind === 'style') {
			discard();
			return;
		}
		if (P.data) {
			fillProfile();
			fillChrome();
		}
	}

	function aiBlock(kind) {
		if (!ED.cfg.aiAvailable) {
			return null;
		}
		var t = AI_TEXT[kind];
		var id = 'twd-sk-prop-' + kind;
		var box = el('textarea', { id: id, className: 'twd-sk-ed__textarea twd-sk-ed__short', maxlength: '1000', spellcheck: 'true' });
		var msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		var ask = button('Ask the AI', 'primary', function () {
			var instruction = box.value.trim();
			if (!instruction) {
				message(msg, 'Say what you want changed first.', false);
				box.focus();
				return;
			}
			if (kind !== 'style' && !P.data) {
				message(msg, 'The site details are still loading. Try again in a moment.', false);
				return;
			}
			if (kind === 'style' && !S.data) {
				message(msg, 'The style settings are still loading. Try again in a moment.', false);
				return;
			}
			ask.disabled = true;
			message(msg, 'Asking the AI. This can take up to a minute. Nothing is saved.', true);
			api('POST', '/site/propose', { kind: kind, instruction: instruction }).then(function (data) {
				ask.disabled = false;
				var lines = [];
				var changed = data.changed || [];
				if (!changed.length) {
					message(msg, 'The AI suggested no change.' + ((data.errors && data.errors.length) ? ' Some of what it tried was not allowed:' : ' Try wording it differently.'), false);
					if (data.errors && data.errors.length) {
						listInto(msg, data.errors);
					}
					return;
				}
				fillProposal(kind, data.values || {});
				message(msg, 'The AI suggested ' + changed.length + ' change' + (changed.length === 1 ? '' : 's') + ': ' + changed.map(function (c) {
					return c.label;
				}).join(', ') + '. ' + t.done + ' Check every word before you save.', true);
				if (data.errors && data.errors.length) {
					listInto(msg, data.errors);
				}
				if (data.blocking && data.blocking.length) {
					msg.appendChild(el('strong', { text: 'It cannot be saved as it stands, because this text would be hard to read:' }));
					listInto(msg, data.blocking);
				}
			}, function (err) {
				ask.disabled = false;
				message(msg, err.message, false);
			});
		});
		var undo = button('Undo the suggestion', 'secondary', function () {
			undoProposal(kind);
			message(msg, 'Back to what is saved.', true);
		});
		return el('div', { className: 'twd-sk-ed__card' }, [
			el('label', { className: 'twd-sk-ed__label', 'for': id, text: t.title }),
			el('p', { className: 'twd-sk-ed__help', text: t.help }),
			box,
			el('div', { className: 'twd-sk-ed__actions' }, [ask, undo]),
			msg
		]);
	}

	/* Colours from the logo: previewed like an AI suggestion, never saved until Save style. */
	function brandBlock() {
		var msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		var swatches = el('div', { className: 'twd-sk-ed__swatches', 'aria-hidden': 'true' });
		var go = button('Take colours from the logo', 'secondary', function () {
			if (!S.data) {
				message(msg, 'The style settings are still loading. Try again in a moment.', false);
				return;
			}
			go.disabled = true;
			message(msg, 'Reading the logo...', true);
			api('GET', '/site/brand').then(function (data) {
				go.disabled = false;
				clear(swatches);
				(data.colours || []).forEach(function (hex) {
					swatches.appendChild(el('span', { className: 'twd-sk-ed__swatch', title: hex, 'data-colour': hex }));
				});
				Array.prototype.forEach.call(swatches.children, function (s) {
					s.style.backgroundColor = s.getAttribute('data-colour');
				});
				var n = Object.keys(data.tokens || {}).length;
				if (!n) {
					message(msg, 'No colours from the logo could be used without making text hard to read.', false);
					return;
				}
				fillProposal('style', { tokens: data.tokens });
				message(msg, n + ' colours from the logo are previewed on this page now. Nothing is kept until you press Save style.' + (data.dropped && data.dropped.length ? ' Kept the style\'s own for: ' + data.dropped.join(', ') + ', so text stays easy to read.' : ''), true);
			}, function (err) {
				go.disabled = false;
				message(msg, err.message, false);
			});
		});
		var undo = button('Undo', 'secondary', function () {
			undoProposal('style');
			clear(swatches);
			message(msg, 'Back to what is saved.', true);
		});
		return el('div', { className: 'twd-sk-ed__card' }, [
			el('p', { className: 'twd-sk-ed__label', text: 'Colours from the logo' }),
			el('p', { className: 'twd-sk-ed__help', text: 'Uses the main colours of the logo in Site details for the buttons, headings, bands and accents. Every colour is checked so text stays easy to read.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [go, undo]),
			swatches,
			msg
		]);
	}

	function aiBlocks(kinds) {
		return kinds.map(aiBlock).filter(function (b) {
			return b;
		});
	}

	/* ---- the site brief: one JSON file for the whole site ---- */

	var bui = { plan: null, lastText: '', applied: true };

	function copyText(text, box, msg, okText) {
		var copied = false;
		try {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text);
				copied = true;
			}
		} catch (e) {
			copied = false;
		}
		box.value = text;
		if (copied) {
			show(box, false);
			message(msg, okText, true);
		} else {
			show(box, true);
			box.focus();
			box.select();
			message(msg, 'The text is selected below. Press Ctrl+C (Cmd+C on a Mac) to copy it.', false);
		}
	}

	function briefLines(plan) {
		var out = [];
		(plan.items || []).forEach(function (i) {
			var what = i.action === 'set' ? 'will be set' : (i.action === 'skip_filled' ? 'already filled in, kept as it is' : 'no change');
			out.push(i.label + ': ' + what + (i.action === 'set' ? ' (' + i.before + ' to ' + i.after + ')' : ''));
		});
		return out;
	}

	function renderBriefPlan(plan) {
		clear(bui.planBox);
		var lines = briefLines(plan);
		bui.planBox.appendChild(el('strong', { text: 'What this brief would do:' }));
		if (lines.length) {
			listInto(bui.planBox, lines);
		} else {
			bui.planBox.appendChild(el('p', { text: 'Nothing in the site details, header, footer, style or facts would change.' }));
		}
		if ((plan.pages || []).length) {
			bui.planBox.appendChild(el('strong', { text: 'Pages listed in the brief:' }));
			listInto(bui.planBox, plan.pages.map(function (pg) {
				return pg.title + ' (' + pg.type + ')' + (pg.exists ? ': already exists, will not be created again' : (bui.build.input.checked ? ': will be created as a draft outline' : ': not created unless you tick the box below'));
			}));
		}
		if (plan.errors && plan.errors.length) {
			bui.planBox.appendChild(el('strong', { text: 'Not allowed, so dropped:' }));
			listInto(bui.planBox, plan.errors);
		}
		if (plan.ignored && plan.ignored.length) {
			bui.planBox.appendChild(el('strong', { text: 'Ignored:' }));
			listInto(bui.planBox, plan.ignored);
		}
		if (plan.blocking && plan.blocking.length) {
			bui.planBox.appendChild(el('strong', { text: 'The style cannot be saved, because this text would be hard to read:' }));
			listInto(bui.planBox, plan.blocking);
		}
		show(bui.planBox, true);
		var can = (plan.items || []).some(function (i) {
			return i.action === 'set';
		}) || (bui.build.input.checked && (plan.pages || []).some(function (pg) {
			return !pg.exists;
		}));
		bui.applyBtn.disabled = !can;
	}

	function briefCheck() {
		var text = bui.text.value;
		if (!text.trim()) {
			message(bui.msg, 'Paste a site brief first.', false);
			return;
		}
		message(bui.msg, 'Checking...', true);
		bui.applyBtn.disabled = true;
		api('POST', '/site/brief/plan', { brief: text, overwrite: bui.over.input.checked }).then(function (plan) {
			bui.plan = plan;
			bui.lastText = text;
			message(bui.msg, 'Checked. Nothing has been changed yet. Read the list below, then press Apply.', true);
			renderBriefPlan(plan);
		}, function (err) {
			show(bui.planBox, false);
			message(bui.msg, err.message, false);
		});
	}

	function refreshAfterBrief(state) {
		if (state && P.data) {
			P.data = state;
			fillProfile();
			fillChrome();
		}
		api('GET', '/site/facts').then(function (data) {
			fui.data = data;
			fillFacts();
		}, function () {});
		load();
	}

	function fillPage(entry, btn, note) {
		btn.disabled = true;
		message(note, 'Asking the AI. This can take up to a minute.', true);
		api('POST', '/pages/generate', { page_id: entry.id, type: entry.type, title: entry.title, topic: entry.topic, notes: entry.notes }).then(function (data) {
			message(note, 'Filled in. ' + (data.must_count ? data.must_count + ' placeholder or example items still need replacing before it can be published. ' : '') + 'Check every fact.', true);
			btn.disabled = false;
		}, function (err) {
			message(note, err.message, false);
			btn.disabled = false;
		});
	}

	function renderBriefResult(res) {
		clear(bui.result);
		if (res.applied && res.applied.length) {
			bui.result.appendChild(el('strong', { text: 'Done:' }));
			listInto(bui.result, res.applied);
		}
		if (res.skipped && res.skipped.length) {
			bui.result.appendChild(el('strong', { text: 'Left as it was:' }));
			listInto(bui.result, res.skipped);
		}
		if (res.errors && res.errors.length) {
			bui.result.appendChild(el('strong', { text: 'Not saved:' }));
			listInto(bui.result, res.errors);
		}
		if (res.ignored && res.ignored.length) {
			bui.result.appendChild(el('strong', { text: 'Ignored:' }));
			listInto(bui.result, res.ignored);
		}
		(res.pages || []).forEach(function (pg) {
			var row = el('div', { className: 'twd-sk-ed__row' });
			var safe = typeof pg.url === 'string' && (pg.url.indexOf(window.location.origin + '/') === 0 || pg.url.charAt(0) === '/');
			row.appendChild(document.createTextNode('Draft "' + pg.title + '" (' + pg.type + '). '));
			if (safe) {
				row.appendChild(el('a', { className: 'twd-sk-ed__link', href: pg.url, text: 'Open it' }));
			}
			bui.result.appendChild(row);
			if (ED.cfg.aiAvailable) {
				var note = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
				var btn = button('Fill "' + pg.title + '" with AI', 'secondary', function () {
					fillPage(pg, btn, note);
				});
				bui.result.appendChild(el('div', { className: 'twd-sk-ed__actions' }, [btn]));
				bui.result.appendChild(note);
			}
		});
		show(bui.result, true);
	}

	function briefApply() {
		ED.confirmInline(bui.confirmHost, {
			text: 'Apply this site brief? Details that are already filled in are ' + (bui.over.input.checked ? 'REPLACED' : 'kept') + '. ' + (bui.build.input.checked ? 'Pages in the brief are created as drafts. ' : 'No pages are created. ') + 'Nothing is published.',
			yesLabel: 'Yes, apply it',
			onYes: function () {
				message(bui.msg, 'Applying...', true);
				bui.applyBtn.disabled = true;
				api('POST', '/site/brief/apply', { brief: bui.lastText, overwrite: bui.over.input.checked, build_pages: bui.build.input.checked, confirm: true }).then(function (res) {
					bui.applied = true;
					bui.text.value = '';
					show(bui.planBox, false);
					message(bui.msg, 'Applied.', true);
					renderBriefResult(res);
					refreshAfterBrief(res.profile_state);
				}, function (err) {
					bui.applyBtn.disabled = false;
					message(bui.msg, err.message, false);
				});
			}
		});
	}

	function buildBriefSection(panel) {
		bui.text = el('textarea', { id: 'twd-sk-brief', className: 'twd-sk-ed__textarea twd-sk-ed__tall', spellcheck: 'false' });
		bui.text.addEventListener('input', function () {
			bui.applied = bui.text.value.trim() === '';
			bui.applyBtn.disabled = true;
		});
		bui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		bui.planBox = el('div', { className: 'twd-sk-ed__banner', role: 'status', hidden: '' });
		bui.result = el('div', { className: 'twd-sk-ed__banner', role: 'status', hidden: '' });
		bui.confirmHost = el('div', {});
		bui.promptBox = el('textarea', { className: 'twd-sk-ed__textarea', readonly: '', 'aria-label': 'The interview prompt, selected so you can copy it', hidden: '' });
		bui.over = checkbox('twd-sk-brief-over', 'Replace details that are already filled in');
		bui.build = checkbox('twd-sk-brief-build', 'Create the pages in the brief as draft outlines');
		bui.build.input.checked = true;
		[bui.over.input, bui.build.input].forEach(function (c) {
			c.addEventListener('change', function () {
				if (bui.text.value.trim() && bui.plan) {
					briefCheck();
				}
			});
		});
		bui.checkBtn = button('Check it', 'primary', briefCheck);
		bui.applyBtn = button('Apply', 'secondary', briefApply);
		bui.applyBtn.disabled = true;
		bui.promptBtn = button('Copy the interview prompt', 'secondary', function () {
			message(bui.msg, 'Preparing the prompt...', true);
			api('GET', '/site/brief').then(function (data) {
				copyText(data.prompt, bui.promptBox, bui.msg, 'Copied. Paste it into a Claude conversation. It will interview the ' + W('person', 'therapist') + ' and finish with the brief.');
			}, function (err) {
				message(bui.msg, err.message, false);
			});
		});
		bui.exportBtn = button('Download the current brief', 'secondary', function () {
			message(bui.msg, 'Preparing the file...', true);
			api('GET', '/site/brief').then(function (data) {
				var blob = new Blob([JSON.stringify(data.brief, null, 2)], { type: 'application/json' });
				var url = window.URL.createObjectURL(blob);
				var a = el('a', { href: url, download: 'site-brief.json' });
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				window.setTimeout(function () {
					window.URL.revokeObjectURL(url);
				}, 1000);
				message(bui.msg, 'Downloaded site-brief.json.', true);
			}, function (err) {
				message(bui.msg, err.message, false);
			});
		});
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Site brief' }),
			el('p', { className: 'twd-sk-ed__help', text: 'One file that describes the whole site: site type, details, menu, header and footer, style, ' + FACTS + ' and the pages to create. Gather it in a conversation (copy the interview prompt), paste the result here, check what it would do, then apply it. You can download the current site as a brief at any time.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [bui.promptBtn, bui.exportBtn]),
			bui.promptBox,
			field('Paste a site brief (JSON)', bui.text),
			bui.over.label,
			bui.build.label,
			el('div', { className: 'twd-sk-ed__actions' }, [bui.checkBtn, bui.applyBtn]),
			bui.confirmHost,
			bui.msg,
			bui.planBox,
			bui.result
		]));
	}

	ED.addDirtyCheck(function () {
		return (bui.text && bui.text.value.trim() && !bui.applied) ? 'a site brief that has not been applied' : '';
	}, function () {
		if (bui.text) {
			bui.text.value = '';
			bui.applied = true;
		}
	});

	/* ---- the site report (the doctor) ---- */

	function buildDoctorSection(panel) {
		var msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		var box = el('textarea', { id: 'twd-sk-doctor', className: 'twd-sk-ed__textarea twd-sk-ed__tall', readonly: '', 'aria-label': 'The site report', spellcheck: 'false', hidden: '' });
		var copyBtn = button('Copy the report', 'secondary', function () {
			copyText(box.value, box, msg, 'Copied. Paste it into a message.');
			show(box, true);
		});
		show(copyBtn, false);
		var showBtn = button('Show the site report', 'primary', function () {
			message(msg, 'Reading the site...', true);
			showBtn.disabled = true;
			api('GET', '/site/doctor').then(function (data) {
				showBtn.disabled = false;
				box.value = data.report;
				show(box, true);
				show(copyBtn, true);
				message(msg, 'This is a plain-text report of the site. It holds no keys or passwords and none of the text of your ' + FACTS + '.', true);
			}, function (err) {
				showBtn.disabled = false;
				message(msg, err.message, false);
			});
		});
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Site report' }),
			el('p', { className: 'twd-sk-ed__help', text: 'A plain-text report of how the site stands: versions, safe mode, the header and footer templates, which pages use the kit and what is left to fix, what is filled in (never its contents), and the pictures with their alt text. Paste it into a message when something looks wrong. It changes nothing.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [showBtn, copyBtn]),
			msg,
			box
		]));
	}

	/* ---- the launch check ---- */

	function buildLaunchSection(panel) {
		var msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		var out = el('div', { className: 'twd-sk-ed__launch' });
		var marks = { fail: 'Fix: ', warn: 'Look: ', ok: 'OK: ' };
		var run = button('Run the launch check', 'primary', function () {
			run.disabled = true;
			message(msg, 'Checking the site...', true);
			api('GET', '/site/launch').then(function (check) {
				run.disabled = false;
				clear(out);
				message(msg, check.counts.fail + ' to fix, ' + check.counts.warn + ' to look at, ' + check.counts.ok + ' fine.', !check.counts.fail);
				var ul = el('ul', { className: 'twd-sk-ed__list' });
				check.items.forEach(function (it) {
					ul.appendChild(el('li', { text: marks[it.status] + it.label + (it.detail && it.status !== 'ok' ? '. ' + it.detail : '') }));
				});
				out.appendChild(ul);
				var setup = ED.cfg.setupUrl;
				if (setup) {
					out.appendChild(el('p', {}, [el('a', { className: 'twd-sk-ed__link', href: setup + (setup.indexOf('?') === -1 ? '?' : '&') + 'step=launch', text: 'Publish the pages that are ready (opens the setup page)' })]));
				}
			}, function (err) {
				run.disabled = false;
				message(msg, err.message, false);
			});
		});
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Launch check' }),
			el('p', { className: 'twd-sk-ed__help', text: 'Everything worth checking before the site goes live: example text still to replace, search wording, the front page, the privacy policy, the site details and more. It changes nothing.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [run]),
			msg,
			out
		]));
	}

	/* ---- site type: therapy or business ---- */

	/* ---- the Look panel (0.15.0): Box, Border and Padding for every editor, or administrators only ---- */
	function buildLooksSection(panel) {
		var msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		var id = 'twd-sk-looks-advanced';
		var box = el('input', { id: id, type: 'checkbox' });
		var row = el('label', { className: 'twd-sk-ed__check', 'for': id }, [box, el('span', { text: 'Offer Box, Border and Padding to everyone who edits pages (otherwise administrators only)' })]);
		var saved = null;
		api('GET', '/site/looks').then(function (data) {
			saved = !!data.advanced_for_all;
			box.checked = saved;
		}, function () {});
		var save = button('Save', 'primary', function () {
			message(msg, 'Saving...', true);
			api('POST', '/site/looks', { advanced_for_all: box.checked }).then(function (data) {
				saved = !!data.advanced_for_all;
				box.checked = saved;
				message(msg, 'Saved. ' + (saved ? 'Everyone who edits pages now sees Box, Border and Padding in the Look panel after a reload.' : 'Box, Border and Padding show for administrators only.'), true);
			}, function (err) {
				message(msg, err.message, false);
			});
		});
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Look panel' }),
			el('p', { className: 'twd-sk-ed__help', text: 'The Visual editor\'s Look panel offers Layout, Space above and below, and Background to everyone. Box, Border and Padding are for you unless you offer them to clients too.' }),
			row,
			el('div', { className: 'twd-sk-ed__actions' }, [save]),
			msg
		]));
		ED.addDirtyCheck(function () {
			return (saved !== null && box.checked !== saved) ? 'the Look panel switch' : '';
		}, function () {
			if (saved !== null) {
				box.checked = saved;
			}
		});
	}

	function buildModeSection(panel) {
		var msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		var group = el('div', { className: 'twd-sk-ed__modes', role: 'radiogroup', 'aria-label': 'Site type' });
		var modes = WORDS.modes || { therapy: { label: 'Therapy', help: '' } };
		var current = WORDS.mode || 'therapy';
		var radios = [];
		Object.keys(modes).forEach(function (slug) {
			var id = 'twd-sk-sitetype-' + slug;
			var r = el('input', { id: id, type: 'radio', name: 'twd-sk-sitetype', value: slug });
			r.checked = slug === current;
			radios.push(r);
			group.appendChild(el('label', { className: 'twd-sk-ed__check', 'for': id }, [r, el('span', { text: modes[slug].label + (modes[slug].help ? ': ' + modes[slug].help : '') })]));
		});
		var reload = button('Reload the page to see it', 'secondary', function () {
			window.location.reload();
		});
		show(reload, false);
		var save = button('Save site type', 'primary', function () {
			var chosen = '';
			radios.forEach(function (r) {
				if (r.checked) {
					chosen = r.value;
				}
			});
			message(msg, 'Saving...', true);
			api('POST', '/site/mode', { mode: chosen }).then(function (data) {
				current = data.mode;
				message(msg, 'Saved. Pages already written keep their wording. Reload the page to see the new labels.', true);
				show(reload, true);
			}, function (err) {
				message(msg, err.message, false);
			});
		});
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Site type' }),
			el('p', { className: 'twd-sk-ed__help', text: 'Changes the wording the editor and the AI use, the facts outline, the page types and the search engine details. It never rewrites a page that is already written.' }),
			group,
			el('div', { className: 'twd-sk-ed__actions' }, [save, reload]),
			msg
		]));
		ED.addDirtyCheck(function () {
			var chosen = current;
			radios.forEach(function (r) {
				if (r.checked) {
					chosen = r.value;
				}
			});
			return chosen !== current ? 'site type' : '';
		}, function () {
			radios.forEach(function (r) {
				r.checked = r.value === current;
			});
		});
	}

	function buildSnagsSection(panel) {
		var msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		var on = el('input', { id: 'twd-sk-snags-on', type: 'checkbox' });
		var months = el('select', { id: 'twd-sk-snags-months', className: 'twd-sk-ed__input' });
		var emailOn = el('input', { id: 'twd-sk-snags-emailon', type: 'checkbox' });
		var email = el('input', { id: 'twd-sk-snags-email', className: 'twd-sk-ed__input', type: 'email', autocomplete: 'off', maxlength: '120' });
		var saved = null;
		function fill(data) {
			saved = data;
			on.checked = !!data.enabled;
			clear(months);
			(data.month_choices || [12]).forEach(function (m) {
				var o = el('option', { value: String(m), text: m + ' months' });
				if (m === data.months) {
					o.selected = true;
				}
				months.appendChild(o);
			});
			emailOn.checked = !!data.email_on;
			email.value = data.email || '';
		}
		function current() {
			return { enabled: on.checked, months: parseInt(months.value, 10) || 12, email_on: emailOn.checked, email: email.value.trim() };
		}
		var save = button('Save Tell us settings', 'primary', function () {
			message(msg, 'Saving...', true);
			api('POST', '/snags/settings', current()).then(function (data) {
				fill(data);
				message(msg, 'Saved. Reload the page to see the Tell us button come or go.', true);
			}, function (err) {
				message(msg, err.message, false);
			});
		});
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Tell us notes' }),
			el('p', { className: 'twd-sk-ed__help', text: 'Lets anyone who can edit pages tell you what they would like changed, from the front of their own site. Notes are kept on this site only, as text, and deleted after the time you choose. Nothing is sent anywhere except the optional email, which says a note has arrived and never what it says. Safe mode switches this off.' }),
			el('label', { className: 'twd-sk-ed__check', 'for': 'twd-sk-snags-on' }, [on, el('span', { text: 'Show the Tell us button' })]),
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-snags-months', text: 'Keep notes for' }),
			months,
			el('label', { className: 'twd-sk-ed__check', 'for': 'twd-sk-snags-emailon' }, [emailOn, el('span', { text: 'Email me when a note arrives (the email says that one arrived, not what it says)' })]),
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-snags-email', text: 'Send that email to' }),
			email,
			el('div', { className: 'twd-sk-ed__actions' }, [save]),
			msg
		]));
		api('GET', '/snags/settings').then(fill, function (err) {
			message(msg, err.message, false);
		});
		ED.addDirtyCheck(function () {
			if (!saved) {
				return '';
			}
			var c = current();
			return c.enabled !== !!saved.enabled || c.months !== saved.months || c.email_on !== !!saved.email_on || c.email !== (saved.email || '') ? 'Tell us settings' : '';
		}, function () {
			if (saved) {
				fill(saved);
			}
		});
	}

	/* ---- embeds (0.15.0): code kept apart from the page ---- */

	var EM = { data: null, editing: null, snap: '' };
	var emui = {};

	function embedUsedText(item) {
		var pages = item.used_on || [];
		if (!pages.length) {
			return 'Not on any page yet.';
		}
		return 'On ' + pages.map(function (p) {
			return '"' + p.title + '"';
		}).join(', ') + '.';
	}

	function embedForm() {
		return { label: emui.label.value, kind: emui.kind.value, code: emui.code.value };
	}

	function embedFormText() {
		var f = embedForm();
		return f.label + '|' + f.kind + '|' + f.code;
	}

	function openEmbedForm(item) {
		EM.editing = item ? item.id : 0;
		emui.label.value = item ? item.label : '';
		emui.kind.value = item ? item.kind : 'shortcode';
		emui.code.value = item ? item.code : '';
		EM.snap = embedFormText();
		emui.formTitle.textContent = item ? 'Change "' + item.label + '"' : 'New embed';
		message(emui.msg, '', true);
		show(emui.form, true);
		show(emui.newBtn, false);
		emui.label.focus();
	}

	function closeEmbedForm() {
		EM.editing = null;
		EM.snap = '';
		show(emui.form, false);
		show(emui.newBtn, true);
	}

	function drawEmbeds() {
		clear(emui.list);
		var items = (EM.data && EM.data.embeds) || [];
		// Keep the Add to this page list in the pop-up in step (names only, never the code).
		if (ED.cfg) {
			ED.cfg.embeds = items.map(function (i) {
				return { id: i.id, label: i.label, kind: i.kind };
			});
		}
		if (!items.length) {
			emui.list.appendChild(el('p', { className: 'twd-sk-ed__help', text: 'No embeds yet.' }));
			return;
		}
		items.forEach(function (item) {
			var actions = [];
			if (ED.cfg && ED.cfg.isKitPage) {
				actions.push(button('Add to this page', 'primary', function () {
					addEmbedToPage(item);
				}));
			}
			actions.push(button('Change', 'secondary', function () {
				openEmbedForm(item);
			}));
			actions.push(button('Delete', 'secondary', function () {
				var host = emui.list;
				ED.confirmInline(host, {
					text: 'Delete "' + item.label + '"? Pages that show it will have a gap until you choose another.',
					yesLabel: 'Delete',
					onYes: function () {
						deleteEmbed(item, false);
					}
				});
			}));
			emui.list.appendChild(el('div', { className: 'twd-sk-ed__card' }, [
				el('p', { text: item.label }),
				el('p', { className: 'twd-sk-ed__help', text: ((EM.data.kinds || {})[item.kind] || item.kind) + '. ' + embedUsedText(item) }),
				el('div', { className: 'twd-sk-ed__actions' }, actions)
			]));
		});
	}

	function loadEmbeds() {
		api('GET', '/site/embeds').then(function (data) {
			EM.data = data;
			var htmlOption = emui.kind.querySelector('option[value="html"]');
			if (htmlOption) {
				htmlOption.disabled = !data.can_html;
			}
			drawEmbeds();
		}, function (err) {
			message(emui.msg, err.message, false);
		});
	}

	function saveEmbed() {
		message(emui.msg, 'Saving...', true);
		var body = embedForm();
		body.embed_id = EM.editing || 0;
		api('POST', '/site/embeds', body).then(function (data) {
			EM.data = data;
			closeEmbedForm();
			drawEmbeds();
			message(emui.msg, 'Saved. Choose Add to this page to show it on a page.', true);
		}, function (err) {
			message(emui.msg, err.message, false);
		});
	}

	function deleteEmbed(item, confirmed) {
		api('POST', '/site/embeds/' + item.id + '/delete', { confirm: confirmed }).then(function (data) {
			EM.data = data;
			drawEmbeds();
			message(emui.msg, 'Deleted.', true);
		}, function (err) {
			if (err.status === 409) {
				ED.confirmInline(emui.list, {
					text: err.message,
					yesLabel: 'Delete anyway',
					onYes: function () {
						deleteEmbed(item, true);
					}
				});
				return;
			}
			message(emui.msg, err.message, false);
		});
	}

	// A new Embed section at the end of this page, shown in the normal preview. Nothing is saved until Apply.
	function addEmbedToPage(item) {
		message(emui.msg, 'Preparing the preview...', true);
		api('GET', '/pages/' + ED.cfg.pageId + '/sections').then(function (info) {
			var count = (info.sections || []).length;
			if (!count) {
				message(emui.msg, 'This page has no sections yet.', false);
				return;
			}
			return api('POST', '/pages/' + ED.cfg.pageId + '/add-section', {
				base_version: ED.version(),
				after: count - 1,
				component: 'embed',
				variant: emui.bare.checked ? 'twd-sk-embed--bare' : '',
				embed: item.id
			}).then(function (out) {
				if (ED.proposeHtml(out.html, 'Added the embed "' + item.label + '"')) {
					message(emui.msg, (out.report || []).join(' ') + ' Check the preview in Edit this page, then apply it.', true);
				}
			});
		}).then(null, function (err) {
			message(emui.msg, err.message, false);
		});
	}

	function buildEmbedsSection(panel) {
		emui.label = textInput('twd-sk-embed-label', 80);
		emui.kind = el('select', { id: 'twd-sk-embed-kind', className: 'twd-sk-ed__input' }, [
			el('option', { value: 'shortcode', text: 'A shortcode (from a booking, map or form plugin)' }),
			el('option', { value: 'html', text: 'HTML code (a snippet from another service)' })
		]);
		emui.code = textArea('twd-sk-embed-code', 8);
		emui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		emui.bare = el('input', { id: 'twd-sk-embed-bare', type: 'checkbox' });
		emui.list = el('div', { className: 'twd-sk-ed__embeds' });
		emui.formTitle = el('h4', { className: 'twd-sk-ed__sub-title', text: 'New embed' });
		emui.form = el('div', { className: 'twd-sk-ed__card', hidden: '' }, [
			emui.formTitle,
			field('Name', emui.label, 'A short name so you can tell it apart, for example Booking calendar. Only you see it.'),
			field('Kind', emui.kind),
			field('The shortcode or code', emui.code, 'Paste it exactly as the plugin or service gave it. It is kept apart from the page and shown as it is, so only add code you trust.'),
			el('div', { className: 'twd-sk-ed__actions' }, [
				button('Save embed', 'primary', saveEmbed),
				button('Cancel', 'secondary', closeEmbedForm)
			])
		]);
		emui.newBtn = button('New embed', 'secondary', function () {
			openEmbedForm(null);
		});
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Embeds' }),
			el('p', { className: 'twd-sk-ed__help', text: 'Show a shortcode or a piece of HTML code from another plugin or service on a page, such as a booking calendar or a map. The code is kept here, apart from the page: it is never part of the page text, the AI never sees it and the Visual editor cannot change it. Only administrators can change an embed.' }),
			el('label', { className: 'twd-sk-ed__check', 'for': 'twd-sk-embed-bare' }, [emui.bare, el('span', { text: 'When I add one to this page, leave out the heading' })]),
			emui.list,
			emui.form,
			el('div', { className: 'twd-sk-ed__actions' }, [emui.newBtn]),
			emui.msg
		]));
		loadEmbeds();
		ED.addDirtyCheck(function () {
			return EM.editing !== null && embedFormText() !== EM.snap ? 'Embeds' : '';
		}, function () {
			closeEmbedForm();
		});
	}

	/* ---- bring pages up to date (0.15.0): add what a later version added to sections built earlier ---- */

	var UP = { data: null };
	var upui = {};

	function drawUpgrades() {
		clear(upui.list);
		var pages = (UP.data && UP.data.pages) || [];
		if (!pages.length) {
			upui.list.appendChild(el('p', { className: 'twd-sk-ed__help', text: 'Every page is up to date.' }));
			show(upui.runBtn, false);
			return;
		}
		pages.forEach(function (p) {
			var lines = p.items.map(function (i) {
				return i.label + (i.count > 1 ? ' (' + i.count + ' places)' : '');
			});
			var card = el('div', { className: 'twd-sk-ed__card' }, [
				el('p', { text: p.title + (p.status !== 'publish' ? ' (' + p.status + ')' : '') })
			]);
			listInto(card, lines);
			upui.list.appendChild(card);
		});
		upui.runBtn.textContent = 'Bring ' + pages.length + ' page' + (pages.length === 1 ? '' : 's') + ' up to date';
		show(upui.runBtn, true);
	}

	function loadUpgrades() {
		api('GET', '/site/upgrades').then(function (data) {
			UP.data = data;
			drawUpgrades();
		}, function (err) {
			message(upui.msg, err.message, false);
		});
	}

	function runUpgrades() {
		message(upui.msg, 'Working...', true);
		api('POST', '/site/upgrades/apply', { confirm: true }).then(function (data) {
			UP.data = data;
			drawUpgrades();
			var n = (data.updated || []).length;
			var bad = (data.failed || []).map(function (f) {
				return '"' + f.title + '": ' + f.reason;
			});
			message(upui.msg, 'Brought ' + n + ' page' + (n === 1 ? '' : 's') + ' up to date. Each is a new version, so Undo in the history puts it back. Reload this page to see the change.' + (bad.length ? ' Not changed: ' + bad.join(' ') : ''), !bad.length);
		}, function (err) {
			message(upui.msg, err.message, false);
		});
	}

	function buildUpgradesSection(panel) {
		upui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		upui.list = el('div', { className: 'twd-sk-ed__upgrades' });
		upui.runBtn = button('Bring pages up to date', 'primary', function () {
			ED.confirmInline(upui.list, {
				text: 'Add what is missing to these pages? Nothing you wrote is changed, and each page keeps its old version in the history.',
				yesLabel: 'Bring them up to date',
				onYes: runUpgrades
			});
		});
		show(upui.runBtn, false);
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Bring pages up to date' }),
			el('p', { className: 'twd-sk-ed__help', text: 'When a newer version of Site Kit adds a little more to a section, pages built earlier do not have it. This lists them and adds only what is missing, without changing any of your words.' }),
			upui.list,
			el('div', { className: 'twd-sk-ed__actions' }, [upui.runBtn]),
			upui.msg
		]));
		loadUpgrades();
	}

	/* ---- Site changes (0.15.0): the settings history with Undo and Restore ----
	 * Every Save in this tab records the settings as they were, so a footer, menu, style or header change
	 * can be undone like a page change. Undo steps back one change; the page behind redraws. */
	var hui = {};
	var HIST = { list: [], busy: false };

	function drawHistory() {
		clear(hui.list);
		hui.undoBtn.disabled = HIST.busy || !HIST.list.length;
		if (!HIST.list.length) {
			hui.list.appendChild(el('li', { className: 'twd-sk-ed__help', text: 'No site changes recorded yet. From now on every Save in this tab is listed here.' }));
			return;
		}
		HIST.list.forEach(function (e) {
			var when = e.at ? new Date(String(e.at).replace(' ', 'T') + 'Z') : null;
			var head = (e.kind === 'undo' ? 'Before an undo: ' : '') + e.label + (e.same ? ' (the site looks like this now)' : '');
			var row = el('li', { className: 'twd-sk-ed__version' }, [
				el('span', { className: 'twd-sk-ed__version-text' }, [
					el('span', { className: e.same ? 'twd-sk-ed__current' : '', text: head }),
					el('span', { className: 'twd-sk-ed__version-note', text: 'The site as it was ' + (when && !isNaN(when.getTime()) ? 'on ' + when.toLocaleString() : 'then') + (e.by ? ', saved by ' + e.by : '') })
				])
			]);
			if (!e.same) {
				row.appendChild(button('Put the site back to this', 'secondary', function () {
					historyAction('/site/history/restore', { index: e.index, at: e.at }, 'Put the site back to how it was before "' + e.label + '".');
				}));
			}
			hui.list.appendChild(row);
		});
	}

	function loadHistory() {
		if (!hui.list) {
			return Promise.resolve();
		}
		return api('GET', '/site/history').then(function (data) {
			HIST.list = data.history || [];
			drawHistory();
		}, function (err) {
			message(hui.msg, err.message, false);
		});
	}

	function refreshForms() {
		// The style, site details and header and footer boxes show what is saved, so fill them again.
		var jobs = [load()];
		jobs.push(api('GET', '/site/profile').then(function (data) {
			P.data = data;
			fillProfile();
			fillChrome();
		}, function () {}));
		return Promise.all(jobs);
	}

	function historyAction(path, body, what, where) {
		if (HIST.busy) {
			return;
		}
		// The message shows where the button was pressed (the Header and footer section has its own Undo, session 7).
		var msgNode = where || hui.msg;
		HIST.busy = true;
		hui.undoBtn.disabled = true;
		message(msgNode, 'Working...', true);
		api('POST', path, body).then(function (data) {
			HIST.busy = false;
			HIST.list = data.history || [];
			drawHistory();
			var text = data.undid ? 'Undid: ' + data.undid + '.' : (data.unchanged ? 'The site already looked like that.' : what);
			message(msgNode, text + ' Redrawing the page...', true);
			refreshForms().then(function () {
				return ED.redraw();
			}).then(function (ok) {
				message(msgNode, text + (ok ? '' : ' Reload the page to see it.'), true);
				show(hui.reloadBtn, !ok);
			});
		}, function (err) {
			HIST.busy = false;
			drawHistory();
			message(msgNode, err.message, false);
		});
	}

	function buildHistorySection(panel) {
		hui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		hui.list = el('ol', { className: 'twd-sk-ed__versions' });
		hui.undoBtn = button('Undo the last site change', 'secondary', function () {
			historyAction('/site/history/undo', {}, 'Went back one site change.');
		});
		hui.undoBtn.disabled = true;
		hui.reloadBtn = button('Reload the page to see it', 'secondary', function () {
			window.location.reload();
		});
		show(hui.reloadBtn, false);
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Site changes' }),
			el('p', { className: 'twd-sk-ed__help', text: 'Every Save of the style, site details or header and footer is listed here, newest first. Undo steps back one change (the page words are separate: use History and undo on the Edit tab for those). Nothing is deleted: going back is recorded too, so you can go forward again.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [hui.undoBtn, hui.reloadBtn]),
			hui.msg,
			hui.list
		]));
		loadHistory();
	}

	// Your Edits menu (session 7, section 21b): one clearly named way to the header and footer settings.
	if (ED.addLauncher) {
		ED.addLauncher({ id: 'chrome', order: 45, label: function () { return 'Header and footer'; }, show: function () { return !(ED.toolsOff && ED.toolsOff()); }, run: function () {
			ED.openAt('site', 'twd-sk-chrome-ways');
		} });
	}
	// Your Edits menu: undo the last site change from anywhere (opens the Site tab at Site changes so the result is seen).
	if (ED.addLauncher) {
		ED.addLauncher({ id: 'undo-site', order: 50, label: function () { return 'Undo the last site change'; }, show: function () { return !(ED.toolsOff && ED.toolsOff()); }, run: function () {
			ED.openAt('site');
			if (hui.list) {
				ED.revealFold(hui.list);
				hui.list.scrollIntoView({ block: 'center' });
				historyAction('/site/history/undo', {}, 'Went back one site change.');
			}
		} });
	}
	ED.siteHistoryReload = loadHistory;

	function buildSite(panel) {
		buildModeSection(panel);
		buildLooksSection(panel);
		buildBriefSection(panel);
		buildStyleSection(panel);
		buildProfileSection(panel);
		buildHistorySection(panel);
		buildFactsSection(panel);
		buildEmbedsSection(panel);
		buildUpgradesSection(panel);
		buildChromeSection(panel);
		buildSetupSection(panel);
		buildLaunchSection(panel);
		buildSnagsSection(panel);
		buildDoctorSection(panel);
		var summaries = {
			'Site type': 'Now: ' + ((WORDS.modes && WORDS.modes[WORDS.mode]) ? WORDS.modes[WORDS.mode].label : 'Therapy'),
			'Site brief': 'Build or update the whole site from one file',
			'Style': 'Pick a look, colours, fonts and corners',
			'Site details': 'Name, menu, contact lines, footer text',
			'Site changes': 'Undo a saved style, site details or header and footer change',
			'Embeds': 'Show a booking calendar, map or other outside code on a page',
			'Bring pages up to date': 'Add what newer versions added to pages built earlier',
			'Header and footer': 'Who places them, layout, fixed header',
			'Set up a new site': 'Create the starter pages',
			'Launch check': 'Everything to check before going live',
			'Tell us notes': 'Let clients send you notes, and how long you keep them',
			'Look panel': 'Box, Border and Padding: for you, or for clients too',
			'Site report': 'A plain-text report to paste when something looks wrong'
		};
		summaries[FACTS_TITLE] = 'The master document the AI writes from';
		Array.prototype.forEach.call(panel.querySelectorAll('.twd-sk-ed__step'), function (sec) {
			var t = sec.querySelector('.twd-sk-ed__step-title');
			ED.foldSection(sec, { summary: t && summaries[t.textContent] ? summaries[t.textContent] : '' });
		});
	}

	ED.addTab('site', 'Site', buildSite);
})();
