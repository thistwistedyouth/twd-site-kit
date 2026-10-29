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
	var S = { on: false, texts: [], pics: [], spots: [], orig: {}, origText: {}, origPic: {}, hrefs: {}, picks: {}, spotPicks: {}, removes: {}, removeOrder: [], looks: {}, origLook: {}, lookSec: null, last: null, range: null, linkTarget: null, target: null, saving: false, uploading: false };
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

	function restoreChildren(node, frag) {
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
		return n + Object.keys(S.picks).length + Object.keys(S.spotPicks).length + Object.keys(S.removes).length + Object.keys(S.looks).length;
	}

	/* ---- removing a section, an item or a button (0.14.0) ----
	   Hidden on screen until Save; Put back undoes it here, and every save is a version the history can restore. */

	function removableOf(node) {
		var n = node && node.nodeType === 1 ? node : (node ? node.parentNode : null);
		var r = n && n.closest ? n.closest('[data-twd-sk-r]') : null;
		return r && !r.hasAttribute('hidden') ? r : null;
	}

	function kindOf(node) {
		if (node.tagName === 'SECTION') {
			return 'section';
		}
		if (node.tagName === 'A' && node.classList.contains('twd-sk-btn')) {
			return 'button';
		}
		return 'item';
	}

	function removeThis() {
		var node = removableOf(S.last);
		if (!node) {
			status('Click the list entry, card, button or section you want to remove, then press Remove.');
			return;
		}
		var key = node.getAttribute('data-twd-sk-r');
		node.setAttribute('hidden', '');
		S.removes[key] = node;
		S.removeOrder.push(key);
		S.last = null;
		counter();
		status('Removed a ' + kindOf(node) + '. Put back undoes it; Save makes it final (every save is a version you can go back to).');
		ui.putBack.disabled = false;
	}

	function putBack() {
		var key = S.removeOrder.pop();
		if (key === undefined) {
			status('Nothing to put back: Put back only returns things you removed with Remove. Use Undo to step back a move, an added section or the last save.');
			return;
		}
		var node = S.removes[key];
		delete S.removes[key];
		if (node) {
			node.removeAttribute('hidden');
		}
		counter();
		status('Put back.');
		ui.putBack.disabled = !S.removeOrder.length;
	}

	function status(text) {
		ui.status.textContent = text;
	}

	function counter() {
		var n = changedCount();
		status(n ? n + ' change' + (n === 1 ? '' : 's') + ' not saved yet.' : 'Click any words to change them. Click a picture to change it' + (S.spots.length ? ', or a dashed Add a picture box to put one there.' : '.') + ' Click something, then Remove, to take it out. Click inside a section, then Look, to change its layout or options, or to move it or add a new one after it.');
		ui.save.disabled = !n || S.saving || S.uploading;
		adminBar();
	}

	/* ---- the WordPress admin bar (0.10.2): Visual editor, then Save changes and Cancel while editing ---- */

	function adminItem(list, id, onClick) {
		var a = el('a', { className: 'ab-item', href: '#', role: 'button' });
		a.addEventListener('click', function (e) {
			e.preventDefault();
			onClick();
		});
		var li = el('li', { id: 'wp-admin-bar-' + id }, [a]);
		list.appendChild(li);
		return { li: li, a: a };
	}

	function buildAdminBar() {
		var list = document.getElementById('wp-admin-bar-root-default');
		if (!list) {
			return;
		}
		ui.abMain = adminItem(list, 'twd-sk-text', function () {
			if (S.on) {
				save();
			} else {
				start();
			}
		});
		ui.abCancel = adminItem(list, 'twd-sk-text-cancel', function () {
			if (S.on) {
				cancel();
			}
		});
		// The editing tools switch (0.14.1) hides this item too, unless an editing session is open.
		if (ED.onToolsChange) {
			ED.onToolsChange(adminBar);
		}
		adminBar();
	}

	function adminBar() {
		if (!ui.abMain) {
			return;
		}
		var n = S.on ? changedCount() : 0;
		ui.abMain.a.textContent = S.on ? (n ? 'Save changes (' + n + ')' : 'Save changes') : 'Visual editor';
		ui.abMain.a.setAttribute('aria-disabled', S.on && (!n || S.saving || S.uploading) ? 'true' : 'false');
		ui.abCancel.a.textContent = 'Cancel';
		ui.abCancel.li.style.display = S.on ? '' : 'none';
		// Not editing: the Your Edits menu offers Visual editor; this item shows only while a session is open.
		ui.abMain.li.style.display = S.on ? '' : 'none';
		if (ED.redrawMenu) {
			ED.redrawMenu();
		}
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

	/* ---- the look of a section (0.15.0): its layout and options ----
	   The server numbers each section (data-twd-sk-sec) and says which layouts it can move to (data-twd-sk-lay:
	   only layouts that use the same pieces, so every word and picture keeps its place). The choices come from
	   the registry (cfg.look). Changing one sets the section's classes here so it shows at once; Save sends the
	   wanted state by section number and the server works the classes out again and checks them. */

	// The shared options (space above and below, background) come once in cfg.look.shared and are added to every component here.
	var LOOK = null;
	function lookConfig() {
		if (LOOK) {
			return LOOK;
		}
		var src = (cfg.look && cfg.look.components) || {};
		var shared = (cfg.look && cfg.look.shared) || [];
		LOOK = {};
		Object.keys(src).forEach(function (id) {
			LOOK[id] = { label: src[id].label, layouts: src[id].layouts, options: (src[id].options || []).concat(shared) };
		});
		return LOOK;
	}

	function classesOf(sec) {
		return sec.className.split(/\s+/).filter(function (c) {
			return c;
		});
	}

	function componentOfSection(sec) {
		var list = classesOf(sec);
		for (var k = 0; k < list.length; k++) {
			var c = list[k];
			if (c.indexOf('twd-sk-') === 0 && c.indexOf('--') < 0 && c.indexOf('__') < 0 && c.indexOf('twd-sk-tone-') !== 0) {
				return c.slice(7).replace(/-/g, '_');
			}
		}
		return '';
	}

	function lookMet(o, classes) {
		if (o.requires && classes.indexOf(o.requires) < 0) {
			return false;
		}
		if (o.requires_any && !o.requires_any.some(function (c) {
			return classes.indexOf(c) > -1;
		})) {
			return false;
		}
		return true;
	}

	function layoutOf(info, classes) {
		var std = cfg.look.standard;
		var found = null;
		info.layouts.forEach(function (l) {
			if (found === null && l[0] !== std && classes.indexOf(l[0]) > -1) {
				found = l[0];
			}
		});
		if (found !== null) {
			return found;
		}
		return info.layouts.some(function (l) {
			return l[0] === std;
		}) ? std : null;
	}

	// The same rules as TWD_SK_Looks::classes_for() on the server.
	function lookClasses(info, classes, layout, values) {
		var std = cfg.look.standard;
		var next = classes.slice();
		var names = info.layouts.map(function (l) {
			return l[0] === std ? '' : l[0];
		});
		if (layout !== null && layout !== layoutOf(info, classes)) {
			next = next.filter(function (c) {
				return !c || names.indexOf(c) < 0;
			});
			if (layout !== std) {
				next.splice(1, 0, layout);
			}
		}
		info.options.forEach(function (o) {
			if (!Object.prototype.hasOwnProperty.call(values, o.key)) {
				return;
			}
			var v = values[o.key];
			if (o.kind === 'toggle') {
				next = next.filter(function (c) {
					return c !== o.class;
				});
				if (v && lookMet(o, next)) {
					next.push(o.class);
				}
				return;
			}
			if (!lookMet(o, next)) {
				return;
			}
			var all = o.choices.map(function (ch) {
				return ch[0];
			});
			next = next.filter(function (c) {
				return all.indexOf(c) < 0;
			});
			if (v !== std) {
				next.push(v);
			}
		});
		info.options.forEach(function (o) {
			if (o.requires && next.indexOf(o.requires) < 0) {
				var drop = o.kind === 'toggle' ? [o.class] : o.choices.map(function (ch) {
					return ch[0];
				});
				next = next.filter(function (c) {
					return drop.indexOf(c) < 0;
				});
			}
		});
		return next;
	}

	// What the section looks like now, as the wanted state the server understands.
	function lookState(info, classes) {
		var std = cfg.look.standard;
		var state = { options: {} };
		var layout = layoutOf(info, classes);
		if (layout !== null) {
			state.layout = layout;
		}
		info.options.forEach(function (o) {
			if (!lookMet(o, classes)) {
				return;
			}
			if (o.kind === 'toggle') {
				state.options[o.key] = classes.indexOf(o.class) > -1;
				return;
			}
			var chosen = std;
			o.choices.forEach(function (ch) {
				if (ch[0] !== std && classes.indexOf(ch[0]) > -1) {
					chosen = ch[0];
				}
			});
			state.options[o.key] = chosen;
		});
		return state;
	}

	function changeLook(layout, values) {
		var sec = S.lookSec;
		var info = lookConfig()[componentOfSection(sec)];
		var i = sec.getAttribute('data-twd-sk-sec');
		sec.className = lookClasses(info, classesOf(sec), layout, values).join(' ');
		if (sec.className === S.origLook[i]) {
			delete S.looks[i];
			sec.removeAttribute('data-twd-sk-looked');
		} else {
			var state = lookState(info, classesOf(sec));
			state.i = parseInt(i, 10);
			S.looks[i] = state;
			sec.setAttribute('data-twd-sk-looked', '1');
		}
		counter();
		drawLook();
	}

	function lookSelect(id, label, choices, current, onPick, help) {
		var select = el('select', { id: id, className: 'twd-sk-ed__input' });
		choices.forEach(function (ch) {
			select.appendChild(el('option', { value: ch[0], text: ch[1] }));
		});
		select.value = current;
		select.addEventListener('change', function () {
			onPick(select.value);
		});
		var kids = [el('label', { className: 'twd-sk-ed__label', 'for': id, text: label })];
		if (help) {
			kids.push(el('p', { className: 'twd-sk-ed__help', text: help }));
		}
		kids.push(select);
		return el('div', { className: 'twd-sk-ed__field' }, kids);
	}

	function drawLook() {
		var sec = S.lookSec;
		var info = lookConfig()[componentOfSection(sec)];
		var classes = classesOf(sec);
		var std = cfg.look.standard;
		ED.clear(ui.lookBody);
		ui.lookHead.textContent = 'Look of this section: ' + info.label;
		var can = (sec.getAttribute('data-twd-sk-lay') || '').split(' ').filter(function (c) {
			return c;
		});
		if (can.length > 1) {
			var current = layoutOf(info, classes);
			ui.lookBody.appendChild(lookSelect('twd-sk-look-layout', 'Layout', info.layouts.filter(function (l) {
				return can.indexOf(l[0]) > -1;
			}), current === null ? std : current, function (value) {
				changeLook(value, {});
			}, 'Your words and pictures stay exactly as they are.'));
		}
		info.options.forEach(function (o) {
			if (!lookMet(o, classes)) {
				return;
			}
			var id = 'twd-sk-look-' + o.key;
			if (o.kind === 'toggle') {
				var box = el('input', { id: id, type: 'checkbox' });
				box.checked = classes.indexOf(o.class) > -1;
				box.addEventListener('change', function () {
					var v = {};
					v[o.key] = box.checked;
					changeLook(null, v);
				});
				var kids = [box, el('span', { text: o.label })];
				ui.lookBody.appendChild(el('label', { className: 'twd-sk-ed__check', 'for': id }, kids));
				if (o.help) {
					ui.lookBody.appendChild(el('p', { className: 'twd-sk-ed__help', text: o.help }));
				}
				return;
			}
			var now = lookState(info, classes).options[o.key];
			ui.lookBody.appendChild(lookSelect(id, o.label, o.choices, now, function (value) {
				var v = {};
				v[o.key] = value;
				changeLook(null, v);
			}, o.help));
		});
		if (!ui.lookBody.firstChild) {
			ui.lookBody.appendChild(el('p', { className: 'twd-sk-ed__help', text: 'This section has nothing to choose here.' }));
		}
	}

	function openLook() {
		var sec = currentSection();
		if (!sec || sec.tagName !== 'SECTION') {
			status('Click inside the section you want to change, then press Look.');
			return;
		}
		var info = lookConfig()[componentOfSection(sec)];
		var can = (sec.getAttribute('data-twd-sk-lay') || '').split(' ').filter(function (c) {
			return c;
		});
		var classes = classesOf(sec);
		var hasOption = info && info.options.some(function (o) {
			return lookMet(o, classes);
		});
		if (!info || (can.length < 2 && !hasOption)) {
			status('This section has nothing to choose here.');
			return;
		}
		var i = sec.getAttribute('data-twd-sk-sec');
		if (S.origLook[i] === undefined) {
			S.origLook[i] = sec.className;
		}
		closePanels();
		S.lookSec = sec;
		drawLook();
		show(ui.lookPanel, true);
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
		ui.save.className += ' twd-sk-ed__inlinesave';
		ui.cancel = ED.button('Cancel', 'secondary', cancel);
		ui.bold = toolButton('B', 'Bold', function () {
			format('bold');
		});
		ui.italic = toolButton('I', 'Italic', function () {
			format('italic');
		});
		ui.link = toolButton('Link', 'Add or change a link', openLink);
		ui.remove = toolButton('Remove', 'Remove this list entry, card, button or section', removeThis);
		ui.putBack = toolButton('Put back', 'Put back the last thing removed', putBack);
		ui.putBack.disabled = true;
		ui.look = toolButton('Look', 'Change the layout or options of this section', openLook);
		ui.lookHead = el('p', { className: 'twd-sk-ed__inlinehead', tabindex: '-1' });
		ui.lookBody = el('div', { className: 'twd-sk-ed__lookbody' });
		ui.lookPanel = el('div', { className: 'twd-sk-ed__inlinepanel', hidden: '' }, [
			ui.lookHead, ui.lookBody, ED.button('Close', 'secondary', closePanels)
		]);
		// Add a section and move one, no AI (0.14.1). Add needs the list of sections the server gave this page.
		ui.addSec = toolButton('Add section', 'Add a new section to the page', openAdd);
		ui.moveUp = toolButton('Move up', 'Move this section up the page', function () {
			moveSection('up');
		});
		ui.moveDown = toolButton('Move down', 'Move this section down the page', function () {
			moveSection('down');
		});
		ui.undo = toolButton('Undo', 'Step back the last saved change: a move, an added section, or the last save', function () {
			undoLast(false);
		});
		// A hero (one main heading per page) and an embed (it needs a saved embed, chosen in the Site tab) are not offered here.
		var kinds = Object.keys(cfg.components || {}).filter(function (id) {
			return id !== 'hero' && id !== 'embed';
		});
		ui.addKind = el('select', { id: 'twd-sk-inline-addkind', className: 'twd-sk-ed__select' });
		kinds.forEach(function (id) {
			ui.addKind.appendChild(el('option', { value: id, text: cfg.components[id].label }));
		});
		ui.addKind.addEventListener('change', fillLayouts);
		ui.addLayout = el('select', { id: 'twd-sk-inline-addlayout', className: 'twd-sk-ed__select' });
		ui.addDesc = el('p', { className: 'twd-sk-ed__inlinehead' });
		ui.addWhere = el('p', { className: 'twd-sk-ed__inlinehead' });
		ui.addPanel = el('div', { className: 'twd-sk-ed__inlinepanel', hidden: '' }, [
			ui.addWhere,
			el('label', { 'for': 'twd-sk-inline-addkind', text: 'What kind of section' }),
			ui.addKind,
			el('label', { 'for': 'twd-sk-inline-addlayout', text: 'Which layout' }),
			ui.addLayout,
			ui.addDesc,
			ED.button('Add it', 'primary', addIt),
			ED.button('Close', 'secondary', closePanels)
		]);
		fillLayouts();
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
			el('div', { className: 'twd-sk-ed__inlinetools' }, [ui.bold, ui.italic, ui.link, ui.look, ui.remove, ui.putBack]),
			el('div', { className: 'twd-sk-ed__inlinetools', role: 'group', 'aria-label': 'Sections' }, kinds.length ? [ui.addSec, ui.moveUp, ui.moveDown, ui.undo] : [ui.moveUp, ui.moveDown, ui.undo]),
			el('div', { className: 'twd-sk-ed__inlineactions' }, [ui.save, ui.cancel]),
			ui.status, ui.linkPanel, ui.altPanel, ui.picPanel, ui.lookPanel, ui.addPanel, ui.confirm
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
		show(ui.lookPanel, false);
		show(ui.addPanel, false);
		S.linkTarget = null;
	}

	function panelOpen() {
		return !ui.linkPanel.hasAttribute('hidden') || !ui.altPanel.hasAttribute('hidden') || !ui.picPanel.hasAttribute('hidden') || !ui.lookPanel.hasAttribute('hidden') || !ui.addPanel.hasAttribute('hidden');
	}

	function openLink() {
		var sel = window.getSelection();
		var t = inEditable();
		if (!t) {
			status('Put the cursor in some words, or select some, first.');
			return;
		}
		if (t.classList.contains('twd-sk-faq__tablink')) {
			status('A group tab always opens its own group, so its address cannot be changed here.');
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

	// A question group's tab and its heading carry the same words, so changing one changes the other (0.15.0).
	function groupPair(t) {
		var link = null;
		var name = null;
		if (t.classList.contains('twd-sk-faq__tablink')) {
			link = t;
			var id = (t.getAttribute('href') || '').replace(/^#/, '');
			var group = id ? document.getElementById(id) : null;
			name = group ? group.querySelector('.twd-sk-faq__groupname[data-twd-sk-t]') : null;
		} else if (t.classList.contains('twd-sk-faq__groupname')) {
			name = t;
			var g = t.closest('.twd-sk-faq__group');
			link = g && g.id ? document.querySelector('.twd-sk-faq__tablink[href="#' + g.id + '"][data-twd-sk-t]') : null;
		}
		return link && name ? { link: link, name: name } : null;
	}

	function syncGroup(t) {
		var pair = groupPair(t);
		if (!pair) {
			return;
		}
		var to = t === pair.link ? pair.name : pair.link;
		if (to.textContent !== t.textContent) {
			to.textContent = t.textContent;
			markChanged(to);
		}
	}

	function onInput(e) {
		var t = editableOf(e.target);
		if (t) {
			markChanged(t);
			syncGroup(t);
		}
	}

	// While editing, links on the page do not go anywhere, and a click on a picture swaps it.
	function onClick(e) {
		if (!S.on || (ui.bar && ui.bar.contains(e.target))) {
			return;
		}
		S.last = e.target;
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

	/* ---- adding and moving a section (0.14.1) ----
	   Each is saved at once as its own version (so the history can undo it), then the page reloads straight back into
	   this editor with the section in view. Changes not yet saved are never carried across a reload, so the buttons ask
	   for them to be saved or thrown away first. The server numbers the sections (data-twd-sk-sec) and checks the rules:
	   the section with the main heading stays at the top. */

	function sectionOf(node) {
		var n = node && node.nodeType === 1 ? node : (node ? node.parentNode : null);
		return n && n.closest ? n.closest('[data-twd-sk-sec]') : null;
	}

	function currentSection() {
		var sel = window.getSelection ? window.getSelection() : null;
		return sectionOf(S.last) || sectionOf(sel && sel.rangeCount ? sel.anchorNode : null);
	}

	function sectionName(sec) {
		var h = sec.querySelector('h1, h2, h3');
		var text = h ? h.textContent.replace(/\s+/g, ' ').trim() : '';
		if (!text) {
			return 'this section';
		}
		return text.length > 40 ? text.slice(0, 37) + '...' : text;
	}

	// True (with a message) when the page cannot be reloaded without losing something.
	function notNow() {
		var n = changedCount();
		if (S.saving || S.uploading) {
			status('Wait a moment: something is still saving or uploading.');
			return true;
		}
		if (n) {
			status('Save or cancel your ' + n + ' change' + (n === 1 ? '' : 's') + ' first: adding or moving a section reloads the page.');
			return true;
		}
		return false;
	}

	// The address's own query string without the two this editor adds ('' or '?a=1&b=2').
	function plainSearch() {
		var keep = window.location.search.replace(/^\?/, '').split('&').filter(function (pair) {
			return pair !== '' && !/^twd_sk_(edit|at)=/.test(pair);
		});
		return keep.length ? '?' + keep.join('&') : '';
	}

	function reloadEditing(at) {
		var search = plainSearch();
		var url = window.location.pathname + search + (search ? '&' : '?') + 'twd_sk_edit=1&twd_sk_at=' + parseInt(at, 10);
		window.setTimeout(function () {
			window.location.assign(url);
		}, 600);
	}

	function structure(body, working, done) {
		S.saving = true;
		body.base_version = cfg.currentVersion;
		status(working);
		ED.api('POST', '/pages/' + cfg.pageId + '/structure', body).then(function (data) {
			status(done + ' Saved as version ' + data.version + '. Reloading so you see it.');
			reloadEditing(data.at);
		}, function (err) {
			S.saving = false;
			status(err.message);
		});
	}

	function moveSection(dir) {
		if (notNow()) {
			return;
		}
		var sec = currentSection();
		if (!sec) {
			status('Click inside the section you want to move first.');
			return;
		}
		structure({ op: 'move', index: parseInt(sec.getAttribute('data-twd-sk-sec'), 10), dir: dir }, 'Moving it ' + dir + '...', 'Moved ' + dir + '.');
	}

	/* Undo (0.15.0): step back the last SAVED change (a move, an added section, a save) through the page history,
	 * then reload into the editor at the same section. Unsaved edits block it, like Add and Move: use Cancel for those.
	 * The blank starter is refused with a plain question first. */
	function undoLast(allow) {
		if (notNow()) {
			return;
		}
		var sec = currentSection();
		var at = sec ? parseInt(sec.getAttribute('data-twd-sk-sec'), 10) : 0;
		S.saving = true;
		status('Undoing the last saved change...');
		ED.api('POST', '/pages/' + cfg.pageId + '/undo', { base_version: cfg.currentVersion, allow_placeholders: !!allow }).then(function (data) {
			status('Undid: ' + (data.undid || 'the last change') + '. The page now shows version ' + data.target + '. Reloading so you see it.');
			reloadEditing(at);
		}, function (err) {
			S.saving = false;
			if (err.status === 409 && err.code === 'twd_sk_placeholders') {
				ED.clear(ui.confirm);
				ui.confirm.appendChild(el('span', { text: err.message + ' ' }));
				var yes = ED.button('Go back to it anyway', 'primary', function () {
					show(ui.confirm, false);
					undoLast(true);
				});
				var no = ED.button('Not now', 'secondary', function () {
					show(ui.confirm, false);
				});
				ui.confirm.appendChild(yes);
				ui.confirm.appendChild(no);
				show(ui.confirm, true);
				yes.focus();
				return;
			}
			status(err.message);
		});
	}

	function fillLayouts() {
		var c = (cfg.components || {})[ui.addKind.value];
		ED.clear(ui.addLayout);
		if (!c) {
			return;
		}
		Object.keys(c.variants || {}).forEach(function (key) {
			ui.addLayout.appendChild(el('option', { value: key, text: c.variants[key] }));
		});
		ui.addDesc.textContent = c.description || '';
	}

	function openAdd() {
		if (notNow()) {
			return;
		}
		var sec = currentSection();
		var all = document.querySelectorAll('[data-twd-sk-sec]');
		S.addAfter = sec ? parseInt(sec.getAttribute('data-twd-sk-sec'), 10) : all.length - 1;
		ui.addWhere.textContent = sec ? 'The new section goes after "' + sectionName(sec) + '".' : 'The new section goes at the end of the page. To put it somewhere else, click inside the section it should follow first.';
		closePanels();
		show(ui.confirm, false);
		show(ui.addPanel, true);
		ui.addKind.focus();
	}

	function doAdd() {
		structure({ op: 'add', after: S.addAfter, component: ui.addKind.value, variant: ui.addLayout.value }, 'Adding the section...', 'Added.');
	}

	function addIt() {
		if (notNow()) {
			return;
		}
		if (!cfg.pagePublished) {
			doAdd();
			return;
		}
		// A live page would show the placeholder words to visitors until they are replaced.
		ED.clear(ui.confirm);
		ui.confirm.appendChild(el('span', { text: 'This page is live. The new section shows its placeholder words to visitors until you replace them. Add it now anyway? ' }));
		var yes = ED.button('Add it anyway', 'primary', function () {
			show(ui.confirm, false);
			doAdd();
		});
		var no = ED.button('Not yet', 'secondary', function () {
			show(ui.confirm, false);
		});
		ui.confirm.appendChild(yes);
		ui.confirm.appendChild(no);
		show(ui.confirm, true);
		yes.focus();
	}

	// After an add or a move the page comes back with ?twd_sk_edit=1&twd_sk_at=N: open the editor at that section.
	function resume() {
		var q = window.location.search;
		if (!/[?&]twd_sk_edit=1(&|$)/.test(q)) {
			return;
		}
		var m = /[?&]twd_sk_at=(\d+)/.exec(q);
		try {
			window.history.replaceState(null, '', window.location.pathname + plainSearch() + window.location.hash);
		} catch (e) {
			// The address stays as it is; nothing else depends on it.
		}
		start();
		if (!S.on || !m) {
			return;
		}
		var sec = document.querySelector('[data-twd-sk-sec="' + parseInt(m[1], 10) + '"]');
		if (sec) {
			sec.scrollIntoView({ block: 'center' });
			var first = sec.querySelector('[data-twd-sk-t]');
			if (first) {
				first.focus({ preventScroll: true });
			}
			S.last = first || sec;
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
		S.removes = {};
		S.removeOrder = [];
		S.looks = {};
		S.origLook = {};
		S.lookSec = null;
		S.last = null;
		S.target = null;
		if (ui.putBack) {
			ui.putBack.disabled = true;
		}
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
		adminBar();
		show(ui.bar, false);
		show(ui.open, true);
		show(document.getElementById('twd-sk-ed-open'), true);
	}

	function throwAway() {
		S.texts.forEach(function (t) {
			var i = t.getAttribute('data-twd-sk-t');
			if (serialize(t) !== S.origText[i]) {
				restoreChildren(t, S.orig[i]);
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
		Object.keys(S.removes).forEach(function (k) {
			S.removes[k].removeAttribute('hidden');
		});
		Array.prototype.forEach.call(document.querySelectorAll('section[data-twd-sk-sec]'), function (sec) {
			var k = sec.getAttribute('data-twd-sk-sec');
			if (S.origLook[k] !== undefined) {
				sec.className = S.origLook[k];
				sec.removeAttribute('data-twd-sk-looked');
			}
		});
		S.looks = {};
		S.origLook = {};
		S.lookSec = null;
		S.hrefs = {};
		S.linkTarget = null;
		S.range = null;
		S.picks = {};
		S.spotPicks = {};
		S.removes = {};
		S.removeOrder = [];
		if (ui.putBack) {
			ui.putBack.disabled = true;
		}
		closePanels();
		show(ui.confirm, false);
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
		if (S.saving) {
			return;
		}
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
		body.removes = Object.keys(S.removes).map(function (k) {
			return parseInt(k, 10);
		});
		body.looks = Object.keys(S.looks).map(function (k) {
			return S.looks[k];
		});
		if (!body.texts.length && !body.pictures.length && !body.spots.length && !body.removes.length && !body.looks.length) {
			status('Nothing has changed yet.');
			return;
		}
		S.saving = true;
		ui.save.disabled = true;
		adminBar();
		status('Saving...');
		ED.api('POST', '/pages/' + cfg.pageId + '/inline', body).then(function (data) {
			status('Saved as version ' + data.version + '. Reloading so you see the page as visitors will.');
			window.setTimeout(function () {
				window.location.reload();
			}, 900);
		}, function (err) {
			S.saving = false;
			ui.save.disabled = false;
			adminBar();
			status(err.status === 409 ? err.message + ' Your changes are still on screen: copy any you need, then reload.' : err.message);
		});
	}

	/* ---- set up ---- */

	buildBar();
	buildAdminBar();
	ui.open = el('button', { type: 'button', id: 'twd-sk-ed-text', className: 'twd-sk-ed__open twd-sk-ed__open--text', text: 'Visual editor' });
	/* A placeholder marker is edited as one unit (0.14.0): when the cursor lands inside [PLACEHOLDER: ...] the
	   whole marker is selected, so typing replaces all of it and never leaves half a marker on the page. */
	var MARKER = /\[PLACEHOLDER(?::[^\[\]]{0,60})?\]/g;
	function selectMarker() {
		if (!S.on) {
			return;
		}
		var sel = window.getSelection();
		if (!sel || !sel.rangeCount || !sel.isCollapsed) {
			return;
		}
		var node = sel.anchorNode;
		if (!node || node.nodeType !== 3 || !editableOf(node)) {
			return;
		}
		var text = node.nodeValue;
		var off = sel.anchorOffset;
		var m;
		MARKER.lastIndex = 0;
		while ((m = MARKER.exec(text))) {
			if (off > m.index && off < m.index + m[0].length) {
				var r = document.createRange();
				r.setStart(node, m.index);
				r.setEnd(node, m.index + m[0].length);
				sel.removeAllRanges();
				sel.addRange(r);
				return;
			}
		}
	}
	document.addEventListener('selectionchange', selectMarker);
	ui.open.addEventListener('click', start);
	root.appendChild(ui.open);
	document.addEventListener('keydown', onKey, true);
	document.addEventListener('paste', onPaste, true);
	document.addEventListener('input', onInput, true);
	document.addEventListener('click', onClick, true);
	document.addEventListener('focusin', function (e) {
		if (S.on && ui.bar && !ui.bar.contains(e.target)) {
			S.last = e.target;
		}
	});
	// The Edit with AI pop-up offers the same, from its Edit this page tab.
	ED.startInline = function () {
		start();
	};
	if (ED.addLauncher) {
		ED.addLauncher({ id: 'text', order: 10, label: function () { return 'Visual editor'; }, run: start, show: function () { return !S.on && !(ED.toolsOff && ED.toolsOff()); } });
	}
	// Every way out runs the one revert routine: Cancel and Escape (cancel(), asking first), the pop-up's "Close and
	// lose these changes" (through this check), and a page redraw (the nodes are gone, so the session ends).
	ED.addDirtyCheck(function () {
		var n = S.on ? changedCount() : 0;
		return n ? n + ' change' + (n === 1 ? '' : 's') + ' made with the Visual editor and not saved' : '';
	}, function () {
		if (S.on) {
			throwAway();
		}
	});
	if (typeof ED.onRedraw === 'function') {
		ED.onRedraw(function () {
			if (S.on) {
				throwAway();
				status('The page was redrawn, so the Visual editor closed. Press Visual editor to carry on.');
			}
		});
	}
	// For the Your Edits menu and other scripts: step back the last saved change, asking first about the blank starter.
	ED.inlineUndo = function () {
		if (!S.on) {
			start();
		}
		undoLast(false);
	};
	ED.inlineCount = function () {
		return texts().length + pictures().length + spots().length;
	};
	resume();
})();
