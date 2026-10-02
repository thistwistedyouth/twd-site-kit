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
		return {
			profile: {
				site_name: pui.name.value,
				logo_id: logo ? parseInt(logo, 10) : 0,
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
		pui.ctaLabel = textInput('twd-sk-pf-ctalabel', 30);
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
		pui.checks = el('div', { className: 'twd-sk-ed__banner', role: 'status', hidden: '' });
		pui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		pui.saveBtn = button('Save site details', 'primary', saveProfile);
		pui.reloadBtn = button('Reload the page to see it', 'secondary', function () {
			window.location.reload();
		});
		show(pui.reloadBtn, false);
		var logoRow = el('div', { className: 'twd-sk-ed__inline' }, [pui.logo, pui.logoBtn]);
		logoRow.setAttribute('id', 'twd-sk-pf-logo-row');
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Site details' }),
			el('p', { className: 'twd-sk-ed__help', text: 'These feed the header, the footer and the search engine details. Only add what the client has given you. Leave a box empty rather than guess.' }),
			pui.checks,
			el('div', { className: 'twd-sk-ed__grid' }, [
				field('Site name', pui.name, 'Shown as text if there is no logo.'),
				field('Logo picture number', logoRow, 'Optional. A picture from the media library.'),
				field('Header button text', pui.ctaLabel),
				field('Header button link', pui.ctaUrl, 'A page on this site, such as /contact.'),
				field('Phone number', pui.phone),
				field('Email address', pui.email),
				field('Where you work', pui.area, 'For example a town, or Online.'),
				field('Therapist name', pui.personName, 'For the search engine details.'),
				field('Therapist job title', pui.personJob)
			]),
			field('Address lines', pui.address, 'Up to three lines. Leave empty for an online practice.'),
			field('Footer text', pui.footerText, 'One or two sentences.'),
			field('Registration or membership lines', pui.registration, 'Only exactly what the therapist has supplied. One line each, up to four.'),
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

	function fillChrome() {
		var c = P.data.chrome;
		cui.header.value = c.settings.header_variant;
		cui.footer.value = c.settings.footer_variant;
		cui.cols.value = String(c.settings.footer_columns);
		cui.sticky.checked = !!c.settings.sticky;
		cui.button.checked = !!c.settings.show_button;
		cui.strip.checked = !!c.settings.show_strip;
		var e = c.effective;
		cui.snap = chromeSnap();
		cui.note.textContent = 'Now showing: ' + c.header_variants[e.header] + ' (header), ' + c.footer_variants[e.footer] + ' (footer).' + (e.sticky_note ? ' The centred header cannot be fixed to the top, so that setting is ignored.' : '');
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
		api('POST', '/site/chrome', {
			settings: {
				header_variant: cui.header.value,
				footer_variant: cui.footer.value,
				footer_columns: parseInt(cui.cols.value, 10),
				sticky: cui.sticky.checked,
				show_button: cui.button.checked,
				show_strip: cui.strip.checked
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
		var sticky = checkbox('twd-sk-ch-sticky', 'Keep the header fixed at the top of the screen');
		var btn = checkbox('twd-sk-ch-button', 'Show the header button');
		var strip = checkbox('twd-sk-ch-strip', 'Show a thin strip with the phone number and email above the header');
		cui.sticky = sticky.input;
		cui.button = btn.input;
		cui.strip = strip.input;
		cui.note = el('p', { className: 'twd-sk-ed__help' });
		cui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		cui.saveBtn = button('Save header and footer', 'primary', saveChrome);
		cui.reloadBtn = button('Reload the page to see it', 'secondary', function () {
			window.location.reload();
		});
		show(cui.reloadBtn, false);
		cui.exportBtn = button('Download the Theme Builder templates', 'secondary', downloadTemplates);
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Header and footer' }),
			el('p', { className: 'twd-sk-ed__help', text: 'The header and footer print from the site details above, through two shortcodes in the Elementor Theme Builder. Choose how they look here.' }),
			cui.note,
			el('div', { className: 'twd-sk-ed__grid' }, [
				field('Header layout', cui.header),
				field('Footer layout', cui.footer),
				field('Footer columns', cui.cols, 'Used by the column footers.')
			]),
			sticky.label,
			btn.label,
			strip.label,
			el('div', { className: 'twd-sk-ed__actions' }, [cui.saveBtn, cui.exportBtn, cui.reloadBtn]),
			cui.msg
		]));
		// The layout names come with the profile, so fill the lists once it has arrived.
		P.ready.then(function () {
			if (!P.data) {
				return;
			}
			var c = P.data.chrome;
			Object.keys(c.header_variants).forEach(function (k) {
				cui.header.appendChild(el('option', { value: k, text: c.header_variants[k] }));
			});
			Object.keys(c.footer_variants).forEach(function (k) {
				cui.footer.appendChild(el('option', { value: k, text: c.footer_variants[k] }));
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
		return JSON.stringify([cui.header.value, cui.footer.value, cui.cols.value, cui.sticky.checked, cui.button.checked, cui.strip.checked]);
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

	function buildSite(panel) {
		buildStyleSection(panel);
		buildProfileSection(panel);
		buildChromeSection(panel);
		buildSetupSection(panel);
	}

	ED.addTab('site', 'Site', buildSite);
})();
