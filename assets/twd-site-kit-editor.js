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

	function leftoverBanner(box, data) {
		clear(box);
		if (!data || !data.leftover_count) {
			show(box, false);
			return;
		}
		box.appendChild(el('strong', {
			text: data.leftover_count + ' example or missing detail' + (data.leftover_count === 1 ? '' : 's') + ' still on the page.'
		}));
		box.appendChild(document.createTextNode(' You can still apply it, but the page cannot be published until they are replaced.'));
		var lines = data.leftovers.map(function (item) {
			return item.marker + ' (' + item.count + '): ' + item.meaning;
		});
		listInto(box, lines);
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
		closeBtn.addEventListener('click', close);

		var tab = el('button', {
			type: 'button', role: 'tab', id: 'twd-sk-ed-tab-edit', className: 'twd-sk-ed__tab',
			'aria-selected': 'true', 'aria-controls': 'twd-sk-ed-panel-edit', text: 'Edit this page'
		});
		var tabs = el('div', { className: 'twd-sk-ed__tabs', role: 'tablist', 'aria-label': 'Editor sections' }, [tab]);
		tabs.addEventListener('keydown', onTabKeys);

		var panel = el('div', {
			role: 'tabpanel', id: 'twd-sk-ed-panel-edit', 'aria-labelledby': 'twd-sk-ed-tab-edit'
		});
		buildEditPanel(panel);

		var dialog = el('div', {
			className: 'twd-sk-ed__dialog', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'twd-sk-ed-title'
		}, [
			el('div', { className: 'twd-sk-ed__header' }, [title, closeBtn]),
			tabs,
			el('div', { className: 'twd-sk-ed__body' }, [panel])
		]);
		dialog.addEventListener('keydown', trapFocus);

		ui.overlay = el('div', { id: 'twd-sk-ed-overlay', className: 'twd-sk-ed__overlay', hidden: '' }, [dialog]);
		ui.overlay.addEventListener('mousedown', function (e) {
			if (e.target === ui.overlay) {
				close();
			}
		});
		ui.dialog = dialog;
		ui.title = title;
		root.appendChild(ui.overlay);
		state.built = true;
	}

	function buildEditPanel(panel) {
		/* Step 1: the prompt */
		ui.copyBtn = button('Copy prompt', 'primary', copyPrompt);
		ui.copyBtn.disabled = true;
		ui.copyMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.copyBox = el('textarea', { className: 'twd-sk-ed__textarea', readonly: '', 'aria-label': 'The prompt, selected so you can copy it', hidden: '' });
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: '1. Copy the prompt' }),
			el('p', { className: 'twd-sk-ed__help', text: 'Paste this into your AI chat first. It holds the rules for your page and the page as it is now. Then tell the AI what you want to change.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [ui.copyBtn]),
			ui.copyMsg,
			ui.copyBox
		]));

		/* Step 2: paste and preview */
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
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: '2. Paste the result and preview it' }),
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-paste', text: 'The page HTML from the AI' }),
			ui.paste,
			el('div', { className: 'twd-sk-ed__actions' }, [ui.previewBtn, ui.discardBtn]),
			ui.previewMsg,
			ui.banner,
			ui.report,
			ui.frame,
			el('p', {}, [ui.newTab])
		]));

		/* Step 3: apply */
		ui.note = el('input', { id: 'twd-sk-ed-note', className: 'twd-sk-ed__input', type: 'text', maxlength: '200', autocomplete: 'off' });
		ui.applyBtn = button('Apply and save as a new version', 'primary', doApply);
		ui.applyBtn.disabled = true;
		ui.applyMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.reloadBtn = button('Reload the page to see it', 'secondary', function () {
			window.location.reload();
		});
		show(ui.reloadBtn, false);
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: '3. Save it' }),
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-ed-note', text: 'Where did these facts come from?' }),
			el('p', { className: 'twd-sk-ed__help', text: 'For example: the therapist told me on a call, or their old website. This is saved with the version so you can see it later.' }),
			ui.note,
			el('div', { className: 'twd-sk-ed__actions' }, [ui.applyBtn, ui.reloadBtn]),
			ui.applyMsg
		]));

		/* Step 4: history */
		ui.undoBtn = button('Undo the last change', 'secondary', doUndo);
		ui.undoBtn.disabled = true;
		ui.historyMsg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });
		ui.versions = el('ol', { className: 'twd-sk-ed__versions' });
		panel.appendChild(el('section', { className: 'twd-sk-ed__step' }, [
			el('h3', { className: 'twd-sk-ed__step-title', text: '4. History (the last 10 versions)' }),
			el('p', { className: 'twd-sk-ed__help', text: 'Undo and Restore never delete anything. They save a new version, so you can always go back again.' }),
			el('div', { className: 'twd-sk-ed__actions' }, [ui.undoBtn]),
			ui.historyMsg,
			ui.versions
		]));
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
		message(ui.previewMsg, 'Preview discarded. Your pasted text is still in the box.', true);
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
		if (e.key === 'Escape' && !ui.overlay.hasAttribute('hidden')) {
			e.preventDefault();
			close();
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
		loadPrompt();
		loadVersions();
	}

	function close() {
		show(ui.overlay, false);
		openBtn.setAttribute('aria-expanded', 'false');
		openBtn.focus();
	}

	openBtn.addEventListener('click', open);
	show(openBtn, true);
})();
