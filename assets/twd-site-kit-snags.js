/* TWD Site Kit: the "Tell us" pop-up (0.11.0). Vanilla JS, no build step.
 *
 * Loaded only for signed-in users who can edit pages, only when the owner has switched Tell us on,
 * and never in safe mode. It never builds HTML from text (everything goes in with textContent) and
 * talks only to the plugin's own REST address, which the server gave it.
 * A person writes what they would like changed and sends it. Their note is kept on this site.
 * An administrator sees every note instead, can set a status, add an idea, delete a note and
 * copy the new notes as markdown. */
(function () {
	'use strict';

	var cfg = window.TWD_SK_SNAGS;
	var ED = window.TWD_SK_ED;
	var root = document.getElementById('twd-sk-tellus');
	var openBtn = document.getElementById('twd-sk-tellus-open');
	if (!cfg || !ED || !root || !openBtn) {
		return;
	}
	var el = ED.el;
	var clear = ED.clear;
	var show = ED.show;
	var button = ED.button;
	var message = ED.message;

	var ui = {};
	var S = { built: false, data: null, ideaOpen: false };

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
					var err = new Error(data && data.message ? data.message : 'Something went wrong. Please try again.');
					err.status = res.status;
					throw err;
				}
				return data;
			});
		}, function () {
			throw new Error('We could not reach the site. Check your connection and try again.');
		});
	}

	/* ---- the list ---- */

	function twoStepDelete(note, row) {
		var armed = false;
		var timer = 0;
		var del = button('Delete', 'secondary', function () {
			if (!armed) {
				armed = true;
				del.textContent = 'Sure? Delete';
				timer = window.setTimeout(function () {
					armed = false;
					del.textContent = 'Delete';
				}, 5000);
				return;
			}
			window.clearTimeout(timer);
			del.disabled = true;
			api('POST', '/snags/' + note.id + '/delete', {}).then(function () {
				if (row.parentNode) {
					row.parentNode.removeChild(row);
				}
				load();
			}, function (err) {
				del.disabled = false;
				armed = false;
				del.textContent = 'Delete';
				message(ui.msg, err.message, false);
			});
		});
		return del;
	}

	function statusSelect(note) {
		var sel = el('select', { className: 'twd-sk-ed__input twd-sk-tu__status', 'aria-label': 'Status of this note' });
		var names = (S.data && S.data.statuses) || {};
		Object.keys(names).forEach(function (key) {
			var o = el('option', { value: key, text: names[key] });
			if (key === note.status) {
				o.selected = true;
			}
			sel.appendChild(o);
		});
		sel.addEventListener('change', function () {
			api('POST', '/snags/' + note.id + '/status', { status: sel.value }).then(function () {
				message(ui.msg, 'Saved.', true);
				loadCount();
			}, function (err) {
				message(ui.msg, err.message, false);
			});
		});
		return sel;
	}

	function noteRow(note, admin) {
		var head = [el('strong', { text: (note.type === 'idea' ? 'Idea' : 'Snag') + ': ' + note.page_title })];
		var meta = note.date + (admin && note.from ? ' | ' + note.from : '') + (admin ? '' : ' | ' + note.status);
		var kids = [
			el('p', { className: 'twd-sk-tu__head' }, head),
			el('p', { className: 'twd-sk-ed__help', text: meta }),
			el('p', { className: 'twd-sk-tu__text', text: note.message })
		];
		var row = el('li', { className: 'twd-sk-tu__item' }, kids);
		var actions = el('div', { className: 'twd-sk-ed__actions' });
		if (admin) {
			actions.appendChild(statusSelect(note));
		}
		actions.appendChild(twoStepDelete(note, row));
		row.appendChild(actions);
		return row;
	}

	function paintList() {
		var data = S.data;
		clear(ui.list);
		var items = data.items || [];
		ui.listTitle.textContent = data.is_admin ? 'Snags and ideas' : 'Your earlier notes';
		if (!items.length) {
			ui.list.appendChild(el('li', { className: 'twd-sk-ed__help', text: data.is_admin ? 'Nothing here yet.' : 'You have not sent a note yet.' }));
			return;
		}
		items.forEach(function (n) {
			ui.list.appendChild(noteRow(n, data.is_admin));
		});
	}

	function loadCount() {
		if (!S.data || !S.data.is_admin) {
			return;
		}
		api('GET', '/snags/markdown').then(function (d) {
			ui.copy.textContent = 'Copy new notes as markdown (' + d.count + ')';
		}, function () {
			return null;
		});
	}

	function load() {
		return api('GET', '/snags').then(function (data) {
			S.data = data;
			paintList();
			ui.version.textContent = '\u2605 Version ' + (data.version || cfg.version);
			show(ui.client, !data.is_admin);
			show(ui.admin, !!data.is_admin);
			loadCount();
		}, function (err) {
			message(ui.msg, err.message, false);
		});
	}

	/* ---- sending ---- */

	function counter() {
		ui.count.textContent = ui.text.value.length + ' of ' + cfg.max;
		ui.send.disabled = ui.text.value.trim().length < 3;
	}

	function send() {
		ui.send.disabled = true;
		message(ui.msg, 'Sending...', true);
		api('POST', '/snags', { message: ui.text.value, page_id: cfg.pageId }).then(function (data) {
			ui.text.value = '';
			counter();
			message(ui.msg, data.message, true);
			load();
		}, function (err) {
			counter();
			message(ui.msg, err.message, false);
		});
	}

	function addIdea() {
		ui.ideaSave.disabled = true;
		api('POST', '/snags', { message: ui.idea.value, page_id: cfg.pageId, type: 'idea' }).then(function (data) {
			ui.idea.value = '';
			ui.ideaSave.disabled = false;
			message(ui.msg, data.message, true);
			load();
		}, function (err) {
			ui.ideaSave.disabled = false;
			message(ui.msg, err.message, false);
		});
	}

	/* ---- copy as markdown ---- */

	function copyMarkdown() {
		message(ui.msg, 'Getting the notes...', true);
		api('GET', '/snags/markdown').then(function (d) {
			var text = d.markdown;
			var fallback = function () {
				ui.mdbox.value = text;
				show(ui.mdbox, true);
				ui.mdbox.focus();
				ui.mdbox.select();
				message(ui.msg, 'Copy the selected text with Ctrl+C (or Cmd+C).', true);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () {
					show(ui.mdbox, false);
					message(ui.msg, d.count + (d.count === 1 ? ' new note copied.' : ' new notes copied.'), true);
				}, fallback);
			} else {
				fallback();
			}
		}, function (err) {
			message(ui.msg, err.message, false);
		});
	}

	/* ---- the pop-up ---- */

	function focusables() {
		return Array.prototype.filter.call(ui.dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'), function (n) {
			return !n.disabled && n.offsetParent !== null;
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
		ui.title = el('h2', { id: 'twd-sk-tu-title', className: 'twd-sk-ed__title', text: 'Tell us', tabindex: '-1' });
		var closeBtn = el('button', { type: 'button', className: 'twd-sk-ed__close', text: 'Close', 'aria-label': 'Close Tell us' });
		closeBtn.addEventListener('click', close);
		ui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', 'aria-live': 'polite', hidden: '' });

		// What a client sees.
		ui.text = el('textarea', { id: 'twd-sk-tu-text', className: 'twd-sk-ed__textarea', rows: '5', maxlength: String(cfg.max) });
		ui.text.addEventListener('input', counter);
		ui.count = el('span', { className: 'twd-sk-ed__help', 'aria-hidden': 'true' });
		ui.send = button('Send', 'primary', send);
		var pageLine = cfg.pageTitle ? 'This note is about: ' + cfg.pageTitle : 'This note is not about one page.';
		ui.client = el('div', { className: 'twd-sk-tu__client' }, [
			el('p', { className: 'twd-sk-ed__help', text: pageLine }),
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-tu-text', text: 'What would you like changed?' }),
			ui.text,
			ui.count,
			el('p', { className: 'twd-sk-ed__help', text: cfg.privacy }),
			el('div', { className: 'twd-sk-ed__actions' }, [ui.send])
		]);

		// What the site owner sees.
		ui.idea = el('textarea', { id: 'twd-sk-tu-idea', className: 'twd-sk-ed__textarea', rows: '3', maxlength: String(cfg.max) });
		ui.ideaSave = button('Add idea', 'secondary', addIdea);
		ui.copy = button('Copy new notes as markdown', 'primary', copyMarkdown);
		ui.mdbox = el('textarea', { className: 'twd-sk-ed__textarea', rows: '6', readonly: '', 'aria-label': 'The notes as markdown', hidden: '' });
		ui.admin = el('div', { className: 'twd-sk-tu__admin' }, [
			el('div', { className: 'twd-sk-ed__actions' }, [ui.copy]),
			ui.mdbox,
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-tu-idea', text: 'Add an idea (only you can see ideas)' }),
			ui.idea,
			el('div', { className: 'twd-sk-ed__actions' }, [ui.ideaSave])
		]);

		ui.listTitle = el('h3', { className: 'twd-sk-ed__step-title', text: 'Your earlier notes' });
		ui.list = el('ul', { className: 'twd-sk-tu__list' });
		ui.version = el('p', { className: 'twd-sk-tu__version' });

		var body = el('div', { className: 'twd-sk-ed__body' }, [
			el('p', { className: 'twd-sk-ed__help', text: 'What would you like changed?' }),
			ui.client,
			ui.admin,
			ui.msg,
			ui.listTitle,
			ui.list,
			ui.version
		]);
		ui.dialog = el('div', { className: 'twd-sk-ed__dialog', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'twd-sk-tu-title' }, [
			el('div', { className: 'twd-sk-ed__header' }, [ui.title, closeBtn]),
			body
		]);
		ui.dialog.addEventListener('keydown', trapFocus);
		ui.overlay = el('div', { id: 'twd-sk-tu-overlay', className: 'twd-sk-ed__overlay', hidden: '' }, [ui.dialog]);
		ui.overlay.addEventListener('mousedown', function (e) {
			if (e.target === ui.overlay) {
				close();
			}
		});
		root.appendChild(ui.overlay);
		show(ui.client, false);
		show(ui.admin, false);
		S.built = true;
	}

	function onKey(e) {
		if (e.key === 'Escape' && ui.overlay && !ui.overlay.hasAttribute('hidden')) {
			e.stopPropagation();
			close();
		}
	}

	function open() {
		if (!S.built) {
			build();
			document.addEventListener('keydown', onKey, true);
		}
		message(ui.msg, '', true);
		show(ui.overlay, true);
		openBtn.setAttribute('aria-expanded', 'true');
		counter();
		ui.title.focus();
		load();
	}

	function close() {
		show(ui.overlay, false);
		openBtn.setAttribute('aria-expanded', 'false');
		openBtn.focus();
	}

	openBtn.addEventListener('click', open);
	// Without a Visual editor button on this page, the Tell us button takes its place.
	if (!document.getElementById('twd-sk-ed-text')) {
		openBtn.className += ' twd-sk-ed__open--low';
	}
	show(openBtn, true);
})();
