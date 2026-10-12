/* TWD Site Kit: change words and pictures in place on the page (0.10.0). Vanilla JS, no build step.
 *
 * Loaded with the editor, only on a kit page the person can edit. The server numbers every piece of
 * text they may change (data-twd-sk-t), every picture (data-twd-sk-p) and every place marked for a
 * picture (data-twd-sk-s, 0.10.1) when it draws the page for them. Here those pieces become editable;
 * on Save only the changed pieces go back, by number, and the server puts them into the stored page,
 * cleans it and saves a new version (Undo goes back). A picture comes from the media library or is
 * uploaded from the computer to the media library first (0.10.1).
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
	var PICTURE_TYPES = /^image\/(jpeg|png|gif|webp|avif)$/;
	var S = { on: false, texts: [], pics: [], spots: [], orig: {}, origText: {}, origPic: {}, hrefs: {}, picks: {}, spotPicks: {}, range: null, linkTarget: null, target: null, saving: false, uploading: false };
	var ui = {};

	function texts() {
		return Array.prototype.slice.call(document.querySelectorAll('[data-twd-sk-t]'));
	}

	function pictures() {
		return Array.prototype.slice.call(document.querySelectorAll('img[data-twd-sk-p]'));
	}

	function spots() {
		return Array.prototype.slice.call(document.querySelectorAll('[data-twd-sk-s]'));
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
		return n + Object.keys(S.picks).length + Object.keys(S.spotPicks).length;
	}

	function status(text) {
		ui.status.textContent = text;
	}

	function counter() {
		var n = changedCount();
		status(n ? n + ' change' + (n === 1 ? '' : 's') + ' not saved yet.' : 'Click any words to change them. Click a picture to change it' + (S.spots.length ? ', or a dashed Add a picture box to put one there.' : '.'));
		ui.save.disabled = !n || S.saving || S.uploading;
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
		// Where a picture comes from: the media library, or this computer (it goes into the media library).
		ui.picHead = el('p', { className: 'twd-sk-ed__inlinehead', tabindex: '-1' });
		ui.library = ED.button('Choose from the media library', 'primary', fromLibrary);
		ui.upload = ED.button('Upload from this computer', 'secondary', function () {
			ui.file.click();
		});
		ui.describe = ED.button('Change the description', 'secondary', describeOnly);
		ui.file = el('input', { type: 'file', id: 'twd-sk-inline-file', accept: 'image/jpeg,image/png,image/gif,image/webp,image/avif', hidden: '', 'aria-label': 'Upload a picture from this computer' });
		ui.file.addEventListener('change', upload);
		ui.picPanel = el('div', { className: 'twd-sk-ed__inlinepanel', hidden: '' }, [
			ui.picHead, ui.library, ui.upload, ui.describe, ED.button('Close', 'secondary', closePanels), ui.file
		]);
		ui.confirm = el('div', { className: 'twd-sk-ed__inlinepanel', hidden: '' });
		ui.bar = el('div', { className: 'twd-sk-ed__inlinebar', role: 'toolbar', 'aria-label': 'Change words on the page', hidden: '' }, [
			ui.bold, ui.italic, ui.link, ui.status, ui.save, ui.cancel, ui.linkPanel, ui.altPanel, ui.picPanel, ui.confirm
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
		show(ui.picPanel, false);
		S.linkTarget = null;
	}

	function panelOpen() {
		return !ui.linkPanel.hasAttribute('hidden') || !ui.altPanel.hasAttribute('hidden') || !ui.picPanel.hasAttribute('hidden');
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
		show(ui.picPanel, false);
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

	function libraryReady() {
		return !!(window.wp && window.wp.media);
	}

	function uploadReady() {
		return !!(cfg.canUpload && cfg.mediaUrl && window.FormData && window.fetch);
	}

	function picOf(i) {
		return document.querySelector('img[data-twd-sk-p="' + i + '"]');
	}

	function spotOf(i) {
		return document.querySelector('[data-twd-sk-s="' + i + '"]');
	}

	function shownFor(i) {
		return document.querySelector('img[data-twd-sk-for="' + i + '"]');
	}

	// A picture, a place for one, or a picture just chosen for that place.
	function pictureTarget(node) {
		if (!node || !node.closest) {
			return null;
		}
		var n = node.closest('img[data-twd-sk-p], [data-twd-sk-s], img[data-twd-sk-for]');
		if (!n) {
			return null;
		}
		if (n.hasAttribute('data-twd-sk-p')) {
			return { kind: 'pic', i: n.getAttribute('data-twd-sk-p') };
		}
		return { kind: 'spot', i: n.getAttribute(n.hasAttribute('data-twd-sk-s') ? 'data-twd-sk-s' : 'data-twd-sk-for') };
	}

	function openPicture(target) {
		S.target = target;
		var filled = target.kind === 'pic' || !!S.spotPicks[target.i];
		ui.picHead.textContent = filled ? 'Change this picture' : 'Add a picture here';
		show(ui.library, libraryReady());
		show(ui.upload, uploadReady());
		show(ui.describe, filled);
		show(ui.linkPanel, false);
		show(ui.altPanel, false);
		show(ui.picPanel, true);
		if (!libraryReady() && !uploadReady()) {
			status('Pictures cannot be chosen here. Ask the site owner for permission to upload files, or use Edit with AI.');
		}
		(libraryReady() ? ui.library : (uploadReady() ? ui.upload : ui.picHead)).focus();
	}

	function fromLibrary() {
		var target = S.target;
		if (!target || !libraryReady()) {
			return;
		}
		var frame = window.wp.media({ title: target.kind === 'spot' ? 'Add a picture' : 'Choose a picture', library: { type: 'image' }, multiple: false, button: { text: 'Use this picture' } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			usePicture(target, { id: a.id, url: a.sizes && a.sizes.large ? a.sizes.large.url : a.url, alt: a.alt || '' });
		});
		frame.open();
	}

	function busy(on) {
		S.uploading = on;
		ui.library.disabled = on;
		ui.upload.disabled = on;
		ui.describe.disabled = on;
		counter();
	}

	// Upload the chosen file to the media library through WordPress itself, then use it.
	function upload() {
		var file = ui.file.files && ui.file.files[0];
		var target = S.target;
		ui.file.value = '';
		if (!file || !target || S.uploading) {
			return;
		}
		if (!PICTURE_TYPES.test(file.type || '')) {
			status('That file is not a picture the site can show. Choose a JPG, PNG, GIF or WebP file.');
			return;
		}
		var form = new FormData();
		form.append('file', file, file.name);
		busy(true);
		status('Uploading ' + file.name + '...');
		fetch(cfg.mediaUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce }, body: form }).then(function (res) {
			return res.text().then(function (text) {
				var data = null;
				try {
					data = text ? JSON.parse(text) : null;
				} catch (e) {
					data = null;
				}
				if (!res.ok || !data || !data.id) {
					var why = data && typeof data.message === 'string' && data.message ? data.message : 'The picture could not be uploaded (error ' + res.status + ').';
					throw new Error(why + ' You can also upload it in Media, then choose it from the media library.');
				}
				return data;
			});
		}, function () {
			throw new Error('Could not reach the site. Check your connection and try again.');
		}).then(function (data) {
			busy(false);
			var sizes = data.media_details && data.media_details.sizes;
			usePicture(target, { id: data.id, url: sizes && sizes.large ? sizes.large.source_url : data.source_url, alt: data.alt_text || '' });
		}, function (err) {
			busy(false);
			status(err.message);
		});
	}

	// Show the chosen picture where it will go, before it is saved.
	function usePicture(target, pic) {
		var i = target.i;
		if (target.kind === 'pic') {
			var img = picOf(i);
			if (!img) {
				return;
			}
			img.setAttribute('src', pic.url);
			img.removeAttribute('srcset');
			img.removeAttribute('sizes');
			img.setAttribute('alt', pic.alt);
			S.picks[i] = { i: parseInt(i, 10), id: pic.id, alt: pic.alt };
		} else {
			var spot = spotOf(i);
			if (!spot) {
				return;
			}
			var shown = shownFor(i);
			if (!shown) {
				shown = document.createElement('img');
				shown.setAttribute('data-twd-sk-for', i);
				shown.setAttribute('tabindex', '0');
				shown.setAttribute('role', 'button');
				shown.setAttribute('title', 'Click to change this picture');
				var look = lookOf(spot);
				if (look) {
					shown.className = look;
				}
				spot.parentNode.insertBefore(shown, spot.nextSibling);
			}
			shown.setAttribute('src', pic.url);
			shown.setAttribute('alt', pic.alt);
			spot.setAttribute('data-twd-sk-filled', '1');
			S.spotPicks[i] = { i: parseInt(i, 10), id: pic.id, alt: pic.alt };
		}
		S.target = target;
		openAlt(pic.alt);
		counter();
	}

	// The class the server gives a picture in each kind of box (TWD_SK_Inline::picture_class).
	var LOOKS = {
		'twd-sk-image-text__media': 'twd-sk-image-text__img',
		'twd-sk-hero__media': 'twd-sk-hero__img',
		'twd-sk-contact__media': 'twd-sk-contact__img',
		'twd-sk-pictures__item': 'twd-sk-pictures__img',
		'twd-sk-logos__item': 'twd-sk-logos__img',
		'twd-sk-pictures': 'twd-sk-pictures__img',
		'twd-sk-image-text': 'twd-sk-image-text__img',
		'twd-sk-logos': 'twd-sk-logos__img'
	};

	// The look the picture will have once saved, so it matches before it is saved.
	function lookOf(spot) {
		for (var n = spot; n && n.nodeType === 1; n = n.parentNode) {
			for (var c = 0; c < n.classList.length; c++) {
				if (LOOKS[n.classList[c]]) {
					return LOOKS[n.classList[c]];
				}
			}
			if (n.tagName === 'SECTION') {
				break;
			}
		}
		var section = spot.closest('section');
		var imgs = section ? section.querySelectorAll('img[class]') : [];
		for (var k = 0; k < imgs.length; k++) {
			var m = imgs[k].className.match(/(?:^|\s)(twd-sk-[a-z0-9-]+__img)(?:\s|$)/);
			if (m) {
				return m[1];
			}
		}
		return '';
	}

	function openAlt(value) {
		ui.altInput.value = value || '';
		show(ui.picPanel, false);
		show(ui.linkPanel, false);
		show(ui.altPanel, true);
		ui.altInput.focus();
	}

	function describeOnly() {
		var t = S.target;
		if (!t) {
			return;
		}
		if (t.kind === 'pic') {
			var img = picOf(t.i);
			openAlt(img ? img.getAttribute('alt') : '');
		} else if (S.spotPicks[t.i]) {
			openAlt(S.spotPicks[t.i].alt);
		}
	}

	function setAlt() {
		var t = S.target;
		var v = ui.altInput.value.trim();
		if (t && t.kind === 'pic') {
			var img = picOf(t.i);
			var was = S.origPic[t.i] && S.origPic[t.i].alt ? S.origPic[t.i].alt : '';
			if (S.picks[t.i]) {
				S.picks[t.i].alt = v;
			} else if (v !== was) {
				// The same picture with a new description (id 0 keeps the picture).
				S.picks[t.i] = { i: parseInt(t.i, 10), id: 0, alt: v };
			}
			if (S.picks[t.i] && S.picks[t.i].id === 0 && v === was) {
				delete S.picks[t.i];
			}
			if (img) {
				img.setAttribute('alt', v);
			}
		} else if (t && S.spotPicks[t.i]) {
			S.spotPicks[t.i].alt = v;
			var shown = shownFor(t.i);
			if (shown) {
				shown.setAttribute('alt', v);
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
			if (panelOpen()) {
				closePanels();
			} else if (ui.confirm.hasAttribute('hidden')) {
				cancel();
			}
			return;
		}
		// A picture or a place for one, reached with the Tab key.
		var target = (e.key === 'Enter' || e.key === ' ') && !editableOf(e.target) ? pictureTarget(e.target) : null;
		if (target) {
			e.preventDefault();
			openPicture(target);
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
		var target = pictureTarget(e.target);
		if (target) {
			e.preventDefault();
			e.stopPropagation();
			openPicture(target);
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
		S.spots = spots();
		if (!S.texts.length && !S.pics.length && !S.spots.length) {
			status('There is nothing to change on this page yet.');
			return;
		}
		S.on = true;
		S.orig = {};
		S.origText = {};
		S.origPic = {};
		S.hrefs = {};
		S.picks = {};
		S.spotPicks = {};
		S.target = null;
		S.texts.forEach(function (t) {
			var i = t.getAttribute('data-twd-sk-t');
			S.orig[i] = copyChildren(t);
			S.origText[i] = serialize(t);
			t.setAttribute('contenteditable', 'true');
			t.setAttribute('spellcheck', 'true');
		});
		S.pics.forEach(function (img) {
			S.origPic[img.getAttribute('data-twd-sk-p')] = { src: img.getAttribute('src'), alt: img.getAttribute('alt'), srcset: img.getAttribute('srcset') };
			img.setAttribute('title', 'Click to change this picture or its description');
			img.setAttribute('tabindex', '0');
			img.setAttribute('role', 'button');
		});
		S.spots.forEach(function (spot) {
			spot.setAttribute('title', 'Click to add a picture here');
			spot.setAttribute('tabindex', '0');
			spot.setAttribute('role', 'button');
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
			img.removeAttribute('tabindex');
			img.removeAttribute('role');
		});
		S.spots.forEach(function (spot) {
			spot.removeAttribute('title');
			spot.removeAttribute('tabindex');
			spot.removeAttribute('role');
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
		S.spots.forEach(function (spot) {
			var shown = shownFor(spot.getAttribute('data-twd-sk-s'));
			if (shown && shown.parentNode) {
				shown.parentNode.removeChild(shown);
			}
			spot.removeAttribute('data-twd-sk-filled');
		});
		S.picks = {};
		S.spotPicks = {};
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
		if (S.uploading) {
			status('Wait for the picture to finish uploading.');
			return;
		}
		var body = { base_version: cfg.currentVersion, texts: [], pictures: [], spots: [] };
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
		Object.keys(S.spotPicks).forEach(function (k) {
			body.spots.push(S.spotPicks[k]);
		});
		if (!body.texts.length && !body.pictures.length && !body.spots.length) {
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
		return texts().length + pictures().length + spots().length;
	};
})();
