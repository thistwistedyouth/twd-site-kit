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
	var root = document.getElementById('twd-sk-ed');
	var openBtn = document.getElementById('twd-sk-ed-open');
	if (!cfg || !root || !openBtn) {
		return;
	}

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
			return 'Too many requests. Wait a minute and try again.';
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
		box.appendChild(el('strong', { text: 'What the cleaner did' }));
		listInto(box, data.report || []);
		show(box, true);
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

		var tabs = el('div', { className: 'twd-sk-ed__tabs', role: 'tablist', 'aria-label': 'Editor sections' });
		tabs.addEventListener('keydown', onTabKeys);
		var body = el('div', { className: 'twd-sk-ed__body' });
		ui.tabs = {};

		function addTab(name, label, buildPanel) {
			var tab = el('button', {
				type: 'button', role: 'tab', id: 'twd-sk-ed-tab-' + name, className: 'twd-sk-ed__tab',
				'aria-selected': 'false', 'aria-controls': 'twd-sk-ed-panel-' + name, text: label
			});
			var panel = el('div', { role: 'tabpanel', id: 'twd-sk-ed-panel-' + name, 'aria-labelledby': 'twd-sk-ed-tab-' + name, hidden: '' });
			buildPanel(panel);
			tab.addEventListener('click', function () {
				selectTab(name);
			});
			tabs.appendChild(tab);
			body.appendChild(panel);
			ui.tabs[name] = { tab: tab, panel: panel };
		}

		if (cfg.isKitPage && cfg.pageId) {
			addTab('edit', 'Edit this page', buildEditPanel);
		}
		addTab('pages', 'Pages', buildPagesPanel);
		((window.TWD_SK_ED && window.TWD_SK_ED.extraTabs) || []).forEach(function (t) {
			addTab(t.name, t.label, t.build);
		});
		if (cfg.safeMode) {
			body.insertBefore(el('div', { className: 'twd-sk-ed__banner', role: 'status', text: 'Safe mode is on. The Site tab, header and footer, SEO fields and the newest features are switched off. Your pages and this editor still work.' }), body.firstChild);
		}

		var dialog = el('div', {
			className: 'twd-sk-ed__dialog', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'twd-sk-ed-title'
		}, [
			el('div', { className: 'twd-sk-ed__header' }, [title, closeBtn]),
			tabs,
			body
		]);
		dialog.addEventListener('keydown', trapFocus);

		ui.overlay = el('div', { id: 'twd-sk-ed-overlay', className: 'twd-sk-ed__overlay', hidden: '' }, [dialog]);
		ui.overlay.addEventListener('mousedown', function (e) {
			if (e.target === ui.overlay) {
				requestClose();
			}
		});
		ui.dialog = dialog;
		ui.title = title;
		root.appendChild(ui.overlay);
		state.built = true;
		selectTab(ui.tabs.edit ? 'edit' : 'pages');
	}

	function selectTab(name) {
		Object.keys(ui.tabs).forEach(function (key) {
			var on = key === name;
			ui.tabs[key].tab.setAttribute('aria-selected', on ? 'true' : 'false');
			show(ui.tabs[key].panel, on);
		});
		state.tab = name;
		if (name === 'pages') {
			loadInfo();
		}
		var hook = window.TWD_SK_ED && window.TWD_SK_ED.tabHooks && window.TWD_SK_ED.tabHooks[name];
		if (typeof hook === 'function') {
			hook();
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

		if (ai) {
			buildRemixStep(panel);
			// The discard button lives with the preview, not inside the tucked-away external section.
			pasteKids[2] = el('div', { className: 'twd-sk-ed__actions' }, [ui.previewBtn]);
			outKids.unshift(el('div', { className: 'twd-sk-ed__actions' }, [ui.discardBtn]));
			ui.external = el('div', { id: 'twd-sk-ed-external', className: 'twd-sk-ed__external', hidden: '' }, copyKids.concat(pasteKids));
			ui.externalBtn = el('button', { type: 'button', className: 'twd-sk-ed__disclose', 'aria-expanded': 'false', 'aria-controls': 'twd-sk-ed-external', text: 'Use an external AI instead' });
			ui.externalBtn.addEventListener('click', function () {
				var open = ui.externalBtn.getAttribute('aria-expanded') === 'true';
				ui.externalBtn.setAttribute('aria-expanded', open ? 'false' : 'true');
				show(ui.external, !open);
			});
			panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [ui.externalBtn, ui.external]));
			panel.appendChild(step('2. Preview it', outKids));
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
		panel.appendChild(step('3. Save it', [
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-note', text: 'Where did these facts come from?' }),
			el('p', { className: 'twd-sk-ed__help', text: 'For example: the therapist told me on a call, or their old website. This is saved with the version so you can see it later.' }),
			ui.note,
			el('div', { className: 'twd-sk-ed__actions' }, [ui.applyBtn, ui.reloadBtn]),
			ui.applyMsg
		]));

		/* History */
		ui.undoBtn = button('Undo the last change', 'secondary', doUndo);
		ui.undoBtn.disabled = true;
		ui.historyMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.versions = el('ol', { className: 'twd-sk-ed__versions' });
		panel.appendChild(step('4. History (the last 10 versions)', [
			el('p', { className: 'twd-sk-ed__help', text: 'Undo and Restore never delete anything. They save a new version, so you can always go back again.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [ui.undoBtn]),
			ui.historyMsg,
			ui.versions
		]));
	}

	/* ---- step 1 (when the site has an AI key): ask the AI ---- */

	var CHIPS = [
		['Warmer', 'Make it warmer.'],
		['Shorter', 'Make it shorter.'],
		['Simpler words', 'Use simpler words.'],
		['More professional', 'Make the tone more professional.'],
		['Stronger first line', 'Write a stronger first line.'],
		['Fix links and headings', 'Fix link wording and heading order.'],
		['Update from my practice facts', 'Update this so it matches my practice facts. Keep the layout.']
	];

	function buildRemixStep(panel) {
		var modes = el('div', { className: 'twd-sk-ed__modes', role: 'radiogroup', 'aria-label': 'What to change' });
		ui.modeSections = el('input', { id: 'twd-sk-ed-mode-sections', type: 'radio', name: 'twd-sk-ed-mode', value: 'sections', checked: '' });
		ui.modePage = el('input', { id: 'twd-sk-ed-mode-page', type: 'radio', name: 'twd-sk-ed-mode', value: 'page' });
		[ui.modeSections, ui.modePage].forEach(function (r) {
			r.addEventListener('change', syncMode);
		});
		modes.appendChild(el('label', { className: 'twd-sk-ed__check', 'for': 'twd-sk-ed-mode-sections' }, [ui.modeSections, el('span', { text: 'Some sections that I choose (recommended)' })]));
		modes.appendChild(el('label', { className: 'twd-sk-ed__check', 'for': 'twd-sk-ed-mode-page' }, [ui.modePage, el('span', { text: 'The whole page' })]));

		ui.secBox = el('div', { className: 'twd-sk-ed__seclist', role: 'group', 'aria-label': 'Sections of this page' });
		ui.instruction = el('textarea', { id: 'twd-sk-ed-instruction', className: 'twd-sk-ed__textarea twd-sk-ed__short', maxlength: '1500', spellcheck: 'true' });
		var chips = el('div', { className: 'twd-sk-ed__chips' });
		CHIPS.forEach(function (c) {
			var chip = el('button', { type: 'button', className: 'twd-sk-ed__chip', text: c[0] });
			chip.addEventListener('click', function () {
				var v = ui.instruction.value.trim();
				ui.instruction.value = v ? v + ' ' + c[1] : c[1];
				ui.instruction.focus();
			});
			chips.appendChild(chip);
		});
		ui.facts = el('textarea', { id: 'twd-sk-ed-facts', className: 'twd-sk-ed__textarea twd-sk-ed__short', maxlength: '3000', spellcheck: 'true' });
		ui.remixBtn = button('Ask the AI', 'primary', doRemix);
		ui.remixMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });

		panel.appendChild(step('1. Ask the AI to change this page', [
			el('p', { className: 'twd-sk-ed__help', text: 'Choose what to change and say what you want. You see a preview first, and nothing is saved until you apply it.' }),
			modes,
			ui.secBox,
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-instruction', text: 'What do you want changed?' }),
			chips,
			ui.instruction,
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-facts', text: 'Facts the therapist gave you (optional)' }),
			el('p', { className: 'twd-sk-ed__help', text: 'The AI may use only these for anything new. Leave it empty to keep to what is already on the page.' }),
			ui.facts,
			el('p', { className: 'twd-sk-ed__help', text: 'The AI writes drafts. Check every fact and every claim before you apply.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [ui.remixBtn]),
			ui.remixMsg
		]));
	}

	function syncMode() {
		show(ui.secBox, ui.modeSections.checked);
	}

	function loadSections() {
		if (!cfg.ai || !ui.secBox) {
			return;
		}
		api('GET', pagePath('/sections')).then(function (data) {
			state.version = data.version;
			renderSections(data);
		}, function (err) {
			message(ui.remixMsg, err.message, false);
		});
	}

	function renderSections(data) {
		clear(ui.secBox);
		ui.secChecks = [];
		if (!data.ai) {
			message(ui.remixMsg, 'AI is not available on this site right now. Use the external AI option below.', false);
			ui.remixBtn.disabled = true;
			return;
		}
		ui.remixBtn.disabled = false;
		if (!data.sections.length) {
			ui.secBox.appendChild(el('p', { className: 'twd-sk-ed__help', text: 'This page has no sections yet. Paste a page in first (use the external AI option below).' }));
			return;
		}
		data.sections.forEach(function (s) {
			var id = 'twd-sk-ed-sec-' + s.index;
			var box = el('input', { id: id, type: 'checkbox', value: String(s.index) });
			var row = el('div', { className: 'twd-sk-ed__sec' }, [
				el('label', { className: 'twd-sk-ed__check', 'for': id }, [box, el('span', { text: (s.index + 1) + '. ' + s.label + ' (' + s.type + ')' })])
			]);
			var entry = { index: s.index, box: box, unlock: null };
			if (s.locked) {
				var uid = 'twd-sk-ed-unlock-' + s.index;
				var unlock = el('input', { id: uid, type: 'checkbox', value: String(s.index) });
				entry.unlock = unlock;
				row.appendChild(el('p', { className: 'twd-sk-ed__help twd-sk-ed__locknote', text: 'The words here are locked. The AI can change the layout only.' }));
				row.appendChild(el('label', { className: 'twd-sk-ed__check twd-sk-ed__unlock', 'for': uid }, [unlock, el('span', { text: 'Allow new wording (only words the therapist has given you)' })]));
			}
			ui.secChecks.push(entry);
			ui.secBox.appendChild(row);
		});
		syncMode();
	}

	function doRemix() {
		var instruction = ui.instruction.value.trim();
		if (!instruction) {
			message(ui.remixMsg, 'Say what you want changed first.', false);
			ui.instruction.focus();
			return;
		}
		var body = { base_version: state.version, instruction: instruction, facts: ui.facts.value, mode: ui.modePage.checked ? 'page' : 'sections' };
		var names = [];
		if (body.mode === 'sections') {
			body.indexes = [];
			body.unlock = [];
			(ui.secChecks || []).forEach(function (c) {
				if (c.box.checked) {
					body.indexes.push(c.index);
					names.push(String(c.index + 1));
					if (c.unlock && c.unlock.checked) {
						body.unlock.push(c.index);
					}
				}
			});
			if (!body.indexes.length) {
				message(ui.remixMsg, 'Choose at least one section to change, or choose the whole page.', false);
				return;
			}
		}
		ui.remixBtn.disabled = true;
		message(ui.remixMsg, 'Asking the AI. This can take up to a minute. Nothing is saved.', true);
		api('POST', pagePath('/remix'), body).then(function (data) {
			ui.remixBtn.disabled = false;
			clear(ui.remixMsg);
			if (!data.changed || !data.changed.length) {
				message(ui.remixMsg, (data.report && data.report.length ? data.report.join(' ') : 'The AI made no change.') + ' Try wording the instruction differently.', false);
				return;
			}
			message(ui.remixMsg, 'The AI has drafted a change. Check the preview below. Nothing is saved yet.', true);
			if (data.report && data.report.length) {
				listInto(ui.remixMsg, data.report);
			}
			ui.paste.value = data.html;
			state.previewedText = null;
			if (!ui.note.value.trim()) {
				ui.note.value = ('AI remix (' + (body.mode === 'page' ? 'whole page' : 'sections ' + names.join(', ')) + '): ' + instruction).slice(0, 190);
			}
			doPreview();
		}, function (err) {
			ui.remixBtn.disabled = false;
			if (err.status === 409) {
				message(ui.remixMsg, err.message, false);
				show(ui.reloadBtn, true);
				return;
			}
			message(ui.remixMsg, err.message, false);
		});
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
			message(ui.previewMsg, 'Preview ready. Nothing has been saved yet.', true);
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
		ui.undoBtn.disabled = (data.versions || []).length < 2;
		(data.versions || []).forEach(function (v) {
			var who = v.by ? ' by ' + v.by : '';
			var label = 'Version ' + v.id + (v.current ? ' (current)' : '') + ' - ' + formatDate(v.created) + who;
			var text = el('span', { className: 'twd-sk-ed__version-text' }, [
				el('span', { className: v.current ? 'twd-sk-ed__current' : '', text: label }),
				v.note ? el('span', { className: 'twd-sk-ed__version-note', text: v.note }) : null
			]);
			var row = el('li', { className: 'twd-sk-ed__version' }, [text]);
			if (!v.current) {
				row.appendChild(button('Restore this version', 'secondary', function () {
					doRestore(v.id);
				}));
			}
			ui.versions.appendChild(row);
		});
	}

	function loadVersions() {
		return api('GET', pagePath('/versions')).then(renderVersions, function (err) {
			message(ui.historyMsg, err.message, false);
		});
	}

	function afterHistoryChange(data, what) {
		renderVersions(data);
		message(ui.historyMsg, what + ' Saved as version ' + data.version + '.', true);
		show(ui.reloadBtn, true);
		loadPrompt();
	}

	function doUndo() {
		message(ui.historyMsg, 'Undoing...', true);
		api('POST', pagePath('/undo'), { base_version: state.version }).then(function (data) {
			afterHistoryChange(data, 'Went back one step.');
		}, function (err) {
			handleWriteError(ui.historyMsg, err);
		});
	}

	function doRestore(id) {
		message(ui.historyMsg, 'Restoring...', true);
		api('POST', pagePath('/restore'), { version: id, base_version: state.version }).then(function (data) {
			afterHistoryChange(data, 'Restored version ' + id + '.');
		}, function (err) {
			handleWriteError(ui.historyMsg, err);
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
	}

	/* ---- a new page drafted from the practice facts ---- */

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
			el('p', { className: 'twd-sk-ed__help', text: 'For example: Working with anxiety. This becomes the page title.' }),
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
			el('p', { className: 'twd-sk-ed__help', text: 'Draft a new page from the practice facts about the person. It is saved as a draft and nothing is published. Wherever the facts do not say what is needed, you will see a [PLACEHOLDER] to replace before it can be published.' })
		];
		if (!cfg.factsSaved) {
			kids.push(el('p', { className: 'twd-sk-ed__banner', role: 'status', text: 'No practice facts are saved yet, so the draft will be mostly placeholders.' + (cfg.canManage ? '' : ' An administrator can add them in the Site tab.') }));
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
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [el('h3', { className: 'twd-sk-ed__step-title', text: 'New page from your practice facts' })].concat(kids)));
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

	function onDocKeys(e) {
		if (ui.overlay.hasAttribute('hidden')) {
			return;
		}
		if (e.key === 'Escape') {
			e.preventDefault();
			if (ui.closeCard) {
				keepEditing();
			} else {
				requestClose();
			}
			return;
		}
		// If the focused control was hidden or removed (a confirmation closing, say), focus falls to the page.
		// Bring it back inside the pop-up instead of letting Tab wander out.
		if (e.key === 'Tab' && !ui.dialog.contains(document.activeElement)) {
			e.preventDefault();
			var items = focusables();
			(e.shiftKey ? items[items.length - 1] : items[0] || ui.title).focus();
		}
	}

	function open() {
		if (!state.built) {
			build();
			document.addEventListener('keydown', onDocKeys);
		}
		show(ui.overlay, true);
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
	}

	// Everything the person has changed but not saved, as short plain phrases. Other scripts add their own checks.
	function unsavedList() {
		var out = [];
		if (ui.paste && ui.paste.value.trim()) {
			out.push('page text that you pasted but have not applied');
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
				if (target.scrollIntoView) {
					target.scrollIntoView({ block: 'center' });
				}
				target.focus();
			}
		}
	}

	function close() {
		show(ui.overlay, false);
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

	// What other editor scripts (the Site tab) may use. They register tabs here before the pop-up first opens.
	window.TWD_SK_ED = {
		cfg: cfg,
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
		addTab: function (name, label, build) {
			this.extraTabs.push({ name: name, label: label, build: build });
		}
	};

	openBtn.addEventListener('click', open);
	show(openBtn, true);
})();
