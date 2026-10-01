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
		if (name === 'font-heading' || name === 'font-body') {
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

	function evaluate() {
		var tokens = effective();
		var blocking = [];
		var warnings = [];
		S.data.pairs.forEach(function (p) {
			var r = contrastRatio(tokens[p.fg], tokens[p.bg]);
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
		var input = el('input', { id: id, type: 'number', min: '0', max: '100', step: '1', className: 'twd-sk-ed__input' });
		if (m) {
			input.value = String(Math.round(parseFloat(m[1])));
			input.addEventListener('input', function () {
				var n = Math.max(0, Math.min(100, parseInt(input.value, 10) || 0));
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

	function renderControls() {
		clear(ui.controls);
		var tokens = effective();
		var groups = { colours: 'Colours', fonts: 'Fonts', shape: 'Corners' };
		['colours', 'fonts', 'shape'].forEach(function (group) {
			var grid = el('div', { className: 'twd-sk-ed__grid' });
			S.data.editable.forEach(function (item) {
				if (item.group !== group) {
					return;
				}
				var value = tokens[item.token];
				grid.appendChild(item.kind === 'color' ? colourControl(item, value) : item.kind === 'font' ? fontControl(item, value) : radiusControl(item, value));
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
			el('p', { className: 'twd-sk-ed__help', text: 'Pick a style, then change colours, fonts and corners if you need to. Changes show on this page straight away. Nothing is kept until you press Save style.' }),
			ui.packs,
			ui.controls,
			ui.contrast,
			el('div', { className: 'twd-sk-ed__actions' }, [ui.saveBtn, ui.discardBtn, ui.resetBtn, ui.reloadBtn]),
			ui.resetHost,
			ui.msg
		]));
		load();
	}

	function buildSite(panel) {
		buildStyleSection(panel);
	}

	ED.addTab('site', 'Site', buildSite);
})();
