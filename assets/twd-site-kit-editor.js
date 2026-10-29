/* TWD Site Kit: front-end editor (Edit with AI). Vanilla JS, no build step.
 *
 * Loaded only for signed-in users who can edit the page (see TWD_SK_Editor).
 * It never builds HTML from text: every string goes in with textContent, and
 * the only address it talks to is the REST address the server gave it.
 * The AI itself is never called from here. The therapist copies a prompt, pastes
 * the answer back, previews it, and applies it. */
(function () {
	'use strict';

	var cfg = window.TWD_SK_EDITOR;
	/* The one editing window (0.15.0, phase 1). One window with three tabs: On page edits (the Visual editor's tools,
	 * hosted here and shown for what is clicked on the page), AI assisted edits (the Edit this page panel) and Settings
	 * (the Pages, Site and Search panels as a second row of tabs). It opens undocked, sized to its content and never
	 * taller than 70 percent of the window; on a phone it is a bottom sheet. The saving, undo and cancel paths are the
	 * Visual editor's own, unchanged. The classic editor (this full-screen pop-up and the separate card) stays behind a
	 * per-person switch, "Use the classic editor", in the Your Edits menu. */
	var oneWindow = !cfg.classic;
	/* The Simple editor (session 5, 29 Oct 2026): everyone who is not an administrator gets it, and an administrator who
	 * chose "View as: Client". It is a role check on this editor, not a second editor: one round pen button, then the
	 * Visual editor's words and pictures with Bold, Link, Save, Undo, Redo and History (all in twd-site-kit-inline.js)
	 * and nothing else. The window, the pencils, the AI and the Settings never build here, and the server already left
	 * out the admin-only scripts and data. It only hides tools: saving runs with the person's real capabilities. */
	var simple = !!cfg.simple;
	var root = document.getElementById('twd-sk-ed');
	var openBtn = document.getElementById('twd-sk-ed-open');
	if (!cfg || !root || !openBtn) {
		return;
	}

	// Words that change with the site type (therapy or business), from TWD_SK_Mode. Therapy wording if missing.
	var WORDS = cfg.words || {};
	function W(key, fallback) {
		return typeof WORDS[key] === 'string' && WORDS[key] ? WORDS[key] : fallback;
	}
	var FACTS = W('facts', 'practice facts');
	var FACTS_PAGE_TITLE = 'New page from your ' + FACTS;

	var state = {
		tab: '',
		info: null,
		version: cfg.currentVersion || 0,
		prompt: '',
		token: '',
		previewedText: null,
		built: false,
		busy: false
	};
	var ui = {};

	/* ---- small helpers ---- */

	function el(tag, props, kids) {
		var node = document.createElement(tag);
		var key;
		props = props || {};
		for (key in props) {
			if (Object.prototype.hasOwnProperty.call(props, key)) {
				if (key === 'text') {
					node.textContent = props[key];
				} else if (key === 'className') {
					node.className = props[key];
				} else {
					node.setAttribute(key, props[key]);
				}
			}
		}
		(kids || []).forEach(function (kid) {
			if (kid) {
				node.appendChild(kid);
			}
		});
		return node;
	}

	function clear(node) {
		while (node.firstChild) {
			node.removeChild(node.firstChild);
		}
	}

	function button(label, kind, onClick) {
		var b = el('button', { type: 'button', className: 'twd-sk-ed__btn twd-sk-ed__btn--' + kind, text: label });
		b.addEventListener('click', onClick);
		return b;
	}

	function show(node, yes) {
		if (yes) {
			node.removeAttribute('hidden');
		} else {
			node.setAttribute('hidden', '');
		}
	}

	/* ---- talking to the server ---- */

	function api(method, path, body) {
		var opts = {
			method: method,
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' }
		};
		if (body) {
			opts.body = JSON.stringify(body);
		}
		return fetch(cfg.restUrl + path, opts).then(function (res) {
			return res.text().then(function (text) {
				var data = null;
				try {
					data = text ? JSON.parse(text) : null;
				} catch (e) {
					data = null;
				}
				if (!res.ok) {
					var err = new Error(friendly(res.status, data));
					err.status = res.status;
					err.code = data && data.code ? data.code : '';
					// What the server said besides the message (a list of check errors, say).
					err.data = data && data.data && typeof data.data === 'object' ? data.data : null;
					throw err;
				}
				return data;
			});
		}, function () {
			throw new Error('Could not reach the site. Check your connection and try again.');
		});
	}

	function friendly(status, data) {
		if (status === 401) {
			return 'Your sign-in has timed out. Reload the page and sign in again.';
		}
		if (status === 429) {
			// The server says how long for the AI limit (section 20b, point 10).
			return data && typeof data.message === 'string' && data.message ? data.message : 'Too many requests. Wait a minute and try again.';
		}
		if (data && typeof data.message === 'string' && data.message) {
			return data.message;
		}
		return 'Something went wrong (error ' + status + '). Try again.';
	}

	function pagePath(tail) {
		return '/pages/' + cfg.pageId + tail;
	}

	/* ---- messages ---- */

	function message(box, text, ok) {
		clear(box);
		if (!text) {
			show(box, false);
			return;
		}
		box.className = 'twd-sk-ed__msg' + (ok ? ' twd-sk-ed__msg--ok' : '');
		box.appendChild(document.createTextNode(text));
		show(box, true);
	}

	function listInto(box, lines) {
		var ul = el('ul', { className: 'twd-sk-ed__list' });
		lines.forEach(function (line) {
			ul.appendChild(el('li', { text: line }));
		});
		box.appendChild(ul);
	}

	// Example text still on the page, in two levels. "Must fix" blocks publishing (applying is still
	// allowed); "check" only warns and never blocks.
	function leftoverBanner(box, data) {
		clear(box);
		var must = [];
		var check = [];
		((data && data.leftovers) || []).forEach(function (item) {
			(item.level === 'check' ? check : must).push(item);
		});
		if (!must.length && !check.length) {
			show(box, false);
			return;
		}
		function lines(items) {
			return items.map(function (item) {
				return item.marker + ' (' + item.count + '): ' + item.meaning;
			});
		}
		if (must.length) {
			var n = data.must_count;
			box.appendChild(el('strong', { text: 'Must fix before publishing: ' + n + ' example or missing detail' + (n === 1 ? '' : 's') + '.' }));
			box.appendChild(document.createTextNode(' You can still apply this, but the page cannot be published until they are replaced.'));
			listInto(box, lines(must));
		}
		if (check.length) {
			var c = data.check_count;
			box.appendChild(el('strong', { text: 'To check: ' + c + ' piece' + (c === 1 ? '' : 's') + ' of example wording.' }));
			box.appendChild(document.createTextNode(' These never block publishing. Keep them only if they are right for this page.'));
			listInto(box, lines(check));
		}
		show(box, true);
	}

	function resultReport(box, data) {
		clear(box);
		// What changed (0.15.0): worked out on the server by comparing the cleaned page with the page as it is.
		if (data.same) {
			box.appendChild(el('strong', { text: 'Nothing changed' }));
			listInto(box, nothingChangedWhy(data));
			if (oneWindow && hintsFor(state.lastRequest).length) {
				var instead = button('Do this instead', 'secondary', function () {
					var ED = window.TWD_SK_ED;
					selectTab('page');
					if (ED && typeof ED.openLookFor === 'function') {
						ED.openLookFor(state.secIndex !== undefined ? state.secIndex : 0);
					}
				});
				instead.className += ' twd-sk-ed__small';
				box.appendChild(el('div', { className: 'twd-sk-ed__actions' }, [instead]));
			}
		} else if (data.changes && data.changes.length) {
			box.appendChild(el('strong', { text: 'What changed' }));
			listInto(box, data.changes);
		}
		if (data.dropped && data.dropped.length) {
			box.appendChild(el('strong', { text: 'Not possible on this site' }));
			listInto(box, ['The reply used ' + data.dropped.length + ' style name' + (data.dropped.length === 1 ? '' : 's') + ' this site has no layout or option for, so the cleaner took ' + (data.dropped.length === 1 ? 'it' : 'them') + ' out: ' + data.dropped.join(', ') + '.'].concat(hintsFor(state.lastRequest)));
		}
		box.appendChild(el('strong', { text: 'What the cleaner did' }));
		listInto(box, data.report || []);
		show(box, true);
	}

	// Why a preview is the same page, in plain words, with the nearest Look choices for what was asked.
	function nothingChangedWhy(data) {
		var lines = [];
		if (data.dropped && data.dropped.length) {
			lines.push('The reply only used style names this site has no layout or option for, so once they were taken out it was the same page.');
		} else {
			lines.push('The reply came back as the same page. I can only use the layouts and options this site has.');
		}
		var hints = hintsFor(state.lastRequest);
		if (hints.length) {
			lines.push('Nearest choices:');
			lines = lines.concat(hints);
			if (oneWindow) {
				lines.push('Press "Do this instead" to open the Look panel for the section.');
			}
		} else {
			lines.push('Try wording it differently, or use the Visual editor: click inside a section and press Look to see its layouts and options.');
		}
		return lines;
	}

	// The same word lists as TWD_SK_Looks::hints_for() (cfg.look.hints), at most three sentences.
	function hintsFor(text) {
		var out = [];
		var hints = (cfg.look && cfg.look.hints) || [];
		var low = String(text || '').toLowerCase();
		if (!low.trim()) {
			return out;
		}
		hints.forEach(function (h) {
			if (out.length >= 3) {
				return;
			}
			if ((h.words || []).some(function (w) {
				return low.indexOf(w) > -1;
			})) {
				out.push(h.say);
			}
		});
		return out;
	}

	function setBusy(on) {
		state.busy = on;
		ui.previewBtn.disabled = on || !ui.paste.value.trim();
		ui.applyBtn.disabled = on || state.previewedText === null || state.previewedText !== ui.paste.value;
	}

	/* ---- building the pop-up (once, on first open) ---- */

	function build() {
		var title = el('h2', { id: 'twd-sk-ed-title', className: 'twd-sk-ed__title', text: 'Edit with AI', tabindex: '-1' });
		var closeBtn = el('button', { type: 'button', className: 'twd-sk-ed__close', text: 'Close', 'aria-label': 'Close the editor' });
		closeBtn.addEventListener('click', requestClose);
		var minBtn = el('button', { type: 'button', className: 'twd-sk-ed__close twd-sk-ed__minbtn', text: 'Minimise', 'aria-label': 'Minimise the editor to a pencil button', title: 'Shrink the editor to a pencil button; everything in it is kept' });
		minBtn.addEventListener('click', minimise);
		var winBtn = el('button', { type: 'button', className: 'twd-sk-ed__close twd-sk-ed__winbtn', text: 'Window', 'aria-haspopup': 'true', 'aria-expanded': 'false', title: 'Dock the editor to a side, or let it float' });
		winBtn.addEventListener('click', function () {
			toggleWindowMenu(winBtn);
		});
		// Section 23f: Full height fills the screen from top to bottom at the window's width; a second press puts it back.
		var fullBtn = el('button', { type: 'button', className: 'twd-sk-ed__close twd-sk-ed__winbtn twd-sk-ed__fullbtn', text: 'Full height', 'aria-pressed': 'false', title: 'Fill the screen from top to bottom at this width; press again to go back' });
		fullBtn.addEventListener('click', toggleFull);

		var tabs = el('div', { className: 'twd-sk-ed__tabs', role: 'tablist', 'aria-label': 'Editor sections' });
		tabs.addEventListener('keydown', onTabKeys);
		var body = el('div', { className: 'twd-sk-ed__body' });
		ui.tabs = {};
		ui.subTabs = oneWindow ? el('div', { className: 'twd-sk-ed__tabs twd-sk-ed__subtabs', role: 'tablist', 'aria-label': 'Settings sections' }) : null;

		// A tab: in the main row, or (the one window) in the Settings tab's own row.
		function addTab(name, label, buildPanel, sub) {
			var tab = el('button', {
				type: 'button', role: 'tab', id: 'twd-sk-ed-tab-' + name, className: 'twd-sk-ed__tab',
				'aria-selected': 'false', 'aria-controls': 'twd-sk-ed-panel-' + name, text: label
			});
			var panel = el('div', { role: 'tabpanel', id: 'twd-sk-ed-panel-' + name, 'aria-labelledby': 'twd-sk-ed-tab-' + name, hidden: '' });
			buildPanel(panel);
			tab.addEventListener('click', function () {
				selectTab(name);
			});
			(sub && ui.subTabs ? ui.subTabs : tabs).appendChild(tab);
			body.appendChild(panel);
			ui.tabs[name] = { tab: tab, panel: panel, sub: !!(sub && ui.subTabs) };
		}

		if (oneWindow) {
			addTab('page', 'On page edits', buildPagePanel);
			if (cfg.isKitPage && cfg.pageId) {
				addTab('edit', 'AI assisted edits', buildEditPanel);
			}
			addTab('settings', 'Settings', function (panel) {
				panel.appendChild(ui.subTabs);
			});
			addTab('pages', 'Pages', buildPagesPanel, true);
			((window.TWD_SK_ED && window.TWD_SK_ED.extraTabs) || []).forEach(function (t) {
				addTab(t.name, t.label, t.build, true);
			});
		} else {
			if (cfg.isKitPage && cfg.pageId) {
				addTab('edit', 'Edit this page', buildEditPanel);
			}
			addTab('pages', 'Pages', buildPagesPanel);
			((window.TWD_SK_ED && window.TWD_SK_ED.extraTabs) || []).forEach(function (t) {
				addTab(t.name, t.label, t.build);
			});
		}
		if (cfg.safeMode) {
			body.insertBefore(el('div', { className: 'twd-sk-ed__banner', role: 'status', text: 'Safe mode is on. The Site tab, header and footer, SEO fields and the newest features are switched off. Your pages and this editor still work.' }), body.firstChild);
		}

		// View as (19e): an administrator can see the page with the Simple editor, exactly as the client gets it.
		var viewBtn = null;
		if (cfg.canSwitchView && !simple) {
			viewBtn = el('button', { type: 'button', className: 'twd-sk-ed__close twd-sk-ed__viewbtn', text: 'View as Client', title: 'See this page with the Simple editor, exactly as the client gets it' });
			viewBtn.addEventListener('click', function () {
				switchView('client');
			});
		}
		var header = el('div', { className: 'twd-sk-ed__header' }, [title, el('div', { className: 'twd-sk-ed__winbtns' }, [viewBtn, winBtn, fullBtn, minBtn, closeBtn])]);
		var resize = el('div', { className: 'twd-sk-ed__resize', 'aria-hidden': 'true', title: 'Drag to resize' });
		// Section 23f: the bottom edge resizes the height on its own (the corner does both).
		var resizeB = el('div', { className: 'twd-sk-ed__resize-b', 'aria-hidden': 'true', title: 'Drag to change the height' });
		resizeB.addEventListener('pointerdown', function (e) {
			startResize(e, 'height');
		});
		var dialog = el('div', {
			className: 'twd-sk-ed__dialog' + (oneWindow ? ' twd-sk-ed__dialog--compact' : ''), role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'twd-sk-ed-title'
		}, [
			header,
			tabs,
			body,
			resizeB,
			resize
		]);
		dialog.addEventListener('keydown', trapFocus);
		ui.header = header;
		ui.resize = resize;
		ui.resizeB = resizeB;
		ui.winBtn = winBtn;
		ui.fullBtn = fullBtn;

		ui.overlay = el('div', { id: 'twd-sk-ed-overlay', className: 'twd-sk-ed__overlay', hidden: '' }, [dialog]);
		ui.overlay.addEventListener('mousedown', function (e) {
			if (e.target === ui.overlay && win.mode === 'centre') {
				requestClose();
			}
		});
		ui.dialog = dialog;
		ui.title = title;
		root.appendChild(ui.overlay);
		state.built = true;
		if (oneWindow) {
			title.textContent = 'Your Edits';
		}
		selectTab(oneWindow ? 'page' : (ui.tabs.edit ? 'edit' : 'pages'));
		setUpWindow();
	}

	/* ---- the On page edits tab (0.15.0, phase 1) ----
	 * "Click what you want to change on the page." The Visual editor (twd-site-kit-inline.js) puts its tool bar into
	 * this tab when the window opens and shows the right tools for what was clicked (words, a picture, a section).
	 * Above them a small outline of the sections, to jump to one. */
	function buildPagePanel(panel) {
		if (!cfg.inline) {
			panel.appendChild(el('p', { className: 'twd-sk-ed__help', text: 'This page has no words to change here. Use the Settings tab for the site, or make a kit page first.' }));
			return;
		}
		ui.pageHint = el('p', { className: 'twd-sk-ed__help twd-sk-ed__pagehint', text: 'Click what you want to change on the page. The tools for it appear here. Nothing is saved until you press Save changes.' });
		ui.outline = el('ol', { className: 'twd-sk-ed__outline', 'aria-label': 'Sections on this page' });
		ui.inlineHost = el('div', { className: 'twd-sk-ed__inlinehost' });
		panel.appendChild(ui.pageHint);
		panel.appendChild(ui.outline);
		panel.appendChild(ui.inlineHost);
	}

	function buildOutline() {
		if (!ui.outline) {
			return;
		}
		clear(ui.outline);
		Array.prototype.forEach.call(document.querySelectorAll('section[data-twd-sk-sec]'), function (sec) {
			var index = parseInt(sec.getAttribute('data-twd-sk-sec'), 10);
			var b = el('button', { type: 'button', className: 'twd-sk-ed__outlineitem', text: (index + 1) + '. ' + sectionName(sec), title: 'Go to this section' });
			b.addEventListener('click', function () {
				var ED = window.TWD_SK_ED;
				if (ED && typeof ED.focusSection === 'function') {
					ED.focusSection(index);
				} else {
					sec.scrollIntoView({ block: 'start' });
				}
			});
			ui.outline.appendChild(el('li', {}, [b]));
		});
	}

	// The window's width for a tab, until the person resizes it: the page tools are narrow, the settings forms wide.
	function tabWidth(name) {
		if (name === 'page') {
			return 480;
		}
		if (name === 'edit') {
			return 560;
		}
		return 780;
	}

	// Start the Visual editor for the On page edits tab (its own script does the work), once it has loaded.
	function startInlineForPage() {
		var ED = window.TWD_SK_ED;
		if (oneWindow && cfg.inline && state.tab === 'page' && !ui.overlay.hasAttribute('hidden') && ED && typeof ED.startInline === 'function') {
			ED.startInline();
		}
	}

	/* ---- the window (0.15.0): drag by the title bar, resize from the corner, dock left or right, minimise to a pencil ----
	 * Centred (as before) the window is a modal over a dimmed page. Docked or free, the page behind stays usable: the
	 * overlay stops dimming and lets clicks through, Tab is no longer trapped, and only the window itself takes
	 * pointer events. Where it sits is kept per person on the server (cfg.window, POST /editor/window), never in the
	 * browser. Minimise hides the window and shows one large pencil button; nothing inside is thrown away, so the
	 * typed text, a preview, the open fold-outs and unsaved changes are all still there when it comes back. */
	var win = { mode: 'centre', x: 0, y: 0, w: 0, h: 0, mx: -1, my: -1, esc: 'close', full: false, prev: null, minimised: false, menu: null, saveTimer: 0, placed: false, sized: false };

	function phoneWidth() {
		return window.innerWidth <= 700;
	}

	function setUpWindow() {
		var saved = cfg.window || {};
		['mode', 'x', 'y', 'w', 'h', 'mx', 'my', 'esc', 'full'].forEach(function (k) {
			if (saved[k] !== undefined) {
				win[k] = saved[k];
			}
		});
		win.full = win.full === true;
		if (win.mode !== 'left' && win.mode !== 'right' && win.mode !== 'free') {
			win.mode = 'centre';
		}
		win.placed = win.mode === 'free' && (win.x > 0 || win.y > 0);
		win.sized = win.mode === 'free' && win.w > 0;
		// The one window always opens undocked, top right, at its compact size for the tab (the owner's choice, 29 Oct
		// 2026): a drag, a resize or a dock holds only while the page is open. The classic editor keeps the saved place.
		if (oneWindow) {
			win.mode = 'free';
			win.placed = false;
			win.sized = false;
		}
		// Full height (section 23f) is remembered, and only means something for a free window.
		if (win.full && win.mode !== 'free') {
			win.full = false;
		}
		ui.header.addEventListener('pointerdown', startDrag);
		ui.resize.addEventListener('pointerdown', function (e) {
			startResize(e, 'both');
		});
		window.addEventListener('resize', function () {
			applyWindow();
		});
		applyWindow();
	}

	function clamp(n, lo, hi) {
		return Math.max(lo, Math.min(hi, n));
	}

	// Put the window where its state says. On a phone it is always full screen, as before.
	function applyWindow() {
		var o = ui.overlay;
		var d = ui.dialog;
		var mode = phoneWidth() ? 'centre' : win.mode;
		['left', 'right', 'free'].forEach(function (m) {
			o.classList.toggle('twd-sk-ed__overlay--' + m, mode === m);
		});
		// The one window on a phone: a bottom sheet, not the full screen.
		o.classList.toggle('twd-sk-ed__overlay--sheet', oneWindow && phoneWidth());
		d.setAttribute('aria-modal', mode === 'centre' ? 'true' : 'false');
		d.style.left = '';
		d.style.top = '';
		d.style.width = '';
		d.style.height = '';
		var full = mode === 'free' && win.full;
		// Section 23f: a tall window (full height, or a height the person dragged) lifts the compact window's 70 percent cap.
		d.classList.toggle('twd-sk-ed__dialog--tall', !!(oneWindow && (full || (win.sized && win.h > 0))));
		d.classList.toggle('twd-sk-ed__dialog--full', full);
		if (mode === 'free') {
			var maxW = Math.max(320, window.innerWidth - 16);
			var maxH = Math.max(240, window.innerHeight - 16);
			var w = clamp(oneWindow ? (win.sized ? win.w : tabWidth(state.tab)) : (win.w || 760), 320, maxW);
			var h = clamp(win.h || Math.round(window.innerHeight * 0.8), 240, maxH);
			var bar = document.getElementById('wpadminbar');
			var top = bar ? bar.offsetHeight + 16 : 16;
			var x = clamp(oneWindow && !win.placed ? window.innerWidth - w - 24 : win.x, 0, Math.max(0, window.innerWidth - w));
			var y = clamp(oneWindow && !win.placed ? top : win.y, 0, Math.max(0, window.innerHeight - (oneWindow ? 120 : h)));
			if (full) {
				// From the bottom of the admin bar to the bottom of the screen; the contents scroll inside.
				y = bar ? bar.offsetHeight : 0;
				h = Math.max(240, window.innerHeight - y);
			}
			d.style.left = x + 'px';
			d.style.top = y + 'px';
			d.style.width = w + 'px';
			if (!oneWindow || full || (win.sized && win.h > 0)) {
				d.style.height = h + 'px';
			}
		} else if (mode === 'left' || mode === 'right') {
			d.style.width = clamp(win.w || 480, 320, Math.max(320, window.innerWidth - 40)) + 'px';
		}
		if (ui.winBtn) {
			ui.winBtn.textContent = mode === 'centre' ? 'Window' : (mode === 'free' ? 'Window: floating' : 'Window: ' + mode);
		}
		if (ui.fullBtn) {
			ui.fullBtn.textContent = full ? 'Normal height' : 'Full height';
			ui.fullBtn.setAttribute('aria-pressed', full ? 'true' : 'false');
			ui.fullBtn.disabled = mode === 'left' || mode === 'right';
		}
		placeMini();
	}

	// Section 23f: fill the screen from top to bottom at the chosen width; a second press returns to the size before.
	function toggleFull() {
		if (phoneWidth()) {
			return;
		}
		closeWindowMenu();
		if (!win.full) {
			var r = ui.dialog.getBoundingClientRect();
			win.prev = { mode: win.mode, x: win.x, y: win.y, w: win.w, h: win.h, sized: win.sized, placed: win.placed };
			if (win.mode !== 'free') {
				win.mode = 'free';
				win.w = Math.round(r.width);
				win.x = Math.round(r.left);
				win.placed = true;
			}
			win.full = true;
		} else {
			win.full = false;
			if (win.prev) {
				['mode', 'x', 'y', 'w', 'h', 'sized', 'placed'].forEach(function (k) {
					win[k] = win.prev[k];
				});
				win.prev = null;
			}
		}
		applyWindow();
		saveWindow();
		ui.title.focus();
	}

	function saveWindow() {
		window.clearTimeout(win.saveTimer);
		win.saveTimer = window.setTimeout(function () {
			api('POST', '/editor/window', { state: { mode: win.mode, x: win.x, y: win.y, w: win.w, h: win.h, mx: win.mx, my: win.my, esc: win.esc, full: win.full } }).catch(function () {});
		}, 400);
	}

	function dockWindow(mode) {
		win.mode = mode;
		if (mode !== 'free') {
			win.full = false;
			win.prev = null;
		}
		applyWindow();
		saveWindow();
		closeWindowMenu();
		ui.title.focus();
	}

	// Drag by the title bar (not its buttons). Dragging a centred or docked window makes it free, from where it is.
	function startDrag(e) {
		if (e.button !== 0 || e.target.closest('button') || phoneWidth()) {
			return;
		}
		var r = ui.dialog.getBoundingClientRect();
		if (win.mode !== 'free') {
			win.mode = 'free';
			win.w = Math.round(r.width);
			win.h = Math.round(r.height);
		}
		var dx = e.clientX - r.left;
		var dy = e.clientY - r.top;
		e.preventDefault();
		function move(ev) {
			win.x = Math.round(ev.clientX - dx);
			if (!win.full) {
				// A full-height window moves sideways only; it keeps the top and bottom of the screen.
				win.y = Math.round(ev.clientY - dy);
			}
			win.placed = true;
			applyWindow();
		}
		function up() {
			document.removeEventListener('pointermove', move);
			document.removeEventListener('pointerup', up);
			saveWindow();
		}
		document.addEventListener('pointermove', move);
		document.addEventListener('pointerup', up);
	}

	// Resize from the bottom corner (free: both), the bottom edge (free: height only, section 23f) or the inner edge
	// (docked: width only). Dragging the height of a full-height window ends full height.
	function startResize(e, axis) {
		if (e.button !== 0 || phoneWidth() || win.mode === 'centre') {
			return;
		}
		if (win.mode !== 'free' && axis === 'height') {
			return;
		}
		var r = ui.dialog.getBoundingClientRect();
		var startX = e.clientX;
		var startY = e.clientY;
		var w0 = r.width;
		var h0 = r.height;
		e.preventDefault();
		if (win.full) {
			win.full = false;
			win.prev = null;
			win.y = Math.round(r.top);
			win.placed = true;
		}
		function move(ev) {
			if (win.mode === 'left') {
				win.w = Math.round(w0 + (ev.clientX - startX));
			} else if (win.mode === 'right') {
				win.w = Math.round(w0 - (ev.clientX - startX));
			} else {
				if (axis !== 'height') {
					win.w = Math.round(w0 + (ev.clientX - startX));
				}
				win.h = Math.round(h0 + (ev.clientY - startY));
			}
			win.sized = true;
			applyWindow();
		}
		function up() {
			document.removeEventListener('pointermove', move);
			document.removeEventListener('pointerup', up);
			saveWindow();
		}
		document.addEventListener('pointermove', move);
		document.addEventListener('pointerup', up);
	}

	function toggleWindowMenu(btn) {
		if (win.menu) {
			closeWindowMenu();
			return;
		}
		var items = [
			['left', 'Dock on the left'],
			['right', 'Dock on the right'],
			['free', 'Floating (drag the title bar to move it, the corner to resize)'],
			['centre', 'Centred over the page']
		];
		var list = el('div', { className: 'twd-sk-ed__winmenu', role: 'group', 'aria-label': 'Where the editor sits' });
		items.forEach(function (it) {
			var b = el('button', { type: 'button', className: 'twd-sk-ed__winitem', text: it[1], 'aria-pressed': win.mode === it[0] ? 'true' : 'false' });
			b.addEventListener('click', function () {
				dockWindow(it[0]);
			});
			list.appendChild(b);
		});
		var escId = 'twd-sk-ed-esc-min';
		var escBox = el('input', { id: escId, type: 'checkbox' });
		escBox.checked = win.esc === 'minimise';
		escBox.addEventListener('change', function () {
			win.esc = escBox.checked ? 'minimise' : 'close';
			saveWindow();
		});
		list.appendChild(el('label', { className: 'twd-sk-ed__check', 'for': escId }, [escBox, el('span', { text: 'Escape minimises the editor instead of closing it' })]));
		list.appendChild(el('p', { className: 'twd-sk-ed__help', text: 'Docked or floating, the page behind stays usable. Your choice is remembered.' }));
		ui.header.appendChild(list);
		win.menu = list;
		btn.setAttribute('aria-expanded', 'true');
		list.querySelector('button').focus();
	}

	function closeWindowMenu() {
		if (win.menu && win.menu.parentNode) {
			win.menu.parentNode.removeChild(win.menu);
		}
		win.menu = null;
		if (ui.winBtn) {
			ui.winBtn.setAttribute('aria-expanded', 'false');
		}
	}

	// Minimise: hide the window, keep everything in it, show the pencil.
	function minimise() {
		if (!state.built || ui.overlay.hasAttribute('hidden')) {
			return;
		}
		closeWindowMenu();
		win.minimised = true;
		show(ui.overlay, false);
		queuePencils();
		buildMini();
		show(ui.mini, true);
		placeMini();
		ui.mini.focus();
	}

	function restoreWindow() {
		if (!win.minimised) {
			return;
		}
		win.minimised = false;
		if (ui.mini) {
			show(ui.mini, false);
		}
		show(ui.overlay, true);
		queuePencils();
		applyWindow();
		ui.title.focus();
	}

	function buildMini() {
		if (ui.mini) {
			return;
		}
		var b = el('button', { type: 'button', id: 'twd-sk-ed-mini', className: 'twd-sk-ed__mini', 'aria-label': 'Open Edit with AI, minimised', title: 'Open Edit with AI (drag to move this button)', hidden: '' }, [pencilIcon(), el('span', { className: 'twd-sk-ed__mini-plus', 'aria-hidden': 'true', text: '+' })]);
		var moved = false;
		b.addEventListener('click', function () {
			// The click that ends a drag is not a request to open.
			if (moved) {
				return;
			}
			restoreWindow();
		});
		b.addEventListener('pointerdown', function (e) {
			if (e.button !== 0 || phoneWidth()) {
				return;
			}
			var r = b.getBoundingClientRect();
			var dx = e.clientX - r.left;
			var dy = e.clientY - r.top;
			var sx = e.clientX;
			var sy = e.clientY;
			function move(ev) {
				if (!moved && Math.abs(ev.clientX - sx) + Math.abs(ev.clientY - sy) < 4) {
					return;
				}
				moved = true;
				win.mx = Math.round(ev.clientX - dx);
				win.my = Math.round(ev.clientY - dy);
				placeMini();
			}
			function up() {
				document.removeEventListener('pointermove', move);
				document.removeEventListener('pointerup', up);
				if (moved) {
					saveWindow();
					// The browser's own click for this pointer press comes next; the one after that opens again.
					window.setTimeout(function () {
						moved = false;
					}, 0);
				}
			}
			document.addEventListener('pointermove', move);
			document.addEventListener('pointerup', up);
		});
		root.appendChild(b);
		ui.mini = b;
	}

	// The pencil starts bottom right (clear of the Your Edits button bottom left) and stays inside the window.
	function placeMini() {
		if (!ui.mini) {
			return;
		}
		var size = phoneWidth() ? 48 : 64;
		ui.mini.style.left = '';
		ui.mini.style.top = '';
		if (phoneWidth() || win.mx < 0 || win.my < 0) {
			return;
		}
		ui.mini.style.left = clamp(win.mx, 0, Math.max(0, window.innerWidth - size)) + 'px';
		ui.mini.style.top = clamp(win.my, 0, Math.max(0, window.innerHeight - size)) + 'px';
	}

	function selectTab(name) {
		var entry = ui.tabs[name];
		if (!entry) {
			return;
		}
		if (name === 'settings') {
			var first = Object.keys(ui.tabs).filter(function (k) {
				return ui.tabs[k].sub;
			})[0];
			if (first) {
				selectTab(first);
				return;
			}
		}
		Object.keys(ui.tabs).forEach(function (key) {
			var e = ui.tabs[key];
			var on = key === name || (entry.sub && key === 'settings');
			e.tab.setAttribute('aria-selected', on ? 'true' : 'false');
			show(e.panel, on);
		});
		state.tab = name;
		if (oneWindow && state.built) {
			applyWindow();
			if (name === 'page') {
				buildOutline();
				startInlineForPage();
			}
		}
		if (name === 'pages') {
			loadInfo();
		}
		var hook = window.TWD_SK_ED && window.TWD_SK_ED.tabHooks && window.TWD_SK_ED.tabHooks[name];
		if (typeof hook === 'function') {
			hook();
		}
	}

	/* ---- fold-outs: keep the screen simple, with the detail one click away ---- */

	var foldCount = 0;
	var foldSetters = {};

	// Turn a step section (an h3 title then content) into a closed fold-out with the same title.
	function foldSection(section, opts) {
		opts = opts || {};
		var h = section.querySelector('.twd-sk-ed__step-title');
		var title = opts.title || (h ? h.textContent : 'More');
		var id = 'twd-sk-fold-' + (++foldCount);
		var body = el('div', { id: id, className: 'twd-sk-ed__foldbody', 'data-twd-fold': '1' });
		if (h) {
			while (h.nextSibling) {
				body.appendChild(h.nextSibling);
			}
		} else {
			while (section.firstChild) {
				body.appendChild(section.firstChild);
			}
		}
		var kids = [el('span', { className: 'twd-sk-ed__foldtitle', text: title })];
		if (opts.summary) {
			kids.push(el('span', { className: 'twd-sk-ed__foldsummary', text: opts.summary }));
		}
		var btn = el('button', { type: 'button', className: 'twd-sk-ed__fold', 'aria-expanded': 'false', 'aria-controls': id }, kids);
		function setOpen(on) {
			btn.setAttribute('aria-expanded', on ? 'true' : 'false');
			show(body, on);
		}
		btn.addEventListener('click', function () {
			setOpen(btn.getAttribute('aria-expanded') !== 'true');
		});
		if (h) {
			section.replaceChild(btn, h);
		} else {
			section.appendChild(btn);
		}
		section.appendChild(body);
		section.className += ' twd-sk-ed__step--fold';
		foldSetters[id] = setOpen;
		setOpen(!!opts.open);
		return { button: btn, body: body, setOpen: setOpen };
	}

	// Open any closed fold-out around a node, so it can be seen and focused.
	function revealFold(node) {
		var n = node;
		while (n && n.nodeType === 1) {
			if (n.getAttribute('data-twd-fold') === '1' && n.hasAttribute('hidden') && foldSetters[n.id]) {
				foldSetters[n.id](true);
			}
			n = n.parentNode;
		}
	}

	function step(titleText, kids) {
		var all = [];
		if (titleText) {
			all.push(el('h3', { className: 'twd-sk-ed__step-title', text: titleText }));
		}
		return el('section', { className: 'twd-sk-ed__step' }, all.concat(kids));
	}

	function buildEditPanel(panel) {
		var ai = !!cfg.ai;

		/* The prompt for an external AI */
		ui.copyBtn = button('Copy prompt for external AI', ai ? 'secondary' : 'primary', copyPrompt);
		ui.copyBtn.disabled = true;
		ui.copyMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.copyBox = el('textarea', { className: 'twd-sk-ed__textarea', readonly: '', 'aria-label': 'The prompt, selected so you can copy it', hidden: '' });
		var copyKids = [
			el('p', { className: 'twd-sk-ed__help', text: 'Paste this into your AI chat first. It holds the rules for your page and the page as it is now. Then tell the AI what you want to change.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [ui.copyBtn]),
			ui.copyMsg,
			ui.copyBox
		];

		/* Paste and preview */
		ui.paste = el('textarea', { id: 'twd-sk-ed-paste', className: 'twd-sk-ed__textarea', spellcheck: 'false' });
		ui.paste.addEventListener('input', onPasteInput);
		ui.previewBtn = button('Preview', 'primary', doPreview);
		ui.previewBtn.disabled = true;
		ui.discardBtn = button('Discard preview', 'secondary', doDiscard);
		show(ui.discardBtn, false);
		ui.previewMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.banner = el('div', { className: 'twd-sk-ed__banner', hidden: '' });
		ui.report = el('div', { className: 'twd-sk-ed__msg', hidden: '' });
		ui.frame = el('iframe', { className: 'twd-sk-ed__frame', title: 'Preview of your page with the changes. Nothing is saved yet.', tabindex: '-1', hidden: '' });
		ui.newTab = el('a', { className: 'twd-sk-ed__help', target: '_blank', rel: 'noopener', text: 'Open the preview in a new tab', hidden: '' });
		var pasteKids = [
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-paste', text: 'The page HTML from the AI' }),
			ui.paste,
			el('div', { className: 'twd-sk-ed__actions' }, [ui.previewBtn, ui.discardBtn])
		];
		var outKids = [ui.previewMsg, ui.banner, ui.report, ui.frame, el('p', {}, [ui.newTab])];

		if (!oneWindow) {
			buildInlineEntry(panel);
		}
		if (ai) {
			if (!oneWindow) {
				buildSectionStep(panel);
			}
			buildPlanStep(panel);
			// The discard button lives with the preview, not inside the tucked-away external section.
			pasteKids[2] = el('div', { className: 'twd-sk-ed__actions' }, [ui.previewBtn]);
			outKids.unshift(el('div', { className: 'twd-sk-ed__actions' }, [ui.discardBtn]));
			panel.appendChild(step(oneWindow ? 'Preview it' : '2. Preview it', outKids));
		} else {
			panel.appendChild(step('1. Copy the prompt', copyKids));
			panel.appendChild(step('2. Paste the result and preview it', pasteKids.concat(outKids)));
		}

		/* Apply */
		ui.note = el('input', { id: 'twd-sk-ed-note', className: 'twd-sk-ed__input', type: 'text', maxlength: '200', autocomplete: 'off' });
		ui.applyBtn = button('Apply and save as a new version', 'primary', doApply);
		ui.applyBtn.disabled = true;
		ui.applyMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.reloadBtn = button('Reload the page to see it', 'secondary', function () {
			window.location.reload();
		});
		show(ui.reloadBtn, false);
		var noteKids = [
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-note', text: 'Where did these facts come from?' }),
			el('p', { className: 'twd-sk-ed__help', text: 'For example: the ' + W('person', 'therapist') + ' told me on a call, or their old website. This is saved with the version so you can see it later.' }),
			ui.note
		];
		if (ai) {
			panel.appendChild(step(oneWindow ? 'Save it' : '3. Save it', [
				el('div', { className: 'twd-sk-ed__actions' }, [ui.applyBtn, ui.reloadBtn]),
				ui.applyMsg
			]));
			// Everything else is one click away.
			var more = step('More options', noteKids);
			panel.appendChild(more);
			foldSection(more, { summary: 'The note saved with this change' });
			var ext = step('Use an external AI instead', copyKids.concat(pasteKids));
			panel.appendChild(ext);
			foldSection(ext);
		} else {
			panel.appendChild(step('3. Save it', noteKids.concat([
				el('div', { className: 'twd-sk-ed__actions' }, [ui.applyBtn, ui.reloadBtn]),
				ui.applyMsg
			])));
		}

		/* History */
		ui.undoBtn = button('Undo the last change', 'secondary', function () {
			doUndo(false);
		});
		ui.undoBtn.disabled = true;
		ui.redoBtn = button('Redo', 'secondary', function () {
			if (state.redoTo) {
				doRestore(state.redoTo, false, 'Redo');
			}
		});
		show(ui.redoBtn, false);
		ui.historyMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.historyCard = el('div');
		ui.historyReload = button('Reload the page to see it', 'secondary', function () {
			window.location.reload();
		});
		show(ui.historyReload, false);
		ui.versions = el('ol', { className: 'twd-sk-ed__versions' });
		var hist = step('History and undo (the last 20 versions)', [
			el('p', { className: 'twd-sk-ed__help', text: 'Undo steps back one change at a time and the page behind redraws so you can see it. Undo and Restore never delete anything: each saves a new version, so you can always go forward again.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [ui.undoBtn, ui.redoBtn, ui.historyReload]),
			ui.historyMsg,
			ui.historyCard,
			ui.versions
		]);
		panel.appendChild(hist);
		foldSection(hist, { summary: 'Undo, or go back to an earlier version' });
		ui.hist = hist;
	}

	/* ---- changing words in place (twd-site-kit-inline.js) ---- */

	function buildInlineEntry(panel) {
		var ED = window.TWD_SK_ED;
		if (!ED || typeof ED.startInline !== 'function') {
			return;
		}
		panel.appendChild(step('', [
			el('p', { className: 'twd-sk-ed__help', text: 'Fixing a few words, a link or a picture? Change them right on the page. No AI, and nothing is saved until you press Save.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [button('Visual editor on the page', 'secondary', function () {
				close();
				ED.startInline();
			})])
		]));
	}

	/* ---- step 1 (when the site has an AI key): one box, a checklist, then the changes ---- */

	var CHIPS = [
		['Warmer', 'Make the wording warmer.'],
		['Shorter', 'Make the page shorter.'],
		['Simpler words', 'Use simpler words.'],
		['Stronger first line', 'Write a stronger first line.'],
		['Fix links and headings', 'Fix link wording and heading order.'],
		['Update from my ' + FACTS, 'Update the page so it matches my ' + FACTS + '. Keep the layout.']
	];

	/* An info circle (0.15.0): a small round (i) button that shows a line of help on click, so a panel stays clean. */
	var infoCount = 0;

	function infoHelp(text, label) {
		var id = 'twd-sk-ed-info-' + (++infoCount);
		var help = el('p', { id: id, className: 'twd-sk-ed__help twd-sk-ed__infotext', text: text, hidden: '' });
		var btn = el('button', { type: 'button', className: 'twd-sk-ed__info', 'aria-label': label || 'More about this', 'aria-expanded': 'false', 'aria-controls': id, text: 'i' });
		btn.addEventListener('click', function () {
			var open = help.hasAttribute('hidden');
			show(help, open);
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		return el('div', { className: 'twd-sk-ed__inforow' }, [btn, help]);
	}

	/* Change one section (0.15.0): opened by a pencil circle on the page. One box, one section, through the
	 * sections-mode remix; the result goes into the normal preview, then Apply, with Undo. */
	function buildSectionStep(panel) {
		ui.secHead = el('p', { className: 'twd-sk-ed__label', text: '' });
		ui.secBox = el('textarea', { id: 'twd-sk-ed-section', className: 'twd-sk-ed__textarea', maxlength: '1500', spellcheck: 'true' });
		ui.secBtn = button('Preview the change', 'primary', doSection);
		ui.secMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.secStep = step('Change one section', [
			ui.secHead,
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-section', text: 'What should change in this section?' }),
			ui.secBox,
			infoHelp('Only this section is rewritten; the rest of the page is left as it is. Wording you paste is kept word for word. You see a preview first, and nothing is saved until you apply it.', 'How changing one section works'),
			el('div', { className: 'twd-sk-ed__actions' }, [ui.secBtn]),
			ui.secMsg
		]);
		show(ui.secStep, false);
		panel.appendChild(ui.secStep);
	}

	function doSection() {
		var what = ui.secBox.value.trim();
		if (!what) {
			message(ui.secMsg, 'Say what should change first.', false);
			ui.secBox.focus();
			return;
		}
		if (state.secBusy) {
			return;
		}
		state.secBusy = true;
		state.lastRequest = what;
		ui.secBtn.disabled = true;
		message(ui.secMsg, 'Asking the AI about this section...', true);
		api('POST', pagePath('/remix'), { base_version: state.version, mode: 'sections', indexes: [state.secIndex], instruction: what }).then(function (data) {
			state.secBusy = false;
			ui.secBtn.disabled = false;
			var notes = (data.report || []).join(' ');
			message(ui.secMsg, (notes ? notes + ' ' : '') + 'Here is the preview. Apply it below if it reads well.', !notes);
			proposeHtml(data.html, 'AI change to the ' + state.secName + ' section');
		}, function (err) {
			state.secBusy = false;
			ui.secBtn.disabled = false;
			message(ui.secMsg, err.message, false);
		});
	}

	function pickSection(index, name) {
		state.secIndex = index;
		state.secName = name;
		open();
		if (ui.tabs.edit) {
			selectTab('edit');
		}
		if (oneWindow) {
			prefillSection(index, name);
			if (ui.request) {
				ui.request.focus();
			}
			return;
		}
		if (!ui.secStep) {
			return;
		}
		ui.secHead.textContent = 'Section ' + (index + 1) + ': ' + name;
		show(ui.secStep, true);
		revealFold(ui.secStep);
		ui.secStep.scrollIntoView({ block: 'start' });
		ui.secBox.focus();
	}

	/* ---- pencil circles (0.15.0): one small round pencil on the top corner of every section ----
	 * Built from the editor's own section numbers (data-twd-sk-sec, printed for editors only), laid over the page
	 * in the editor wrapper, never inside a section and never stored. Quiet: they show when the pointer is over
	 * the section or the pencil has focus, and always on a touch screen. With AI a pencil opens "Change one
	 * section"; without it, the Visual editor opens at that section. */
	var pencils = [];
	var pencilQueued = false;

	// The pencil is drawn with CSS (a tilted bar with a tip), so the script holds no icon file and no address.
	function pencilIcon() {
		return el('span', { className: 'twd-sk-ed__pencil-icon', 'aria-hidden': 'true' });
	}

	function sectionName(sec) {
		var h = sec.querySelector('h1, h2, h3');
		if (h && h.textContent.trim()) {
			return h.textContent.trim().replace(/\s+/g, ' ').slice(0, 60);
		}
		var cls = (sec.className.match(/\btwd-sk-([a-z-]+?)(?:--|\s|$)/) || [])[1] || '';
		var comp = cfg.look && cfg.look.components && cfg.look.components[cls.replace(/-/g, '_')];
		return comp ? comp.label : (cls || 'section');
	}

	function placePencils() {
		pencilQueued = false;
		var hidden = tools.off || (!oneWindow && (document.body.classList.contains('twd-sk-inline-on') || (ui.overlay && !ui.overlay.hasAttribute('hidden'))));
		pencils.forEach(function (p) {
			var r = p.host.getBoundingClientRect();
			var visible = !hidden && r.height > 0 && r.bottom > 40 && r.top < window.innerHeight - 40;
			show(p.node, visible);
			if (visible) {
				p.node.style.top = Math.max(r.top + 8, 8) + 'px';
				p.node.style.left = Math.max(r.right - 44, 8) + 'px';
			}
		});
	}

	function queuePencils() {
		if (!pencilQueued) {
			pencilQueued = true;
			window.requestAnimationFrame(placePencils);
		}
	}

	function buildPencils() {
		if (simple) {
			return;
		}
		pencils.forEach(function (p) {
			if (p.node.parentNode) {
				p.node.parentNode.removeChild(p.node);
			}
		});
		pencils = [];
		Array.prototype.forEach.call(document.querySelectorAll('section[data-twd-sk-sec]'), function (sec) {
			var index = parseInt(sec.getAttribute('data-twd-sk-sec'), 10);
			var name = sectionName(sec);
			var node = el('button', { type: 'button', className: 'twd-sk-ed__pencil', 'aria-label': 'Change section ' + (index + 1) + ': ' + name, title: 'Section ' + (index + 1) + ': change it', hidden: '' }, [pencilIcon(), el('span', { className: 'twd-sk-ed__pencilnum', 'aria-hidden': 'true', text: String(index + 1) })]);
			node.addEventListener('click', function () {
				if (oneWindow) {
					open();
					if (state.tab !== 'page' && state.tab !== 'edit') {
						selectTab('page');
					}
					prefillSection(index, name);
					// 18c: a pencil means one section: the Visual editor shows that section's view (the list of all
					// sections is hidden until All sections is pressed).
					window.setTimeout(function () {
						var ED = window.TWD_SK_ED;
						if (ED && typeof ED.sectionView === 'function') {
							ED.sectionView(true, index);
						} else if (ED && typeof ED.focusSection === 'function') {
							ED.focusSection(index);
						}
					}, 60);
					return;
				}
				if (cfg.ai) {
					pickSection(index, name);
				} else if (window.TWD_SK_ED && window.TWD_SK_ED.startInline) {
					sec.scrollIntoView({ block: 'center' });
					window.TWD_SK_ED.startInline();
					var first = sec.querySelector('[data-twd-sk-t]');
					if (first) {
						first.focus({ preventScroll: true });
					}
				}
			});
			sec.addEventListener('mouseenter', function () {
				node.classList.add('twd-sk-ed__pencil--near');
			});
			sec.addEventListener('mouseleave', function () {
				node.classList.remove('twd-sk-ed__pencil--near');
			});
			root.appendChild(node);
			pencils.push({ host: sec, node: node });
		});
		if (pencils.length && !buildPencils.bound) {
			buildPencils.bound = true;
			window.addEventListener('scroll', queuePencils, { passive: true });
			window.addEventListener('resize', queuePencils);
			tools.listeners.push(queuePencils);
			redrawHooks.push(buildPencils);
			new MutationObserver(queuePencils).observe(document.body, { attributes: true, attributeFilter: ['class'] });
		}
		queuePencils();
	}

	function buildPlanStep(panel) {
		ui.request = el('textarea', { id: 'twd-sk-ed-request', className: 'twd-sk-ed__textarea', maxlength: '6000', spellcheck: 'true' });
		var chips = el('div', { className: 'twd-sk-ed__chips' });
		CHIPS.forEach(function (c) {
			var chip = el('button', { type: 'button', className: 'twd-sk-ed__chip', text: c[0] });
			chip.addEventListener('click', function () {
				var v = ui.request.value.trim();
				ui.request.value = v ? v + '\n' + c[1] : c[1];
				ui.request.focus();
			});
			chips.appendChild(chip);
		});
		ui.planBtn = button('Plan the changes', 'primary', doPlan);
		ui.planMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.planBox = el('div', { className: 'twd-sk-ed__planbox', hidden: '' });
		// The one window (phase 2): one box headed "Tell us what you would like"; a pencil names its section above it.
		ui.aiSecText = el('span', {});
		var aiSecClear = button('Clear', 'secondary', function () {
			state.secIndex = undefined;
			state.secName = '';
			show(ui.aiSec, false);
		});
		aiSecClear.className += ' twd-sk-ed__small';
		ui.aiSec = el('p', { className: 'twd-sk-ed__aisec', hidden: '' }, [ui.aiSecText, aiSecClear]);
		var head = oneWindow ? 'Tell us what you would like' : '1. Say what you want changed';
		var label = oneWindow
			? 'What would you like changed on this page? Name a section by its number if it helps, for example: In section 2, make the heading shorter.'
			: 'What do you want changed?';
		panel.appendChild(step(head, [
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-request', text: label }),
			ui.aiSec,
			chips,
			ui.request,
			infoHelp('One line, or a list. For example: "1. Change the first paragraph to ... 2. Add three boxes with glowing borders for garden parties, weddings and festivals. 3. Add these testimonials as a slider at the bottom: ...". Paste any wording you want used: it is kept word for word. You see a checklist first, then a preview. Nothing is saved until you apply it, and Undo always goes back. The AI writes drafts. Check every fact and every claim before you apply.', 'How to say what you want changed'),
			el('div', { className: 'twd-sk-ed__actions' }, [ui.planBtn]),
			ui.planMsg,
			ui.planBox
		]));
	}

	// A section pencil (the one window, phase 2): the box starts "In section N (Name): " and the section is named above it.
	function prefillSection(index, name) {
		state.secIndex = index;
		state.secName = name;
		if (!ui.request || !ui.aiSec) {
			return;
		}
		ui.aiSecText.textContent = 'Section ' + (index + 1) + ': ' + name + ' ';
		show(ui.aiSec, true);
		var lead = 'In section ' + (index + 1) + ' (' + name + '): ';
		if (ui.request.value.indexOf('In section ') !== 0) {
			ui.request.value = lead + ui.request.value;
		} else {
			ui.request.value = ui.request.value.replace(/^In section \d+ \([^)]*\): ?/, lead);
		}
	}

	// Kept under its old name: it checks that AI is available and notes the page version.
	function loadSections() {
		if (!cfg.ai || !ui.planBtn) {
			return;
		}
		api('GET', pagePath('/sections')).then(function (data) {
			state.version = data.version;
			ui.planBtn.disabled = !data.ai;
			if (!data.ai) {
				message(ui.planMsg, 'AI is not available on this site right now. Use the external AI option below.', false);
			}
		}, function (err) {
			message(ui.planMsg, err.message, false);
		});
	}

	// Section 23f: a page or a reply from another AI pasted into this box belongs in Import a page.
	function looksLikeHtml(text) {
		var t = text.replace(/^\s+/, '');
		return /^(```[a-z]*\s*)?<[a-zA-Z!]/.test(t) || /<section/i.test(t);
	}

	function doPlan() {
		var request = ui.request.value.trim();
		if (!request) {
			message(ui.planMsg, 'Say what you want changed first.', false);
			ui.request.focus();
			return;
		}
		if (looksLikeHtml(request)) {
			var ED = window.TWD_SK_ED;
			message(ui.planMsg, 'That looks like a page or HTML, not a request. To paste a whole page or a reply from another AI, use Import a page (On page edits, a pencil) or Replace this section.', false);
			if (ED && typeof ED.openImport === 'function') {
				var moveBtn = button('Move it to Import a page', 'secondary', function () {
					var text = ui.request.value;
					ui.request.value = '';
					show(ui.planMsg, false);
					selectTab('page');
					ED.openImport(text);
				});
				ui.planMsg.appendChild(el('div', { className: 'twd-sk-ed__actions' }, [moveBtn]));
			}
			return;
		}
		ui.planBtn.disabled = true;
		clear(ui.planBox);
		show(ui.planBox, false);
		message(ui.planMsg, 'Planning the changes. This takes a few seconds. Nothing is changed yet.', true);
		state.lastRequest = request;
		api('POST', pagePath('/plan'), { base_version: state.version, request: request }).then(function (data) {
			ui.planBtn.disabled = false;
			state.planRequest = request;
			renderPlan(data);
		}, function (err) {
			ui.planBtn.disabled = false;
			message(ui.planMsg, err.message, false);
			if (err.status === 409) {
				show(ui.reloadBtn, true);
			}
		});
	}

	function renderPlan(data) {
		clear(ui.planBox);
		state.plan = [];
		var list = el('ol', { className: 'twd-sk-ed__plan' });
		(data.changes || []).forEach(function (c, n) {
			var id = 'twd-sk-ed-plan-' + n;
			var box = el('input', { id: id, type: 'checkbox', checked: '' });
			var badge = el('span', { className: 'twd-sk-ed__planstate', text: 'To do' });
			var note = el('p', { className: 'twd-sk-ed__plannote', hidden: '' });
			list.appendChild(el('li', { className: 'twd-sk-ed__planitem' }, [
				el('label', { className: 'twd-sk-ed__check', 'for': id }, [box, el('span', { text: c.label })]),
				badge,
				note
			]));
			state.plan.push({ change: c, box: box, badge: badge, note: note });
		});
		if (state.plan.length) {
			message(ui.planMsg, 'Here is the plan. Untick anything you do not want, then make the changes.', true);
			ui.planBox.appendChild(list);
		} else {
			message(ui.planMsg, 'Nothing on that list can be done on this page. See below.', false);
		}
		(data.cannot || []).forEach(function (c) {
			if (oneWindow && c.nearest) {
				var instead = button('Do this instead', 'secondary', function () {
					ui.request.value = (state.secIndex !== undefined ? 'In section ' + (state.secIndex + 1) + ' (' + state.secName + '): ' : '') + c.nearest;
					doPlan();
				});
				instead.className += ' twd-sk-ed__small';
				ui.planBox.appendChild(el('div', { className: 'twd-sk-ed__cannotcard' }, [
					el('p', { className: 'twd-sk-ed__cannot', text: 'This cannot be done on a page: ' + c.what + '.' }),
					el('p', { className: 'twd-sk-ed__help', text: 'What can be done instead: ' + c.nearest + '.' }),
					el('div', { className: 'twd-sk-ed__actions' }, [instead])
				]));
				return;
			}
			ui.planBox.appendChild(el('p', { className: 'twd-sk-ed__cannot', text: 'Not possible on a page: ' + c.what + (c.nearest ? '. Nearest the kit can do: ' + c.nearest + '.' : '.') }));
		});
		if (oneWindow && !state.plan.length && (data.cannot || []).length) {
			message(ui.planMsg, 'None of that can be done on this page as asked. The notes below say what can.', false);
		}
		(data.dropped || []).forEach(function (line) {
			ui.planBox.appendChild(el('p', { className: 'twd-sk-ed__help', text: line }));
		});
		if (state.plan.length) {
			ui.runBtn = button('Make these changes', 'primary', runPlan);
			ui.planBox.appendChild(el('div', { className: 'twd-sk-ed__actions' }, [ui.runBtn]));
		}
		show(ui.planBox, true);
	}

	function setPlanState(item, text, cls) {
		item.badge.textContent = text;
		item.badge.className = 'twd-sk-ed__planstate' + (cls ? ' ' + cls : '');
	}

	// One change per request, on a working copy. The last copy goes to the preview; Apply saves it.
	function runPlan() {
		var todo = state.plan.filter(function (item) {
			return item.box.checked;
		});
		if (!todo.length) {
			message(ui.planMsg, 'Tick at least one change first.', false);
			return;
		}
		ui.runBtn.disabled = true;
		ui.planBtn.disabled = true;
		state.plan.forEach(function (item) {
			item.box.disabled = true;
			setPlanState(item, item.box.checked ? 'Waiting' : 'Skipped', '');
			show(item.note, false);
		});
		var work = { html: '', order: null, done: [] };
		var i = 0;
		message(ui.planMsg, 'Making the changes, one at a time. Nothing is saved.', true);
		function next() {
			if (i >= todo.length) {
				finish();
				return;
			}
			var item = todo[i];
			setPlanState(item, 'Working...', '');
			api('POST', pagePath('/plan/step'), { base_version: state.version, request: state.planRequest, change: item.change, html: work.html, order: work.order }).then(function (out) {
				work.html = out.html;
				work.order = out.order;
				work.done.push(item.change.label);
				setPlanState(item, 'Done', 'is-done');
				if (out.report && out.report.length) {
					item.note.textContent = out.report.join(' ');
					show(item.note, true);
				}
				i++;
				next();
			}, function (err) {
				setPlanState(item, 'Not done', 'is-failed');
				item.note.textContent = err.message;
				show(item.note, true);
				if (err.status === 409) {
					show(ui.reloadBtn, true);
					finish();
					return;
				}
				i++;
				next();
			});
		}
		function finish() {
			ui.runBtn.disabled = false;
			ui.planBtn.disabled = false;
			state.plan.forEach(function (item) {
				item.box.disabled = false;
			});
			if (!work.done.length) {
				message(ui.planMsg, 'No change could be made. See the notes above, then try wording it differently.', false);
				return;
			}
			message(ui.planMsg, work.done.length + ' change' + (work.done.length === 1 ? '' : 's') + ' made. Check the preview below. Nothing is saved yet.', true);
			ui.paste.value = work.html;
			state.previewedText = null;
			if (!ui.note.value.trim()) {
				ui.note.value = ('Changes: ' + work.done.join('; ')).slice(0, 190);
			}
			doPreview();
		}
		next();
	}

	/* ---- step 1: the prompt and the clipboard ---- */

	function loadPrompt() {
		ui.copyBtn.disabled = true;
		return api('GET', pagePath('/prompt')).then(function (data) {
			state.prompt = data.prompt;
			state.version = data.version;
			ui.copyBtn.disabled = false;
		}, function (err) {
			message(ui.copyMsg, err.message, false);
		});
	}

	function copyPrompt() {
		if (!state.prompt) {
			return;
		}
		var text = state.prompt;
		// Must run inside the click so the browser allows it, so no waiting on the network here.
		if (navigator.clipboard && window.isSecureContext && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				show(ui.copyBox, false);
				message(ui.copyMsg, 'Copied. Paste it into your AI chat.', true);
			}, function () {
				copyFallback(text);
			});
			return;
		}
		copyFallback(text);
	}

	// The text is put in a box and selected. execCommand copies it where allowed; otherwise the
	// therapist presses Ctrl+C (Cmd+C on a Mac).
	function copyFallback(text) {
		ui.copyBox.value = text;
		show(ui.copyBox, true);
		ui.copyBox.focus();
		ui.copyBox.select();
		var copied = false;
		try {
			copied = document.execCommand('copy');
		} catch (e) {
			copied = false;
		}
		message(ui.copyMsg, copied
			? 'Copied. Paste it into your AI chat.'
			: 'The text is selected below. Press Ctrl+C (Cmd+C on a Mac) to copy it.', copied);
	}

	/* ---- step 2: paste, preview, discard ---- */

	function onPasteInput() {
		ui.previewBtn.disabled = state.busy || !ui.paste.value.trim();
		if (state.previewedText !== null && state.previewedText !== ui.paste.value) {
			ui.applyBtn.disabled = true;
			message(ui.previewMsg, 'You changed the text. Press Preview again before you apply it.', false);
		}
	}

	function doPreview() {
		var text = ui.paste.value;
		if (!text.trim()) {
			return;
		}
		if (text.length > cfg.maxBytes) {
			message(ui.previewMsg, 'That is larger than the ' + Math.round(cfg.maxBytes / 1024) + ' KB limit for a page.', false);
			return;
		}
		message(ui.previewMsg, 'Checking and building the preview...', true);
		setBusy(true);
		api('POST', pagePath('/preview'), { html: text }).then(function (data) {
			state.token = data.token;
			state.previewedText = text;
			if (data.same) {
				message(ui.previewMsg, 'Nothing changed: this is the same page you already have. The notes below say why and what to try.', false);
			} else {
				message(ui.previewMsg, 'Preview ready. Nothing has been saved yet.', true);
			}
			resultReport(ui.report, data);
			leftoverBanner(ui.banner, data);
			ui.frame.setAttribute('src', data.preview_url);
			ui.newTab.setAttribute('href', data.preview_url);
			show(ui.frame, true);
			show(ui.newTab, true);
			show(ui.discardBtn, true);
			message(ui.applyMsg, '', true);
			setBusy(false);
		}, function (err) {
			message(ui.previewMsg, err.message, false);
			setBusy(false);
		});
	}

	function clearPreview() {
		state.previewedText = null;
		state.token = '';
		ui.frame.removeAttribute('src');
		ui.newTab.removeAttribute('href');
		show(ui.frame, false);
		show(ui.newTab, false);
		show(ui.discardBtn, false);
		show(ui.report, false);
		show(ui.banner, false);
		ui.applyBtn.disabled = true;
	}

	function doDiscard() {
		var token = state.token;
		clearPreview();
		if (cfg.ai) {
			ui.paste.value = '';
			message(ui.previewMsg, 'Preview discarded.', true);
		} else {
			message(ui.previewMsg, 'Preview discarded. Your pasted text is still in the box.', true);
		}
		if (token) {
			api('POST', pagePath('/preview/discard'), { token: token }).then(function () {}, function () {});
		}
	}

	/* ---- step 3: apply ---- */

	function doApply() {
		if (state.previewedText === null || state.previewedText !== ui.paste.value) {
			return;
		}
		var token = state.token;
		message(ui.applyMsg, 'Saving...', true);
		setBusy(true);
		api('POST', pagePath('/apply'), {
			html: state.previewedText,
			base_version: state.version,
			note: ui.note.value
		}).then(function (data) {
			state.version = data.current_version;
			var text = data.unchanged
				? 'No change. That is the same as the page you already have.'
				: 'Saved as version ' + data.version + '.';
			message(ui.applyMsg, text, true);
			if (data.leftover_count) {
				leftoverBanner(ui.banner, data);
			}
			ui.paste.value = '';
			ui.note.value = '';
			if (ui.request) {
				ui.request.value = '';
				clear(ui.planBox);
				show(ui.planBox, false);
			}
			clearPreview();
			renderVersions(data);
			show(ui.reloadBtn, true);
			setBusy(false);
			if (token) {
				api('POST', pagePath('/preview/discard'), { token: token }).then(function () {}, function () {});
			}
			loadPrompt();
			loadSections();
		}, function (err) {
			handleWriteError(ui.applyMsg, err);
			setBusy(false);
		});
	}

	function handleWriteError(box, err) {
		if (err.status === 409) {
			message(box, 'The page was changed by someone else since you opened this. Reload the page, then paste again.', false);
			show(ui.reloadBtn, true);
			return;
		}
		message(box, err.message, false);
	}

	/* ---- step 4: history ---- */

	function formatDate(text) {
		var d = new Date(String(text).replace(' ', 'T') + 'Z');
		return isNaN(d.getTime()) ? text : d.toLocaleString();
	}

	function renderVersions(data) {
		clear(ui.versions);
		state.version = data.current_version;
		var versions = data.versions || [];
		ui.undoBtn.disabled = state.undoing || versions.length < 2;
		// Redo: after an undo, the version it stepped back from is the one just before it in the list.
		state.redoTo = (versions.length > 1 && versions[0].kind === 'undo') ? versions[1].id : 0;
		show(ui.redoBtn, !!state.redoTo);
		if (state.redoTo) {
			ui.redoBtn.textContent = 'Redo (put back version ' + state.redoTo + ')';
			ui.redoBtn.disabled = state.undoing;
		}
		versions.forEach(function (v) {
			var who = v.by ? ' by ' + v.by : '';
			var head = 'Version ' + v.id + (v.current ? ' (current)' : '') + ' - ' + formatDate(v.created) + who;
			var kids = [el('span', { className: v.current ? 'twd-sk-ed__current' : '', text: head })];
			var label = v.label || v.note;
			if (label) {
				kids.push(el('span', { className: 'twd-sk-ed__version-note', text: label }));
			}
			if (v.fix > 0) {
				var starter = /^Outline/.test(label || '');
				kids.push(el('span', { className: 'twd-sk-ed__badge', text: (starter ? 'Blank starter: ' : 'Unfinished: ') + 'placeholder text in ' + v.fix + ' place' + (v.fix === 1 ? '' : 's') }));
			}
			var row = el('li', { className: 'twd-sk-ed__version' }, [el('span', { className: 'twd-sk-ed__version-text' }, kids)]);
			if (!v.current) {
				row.appendChild(button('Restore this version', 'secondary', function () {
					doRestore(v.id, false, 'Restored version ' + v.id);
				}));
			}
			ui.versions.appendChild(row);
		});
	}

	/* ---- redraw the page behind (no full reload) ----
	 * After Undo, Restore or any saved change the page is fetched again and every kit wrapper
	 * (the page and the header and footer) is swapped for the fresh one. Sections whose markup
	 * changed get a short highlight. Resolves false when it could not (the caller offers a reload). */
	var redrawHooks = [];

	function redrawPage() {
		var url = window.location.href.split('#')[0];
		return fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'twd-sk-redraw' } }).then(function (res) {
			if (!res.ok) {
				return false;
			}
			return res.text().then(function (html) {
				var fresh = new DOMParser().parseFromString(html, 'text/html');
				// Only the wrappers the server printed: the Reading box (a script's, carrying the page class for the styles) is not one.
				var oldWraps = Array.prototype.slice.call(document.querySelectorAll('.twd-sk-page:not(.twd-sk-reading__box)'));
				var newWraps = Array.prototype.slice.call(fresh.querySelectorAll('.twd-sk-page:not(.twd-sk-reading__box)'));
				if (!oldWraps.length || oldWraps.length !== newWraps.length) {
					return false;
				}
				var flash = [];
				oldWraps.forEach(function (oldWrap, i) {
					var newWrap = document.importNode(newWraps[i], true);
					var ser = new XMLSerializer();
					var before = Array.prototype.map.call(oldWrap.children, function (n) {
						return ser.serializeToString(n);
					});
					Array.prototype.forEach.call(newWrap.children, function (n, j) {
						if (n.tagName === 'SECTION' && before[j] !== ser.serializeToString(n)) {
							flash.push(n);
						}
					});
					oldWrap.parentNode.replaceChild(newWrap, oldWrap);
				});
				document.body.classList.add('twd-sk-ed-redraw');
				flash.forEach(function (n) {
					n.classList.add('twd-sk-ed__flash');
				});
				window.setTimeout(function () {
					flash.forEach(function (n) {
						n.classList.remove('twd-sk-ed__flash');
					});
					document.body.classList.remove('twd-sk-ed-redraw');
				}, 2500);
				redrawHooks.forEach(function (fn) {
					try {
						fn();
					} catch (err) {
						// One listener must not stop the others.
					}
				});
				return true;
			});
		}, function () {
			return false;
		});
	}

	function loadVersions() {
		return api('GET', pagePath('/versions')).then(renderVersions, function (err) {
			message(ui.historyMsg, err.message, false);
		});
	}

	function afterHistoryChange(data, what) {
		state.undoing = false;
		renderVersions(data);
		if (data.leftover_count) {
			leftoverBanner(ui.banner, data);
		}
		message(ui.historyMsg, what + ' Saved as version ' + data.version + '. Redrawing the page...', true);
		loadPrompt();
		redrawPage().then(function (ok) {
			message(ui.historyMsg, what + ' Saved as version ' + data.version + '.' + (ok ? '' : ' Reload the page to see it.'), true);
			show(ui.historyReload, !ok);
		});
	}

	function historyBusy(on) {
		state.undoing = on;
		ui.undoBtn.disabled = on;
		ui.redoBtn.disabled = on;
		Array.prototype.forEach.call(ui.versions.querySelectorAll('button'), function (b) {
			b.disabled = on;
		});
	}

	// A 409 "placeholders" answer means the version is the blank starter: ask in plain words, then try again allowing it.
	function historyError(err, retry) {
		historyBusy(false);
		if (err.status === 409 && err.code === 'twd_sk_placeholders') {
			message(ui.historyMsg, '', true);
			confirmInline(ui.historyCard, { text: err.message, yesLabel: 'Go back to it anyway', onYes: retry });
			return;
		}
		handleWriteError(ui.historyMsg, err);
	}

	function doUndo(allow) {
		if (state.undoing) {
			return;
		}
		historyBusy(true);
		clear(ui.historyCard);
		message(ui.historyMsg, 'Undoing...', true);
		api('POST', pagePath('/undo'), { base_version: state.version, allow_placeholders: !!allow }).then(function (data) {
			afterHistoryChange(data, 'Undid: ' + (data.undid || 'the last change') + '. The page now shows version ' + data.target + (data.now ? ' (' + data.now + ')' : '') + '.');
		}, function (err) {
			historyError(err, function () {
				doUndo(true);
			});
		});
	}

	function doRestore(id, allow, what) {
		if (state.undoing) {
			return;
		}
		historyBusy(true);
		clear(ui.historyCard);
		message(ui.historyMsg, 'Restoring...', true);
		api('POST', pagePath('/restore'), { version: id, base_version: state.version, allow_placeholders: !!allow }).then(function (data) {
			afterHistoryChange(data, (what || 'Restored version ' + id) + (data.now ? ' (' + data.now + ')' : '') + '.');
		}, function (err) {
			historyError(err, function () {
				doRestore(id, true, what);
			});
		});
	}

	/* ---- the Pages tab: this page's status and template, and new pages ---- */

	var STATUS_LABELS = { publish: 'Published', draft: 'Draft', pending: 'Pending review', 'private': 'Private', future: 'Scheduled' };

	function buildPagesPanel(panel) {
		if (cfg.pageId) {
			ui.pgStatus = el('p', { className: 'twd-sk-ed__row' });
			ui.pgTemplate = el('p', { className: 'twd-sk-ed__row' });
			ui.pgBanner = el('div', { className: 'twd-sk-ed__banner', hidden: '' });
			ui.publishBtn = button('Publish this page', 'primary', function () {
				askPublish('publish');
			});
			ui.unpublishBtn = button('Unpublish this page', 'secondary', function () {
				askPublish('draft');
			});
			ui.switchBtn = button('Switch this page to the kit template', 'secondary', function () {
				doSwitch('kit', false);
			});
			ui.switchBackBtn = button('Switch back to the theme\'s template', 'secondary', function () {
				askSwitchBack();
			});
			[ui.publishBtn, ui.unpublishBtn, ui.switchBtn, ui.switchBackBtn].forEach(function (b) {
				show(b, false);
			});
			ui.pgHelp = el('p', { className: 'twd-sk-ed__help', hidden: '' });
			ui.confirm = el('div', { className: 'twd-sk-ed__card', role: 'group', 'aria-label': 'Please confirm', hidden: '' });
			ui.pgMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
			panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
				el('h3', { className: 'twd-sk-ed__step-title', text: 'This page' }),
				ui.pgStatus,
				ui.pgTemplate,
				ui.pgBanner,
				ui.pgHelp,
				el('div', { className: 'twd-sk-ed__actions' }, [ui.publishBtn, ui.unpublishBtn, ui.switchBtn, ui.switchBackBtn]),
				ui.confirm,
				ui.pgMsg
			]));
		}

		ui.newTitle = el('input', { id: 'twd-sk-ed-newtitle', className: 'twd-sk-ed__input', type: 'text', maxlength: String(cfg.maxTitle || 120), autocomplete: 'off' });
		ui.newTitle.addEventListener('input', function () {
			ui.newBtn.disabled = !ui.newTitle.value.trim();
		});
		ui.newStarter = el('select', { id: 'twd-sk-ed-newstarter', className: 'twd-sk-ed__select' });
		var starters = cfg.starters || { blank: 'Blank page' };
		Object.keys(starters).forEach(function (key) {
			ui.newStarter.appendChild(el('option', { value: key, text: starters[key] }));
		});
		ui.newBtn = button('Create draft page', 'primary', doCreate);
		ui.newBtn.disabled = true;
		ui.newMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.newLink = el('a', { className: 'twd-sk-ed__link', text: 'Open the new draft page', hidden: '' });
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: 'New page' }),
			el('p', { className: 'twd-sk-ed__help', text: 'This makes a draft page that is ready for the kit. Nothing is published until you publish it. More starting layouts arrive later.' }),
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-newtitle', text: 'Page title' }),
			ui.newTitle,
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-newstarter', text: 'Starting layout' }),
			ui.newStarter,
			el('div', { className: 'twd-sk-ed__actions' }, [ui.newBtn]),
			ui.newMsg,
			el('p', {}, [ui.newLink])
		]));
		buildFactsPageSection(panel);
		Array.prototype.forEach.call(panel.querySelectorAll('.twd-sk-ed__step'), function (sec) {
			var t = sec.querySelector('.twd-sk-ed__step-title');
			if (t && t.textContent === 'New page') {
				foldSection(sec, { summary: 'Make an empty draft page' });
			} else if (t && t.textContent === FACTS_PAGE_TITLE) {
				foldSection(sec, { summary: 'Draft an About, Contact or topic page from the facts' });
			}
		});
	}

	/* ---- a new page drafted from the facts ---- */

	function buildFactsPageSection(panel) {
		var recipes = cfg.recipes || {};
		if (!Object.keys(recipes).length) {
			return;
		}
		ui.fpType = el('select', { id: 'twd-sk-ed-fptype', className: 'twd-sk-ed__select' });
		Object.keys(recipes).forEach(function (key) {
			ui.fpType.appendChild(el('option', { value: key, text: recipes[key] }));
		});
		ui.fpType.addEventListener('change', syncFactsPage);
		ui.fpTopic = el('input', { id: 'twd-sk-ed-fptopic', className: 'twd-sk-ed__input', type: 'text', maxlength: '120', autocomplete: 'off' });
		ui.fpTopicBox = el('div', { className: 'twd-sk-ed__field' }, [
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-fptopic', text: 'What is the page about?' }),
			el('p', { className: 'twd-sk-ed__help', text: 'For example: ' + W('topic_example', 'Working with anxiety') + '. This becomes the page title.' }),
			ui.fpTopic
		]);
		ui.fpTitle = el('input', { id: 'twd-sk-ed-fptitle', className: 'twd-sk-ed__input', type: 'text', maxlength: String(cfg.maxTitle || 120), autocomplete: 'off' });
		ui.fpNotes = el('textarea', { id: 'twd-sk-ed-fpnotes', className: 'twd-sk-ed__textarea twd-sk-ed__short', maxlength: '1500', spellcheck: 'true' });
		ui.fpMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.fpLink = el('a', { className: 'twd-sk-ed__link', text: 'Open the new draft page', hidden: '' });
		ui.fpPromptBox = el('textarea', { className: 'twd-sk-ed__textarea', readonly: '', 'aria-label': 'The prompt, selected so you can copy it', hidden: '' });
		ui.fpCreate = button('Create a draft with AI', 'primary', doFactsPage);
		ui.fpCopy = button('Copy prompt for external AI', cfg.aiAvailable ? 'secondary' : 'primary', doFactsPrompt);
		show(ui.fpCreate, !!cfg.aiAvailable);
		var kids = [
			el('p', { className: 'twd-sk-ed__help', text: 'Draft a new page from the ' + FACTS + '. It is saved as a draft and nothing is published. Wherever the facts do not say what is needed, you will see a [PLACEHOLDER] to replace before it can be published.' })
		];
		if (!cfg.factsSaved) {
			kids.push(el('p', { className: 'twd-sk-ed__banner', role: 'status', text: 'No ' + FACTS + ' are saved yet, so the draft will be mostly placeholders.' + (cfg.canManage ? '' : ' An administrator can add them in the Site tab.') }));
		}
		kids.push(
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-fptype', text: 'Page type' }),
			ui.fpType,
			ui.fpTopicBox,
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-fptitle', text: 'Page title (optional)' }),
			ui.fpTitle,
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-fpnotes', text: 'Anything to add? (optional)' }),
			ui.fpNotes,
			el('div', { className: 'twd-sk-ed__actions' }, [ui.fpCreate, ui.fpCopy]),
			ui.fpMsg,
			ui.fpPromptBox,
			el('p', {}, [ui.fpLink])
		);
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [el('h3', { className: 'twd-sk-ed__step-title', text: FACTS_PAGE_TITLE })].concat(kids)));
		syncFactsPage();
	}

	function syncFactsPage() {
		show(ui.fpTopicBox, ui.fpType.value === 'service');
	}

	function factsBody() {
		return { type: ui.fpType.value, topic: ui.fpTopic.value, title: ui.fpTitle.value, notes: ui.fpNotes.value };
	}

	function doFactsPage() {
		var body = factsBody();
		if (body.type === 'service' && !body.topic.trim()) {
			message(ui.fpMsg, 'Say what the page is about first.', false);
			ui.fpTopic.focus();
			return;
		}
		ui.fpCreate.disabled = true;
		show(ui.fpLink, false);
		message(ui.fpMsg, 'Asking the AI. This can take up to a minute. No page is created until it answers.', true);
		api('POST', '/pages/generate', body).then(function (data) {
			ui.fpCreate.disabled = false;
			var text = 'Draft "' + data.title + '" created with ' + data.sections + ' sections. It is not published.';
			if (data.must_count) {
				text += ' ' + data.must_count + ' placeholder or example items still need replacing before it can be published.';
			}
			message(ui.fpMsg, text + ' Check every fact before you publish.', true);
			if (typeof data.url === 'string' && (data.url.indexOf(window.location.origin + '/') === 0 || data.url.charAt(0) === '/')) {
				ui.fpLink.setAttribute('href', data.url);
				show(ui.fpLink, true);
			}
		}, function (err) {
			ui.fpCreate.disabled = false;
			message(ui.fpMsg, err.message, false);
		});
	}

	function doFactsPrompt() {
		var body = factsBody();
		message(ui.fpMsg, 'Preparing the prompt...', true);
		api('POST', '/pages/recipe-prompt', body).then(function (data) {
			var copied = false;
			ui.fpPromptBox.value = data.prompt;
			show(ui.fpPromptBox, true);
			try {
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(data.prompt);
					copied = true;
				}
			} catch (e) {
				copied = false;
			}
			if (!copied) {
				ui.fpPromptBox.focus();
				ui.fpPromptBox.select();
			}
			message(ui.fpMsg, (copied ? 'Copied. ' : 'The text is selected below. Press Ctrl+C (Cmd+C on a Mac) to copy it. ') + 'Paste it into your AI chat. When you have the page HTML, create a blank draft above and paste the result in with Edit this page.', copied);
		}, function (err) {
			message(ui.fpMsg, err.message, false);
		});
	}

	function loadInfo() {
		if (!cfg.pageId) {
			return Promise.resolve();
		}
		return api('GET', pagePath('/info')).then(renderInfo, function (err) {
			message(ui.pgMsg, err.message, false);
		});
	}

	function renderInfo(info) {
		state.info = info;
		var published = info.status === 'publish';
		clear(ui.pgStatus);
		ui.pgStatus.appendChild(el('strong', { text: 'Status: ' }));
		ui.pgStatus.appendChild(document.createTextNode(STATUS_LABELS[info.status] || info.status));
		clear(ui.pgTemplate);
		ui.pgTemplate.appendChild(el('strong', { text: 'Template: ' }));
		ui.pgTemplate.appendChild(document.createTextNode(info.uses_template ? 'TWD Kit Page (no page builder needed)' : 'The theme\'s own template'));
		leftoverBanner(ui.pgBanner, info);

		var kit = info.is_kit_page;
		show(ui.publishBtn, kit && cfg.canPublish && !published);
		show(ui.unpublishBtn, kit && cfg.canPublish && published && !info.is_site_page);
		show(ui.switchBtn, kit && !info.uses_template);
		show(ui.switchBackBtn, kit && info.uses_template);
		var help = '';
		if (!kit) {
			help = 'This page does not use the kit, so there is nothing to publish or switch here.';
		} else if (!cfg.canPublish) {
			help = 'You can edit this page, but only someone with publishing rights can publish or unpublish it.';
		} else if (!info.uses_template) {
			help = 'This page shows the kit through a shortcode. The kit template shows it with no page builder setup, and keeps your theme header and footer.';
		}
		ui.pgHelp.textContent = help;
		show(ui.pgHelp, !!help);
	}

	// One inline confirmation box for every risky action. No browser dialogs.
	function askConfirm(opts) {
		clear(ui.confirm);
		ui.confirm.appendChild(el('p', { text: opts.text }));
		if (opts.lines && opts.lines.length) {
			listInto(ui.confirm, opts.lines);
		}
		var override = null;
		var yes = button(opts.yesLabel, 'primary', function () {
			show(ui.confirm, false);
			opts.onYes(override ? override.checked : false);
		});
		if (opts.overrideLabel) {
			override = el('input', { type: 'checkbox', id: 'twd-sk-ed-override' });
			var label = el('label', { className: 'twd-sk-ed__check', 'for': 'twd-sk-ed-override' }, [override, el('span', { text: opts.overrideLabel })]);
			override.addEventListener('change', function () {
				yes.disabled = !override.checked;
			});
			yes.disabled = true;
			ui.confirm.appendChild(label);
		}
		var no = button('Cancel', 'secondary', function () {
			show(ui.confirm, false);
		});
		ui.confirm.appendChild(el('div', { className: 'twd-sk-ed__actions' }, [yes, no]));
		show(ui.confirm, true);
		yes.focus();
	}

	function markerLines(items) {
		return (items || []).map(function (item) {
			return item.marker + ' (' + item.count + '): ' + item.meaning;
		});
	}

	function askPublish(target) {
		message(ui.pgMsg, '', true);
		api('GET', pagePath('/info')).then(function (info) {
			renderInfo(info);
			if (target === 'draft') {
				askConfirm({
					text: 'Unpublish this page? It goes back to draft and visitors will no longer see it.',
					yesLabel: 'Yes, unpublish',
					onYes: function () {
						doStatus('draft', false);
					}
				});
				return;
			}
			var must = (info.leftovers || []).filter(function (i) {
				return i.level !== 'check';
			});
			var check = (info.leftovers || []).filter(function (i) {
				return i.level === 'check';
			});
			if (!info.has_content) {
				message(ui.pgMsg, 'This page is empty. Add content on the Edit this page tab before publishing.', false);
				return;
			}
			if (must.length) {
				askConfirm({
					text: 'This page still has example text that should be replaced before it goes live:',
					lines: markerLines(must).concat(check.length ? ['Also worth checking:'].concat(markerLines(check)) : []),
					yesLabel: 'Yes, publish anyway',
					overrideLabel: 'I understand, publish with the example text still on the page',
					onYes: function (override) {
						doStatus('publish', override);
					}
				});
				return;
			}
			askConfirm({
				text: 'Publish this page? Anyone with the link will be able to see it.',
				lines: check.length ? ['Worth checking (this does not block publishing):'].concat(markerLines(check)) : [],
				yesLabel: 'Yes, publish',
				onYes: function () {
					doStatus('publish', false);
				}
			});
		}, function (err) {
			message(ui.pgMsg, err.message, false);
		});
	}

	function doStatus(target, override) {
		message(ui.pgMsg, target === 'publish' ? 'Publishing...' : 'Unpublishing...', true);
		api('POST', pagePath('/status'), { status: target, confirm: true, override_placeholders: !!override }).then(function (info) {
			renderInfo(info);
			message(ui.pgMsg, target === 'publish' ? 'Published.' : 'Unpublished. The page is a draft again.', true);
		}, function (err) {
			message(ui.pgMsg, err.message, false);
			loadInfo();
		});
	}

	function doSwitch(use, confirmOther) {
		message(ui.pgMsg, 'Switching...', true);
		api('POST', pagePath('/template'), { use: use, confirm_other_content: !!confirmOther }).then(function (info) {
			renderInfo(info);
			message(ui.pgMsg, use === 'kit' ? 'Done. This page now uses the kit template. Reload the page to see it.' : 'Done. This page now uses the theme\'s template. Reload the page to see it.', true);
		}, function (err) {
			if (err.status === 409 && err.code === 'twd_sk_other_content') {
				message(ui.pgMsg, '', true);
				askConfirm({
					text: err.message + ' Switch anyway?',
					yesLabel: 'Switch anyway',
					onYes: function () {
						doSwitch('kit', true);
					}
				});
				return;
			}
			message(ui.pgMsg, err.message, false);
		});
	}

	function askSwitchBack() {
		message(ui.pgMsg, '', true);
		askConfirm({
			text: 'Switch back to the theme\'s template? The kit page only shows if the page still holds the kit shortcode.',
			yesLabel: 'Yes, switch back',
			onYes: function () {
				doSwitch('default', false);
			}
		});
	}

	function doCreate() {
		var title = ui.newTitle.value.trim();
		if (!title) {
			return;
		}
		message(ui.newMsg, 'Creating the draft page...', true);
		show(ui.newLink, false);
		ui.newBtn.disabled = true;
		api('POST', '/pages', { title: title, starter: ui.newStarter.value }).then(function (data) {
			message(ui.newMsg, 'Draft page "' + data.title + '" created. It is not published.', true);
			if (typeof data.url === 'string' && (data.url.indexOf(window.location.origin + '/') === 0 || data.url.charAt(0) === '/')) {
				ui.newLink.setAttribute('href', data.url);
				show(ui.newLink, true);
			}
			ui.newTitle.value = '';
		}, function (err) {
			message(ui.newMsg, err.message, false);
			ui.newBtn.disabled = !ui.newTitle.value.trim();
		});
	}

	/* ---- opening, closing, keyboard ---- */

	function focusables() {
		var nodes = ui.dialog.querySelectorAll('button, [href], input, textarea, select, [tabindex]');
		return Array.prototype.filter.call(nodes, function (n) {
			return !n.disabled && n.getAttribute('tabindex') !== '-1' && !n.hasAttribute('hidden') && n.offsetParent !== null;
		});
	}

	function trapFocus(e) {
		if (e.key !== 'Tab' || !windowIsModal()) {
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

	function onTabKeys(e) {
		// One tab for now. Arrow keys are ready for when more are added.
		var tabsList = ui.dialog.querySelectorAll('[role="tab"]');
		if (tabsList.length < 2 || (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft')) {
			return;
		}
		var i = Array.prototype.indexOf.call(tabsList, document.activeElement);
		var next = (i + (e.key === 'ArrowRight' ? 1 : tabsList.length - 1)) % tabsList.length;
		tabsList[next].focus();
		e.preventDefault();
	}

	function windowIsModal() {
		return phoneWidth() || win.mode === 'centre';
	}

	function onDocKeys(e) {
		if (ui.overlay.hasAttribute('hidden')) {
			return;
		}
		if (e.key === 'Escape') {
			if (win.menu) {
				e.preventDefault();
				closeWindowMenu();
				ui.winBtn.focus();
				return;
			}
			// Docked or floating, Escape pressed on the page itself is the page's business.
			if (!windowIsModal() && !ui.dialog.contains(document.activeElement)) {
				return;
			}
			e.preventDefault();
			if (ui.closeCard) {
				keepEditing();
			} else if (win.esc === 'minimise') {
				minimise();
			} else {
				requestClose();
			}
			return;
		}
		// If the focused control was hidden or removed (a confirmation closing, say), focus falls to the page.
		// Bring it back inside the pop-up instead of letting Tab wander out. Only while the window is a modal.
		if (e.key === 'Tab' && windowIsModal() && !ui.dialog.contains(document.activeElement)) {
			e.preventDefault();
			var items = focusables();
			(e.shiftKey ? items[items.length - 1] : items[0] || ui.title).focus();
		}
	}

	function open() {
		if (simple) {
			var EDs = window.TWD_SK_ED;
			if (EDs && typeof EDs.startInline === 'function') {
				EDs.startInline();
			}
			return;
		}
		if (!state.built) {
			build();
			document.addEventListener('keydown', onDocKeys);
		}
		if (win.minimised) {
			restoreWindow();
			return;
		}
		var wasHidden = ui.overlay.hasAttribute('hidden');
		if (oneWindow && win.mode !== 'free') {
			win.mode = 'free';
			applyWindow();
		}
		show(ui.overlay, true);
		// The one window always opens on On page edits; a caller that wants another tab (the header and footer pills,
		// openAt) selects it after this. A pencil pressed while the window is already open keeps the tab in use.
		if (oneWindow && wasHidden && ui.tabs.page && state.tab !== 'page') {
			selectTab('page');
		}
		queuePencils();
		openBtn.setAttribute('aria-expanded', 'true');
		ui.title.focus();
		if (ui.tabs.edit) {
			loadPrompt();
			loadVersions();
			loadSections();
		}
		if (state.tab === 'pages') {
			loadInfo();
		}
		var hooks = (window.TWD_SK_ED && window.TWD_SK_ED.onOpen) || [];
		hooks.forEach(function (fn) {
			try {
				fn();
			} catch (err) {
				// A broken extra script must never stop the editor opening.
			}
		});
		if (oneWindow) {
			buildOutline();
			startInlineForPage();
		}
	}

	// Everything the person has changed but not saved, as short plain phrases. Other scripts add their own checks.
	function unsavedList() {
		var out = [];
		if (ui.paste && ui.paste.value.trim()) {
			out.push('page text that you pasted but have not applied');
		} else if (ui.request && ui.request.value.trim()) {
			out.push('the changes you typed in the box');
		}
		((window.TWD_SK_ED && window.TWD_SK_ED.dirtyChecks) || []).forEach(function (check) {
			var label = '';
			try {
				label = check.test();
			} catch (err) {
				label = '';
			}
			if (label) {
				out.push(label);
			}
		});
		return out;
	}

	function discardUnsaved() {
		if (ui.request) {
			ui.request.value = '';
			clear(ui.planBox);
			show(ui.planBox, false);
			message(ui.planMsg, '', true);
		}
		if (ui.paste && ui.paste.value) {
			var token = state.token;
			ui.paste.value = '';
			clearPreview();
			message(ui.previewMsg, '', true);
			if (token) {
				api('POST', pagePath('/preview/discard'), { token: token }).then(function () {}, function () {});
			}
		}
		((window.TWD_SK_ED && window.TWD_SK_ED.dirtyChecks) || []).forEach(function (check) {
			if (typeof check.discard === 'function') {
				try {
					check.discard();
				} catch (err) {
					// Closing must still work.
				}
			}
		});
	}

	function keepEditing() {
		if (ui.closeCard && ui.closeCard.parentNode) {
			ui.closeCard.parentNode.removeChild(ui.closeCard);
		}
		ui.closeCard = null;
		ui.title.focus();
	}

	// Close, but ask first when something has not been saved. Inline card, no browser dialogs.
	function requestClose() {
		if (ui.overlay.hasAttribute('hidden')) {
			return;
		}
		if (ui.closeCard) {
			ui.closeCard.querySelector('button').focus();
			return;
		}
		var list = unsavedList();
		if (!list.length) {
			close();
			return;
		}
		var stay = button('Keep editing', 'primary', keepEditing);
		var leave = button('Close and lose these changes', 'secondary', function () {
			keepEditing();
			discardUnsaved();
			close();
		});
		var card = el('div', { className: 'twd-sk-ed__card twd-sk-ed__closecard', role: 'alertdialog', 'aria-labelledby': 'twd-sk-ed-close-title' }, [
			el('p', { id: 'twd-sk-ed-close-title', className: 'twd-sk-ed__label', text: 'You have changes that are not saved.' })
		]);
		listInto(card, list);
		card.appendChild(el('p', { text: 'If you close now they are lost.' }));
		card.appendChild(el('div', { className: 'twd-sk-ed__actions' }, [stay, leave]));
		ui.dialog.insertBefore(card, ui.dialog.querySelector('.twd-sk-ed__tabs'));
		ui.closeCard = card;
		stay.focus();
	}

	// Open the pop-up on a tab and put the cursor in a named field (the edit pills use this).
	function openAt(tab, fieldId) {
		if (ui.overlay && !ui.overlay.hasAttribute('hidden')) {
			keepEditing();
		}
		open();
		if (ui.tabs[tab]) {
			selectTab(tab);
		}
		if (fieldId) {
			var target = document.getElementById(fieldId);
			if (target) {
				revealFold(target);
				if (target.scrollIntoView) {
					target.scrollIntoView({ block: 'center' });
				}
				target.focus();
			}
		}
	}

	function close() {
		closeWindowMenu();
		win.minimised = false;
		if (ui.mini) {
			show(ui.mini, false);
		}
		// The one window: closing ends the page tools too (unsaved changes were asked about, or there were none).
		var ED = window.TWD_SK_ED;
		if (oneWindow && ED && typeof ED.stopInline === 'function') {
			ED.stopInline();
		}
		show(ui.overlay, false);
		queuePencils();
		openBtn.setAttribute('aria-expanded', 'false');
		openBtn.focus();
	}

	// A small inline confirmation inside any container, for tabs added by other scripts. No browser dialogs.
	function confirmInline(host, opts) {
		var box = el('div', { className: 'twd-sk-ed__card', role: 'group', 'aria-label': 'Please confirm' });
		box.appendChild(el('p', { text: opts.text }));
		var yes = button(opts.yesLabel || 'Yes', 'primary', function () {
			host.removeChild(box);
			opts.onYes();
		});
		var no = button('Cancel', 'secondary', function () {
			host.removeChild(box);
		});
		box.appendChild(el('div', { className: 'twd-sk-ed__actions' }, [yes, no]));
		host.appendChild(box);
		yes.focus();
	}

	/* ---- the editing tools switch (0.14.1) ----
	 * One switch that hides the floating buttons (Edit with AI, Visual editor, Tell us), the Edit header and
	 * Edit footer pills and the version label, so the site can be looked at as a visitor sees it, and brings
	 * them back when pressed again. It sits in the WordPress admin bar when there is one (top left), and as a
	 * small floating switch otherwise, and on a phone, where WordPress hides most admin bar items. The choice is
	 * kept for the signed-in user on the server (no browser storage), so it follows them between pages and devices.
	 * A session already open (the pop-up, the editing card) is never closed by it. */
	var tools = { off: !!cfg.toolsOff, listeners: [], bar: null, floating: null };
	// The phone contact bar (0.15.0) sits at the bottom: the floating button and the pencil move up out of its way.
	if (document.querySelector('.twd-sk-contactbar')) {
		root.classList.add('twd-sk-ed--contactbar');
	}

	function toolsLabel() {
		return 'Editing tools: ' + (tools.off ? 'off' : 'on');
	}

	function applyTools() {
		[root, document.getElementById('twd-sk-tellus')].forEach(function (node) {
			if (!node) {
				return;
			}
			if (tools.off) {
				node.setAttribute('data-twd-sk-tools', 'off');
			} else {
				node.removeAttribute('data-twd-sk-tools');
			}
		});
		if (menu.built) {
			drawMenu();
		}
		tools.listeners.forEach(function (fn) {
			fn(tools.off);
		});
	}

	function flipTools() {
		tools.off = !tools.off;
		applyTools();
		// Remembered for this user. If it cannot be saved the switch still works for this page view.
		api('POST', '/editor/tools', { off: tools.off }).catch(function () {});
	}

	/* ---- Your Edits (0.15.0): one menu for every editing tool ----
	 * Replaces the stack of floating buttons (Edit with AI, Visual editor, Tell us) and the separate switch, so
	 * nothing floats over the page's words. In the WordPress admin bar it is one drop-down, "Your Edits". On a
	 * phone (WordPress hides most admin bar items under 783px) and with no admin bar, one small floating
	 * "Your Edits" button opens the same list. Other editor scripts add their items with ED.addLauncher(). */
	var menu = { items: [], bar: null, list: null, pop: null, popList: null, button: null, built: false };

	function addLauncher(item) {
		menu.items.push(item);
		if (menu.built) {
			drawMenu();
		}
	}

	function menuItems() {
		return menu.items.filter(function (it) {
			return !it.show || it.show();
		}).sort(function (a, b) {
			return (a.order || 50) - (b.order || 50);
		});
	}

	function drawMenu() {
		[menu.list, menu.popList].forEach(function (list) {
			if (!list) {
				return;
			}
			clear(list);
			menuItems().forEach(function (it) {
				var inBar = list === menu.list;
				var node;
				if (it.kind === 'note') {
					node = el('span', { className: inBar ? 'ab-item ab-empty-item' : 'twd-sk-ed__menunote', text: it.label() });
				} else {
					node = el(inBar ? 'a' : 'button', inBar ? { className: 'ab-item', href: '#' } : { type: 'button', className: 'twd-sk-ed__menuitem' });
					node.textContent = it.label();
					if (it.kind === 'switch') {
						node.setAttribute('role', 'switch');
						node.setAttribute('aria-checked', it.checked() ? 'true' : 'false');
					}
					node.addEventListener('click', function (e) {
						e.preventDefault();
						closeMenu();
						it.run();
					});
				}
				list.appendChild(inBar ? el('li', { id: 'wp-admin-bar-twd-sk-' + it.id }, [node]) : el('li', {}, [node]));
			});
		});
	}

	function closeMenu() {
		if (menu.bar) {
			menu.bar.classList.remove('hover');
		}
		if (menu.pop) {
			show(menu.pop, false);
			menu.button.setAttribute('aria-expanded', 'false');
		}
	}

	// The Your Edits drop-down in the WordPress admin bar, right after the WordPress logo, so it sits at the top left.
	function buildBarMenu() {
		var list = document.getElementById('wp-admin-bar-root-default');
		if (!list) {
			return;
		}
		var top = el('a', { className: 'ab-item', href: '#', 'aria-haspopup': 'true', 'aria-expanded': 'false', text: 'Your Edits' });
		menu.list = el('ul', { id: 'wp-admin-bar-twd-sk-edits-default', className: 'ab-submenu' });
		menu.bar = el('li', { id: 'wp-admin-bar-twd-sk-edits', className: 'menupop' }, [top, el('div', { className: 'ab-sub-wrapper' }, [menu.list])]);
		top.addEventListener('click', function (e) {
			e.preventDefault();
			var on = menu.bar.classList.toggle('hover');
			top.setAttribute('aria-expanded', on ? 'true' : 'false');
		});
		list.insertBefore(menu.bar, list.children.length > 1 ? list.children[1] : null);
		root.setAttribute('data-twd-sk-ab', '1');
	}

	/* View as (19e): one click each way, remembered on the server for this administrator, then the page reloads so
	 * the server hands out exactly what a client gets (or everything). Never offered to anyone else. */
	function switchView(as) {
		if (!cfg.canSwitchView) {
			return;
		}
		if (state.built && !ui.overlay.hasAttribute('hidden') && unsavedList().length) {
			requestClose();
			return;
		}
		api('POST', '/editor/view-as', { as: as }).then(function () {
			window.location.reload();
		}, function () {
			window.location.reload();
		});
	}

	/* The Simple editor's launchers (19b): the one round pen button, bottom right, glowing softly, labelled "Edit this
	 * page", and one plain "Edit this page" in the admin bar in place of the Your Edits menu. An administrator viewing
	 * as the client keeps the Your Edits drop-down, holding only Edit this page and the way back. Nothing else floats. */
	function buildSimpleLaunchers() {
		openBtn.className = 'twd-sk-ed__mini twd-sk-ed__pen';
		openBtn.textContent = '';
		openBtn.setAttribute('aria-label', 'Edit this page');
		openBtn.setAttribute('title', 'Edit this page');
		openBtn.removeAttribute('aria-haspopup');
		openBtn.appendChild(pencilIcon());
		var list = document.getElementById('wp-admin-bar-root-default');
		if (list && cfg.canSwitchView) {
			buildBarMenu();
			addLauncher({ id: 'ai', order: 20, label: function () { return 'Edit this page'; }, run: open });
			addLauncher({ id: 'viewas', order: 85, label: function () { return 'Viewing as Client - switch back'; }, run: function () {
				switchView('admin');
			} });
			document.addEventListener('click', function (e) {
				if (menu.bar && menu.bar.classList.contains('hover') && !menu.bar.contains(e.target)) {
					closeMenu();
				}
			});
		} else if (list) {
			var a = el('a', { className: 'ab-item', href: '#', text: 'Edit this page' });
			a.addEventListener('click', function (e) {
				e.preventDefault();
				open();
			});
			list.insertBefore(el('li', { id: 'wp-admin-bar-twd-sk-edit' }, [a]), list.children.length > 1 ? list.children[1] : null);
			root.setAttribute('data-twd-sk-ab', '1');
		}
		menu.built = true;
		drawMenu();
	}

	function buildMenu() {
		if (simple) {
			buildSimpleLaunchers();
			return;
		}
		buildBarMenu();
		menu.button = el('button', { type: 'button', className: 'twd-sk-ed__menu', 'aria-haspopup': 'true', 'aria-expanded': 'false', text: 'Your Edits' });
		menu.popList = el('ul', { className: 'twd-sk-ed__menulist' });
		menu.pop = el('div', { className: 'twd-sk-ed__menupop', hidden: '' }, [menu.popList]);
		menu.button.addEventListener('click', function () {
			var open = menu.pop.hasAttribute('hidden');
			show(menu.pop, open);
			menu.button.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) {
				var first = menu.popList.querySelector('button');
				if (first) {
					first.focus();
				}
			}
		});
		root.appendChild(menu.pop);
		root.appendChild(menu.button);
		document.addEventListener('click', function (e) {
			if ((menu.bar && menu.bar.classList.contains('hover') && !menu.bar.contains(e.target)) || (menu.pop && !menu.pop.hasAttribute('hidden') && !menu.pop.contains(e.target) && e.target !== menu.button)) {
				closeMenu();
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && ((menu.bar && menu.bar.classList.contains('hover')) || (menu.pop && !menu.pop.hasAttribute('hidden')))) {
				closeMenu();
			}
		});
		var version = root.querySelector('.twd-sk-ed__version');
		addLauncher({ id: 'ai', order: 20, label: function () { return oneWindow ? 'Edit this page' : 'Edit with AI'; }, run: open, show: function () { return !tools.off; } });
		// The classic editor (the full-screen pop-up and the separate Visual editor card), by choice, until the one window has settled.
		addLauncher({ id: 'classic', order: 92, kind: 'switch', label: function () { return 'Use the classic editor: ' + (cfg.classic ? 'on' : 'off'); }, checked: function () { return !!cfg.classic; }, run: function () {
			var on = !cfg.classic;
			api('POST', '/editor/classic', { on: on }).then(function () {
				window.location.reload();
			}, function () {
				window.location.reload();
			});
		} });
		if (cfg.pageId) {
			addLauncher({ id: 'undo-page', order: 40, label: function () { return 'Undo the last page change'; }, show: function () { return !tools.off; }, run: function () {
				open();
				if (ui.hist) {
					revealFold(ui.hist);
					ui.hist.scrollIntoView({ block: 'start' });
				}
				doUndo(false);
			} });
		}
		addLauncher({ id: 'tools', order: 90, kind: 'switch', label: toolsLabel, checked: function () { return !tools.off; }, run: flipTools });
		// View as (19e): administrators only; a real client never has this item.
		if (cfg.canSwitchView) {
			addLauncher({ id: 'viewas', order: 85, label: function () { return 'View as Client (see what the client sees)'; }, run: function () {
				switchView('client');
			} });
		}
		if (version && version.textContent) {
			addLauncher({ id: 'version', order: 99, kind: 'note', label: function () { return 'Site Kit ' + version.textContent; } });
		}
		menu.built = true;
		drawMenu();
		applyTools();
	}

	// What other editor scripts (the Site tab) may use. They register tabs here before the pop-up first opens.
	window.TWD_SK_ED = {
		cfg: cfg,
		toolsOff: function () {
			return tools.off;
		},
		onToolsChange: function (fn) {
			tools.listeners.push(fn);
		},
		// Your Edits menu (0.15.0): { id, order, label(), run(), show()?, kind: 'item' | 'switch' (with checked()) | 'note' }.
		addLauncher: addLauncher,
		redrawMenu: drawMenu,
		// The Simple editor (session 5): run a launcher another script registered (the panel's Tell us line), and the switch.
		hasLauncher: function (id) {
			return menu.items.some(function (it) {
				return it.id === id;
			});
		},
		runLauncher: function (id) {
			menu.items.forEach(function (it) {
				if (it.id === id) {
					it.run();
				}
			});
		},
		simple: simple,
		switchView: switchView,
		formatDate: formatDate,
		// The page version the editor works from, set by a script that saved, undid or restored without a reload.
		setVersion: function (v) {
			state.version = parseInt(v, 10) || 0;
			cfg.currentVersion = state.version;
		},
		W: W,
		api: api,
		el: el,
		clear: clear,
		show: show,
		button: button,
		message: message,
		listInto: listInto,
		confirmInline: confirmInline,
		extraTabs: [],
		tabHooks: {},
		onOpen: [],
		dirtyChecks: [],
		addDirtyCheck: function (test, discard) {
			this.dirtyChecks.push({ test: test, discard: discard });
		},
		openAt: openAt,
		// The page version the editor is working from (a change to the page names it).
		version: function () {
			return state.version;
		},
		// Puts a candidate page in the paste box and runs the normal preview. Nothing is saved until Apply.
		proposeHtml: function (html, note) {
			if (!ui.paste || !ui.tabs || !ui.tabs.edit) {
				return false;
			}
			ui.paste.value = html;
			state.previewedText = null;
			if (note && ui.note && !ui.note.value.trim()) {
				ui.note.value = String(note).slice(0, 190);
			}
			selectTab('edit');
			doPreview();
			return true;
		},
		foldSection: foldSection,
		revealFold: revealFold,
		// 18c: the On page edits tab's list of sections, hidden while one section's view is open.
		showOutline: function (on) {
			if (ui.outline) {
				show(ui.outline, !!on);
			}
			if (ui.pageHint) {
				show(ui.pageHint, !!on);
			}
		},
		// The window (0.15.0): shrink it to the pencil, or bring it back.
		minimise: minimise,
		restoreWindow: restoreWindow,
		isMinimised: function () {
			return win.minimised;
		},
		// Fetch the page again and swap the kit wrappers in place (no reload). Resolves true when it worked.
		redraw: redrawPage,
		onRedraw: function (fn) {
			redrawHooks.push(fn);
		},
		addTab: function (name, label, build) {
			this.extraTabs.push({ name: name, label: label, build: build });
		},
		// The one window (0.15.0, phase 1): where the Visual editor puts its tools, and whether the one window is on.
		oneWindow: oneWindow,
		inlineHost: function () {
			return ui.inlineHost || null;
		},
		selectTab: function (name) {
			if (state.built) {
				selectTab(name);
			}
		}
	};

	openBtn.addEventListener('click', open);
	show(openBtn, true);
	buildMenu();
	buildPencils();
})();
