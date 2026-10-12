/* TWD Site Kit: change words and pictures in place on the page (0.10.0). Vanilla JS, no build step.
 *
 * Loaded with the editor, only on a kit page the person can edit. The server numbers every piece of
 * text they may change (data-twd-sk-t) and every picture (data-twd-sk-p) when it draws the page for
 * them. Here those pieces become editable; on Save only the changed pieces go back, by number, and the
 * server puts them into the stored page, cleans it and saves a new version (Undo goes back).
 * It never builds HTML from text and never writes raw HTML into the page. */
(function () {
	'use strict';

	var ED = window.TWD_SK_ED;
	var root = document.getElementById('twd-sk-ed');
	if (!ED || !ED.cfg || !ED.cfg.inline || !root) {
		return;
	}
	var cfg = ED.cfg;
	var el = ED.el;
	var show = ED.show;
	var BLOCKS_WITH_BREAKS = { P: 1, LI: 1, DD: 1, FIGCAPTION: 1, BLOCKQUOTE: 1 };
	var S = { on: false, texts: [], pics: [], orig: {}, origText: {}, origPic: {}, hrefs: {}, picks: {}, range: null, linkTarget: null, picIndex: -1, saving: false };
	var ui = {};

	function texts() {
		return Array.prototype.slice.call(document.querySelectorAll('[data-twd-sk-t]'));
	}

	function pictures() {
		return Array.prototype.slice.call(document.querySelectorAll('img[data-twd-sk-p]'));
	}

	// The element's contents as markup, read one child at a time through the browser's own serialiser.
	function serialize(node) {
		var s = new XMLSerializer();
		var out = '';
		Array.prototype.forEach.call(node.childNodes, function (child) {
			out += s.serializeToString(child);
		});
		return out.replace(/ xmlns="[^"]*"/g, '');
	}

	function copyChildren(node) {
		var frag = document.createDocumentFragment();
		Array.prototype.forEach.call(node.childNodes, function (child) {
			frag.appendChild(child.cloneNode(true));
		});
		return frag;
	}

	function putBack(node, frag) {
		while (node.firstChild) {
			node.removeChild(node.firstChild);
		}
		node.appendChild(frag.cloneNode(true));
	}

	function editableOf(node) {
		var n = node && node.nodeType === 1 ? node : (node ? node.parentNode : null);
		return n && n.closest ? n.closest('[data-twd-sk-t]') : null;
	}

	function changedCount() {
		var n = 0;
		S.texts.forEach(function (t) {
			if (t.hasAttribute('data-twd-sk-changed')) {
				n++;
			}
		});
		return n + Object.keys(S.picks).length;
	}

	function status(text) {
		ui.status.textContent = text;
	}

	function counter() {
		var n = changedCount();
		status(n ? n + ' change' + (n === 1 ? '' : 's') + ' not saved yet.' : 'Click any words to change them. Click a picture to swap it.');
		ui.save.disabled = !n || S.saving;
	}

	function markChanged(t) {
		var i = t.getAttribute('data-twd-sk-t');
		if (serialize(t) !== S.origText[i] || S.hrefs[i] !== undefined) {
			t.setAttribute('data-twd-sk-changed', '1');
		} else {
			t.removeAttribute('data-twd-sk-changed');
		}
		counter();
	}

	/* ---- the toolbar ---- */

	function toolButton(label, title, onClick) {
		var b = el('button', { type: 'button', className: 'twd-sk-ed__tool', text: label, title: title, 'aria-label': title });
		// Keep the selection in the text when a tool is pressed.
		b.addEventListener('mousedown', function (e) {
			e.preventDefault();
		});
		b.addEventListener('click', onClick);
		return b;
	}

	function buildBar() {
		ui.status = el('span', { className: 'twd-sk-ed__inlinestatus', role: 'status', 'aria-live': 'polite' });
		ui.save = ED.button('Save changes', 'primary', save);
		ui.cancel = ED.button('Cancel', 'secondary', cancel);
		ui.bold = toolButton('B', 'Bold', function () {
			format('bold');
		});
		ui.italic = toolButton('I', 'Italic', function () {
			format('italic');
		});
		ui.link = toolButton('Link', 'Add or change a link', openLink);
		ui.linkInput = el('input', { type: 'text', id: 'twd-sk-inline-link', className: 'twd-sk-ed__input', placeholder: '/contact, or a full web address', autocomplete: 'off' });
		ui.linkPanel = el('div', { className: 'twd-sk-ed__inlinepanel', hidden: '' }, [
			el('label', { 'for': 'twd-sk-inline-link', text: 'Link address' }),
			ui.linkInput,
			ED.button('Set the link', 'primary', setLink),
			ED.button('Remove the link', 'secondary', removeLink),
			ED.button('Close', 'secondary', closePanels)
		]);
		ui.altInput = el('input', { type: 'text', id: 'twd-sk-inline-alt', className: 'twd-sk-ed__input', maxlength: '200', autocomplete: 'off' });
		ui.altPanel = el('div', { className: 'twd-sk-ed__inlinepanel', hidden: '' }, [
			el('label', { 'for': 'twd-sk-inline-alt', text: 'Describe the picture (for screen readers and search)' }),
			ui.altInput,
			ED.button('Use this description', 'primary', setAlt)
		]);
		ui.confirm = el('div', { className: 'twd-sk-ed__inlinepanel', hidden: '' });
		ui.bar = el('div', { className: 'twd-sk-ed__inlinebar', role: 'toolbar', 'aria-label': 'Change words on the page', hidden: '' }, [
			ui.bold, ui.italic, ui.link, ui.status, ui.save, ui.cancel, ui.linkPanel, ui.altPanel, ui.confirm
		]);
		root.appendChild(ui.bar);
	}

	function inEditable() {
		var sel = window.getSelection();
		return sel && sel.rangeCount ? editableOf(sel.anchorNode) : null;
	}

	function format(command) {
		var t = inEditable();
		if (!t) {
			status('Put the cursor in some words first.');
			return;
		}
		if (t.tagName === 'A') {
			status('Button words cannot be bold or italic.');
			return;
		}
		document.execCommand(command, false, null);
		markChanged(t);
	}

	function closePanels() {
		show(ui.linkPanel, false);
		show(ui.altPanel, false);
		S.linkTarget = null;
	}

	function openLink() {
		var sel = window.getSelection();
		var t = inEditable();
		if (!t) {
			status('Put the cursor in some words, or select some, first.');
			return;
		}
		S.range = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
		var i = t.getAttribute('data-twd-sk-t');
		var a = t.tagName === 'A' ? t : (sel.anchorNode && sel.anchorNode.parentNode && sel.anchorNode.parentNode.closest ? sel.anchorNode.parentNode.closest('a') : null);
		if (a && !t.contains(a) && a !== t) {
			a = null;
		}
		if (!a && (!S.range || S.range.collapsed)) {
			status('Select the words to link first, then press Link.');
			return;
		}
		S.linkTarget = { t: t, a: a, i: i };
		ui.linkInput.value = a ? (t === a && S.hrefs[i] !== undefined ? S.hrefs[i] : a.getAttribute('href') || '') : '';
		show(ui.altPanel, false);
		show(ui.linkPanel, true);
		ui.linkInput.focus();
	}

	function goodAddress(v) {
		return /^(\/(?!\/)|#|https?:\/\/|mailto:|tel:)/i.test(v);
	}

	function setLink() {
		var v = ui.linkInput.value.trim();
		var target = S.linkTarget;
		if (!target) {
			closePanels();
			return;
		}
		if (!goodAddress(v)) {
			status('Use a page on this site (starting with /), a full https address, mailto: or tel:.');
			ui.linkInput.focus();
			return;
		}
		if (target.a === target.t) {
			S.hrefs[target.i] = v;
		} else if (target.a) {
			target.a.setAttribute('href', v);
		} else if (S.range) {
			var sel = window.getSelection();
			sel.removeAllRanges();
			sel.addRange(S.range);
			document.execCommand('createLink', false, v);
		}
		closePanels();
		markChanged(target.t);
	}

	function removeLink() {
		var target = S.linkTarget;
		if (target && target.a && target.a !== target.t) {
			var a = target.a;
			while (a.firstChild) {
				a.parentNode.insertBefore(a.firstChild, a);
			}
			a.parentNode.removeChild(a);
			markChanged(target.t);
		} else if (target && target.a === target.t) {
			status('A button needs a link. Change its address instead.');
			return;
		}
		closePanels();
	}

	/* ---- pictures ---- */

	function choosePicture(img) {
		if (!(window.wp && window.wp.media)) {
			status('The media library is not available here. Use Edit with AI to change this picture.');
			return;
		}
		var frame = window.wp.media({ title: 'Choose a picture', library: { type: 'image' }, multiple: false, button: { text: 'Use this picture' } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var i = img.getAttribute('data-twd-sk-p');
			var url = a.sizes && a.sizes.large ? a.sizes.large.url : a.url;
			img.setAttribute('src', url);
			img.removeAttribute('srcset');
			img.removeAttribute('sizes');
			S.picks[i] = { i: parseInt(i, 10), id: a.id, alt: a.alt || '' };
			S.picIndex = i;
			ui.altInput.value = a.alt || '';
			show(ui.linkPanel, false);
			show(ui.altPanel, true);
			ui.altInput.focus();
			counter();
		});
		frame.open();
	}

	function setAlt() {
		var i = S.picIndex;
		if (S.picks[i]) {
			S.picks[i].alt = ui.altInput.value.trim();
			var img = document.querySelector('img[data-twd-sk-p="' + i + '"]');
			if (img) {
				img.setAttribute('alt', S.picks[i].alt);
			}
		}
		show(ui.altPanel, false);
		counter();
	}

	/* ---- typing ---- */

	function onKey(e) {
		if (!S.on) {
			return;
		}
		// Escape anywhere while editing: close an open panel first, otherwise leave (asking if anything changed).
		if (e.key === 'Escape') {
			e.preventDefault();
			if (!ui.linkPanel.hasAttribute('hidden') || !ui.altPanel.hasAttribute('hidden')) {
				closePanels();
			} else if (ui.confirm.hasAttribute('hidden')) {
				cancel();
			}
			return;
		}
		var t = editableOf(e.target);
		if (!t) {
			return;
		}
		if (e.key === 'Enter') {
			e.preventDefault();
			if (e.shiftKey && BLOCKS_WITH_BREAKS[t.tagName]) {
				document.execCommand('insertLineBreak', false, null);
			}
		} else if (e.key === ' ' && t.tagName === 'SUMMARY') {
			// A space would open or close the question instead of typing one.
			e.preventDefault();
			document.execCommand('insertText', false, ' ');
		}
	}

	function onPaste(e) {
		var t = editableOf(e.target);
		if (!t) {
			return;
		}
		e.preventDefault();
		var text = (e.clipboardData || window.clipboardData).getData('text/plain') || '';
		document.execCommand('insertText', false, text.replace(/\s*[\r\n]+\s*/g, ' '));
	}

	function onInput(e) {
		var t = editableOf(e.target);
		if (t) {
			markChanged(t);
		}
	}

	// While editing, links on the page do not go anywhere, and a click on a picture swaps it.
	function onClick(e) {
		if (!S.on || (ui.bar && ui.bar.contains(e.target))) {
			return;
		}
		var img = e.target.closest ? e.target.closest('img[data-twd-sk-p]') : null;
		if (img) {
			e.preventDefault();
			e.stopPropagation();
			choosePicture(img);
			return;
		}
		var a = e.target.closest ? e.target.closest('a') : null;
		if (a && (editableOf(a) || a.closest('.twd-sk-page'))) {
			e.preventDefault();
		}
	}

	/* ---- starting, saving, stopping ---- */

	function start() {
		if (S.on) {
			return;
		}
		S.texts = texts();
		S.pics = pictures();
		if (!S.texts.length && !S.pics.length) {
			status('There is nothing to change on this page yet.');
			return;
		}
		S.on = true;
		S.orig = {};
		S.origText = {};
		S.origPic = {};
		S.hrefs = {};
		S.picks = {};
		S.texts.forEach(function (t) {
			var i = t.getAttribute('data-twd-sk-t');
			S.orig[i] = copyChildren(t);
			S.origText[i] = serialize(t);
			S.origPic[i] = null;
			t.setAttribute('contenteditable', 'true');
			t.setAttribute('spellcheck', 'true');
		});
		S.pics.forEach(function (img) {
			S.origPic[img.getAttribute('data-twd-sk-p')] = { src: img.getAttribute('src'), alt: img.getAttribute('alt'), srcset: img.getAttribute('srcset') };
			img.setAttribute('title', 'Click to choose another picture');
		});
		document.body.classList.add('twd-sk-inline-on');
		show(ui.open, false);
		show(document.getElementById('twd-sk-ed-open'), false);
		show(ui.bar, true);
		closePanels();
		show(ui.confirm, false);
		counter();
		if (S.texts[0]) {
			S.texts[0].focus();
		}
	}

	function stop() {
		S.on = false;
		S.texts.forEach(function (t) {
			t.removeAttribute('contenteditable');
			t.removeAttribute('spellcheck');
			t.removeAttribute('data-twd-sk-changed');
		});
		S.pics.forEach(function (img) {
			img.removeAttribute('title');
		});
		document.body.classList.remove('twd-sk-inline-on');
		show(ui.bar, false);
		show(ui.open, true);
		show(document.getElementById('twd-sk-ed-open'), true);
	}

	function throwAway() {
		S.texts.forEach(function (t) {
			var i = t.getAttribute('data-twd-sk-t');
			if (serialize(t) !== S.origText[i]) {
				putBack(t, S.orig[i]);
			}
		});
		S.pics.forEach(function (img) {
			var o = S.origPic[img.getAttribute('data-twd-sk-p')];
			if (o) {
				img.setAttribute('src', o.src);
				img.setAttribute('alt', o.alt || '');
				if (o.srcset) {
					img.setAttribute('srcset', o.srcset);
				}
			}
		});
		stop();
	}

	function cancel() {
		var n = changedCount();
		if (!n) {
			stop();
			return;
		}
		ED.clear(ui.confirm);
		ui.confirm.appendChild(el('span', { text: 'Throw away ' + n + ' change' + (n === 1 ? '' : 's') + '? ' }));
		var yes = ED.button('Throw them away', 'primary', function () {
			show(ui.confirm, false);
			throwAway();
		});
		var no = ED.button('Keep editing', 'secondary', function () {
			show(ui.confirm, false);
		});
		ui.confirm.appendChild(yes);
		ui.confirm.appendChild(no);
		show(ui.confirm, true);
		yes.focus();
	}

	function save() {
		var body = { base_version: cfg.currentVersion, texts: [], pictures: [] };
		S.texts.forEach(function (t) {
			var i = t.getAttribute('data-twd-sk-t');
			if (t.hasAttribute('data-twd-sk-changed')) {
				var item = { i: parseInt(i, 10), html: serialize(t) };
				if (S.hrefs[i] !== undefined) {
					item.href = S.hrefs[i];
				}
				body.texts.push(item);
			}
		});
		Object.keys(S.picks).forEach(function (k) {
			body.pictures.push(S.picks[k]);
		});
		if (!body.texts.length && !body.pictures.length) {
			status('Nothing has changed yet.');
			return;
		}
		S.saving = true;
		ui.save.disabled = true;
		status('Saving...');
		ED.api('POST', '/pages/' + cfg.pageId + '/inline', body).then(function (data) {
			status('Saved as version ' + data.version + '. Reloading so you see the page as visitors will.');
			window.setTimeout(function () {
				window.location.reload();
			}, 900);
		}, function (err) {
			S.saving = false;
			ui.save.disabled = false;
			status(err.status === 409 ? err.message + ' Your changes are still on screen: copy any you need, then reload.' : err.message);
		});
	}

	/* ---- set up ---- */

	buildBar();
	ui.open = el('button', { type: 'button', id: 'twd-sk-ed-text', className: 'twd-sk-ed__open twd-sk-ed__open--text', text: 'Edit text' });
	ui.open.addEventListener('click', start);
	root.appendChild(ui.open);
	document.addEventListener('keydown', onKey, true);
	document.addEventListener('paste', onPaste, true);
	document.addEventListener('input', onInput, true);
	document.addEventListener('click', onClick, true);
	// The Edit with AI pop-up offers the same, from its Edit this page tab.
	ED.startInline = function () {
		start();
	};
	ED.inlineCount = function () {
		return texts().length + pictures().length;
	};
})();
