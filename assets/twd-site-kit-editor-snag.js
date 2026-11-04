/* TWD Site Kit: the Snag button for administrators (0.15.0, section 24, 30 Oct 2026). Vanilla JS, no build step.
 *
 * Loaded only for administrators, outside safe mode, in both views (Administrator and viewing as the client).
 * A real client never gets this script: they keep Tell us. One press (the Your Edits menu, the Snag button in
 * the window's header, or Alt+Shift+S anywhere on the page) opens a small box: one line, a category, Save.
 * Saved with it, automatically: the lead-up record the main editor script keeps (the last 30 editor events),
 * the page, section, versions, build, view, window and browser, and the editor's own script errors. A tick
 * attaches the last Preview and the What changed note. Nothing is sent until Save; nothing leaves the site.
 * It never builds HTML from text and talks only through the editor's own api (the address the server gave). */
(function () {
	'use strict';

	var cfg = window.TWD_SK_SNAG;
	var ED = window.TWD_SK_ED;
	var root = document.getElementById('twd-sk-ed');
	if (!cfg || !ED || !root || !ED.api) {
		return;
	}
	var el = ED.el;
	var clear = ED.clear;
	var show = ED.show;
	var button = ED.button;
	var message = ED.message;
	var api = ED.api;
	var SHORTCUT = cfg.shortcut || 'Alt+Shift+S';
	var MAX_TEXT = 20000;

	var ui = {};
	var S = { built: false, opener: null, list: null, listUi: null, counts: null };

	/* ---- what goes with a snag ---- */

	function browserName() {
		var ua = navigator.userAgent || '';
		var m;
		var name = 'Browser';
		if ((m = ua.match(/Edg\/(\d+)/))) {
			name = 'Edge ' + m[1];
		} else if ((m = ua.match(/OPR\/(\d+)/))) {
			name = 'Opera ' + m[1];
		} else if ((m = ua.match(/Firefox\/(\d+)/))) {
			name = 'Firefox ' + m[1];
		} else if ((m = ua.match(/Chrome\/(\d+)/))) {
			name = 'Chrome ' + m[1];
		} else if ((m = ua.match(/Version\/(\d+)[^ ]* .*Safari/))) {
			name = 'Safari ' + m[1];
		}
		var os = /Windows/.test(ua) ? 'Windows' : (/iPhone|iPad/.test(ua) ? 'iOS' : (/Android/.test(ua) ? 'Android' : (/Mac/.test(ua) ? 'Mac' : (/Linux/.test(ua) ? 'Linux' : ''))));
		return name + (os ? ' on ' + os : '');
	}

	function context() {
		var ec = ED.cfg || {};
		var sec = ED.currentSection || null;
		var c = {
			page_id: ec.pageId || 0,
			page_title: cfg.pageTitle || (document.title || '').split(' - ')[0],
			page_version: typeof ED.version === 'function' ? ED.version() : (ec.currentVersion || 0),
			view: ec.simple ? 'Client' : 'Administrator',
			width: window.innerWidth,
			height: window.innerHeight,
			browser: browserName(),
			window: typeof ED.windowState === 'function' ? ED.windowState() : '',
			// The path only: a query string could hold a preview token.
			url_path: window.location.pathname,
			client_time: new Date().toString().replace(/\s*\(.*\)$/, ''),
			errors: typeof ED.scriptErrors === 'function' ? ED.scriptErrors() : []
		};
		if (sec && typeof sec.i === 'number' && sec.i >= 0) {
			c.section = sec.i;
			c.section_kind = sec.kind || '';
		}
		return c;
	}

	function contextLines(c) {
		var lines = [];
		lines.push('Page: ' + (c.page_title || 'none') + (c.page_id ? ' (ID ' + c.page_id + ')' : '') + (c.page_version ? ', version ' + c.page_version : ''));
		if (c.section !== undefined) {
			lines.push('Section: ' + c.section + (c.section_kind ? ' (' + c.section_kind + ')' : ''));
		}
		lines.push('View: ' + c.view + (c.window ? ', window ' + c.window : ''));
		lines.push('Browser: ' + c.browser + ', ' + c.width + ' by ' + c.height + ' px');
		if (c.errors && c.errors.length) {
			lines.push('Script errors: ' + c.errors.length);
		}
		var events = typeof ED.record === 'function' ? ED.record() : [];
		lines.push('Lead-up: the last ' + events.length + ' editor event' + (events.length === 1 ? '' : 's') + ' (presses, tabs, sections, routes with their status, refusals shown)');
		return lines;
	}

	/* ---- the box ---- */

	function focusables() {
		return Array.prototype.filter.call(ui.dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'), function (n) {
			return !n.disabled && !n.hasAttribute('hidden');
		});
	}

	function trapFocus(e) {
		if (e.key !== 'Tab') {
			return;
		}
		var items = focusables();
		if (!items.length) {
			e.preventDefault();
			return;
		}
		var first = items[0];
		var last = items[items.length - 1];
		if (e.shiftKey && (document.activeElement === first || document.activeElement === ui.title)) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	function build() {
		ui.title = el('h2', { id: 'twd-sk-snag-title', className: 'twd-sk-ed__title', text: 'Snag', tabindex: '-1' });
		var closeBtn = el('button', { type: 'button', className: 'twd-sk-ed__close', text: 'Close', 'aria-label': 'Close the Snag box' });
		closeBtn.addEventListener('click', close);
		ui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });

		ui.text = el('input', { type: 'text', id: 'twd-sk-snag-text', className: 'twd-sk-ed__input', maxlength: String(cfg.maxLine || 500), autocomplete: 'off' });
		ui.text.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				save();
			}
		});
		ui.cats = {};
		var radios = el('div', { className: 'twd-sk-ed__radios', role: 'radiogroup', 'aria-label': 'Category' });
		[['fault', 'Fault'], ['wanted', 'Wanted'], ['question', 'Question']].forEach(function (pair, i) {
			var r = el('input', { type: 'radio', name: 'twd-sk-snag-cat', id: 'twd-sk-snag-cat-' + pair[0], value: pair[0] });
			if (i === 0) {
				r.checked = true;
			}
			ui.cats[pair[0]] = r;
			radios.appendChild(el('label', { 'for': 'twd-sk-snag-cat-' + pair[0] }, [r, el('span', { text: pair[1] })]));
		});
		ui.include = el('input', { type: 'checkbox', id: 'twd-sk-snag-include' });
		ui.withList = el('ul', { className: 'twd-sk-ed__list' });
		ui.save = button('Save', 'primary', save);

		var body = el('div', { className: 'twd-sk-ed__body' }, [
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-snag-text', text: 'What went wrong or what do you want?' }),
			ui.text,
			el('p', { className: 'twd-sk-ed__label', text: 'Category' }),
			radios,
			el('label', { className: 'twd-sk-ed__check', 'for': 'twd-sk-snag-include' }, [ui.include, el('span', { text: 'Include the last Preview and What changed note (the candidate HTML and CSS as sent, and the AI\'s note)' })]),
			el('p', { className: 'twd-sk-ed__help', text: 'Saved with it, on this site only:' }),
			ui.withList,
			el('p', { className: 'twd-sk-ed__help', text: 'Never saved: the words typed on the page, the contents of the HTML and CSS boxes (their sizes only), passwords, keys or sign-ins. Nothing is sent anywhere else; export it from the laptop with wp twd-sk snags export.' }),
			ui.msg,
			el('div', { className: 'twd-sk-ed__actions' }, [ui.save, closeBtn])
		]);
		ui.dialog = el('div', { className: 'twd-sk-ed__dialog twd-sk-ed__dialog--compact', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'twd-sk-snag-title' }, [
			el('div', { className: 'twd-sk-ed__header' }, [ui.title, el('span', { className: 'twd-sk-ed__help', text: SHORTCUT })]),
			body
		]);
		ui.dialog.addEventListener('keydown', trapFocus);
		ui.overlay = el('div', { id: 'twd-sk-snag-overlay', className: 'twd-sk-ed__overlay', hidden: '' }, [ui.dialog]);
		ui.overlay.addEventListener('mousedown', function (e) {
			if (e.target === ui.overlay) {
				close();
			}
		});
		root.appendChild(ui.overlay);
		S.built = true;
	}

	function fillWith() {
		clear(ui.withList);
		contextLines(context()).forEach(function (line) {
			ui.withList.appendChild(el('li', { text: line }));
		});
	}

	function onKey(e) {
		if (e.key === 'Escape' && ui.overlay && !ui.overlay.hasAttribute('hidden')) {
			e.stopPropagation();
			e.preventDefault();
			close();
		}
	}

	function open() {
		if (!S.built) {
			build();
			document.addEventListener('keydown', onKey, true);
		}
		if (!ui.overlay.hasAttribute('hidden')) {
			ui.text.focus();
			return;
		}
		if (typeof ED.log === 'function') {
			ED.log('snag', 'Snag box opened');
		}
		S.opener = document.activeElement;
		message(ui.msg, '', true);
		ui.save.disabled = false;
		fillWith();
		show(ui.overlay, true);
		ui.text.focus();
	}

	function close() {
		if (!S.built) {
			return;
		}
		show(ui.overlay, false);
		if (S.opener && typeof S.opener.focus === 'function' && document.body.contains(S.opener)) {
			S.opener.focus();
		}
		S.opener = null;
	}

	function category() {
		var out = 'fault';
		Object.keys(ui.cats).forEach(function (k) {
			if (ui.cats[k].checked) {
				out = k;
			}
		});
		return out;
	}

	function save() {
		var text = ui.text.value.trim();
		if (text.length < 3) {
			message(ui.msg, 'Write a line about what went wrong or what you want.', false);
			ui.text.focus();
			return;
		}
		var body = {
			text: text,
			category: category(),
			events: typeof ED.record === 'function' ? ED.record() : [],
			context: context(),
			include: !!ui.include.checked
		};
		if (body.include && typeof ED.lastPreview === 'function') {
			var last = ED.lastPreview();
			body.preview_html = String(last.html || '').slice(0, MAX_TEXT);
			body.preview_css = String(last.css || '').slice(0, MAX_TEXT);
			body.what_changed = String(last.note || '').slice(0, MAX_TEXT);
		}
		ui.save.disabled = true;
		message(ui.msg, 'Saving...', true);
		api('POST', '/site/snagbook', body).then(function (data) {
			ui.save.disabled = false;
			ui.text.value = '';
			ui.include.checked = false;
			var n = data && data.saved ? data.saved.id : 0;
			message(ui.msg, 'Saved as snag ' + n + '. It is in Settings, Snags. From the laptop: wp twd-sk snags export --site=NAME', true);
			S.counts = data && data.counts ? data.counts : S.counts;
			if (S.listUi) {
				loadList();
			}
		}, function (err) {
			ui.save.disabled = false;
			message(ui.msg, err.message, false);
		});
	}

	/* ---- the list, in Settings ---- */

	function statusLabel(s) {
		return s === 'done' ? 'Done' : (s === 'wontdo' ? "Won't do" : 'Open');
	}

	function catLabel(c) {
		return c === 'wanted' ? 'Wanted' : (c === 'question' ? 'Question' : 'Fault');
	}

	function detailText(s) {
		var lines = [];
		(s.detail || '').split('\n').forEach(function (line) {
			lines.push(line);
		});
		return lines.join('\n');
	}

	function drawList() {
		var L = S.listUi;
		clear(L.list);
		var counts = S.counts || { open: 0, unexported: 0, total: 0, cap: 200 };
		L.summary.textContent = counts.total + (counts.total === 1 ? ' snag' : ' snags') + ' (' + counts.open + ' open, ' + counts.unexported + ' not yet exported; the list keeps the newest ' + counts.cap + ').';
		if (!S.list || !S.list.length) {
			L.list.appendChild(el('li', { className: 'twd-sk-ed__help', text: 'No snags yet. Press Snag (' + SHORTCUT + ') the moment something goes wrong, or when you want something.' }));
			return;
		}
		S.list.forEach(function (s) {
			if (L.filter.value !== 'all' && s.status !== L.filter.value) {
				return;
			}
			var head = el('span', { className: 'twd-sk-ed__version-text' }, [
				el('span', { className: s.status === 'open' ? 'twd-sk-ed__current' : '', text: s.time + ' | ' + (s.page_title || 'Not on a page') + ' | ' + catLabel(s.category) + ': ' + s.text }),
				el('span', { className: 'twd-sk-ed__version-note', text: 'Snag ' + s.id + ', ' + statusLabel(s.status) + (s.exported ? ', exported' : ', not yet exported') + (s.build ? ', build ' + s.build : '') + ', ' + s.events + ' events' + (s.has_texts ? ', with the preview texts' : '') })
			]);
			var row = el('li', { className: 'twd-sk-ed__version' }, [head]);
			var actions = el('div', { className: 'twd-sk-ed__actions' });
			['open', 'done', 'wontdo'].forEach(function (st) {
				if (st === s.status) {
					return;
				}
				actions.appendChild(button(st === 'open' ? 'Reopen' : (st === 'done' ? 'Mark done' : "Won't do"), 'secondary', function () {
					setStatus(s.id, st);
				}));
			});
			var box = el('textarea', { className: 'twd-sk-ed__textarea', rows: '12', readonly: '', 'aria-label': 'Snag ' + s.id + ' detail', hidden: '' });
			box.value = (s.line || '') + '\n\n' + detailText(s);
			var detailBtn = button('Detail', 'secondary', function () {
				var on = box.hasAttribute('hidden');
				show(box, on);
				detailBtn.textContent = on ? 'Hide detail' : 'Detail';
			});
			actions.appendChild(detailBtn);
			row.appendChild(actions);
			row.appendChild(box);
			L.list.appendChild(row);
		});
	}

	function loadList() {
		var L = S.listUi;
		if (!L) {
			return;
		}
		api('GET', '/site/snagbook').then(function (data) {
			S.list = data.snags || [];
			S.counts = data.counts || null;
			drawList();
		}, function (err) {
			message(L.msg, err.message, false);
		});
	}

	function setStatus(id, status) {
		var L = S.listUi;
		message(L.msg, 'Saving...', true);
		api('POST', '/site/snagbook/' + id + '/status', { status: status }).then(function (data) {
			message(L.msg, 'Snag ' + id + ' is now ' + statusLabel(status) + '.', true);
			S.counts = data.counts || S.counts;
			loadList();
		}, function (err) {
			message(L.msg, err.message, false);
		});
	}

	function buildPanel(panel) {
		var L = {};
		S.listUi = L;
		L.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		L.summary = el('p', { className: 'twd-sk-ed__help', text: 'Loading...' });
		L.filter = el('select', { className: 'twd-sk-ed__select', id: 'twd-sk-snag-filter', 'aria-label': 'Show' });
		[['open', 'Open'], ['done', 'Done'], ['wontdo', "Won't do"], ['all', 'All']].forEach(function (pair) {
			L.filter.appendChild(el('option', { value: pair[0], text: pair[1] }));
		});
		L.filter.addEventListener('change', drawList);
		L.list = el('ol', { className: 'twd-sk-ed__versions' });
		var newBtn = button('Snag (' + SHORTCUT + ')', 'primary', open);
		var reload = button('Reload the list', 'secondary', loadList);
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'Snags' }),
			el('p', { className: 'twd-sk-ed__help', text: 'Your own notes of what went wrong or what you want, each with the editor events that led up to it and where you were. Kept on this site only. A session on the laptop exports them into the client\'s snags.md with wp twd-sk snags export --site=NAME, then marks them exported. Clients never see this list; they have Tell us.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [newBtn, reload]),
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-snag-filter', text: 'Show' }),
			L.filter,
			L.summary,
			L.msg,
			L.list
		]));
		loadList();
	}

	/* ---- the ways in ---- */

	// The keyboard shortcut, anywhere on the page (a text box included: it is the administrator's own account).
	document.addEventListener('keydown', function (e) {
		if (e.altKey && e.shiftKey && !e.ctrlKey && !e.metaKey && (e.code === 'KeyS' || e.key === 'S' || e.key === 's')) {
			e.preventDefault();
			open();
		}
	}, true);

	if (ED.addLauncher) {
		ED.addLauncher({ id: 'snag', order: 35, label: function () { return 'Snag (' + SHORTCUT + ')'; }, run: open });
	}
	if (ED.winButtons) {
		ED.winButtons.push(function () {
			var b = el('button', { type: 'button', className: 'twd-sk-ed__close twd-sk-ed__winbtn twd-sk-ed__snagbtn', text: 'Snag', 'aria-keyshortcuts': SHORTCUT, title: 'Note what went wrong or what you want, with what led up to it (' + SHORTCUT + ')' });
			b.addEventListener('click', open);
			return b;
		});
	}
	// The list: a Settings tab (the one window shows it in the Settings row), not in the Simple editor.
	if (ED.addTab && !(ED.cfg && ED.cfg.simple)) {
		ED.addTab('snags', 'Snags', buildPanel);
		ED.tabHooks.snags = loadList;
	}
})();
