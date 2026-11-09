/* TWD Site Kit: the Edit product window (Shop module). Vanilla JS, no build step.
 *
 * Loaded only for signed-in people who can edit products, on product pages (Edit product) and on
 * the shop and category pages (Add a product). It never builds HTML from text: every string goes
 * in with textContent, and text from the server is copied in node by node through a short list of
 * allowed tags. The only address it talks to is the REST address the server gave it. */
(function () {
	'use strict';

	var cfg = window.TWD_SK_SHOP;
	if (!cfg || !cfg.rest || !window.fetch) {
		return;
	}

	var SECTION_TAGS = { P: 'p', BR: 'br', STRONG: 'strong', B: 'strong', EM: 'em', I: 'em', UL: 'ul', OL: 'ol', LI: 'li', A: 'a', DIV: 'p' };
	var SUMMARY_TAGS = { P: 'p', BR: 'br', STRONG: 'strong', B: 'strong', EM: 'em', I: 'em', DIV: 'p' };
	var DEFAULT_SECTIONS = ['About this piece', 'Condition', 'History'];

	// -- Small helpers --------------------------------------------------------------

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) {
			n.className = cls;
		}
		if (text !== undefined && text !== null) {
			n.textContent = String(text);
		}
		return n;
	}

	function button(text, cls, onClick) {
		var b = el('button', 'twd-sk-shop-ed__btn' + (cls ? ' ' + cls : ''), text);
		b.type = 'button';
		if (onClick) {
			b.addEventListener('click', onClick);
		}
		return b;
	}

	function field(labelText, input, hint) {
		var wrap = el('div', 'twd-sk-shop-ed__field');
		var id = 'twd-sk-shop-f' + Math.random().toString(36).slice(2, 9);
		var label = el('label', 'twd-sk-shop-ed__label', labelText);
		label.setAttribute('for', id);
		input.id = id;
		wrap.appendChild(label);
		wrap.appendChild(input);
		if (hint) {
			wrap.appendChild(el('p', 'twd-sk-shop-ed__hint', hint));
		}
		return wrap;
	}

	function textInput(value, max) {
		var i = el('input', 'twd-sk-shop-ed__input');
		i.type = 'text';
		i.value = value || '';
		if (max) {
			i.maxLength = max;
		}
		return i;
	}

	function api(method, path, body) {
		var opts = {
			method: method,
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce }
		};
		if (body) {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(body);
		}
		return window.fetch(cfg.rest + path, opts).then(function (r) {
			return r.json().catch(function () {
				return {};
			}).then(function (data) {
				if (!r.ok) {
					var e = new Error(data && data.message ? data.message : 'Something went wrong (' + r.status + '). Try again.');
					e.status = r.status;
					throw e;
				}
				return data;
			});
		});
	}

	/* Copy server HTML (already cleaned) into a box, keeping only the allowed tags and a link's
	 * address. DOMParser documents never run scripts; nothing is written as markup. */
	function fillRich(box, html, tags) {
		while (box.firstChild) {
			box.removeChild(box.firstChild);
		}
		var doc = new window.DOMParser().parseFromString('<body>' + (html || '') + '</body>', 'text/html');
		copyNodes(doc.body, box, tags);
	}

	function copyNodes(from, to, tags) {
		var kids = from.childNodes;
		for (var i = 0; i < kids.length; i++) {
			var k = kids[i];
			if (k.nodeType === 3) {
				to.appendChild(document.createTextNode(k.nodeValue));
			} else if (k.nodeType === 1) {
				var name = tags[k.nodeName];
				if (!name) {
					copyNodes(k, to, tags);
					continue;
				}
				var n = document.createElement(name);
				if (name === 'a') {
					var href = k.getAttribute('href') || '';
					if (/^(https?:|mailto:|tel:|\/|#)/i.test(href)) {
						n.setAttribute('href', href);
					}
				}
				copyNodes(k, n, tags);
				to.appendChild(n);
			}
		}
	}

	/* Read a box back as HTML by walking its nodes. The server cleans it again. */
	function readRich(box, tags) {
		var out = '';
		function esc(s) {
			return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
		}
		function walk(node) {
			var kids = node.childNodes;
			for (var i = 0; i < kids.length; i++) {
				var k = kids[i];
				if (k.nodeType === 3) {
					out += esc(k.nodeValue);
				} else if (k.nodeType === 1) {
					var name = tags[k.nodeName];
					if (!name) {
						walk(k);
						continue;
					}
					if (name === 'br') {
						out += '<br>';
						continue;
					}
					if (name === 'a') {
						out += '<a href="' + esc(k.getAttribute('href') || '').replace(/"/g, '&quot;') + '">';
					} else {
						out += '<' + name + '>';
					}
					walk(k);
					out += '</' + name + '>';
				}
			}
		}
		walk(box);
		return out;
	}

	// -- The window ---------------------------------------------------------------------

	var ui = {};
	var data = null;      // what the server sent
	var draft = null;     // the fields as edited, not yet saved
	var startFields = ''; // the fields as opened, to know what changed
	var savedSomething = false;
	var lastFocus = null;
	var busy = false;

	function buildShell() {
		var root = el('div', 'twd-sk-shop-ed');
		root.hidden = true;
		var backdrop = el('div', 'twd-sk-shop-ed__backdrop');
		backdrop.addEventListener('click', requestClose);
		var dlg = el('div', 'twd-sk-shop-ed__dialog');
		dlg.setAttribute('role', 'dialog');
		dlg.setAttribute('aria-modal', 'true');
		dlg.setAttribute('aria-labelledby', 'twd-sk-shop-ed-title');

		var head = el('div', 'twd-sk-shop-ed__head');
		var title = el('h2', 'twd-sk-shop-ed__title', 'Edit product');
		title.id = 'twd-sk-shop-ed-title';
		head.appendChild(title);
		ui.status = el('span', 'twd-sk-shop-ed__badge', '');
		head.appendChild(ui.status);
		var close = button('Close', 'twd-sk-shop-ed__close', requestClose);
		close.setAttribute('aria-label', 'Close the Edit product window');
		head.appendChild(close);
		dlg.appendChild(head);

		ui.tabs = el('div', 'twd-sk-shop-ed__tabs');
		ui.tabs.setAttribute('role', 'tablist');
		dlg.appendChild(ui.tabs);

		ui.body = el('div', 'twd-sk-shop-ed__body');
		dlg.appendChild(ui.body);

		ui.message = el('div', 'twd-sk-shop-ed__message');
		ui.message.setAttribute('role', 'status');
		ui.message.setAttribute('aria-live', 'polite');
		dlg.appendChild(ui.message);

		var foot = el('div', 'twd-sk-shop-ed__foot');
		ui.history = button('History', 'twd-sk-shop-ed__btn--quiet', toggleHistory);
		ui.undo = button('Undo', 'twd-sk-shop-ed__btn--quiet', doUndo);
		ui.publish = button('Publish', 'twd-sk-shop-ed__btn--line', doPublish);
		ui.save = button('Save', 'twd-sk-shop-ed__btn--main', doSave);
		foot.appendChild(ui.history);
		foot.appendChild(ui.undo);
		var spacer = el('span', 'twd-sk-shop-ed__spacer');
		foot.appendChild(spacer);
		foot.appendChild(ui.publish);
		foot.appendChild(ui.save);
		dlg.appendChild(foot);

		ui.confirm = el('div', 'twd-sk-shop-ed__confirm');
		ui.confirm.hidden = true;
		dlg.appendChild(ui.confirm);

		root.appendChild(backdrop);
		root.appendChild(dlg);
		document.body.appendChild(root);
		ui.root = root;
		ui.dialog = dlg;

		root.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				e.preventDefault();
				requestClose();
			} else if (e.key === 'Tab') {
				trapFocus(e);
			}
		});
	}

	function trapFocus(e) {
		var items = ui.dialog.querySelectorAll('button, input, select, textarea, [contenteditable="true"], a[href]');
		var list = [];
		for (var i = 0; i < items.length; i++) {
			if (items[i].offsetParent !== null && !items[i].disabled) {
				list.push(items[i]);
			}
		}
		if (!list.length) {
			return;
		}
		var first = list[0];
		var last = list[list.length - 1];
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	function say(text, kind) {
		ui.message.textContent = text || '';
		ui.message.className = 'twd-sk-shop-ed__message' + (kind ? ' twd-sk-shop-ed__message--' + kind : '');
	}

	function open() {
		if (!ui.root) {
			buildShell();
		}
		lastFocus = document.activeElement;
		ui.root.hidden = false;
		document.documentElement.classList.add('twd-sk-shop-ed-open');
		say('Loading the product...');
		load();
	}

	function load() {
		api('GET', 'products/' + cfg.product).then(function (d) {
			show(d);
		}).catch(function (e) {
			say(e.message, 'bad');
		});
	}

	function show(d) {
		data = d;
		draft = JSON.parse(JSON.stringify(d.fields));
		if (!draft.sections.length) {
			draft.sections = DEFAULT_SECTIONS.map(function (h) {
				return { heading: h, html: '' };
			});
		}
		startFields = JSON.stringify(draft);
		ui.status.textContent = d.status === 'publish' ? 'Live' : 'Draft';
		ui.status.className = 'twd-sk-shop-ed__badge' + (d.status === 'publish' ? '' : ' twd-sk-shop-ed__badge--draft');
		ui.publish.hidden = !d.can_publish;
		ui.undo.disabled = !d.can_undo;
		buildTabs();
		var notes = (d.notes || []).join(' ');
		if (!d.editable) {
			say('This kind of product is edited in WooCommerce. Use the link in Basics.', 'warn');
		} else if (d.markers && d.markers.length) {
			say((notes ? notes + ' ' : '') + 'Still to fill in: ' + d.markers.join(', ') + '.', 'warn');
		} else {
			say(notes || (d.saved ? 'Saved.' : ''), d.saved ? 'good' : '');
		}
		updateSave();
	}

	// -- Tabs ---------------------------------------------------------------------------

	var TABS = [
		{ key: 'basics', label: 'Basics', build: buildBasics },
		{ key: 'photos', label: 'Photos', build: buildPhotos },
		{ key: 'details', label: 'Details', build: buildDetails },
		{ key: 'story', label: 'Story and condition', build: buildStory }
	];
	var currentTab = 'basics';

	function buildTabs() {
		while (ui.tabs.firstChild) {
			ui.tabs.removeChild(ui.tabs.firstChild);
		}
		ui.panels = {};
		while (ui.body.firstChild) {
			ui.body.removeChild(ui.body.firstChild);
		}
		TABS.forEach(function (t) {
			var b = el('button', 'twd-sk-shop-ed__tab', t.label);
			b.type = 'button';
			b.setAttribute('role', 'tab');
			b.id = 'twd-sk-shop-tab-' + t.key;
			b.addEventListener('click', function () {
				selectTab(t.key);
			});
			ui.tabs.appendChild(b);
			var panel = el('div', 'twd-sk-shop-ed__panel');
			panel.setAttribute('role', 'tabpanel');
			panel.setAttribute('aria-labelledby', b.id);
			ui.body.appendChild(panel);
			ui.panels[t.key] = { tab: b, panel: panel };
			t.build(panel);
		});
		selectTab(currentTab);
	}

	function selectTab(key) {
		currentTab = key;
		Object.keys(ui.panels).forEach(function (k) {
			var on = k === key;
			ui.panels[k].tab.setAttribute('aria-selected', on ? 'true' : 'false');
			ui.panels[k].tab.tabIndex = on ? 0 : -1;
			ui.panels[k].panel.hidden = !on;
		});
	}

	function changed() {
		updateSave();
	}

	function isDirty() {
		return !!draft && JSON.stringify(draft) !== startFields;
	}

	function updateSave() {
		ui.save.disabled = busy || !data || !data.editable || !isDirty();
		ui.save.textContent = isDirty() ? 'Save' : 'Saved';
	}

	// Basics.
	function buildBasics(p) {
		if (!data.editable) {
			p.appendChild(el('p', 'twd-sk-shop-ed__note', 'This product is a ' + data.type + ' product. Edit it in WooCommerce.'));
		}
		var title = textInput(draft.title, data.limits.title);
		title.addEventListener('input', function () {
			draft.title = title.value;
			changed();
		});
		p.appendChild(field('Title', title));

		var row = el('div', 'twd-sk-shop-ed__row');
		var price = textInput(draft.price, 12);
		price.inputMode = 'decimal';
		price.addEventListener('input', function () {
			draft.price = price.value;
			changed();
		});
		row.appendChild(field('Price in pounds', price, 'For example 1250 or 1250.50. Leave empty for no price.'));
		var sale = textInput(draft.sale, 12);
		sale.inputMode = 'decimal';
		sale.addEventListener('input', function () {
			draft.sale = sale.value;
			changed();
		});
		row.appendChild(field('Reduced price (optional)', sale));
		p.appendChild(row);

		var sku = textInput(draft.sku, 60);
		sku.addEventListener('input', function () {
			draft.sku = sku.value;
			changed();
		});
		p.appendChild(field('Stock number (optional)', sku));

		// For sale or sold.
		var state = el('div', 'twd-sk-shop-ed__field');
		state.appendChild(el('span', 'twd-sk-shop-ed__label', 'For sale or sold'));
		var pair = el('div', 'twd-sk-shop-ed__pair');
		var forSale = button('For sale', 'twd-sk-shop-ed__choice');
		var sold = button('Sold', 'twd-sk-shop-ed__choice');
		function paint() {
			forSale.setAttribute('aria-pressed', draft.sold ? 'false' : 'true');
			sold.setAttribute('aria-pressed', draft.sold ? 'true' : 'false');
		}
		forSale.addEventListener('click', function () {
			draft.sold = false;
			paint();
			changed();
		});
		sold.addEventListener('click', function () {
			draft.sold = true;
			paint();
			changed();
		});
		paint();
		pair.appendChild(forSale);
		pair.appendChild(sold);
		state.appendChild(pair);
		state.appendChild(el('p', 'twd-sk-shop-ed__hint', 'A one-off piece: when someone buys it, it is marked sold by itself.'));
		p.appendChild(state);

		// Categories.
		var cats = el('fieldset', 'twd-sk-shop-ed__cats');
		cats.appendChild(el('legend', 'twd-sk-shop-ed__label', 'Categories'));
		if (!data.categories.length) {
			cats.appendChild(el('p', 'twd-sk-shop-ed__hint', 'There are no product categories yet. Add them in WooCommerce, Products, Categories.'));
		}
		data.categories.forEach(function (c) {
			var lab = el('label', 'twd-sk-shop-ed__check');
			var box = el('input');
			box.type = 'checkbox';
			box.checked = draft.cats.indexOf(c.id) !== -1;
			box.addEventListener('change', function () {
				var at = draft.cats.indexOf(c.id);
				if (box.checked && at === -1) {
					draft.cats.push(c.id);
				} else if (!box.checked && at !== -1) {
					draft.cats.splice(at, 1);
				}
				draft.cats.sort(function (a, b) {
					return a - b;
				});
				changed();
			});
			lab.appendChild(box);
			lab.appendChild(document.createTextNode(' ' + c.name));
			cats.appendChild(lab);
		});
		p.appendChild(cats);

		if (data.edit_url) {
			var more = el('p', 'twd-sk-shop-ed__hint');
			more.appendChild(document.createTextNode('Photos, shipping and everything else: '));
			var a = el('a', '', 'open this product in WooCommerce');
			a.href = data.edit_url;
			a.target = '_blank';
			a.rel = 'noopener';
			more.appendChild(a);
			more.appendChild(document.createTextNode(' (save here first).'));
			p.appendChild(more);
		}
	}

	// Photos: WordPress's own media library window (choose or upload), the main photo and the gallery in order.
	function buildPhotos(p) {
		var photos = data.photos || {};
		var media = window.wp && window.wp.media;
		if (!media) {
			p.appendChild(el('p', 'twd-sk-shop-ed__note', 'The photo picker did not load on this page. Reload the page, or add photos in WooCommerce for now.'));
			return;
		}
		p.appendChild(el('p', 'twd-sk-shop-ed__hint', 'The main photo shows first in the shop and is the picture shared on social media. In the picker you can upload new photos and give each one a short description (Alt Text), which helps search engines and people who use screen readers.'));

		var mainField = el('div', 'twd-sk-shop-ed__field');
		mainField.appendChild(el('span', 'twd-sk-shop-ed__label', 'Main photo'));
		var mainBox = el('div', 'twd-sk-shop-ed__photos');
		mainField.appendChild(mainBox);
		p.appendChild(mainField);

		var galField = el('div', 'twd-sk-shop-ed__field');
		galField.appendChild(el('span', 'twd-sk-shop-ed__label', 'More photos, in the order they show'));
		var galBox = el('div', 'twd-sk-shop-ed__photos');
		galField.appendChild(galBox);
		var addBtn = button('Add photos', 'twd-sk-shop-ed__btn--line', addGallery);
		galField.appendChild(addBtn);
		p.appendChild(galField);

		function remember(att) {
			var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
			photos[att.id] = { thumb: thumb || '', alt: att.alt || '' };
		}

		function frame(title, buttonText, multiple) {
			return media({ title: title, button: { text: buttonText }, library: { type: 'image' }, multiple: multiple });
		}

		function chooseMain() {
			var f = frame('Choose the main photo', 'Use this photo', false);
			f.on('select', function () {
				var a = f.state().get('selection').first().toJSON();
				remember(a);
				var at = draft.gallery.indexOf(a.id);
				if (at !== -1) {
					draft.gallery.splice(at, 1);
				}
				if (draft.image && draft.image !== a.id && draft.gallery.indexOf(draft.image) === -1) {
					// The old main photo stays in the gallery, first, so nothing is lost by a change.
					draft.gallery.unshift(draft.image);
				}
				draft.image = a.id;
				render();
				changed();
			});
			f.open();
		}

		function addGallery() {
			var f = frame('Add photos', 'Add these photos', 'add');
			f.on('select', function () {
				f.state().get('selection').each(function (m) {
					var a = m.toJSON();
					remember(a);
					if (a.id !== draft.image && draft.gallery.indexOf(a.id) === -1) {
						draft.gallery.push(a.id);
					}
				});
				if (!draft.image && draft.gallery.length) {
					draft.image = draft.gallery.shift();
				}
				render();
				changed();
			});
			f.open();
		}

		function tile(id, i) {
			var t = el('div', 'twd-sk-shop-ed__photo');
			var info = photos[id] || {};
			if (info.thumb) {
				var img = el('img');
				img.src = info.thumb;
				img.alt = info.alt || ('Photo ' + id);
				t.appendChild(img);
			} else {
				t.appendChild(el('span', 'twd-sk-shop-ed__nothumb', 'Photo ' + id));
			}
			if (!info.alt) {
				t.appendChild(el('span', 'twd-sk-shop-ed__noalt', 'No description'));
			}
			var tools = el('div', 'twd-sk-shop-ed__phototools');
			if (i === -1) {
				tools.appendChild(button('Change', 'twd-sk-shop-ed__btn--small', chooseMain));
				var rm = button('Remove', 'twd-sk-shop-ed__btn--small', function () {
					draft.image = 0;
					render();
					changed();
				});
				rm.setAttribute('aria-label', 'Remove the main photo');
				tools.appendChild(rm);
			} else {
				var left = button('Left', 'twd-sk-shop-ed__btn--small', function () {
					draft.gallery.splice(i - 1, 0, draft.gallery.splice(i, 1)[0]);
					render();
					changed();
				});
				left.disabled = i === 0;
				left.setAttribute('aria-label', 'Move photo ' + (i + 1) + ' earlier');
				var main = button('Main', 'twd-sk-shop-ed__btn--small', function () {
					var old = draft.image;
					draft.image = draft.gallery.splice(i, 1)[0];
					if (old) {
						draft.gallery.splice(i, 0, old);
					}
					render();
					changed();
				});
				main.setAttribute('aria-label', 'Make photo ' + (i + 1) + ' the main photo');
				var del = button('Remove', 'twd-sk-shop-ed__btn--small', function () {
					draft.gallery.splice(i, 1);
					render();
					changed();
				});
				del.setAttribute('aria-label', 'Remove photo ' + (i + 1) + ' from this product');
				tools.appendChild(left);
				tools.appendChild(main);
				tools.appendChild(del);
			}
			t.appendChild(tools);
			return t;
		}

		function render() {
			while (mainBox.firstChild) {
				mainBox.removeChild(mainBox.firstChild);
			}
			while (galBox.firstChild) {
				galBox.removeChild(galBox.firstChild);
			}
			if (draft.image) {
				mainBox.appendChild(tile(draft.image, -1));
			} else {
				mainBox.appendChild(button('Choose the main photo', 'twd-sk-shop-ed__btn--line', chooseMain));
			}
			draft.gallery.forEach(function (id, i) {
				galBox.appendChild(tile(id, i));
			});
			if (!draft.gallery.length) {
				galBox.appendChild(el('p', 'twd-sk-shop-ed__hint', 'No more photos yet.'));
			}
		}
		render();
		p.appendChild(el('p', 'twd-sk-shop-ed__hint', 'Remove takes a photo off this product only. The photo stays in the media library.'));
	}

	// Details.
	function buildDetails(p) {
		p.appendChild(el('p', 'twd-sk-shop-ed__hint', 'One fact per row, in your own words, for example Period: Victorian, about 1870. Tick "Label" to show it as a small label near the title (up to ' + data.limits.ticks + ').'));
		var listId = 'twd-sk-shop-labels';
		var dl = el('datalist');
		dl.id = listId;
		data.suggested.forEach(function (s) {
			var o = el('option');
			o.value = s;
			dl.appendChild(o);
		});
		p.appendChild(dl);
		var rows = el('div', 'twd-sk-shop-ed__rows');
		p.appendChild(rows);

		function render() {
			while (rows.firstChild) {
				rows.removeChild(rows.firstChild);
			}
			draft.details.forEach(function (r, i) {
				var line = el('div', 'twd-sk-shop-ed__detail');
				var label = textInput(r.label, data.limits.label);
				label.setAttribute('list', listId);
				label.setAttribute('aria-label', 'Label ' + (i + 1));
				label.placeholder = 'Label';
				label.addEventListener('input', function () {
					r.label = label.value;
					changed();
				});
				var value = textInput(r.value, data.limits.value);
				value.setAttribute('aria-label', 'Value ' + (i + 1));
				value.placeholder = 'Value';
				value.addEventListener('input', function () {
					r.value = value.value;
					changed();
				});
				var tagLab = el('label', 'twd-sk-shop-ed__check twd-sk-shop-ed__tag');
				var tag = el('input');
				tag.type = 'checkbox';
				tag.checked = !!r.tag;
				tag.addEventListener('change', function () {
					r.tag = tag.checked;
					changed();
				});
				tagLab.appendChild(tag);
				tagLab.appendChild(document.createTextNode(' Label'));
				var up = button('Up', 'twd-sk-shop-ed__btn--small', function () {
					if (i > 0) {
						draft.details.splice(i - 1, 0, draft.details.splice(i, 1)[0]);
						render();
						changed();
					}
				});
				up.setAttribute('aria-label', 'Move row ' + (i + 1) + ' up');
				up.disabled = i === 0;
				var del = button('Remove', 'twd-sk-shop-ed__btn--small', function () {
					draft.details.splice(i, 1);
					render();
					changed();
				});
				del.setAttribute('aria-label', 'Remove row ' + (i + 1));
				line.appendChild(label);
				line.appendChild(value);
				line.appendChild(tagLab);
				line.appendChild(up);
				line.appendChild(del);
				rows.appendChild(line);
			});
			addBtn.disabled = draft.details.length >= data.limits.rows;
		}
		var addBtn = button('Add a detail', 'twd-sk-shop-ed__btn--line', function () {
			draft.details.push({ label: '', value: '', tag: false });
			render();
			changed();
			var inputs = rows.querySelectorAll('input[type="text"]');
			if (inputs.length) {
				inputs[inputs.length - 2].focus();
			}
		});
		render();
		p.appendChild(addBtn);

		if (data.fixed && data.fixed.length) {
			var fx = el('div', 'twd-sk-shop-ed__fixed');
			fx.appendChild(el('p', 'twd-sk-shop-ed__label', 'Set in WooCommerce (change them there)'));
			data.fixed.forEach(function (r) {
				fx.appendChild(el('p', 'twd-sk-shop-ed__hint', r.label + ': ' + r.value));
			});
			p.appendChild(fx);
		}
	}

	// A small rich text box with Bold, Italic, List and Link.
	function richBox(html, tags, withList, onChange, labelText) {
		var wrap = el('div', 'twd-sk-shop-ed__rich');
		var bar = el('div', 'twd-sk-shop-ed__toolbar');
		var box = el('div', 'twd-sk-shop-ed__richbox');
		box.contentEditable = 'true';
		box.setAttribute('role', 'textbox');
		box.setAttribute('aria-multiline', 'true');
		box.setAttribute('aria-label', labelText);
		fillRich(box, html, tags);
		function cmd(name, value) {
			box.focus();
			document.execCommand(name, false, value || null);
			onChange(readRich(box, tags));
		}
		bar.appendChild(button('B', 'twd-sk-shop-ed__tool', function () {
			cmd('bold');
		}));
		bar.appendChild(button('I', 'twd-sk-shop-ed__tool', function () {
			cmd('italic');
		}));
		bar.lastChild.previousSibling.setAttribute('aria-label', 'Bold');
		bar.lastChild.setAttribute('aria-label', 'Italic');
		if (withList) {
			var lb = button('List', 'twd-sk-shop-ed__tool', function () {
				cmd('insertUnorderedList');
			});
			lb.setAttribute('aria-label', 'Bullet list');
			bar.appendChild(lb);
			var linkRow = el('span', 'twd-sk-shop-ed__linkrow');
			linkRow.hidden = true;
			var linkIn = textInput('', 300);
			linkIn.placeholder = 'Web address, then Add link';
			linkIn.setAttribute('aria-label', 'Link address');
			var saved = null;
			var addLink = button('Add link', 'twd-sk-shop-ed__btn--small', function () {
				var url = linkIn.value.trim();
				if (saved) {
					var sel = window.getSelection();
					sel.removeAllRanges();
					sel.addRange(saved);
				}
				if (/^(https?:\/\/|mailto:|tel:|\/)/i.test(url)) {
					cmd('createLink', url);
				}
				linkRow.hidden = true;
				linkIn.value = '';
			});
			var linkBtn = button('Link', 'twd-sk-shop-ed__tool', function () {
				var sel = window.getSelection();
				saved = sel.rangeCount && box.contains(sel.anchorNode) ? sel.getRangeAt(0).cloneRange() : null;
				linkRow.hidden = false;
				linkIn.focus();
			});
			linkBtn.setAttribute('aria-label', 'Link the selected words');
			bar.appendChild(linkBtn);
			linkRow.appendChild(linkIn);
			linkRow.appendChild(addLink);
			bar.appendChild(linkRow);
		}
		box.addEventListener('input', function () {
			onChange(readRich(box, tags));
		});
		box.addEventListener('paste', function (e) {
			// Pasted text comes in as plain words; the server keeps paragraphs from blank lines.
			e.preventDefault();
			var text = (e.clipboardData || window.clipboardData).getData('text/plain');
			document.execCommand('insertText', false, text);
		});
		wrap.appendChild(bar);
		wrap.appendChild(box);
		return wrap;
	}

	// Story and condition.
	function buildStory(p) {
		try {
			document.execCommand('defaultParagraphSeparator', false, 'p');
		} catch (e) {
			// Older browsers: lines become div, which the server turns into paragraphs.
		}
		var sumField = el('div', 'twd-sk-shop-ed__field');
		sumField.appendChild(el('span', 'twd-sk-shop-ed__label', 'Short summary'));
		sumField.appendChild(el('p', 'twd-sk-shop-ed__hint', 'Two or three lines shown near the price.'));
		sumField.appendChild(richBox(draft.summary, SUMMARY_TAGS, false, function (html) {
			draft.summary = html;
			changed();
		}, 'Short summary'));
		p.appendChild(sumField);

		var list = el('div', 'twd-sk-shop-ed__sections');
		p.appendChild(list);

		function render() {
			while (list.firstChild) {
				list.removeChild(list.firstChild);
			}
			draft.sections.forEach(function (s, i) {
				var box = el('div', 'twd-sk-shop-ed__section');
				var head = textInput(s.heading, data.limits.heading);
				head.setAttribute('aria-label', 'Section heading ' + (i + 1));
				head.className += ' twd-sk-shop-ed__heading';
				head.addEventListener('input', function () {
					s.heading = head.value;
					changed();
				});
				var tools = el('div', 'twd-sk-shop-ed__sectools');
				var up = button('Up', 'twd-sk-shop-ed__btn--small', function () {
					if (i > 0) {
						draft.sections.splice(i - 1, 0, draft.sections.splice(i, 1)[0]);
						render();
						changed();
					}
				});
				up.disabled = i === 0;
				up.setAttribute('aria-label', 'Move section ' + (i + 1) + ' up');
				var del = button('Remove', 'twd-sk-shop-ed__btn--small', function () {
					draft.sections.splice(i, 1);
					render();
					changed();
				});
				del.setAttribute('aria-label', 'Remove section ' + (i + 1));
				tools.appendChild(up);
				tools.appendChild(del);
				var top = el('div', 'twd-sk-shop-ed__sechead');
				top.appendChild(head);
				top.appendChild(tools);
				box.appendChild(top);
				box.appendChild(richBox(s.html, SECTION_TAGS, true, function (html) {
					s.html = html;
					changed();
				}, 'Text of ' + (s.heading || 'this section')));
				list.appendChild(box);
			});
			addBtn.disabled = draft.sections.length >= data.limits.sections;
		}
		var addBtn = button('Add a section', 'twd-sk-shop-ed__btn--line', function () {
			draft.sections.push({ heading: '', html: '' });
			render();
			changed();
			var heads = list.querySelectorAll('.twd-sk-shop-ed__heading');
			if (heads.length) {
				heads[heads.length - 1].focus();
			}
		});
		render();
		p.appendChild(addBtn);
		p.appendChild(el('p', 'twd-sk-shop-ed__hint', 'A section with no words is left out when you save. Delivery and returns will be one text for the whole shop.'));
	}

	// -- Saving, Undo, History, Publish ---------------------------------------------------

	function setBusy(on) {
		busy = on;
		ui.save.disabled = on || !isDirty();
		ui.undo.disabled = on || !data || !data.can_undo;
		ui.publish.disabled = on;
	}

	function after(d) {
		savedSomething = savedSomething || !!d.saved;
		setBusy(false);
		show(d);
	}

	function fail(e) {
		setBusy(false);
		updateSave();
		ui.undo.disabled = !data || !data.can_undo;
		say(e.message, 'bad');
	}

	function doSave() {
		if (busy || !isDirty()) {
			return;
		}
		setBusy(true);
		say('Saving...');
		api('POST', 'products/' + cfg.product, { fields: draft, base: data.base }).then(after).catch(fail);
	}

	function doUndo() {
		if (busy) {
			return;
		}
		if (isDirty()) {
			say('Save or close without saving first: Undo goes back to the last saved version.', 'warn');
			return;
		}
		setBusy(true);
		say('Going back one step...');
		api('POST', 'products/' + cfg.product + '/undo', {}).then(after).catch(fail);
	}

	function doPublish() {
		if (busy) {
			return;
		}
		if (isDirty()) {
			say('Save your changes first, then Publish.', 'warn');
			return;
		}
		ask('Publish this product? It will be live in the shop.', 'Publish', function () {
			setBusy(true);
			say('Publishing...');
			api('POST', 'products/' + cfg.product + '/status', { status: 'publish', confirm: true }).then(after).catch(fail);
		});
	}

	function toggleHistory() {
		if (ui.historyPanel && !ui.historyPanel.hidden) {
			ui.historyPanel.hidden = true;
			return;
		}
		if (!ui.historyPanel) {
			ui.historyPanel = el('div', 'twd-sk-shop-ed__historylist');
			ui.dialog.insertBefore(ui.historyPanel, ui.message);
		}
		while (ui.historyPanel.firstChild) {
			ui.historyPanel.removeChild(ui.historyPanel.firstChild);
		}
		ui.historyPanel.hidden = false;
		var list = data.versions || [];
		if (!list.length) {
			ui.historyPanel.appendChild(el('p', 'twd-sk-shop-ed__hint', 'No versions yet. The first Save keeps the product as it was, so you can always go back.'));
			return;
		}
		list.forEach(function (v, i) {
			var line = el('div', 'twd-sk-shop-ed__version');
			var when = new Date(v.time * 1000);
			line.appendChild(el('span', '', 'Version ' + v.v + ', ' + when.toLocaleString() + (v.who ? ', ' + v.who : '') + ': ' + v.note));
			if (i > 0) {
				line.appendChild(button('Restore', 'twd-sk-shop-ed__btn--small', function () {
					if (isDirty()) {
						say('Save or close without saving first.', 'warn');
						return;
					}
					setBusy(true);
					say('Restoring version ' + v.v + '...');
					api('POST', 'products/' + cfg.product + '/restore', { v: v.v }).then(function (d) {
						ui.historyPanel.hidden = true;
						after(d);
					}).catch(fail);
				}));
			} else {
				line.appendChild(el('span', 'twd-sk-shop-ed__hint', '(the latest)'));
			}
			ui.historyPanel.appendChild(line);
		});
	}

	// An inline question card (no browser dialogs).
	function ask(question, yesText, onYes, noText) {
		while (ui.confirm.firstChild) {
			ui.confirm.removeChild(ui.confirm.firstChild);
		}
		ui.confirm.appendChild(el('p', '', question));
		var yes = button(yesText, 'twd-sk-shop-ed__btn--main', function () {
			ui.confirm.hidden = true;
			onYes();
		});
		var no = button(noText || 'Cancel', 'twd-sk-shop-ed__btn--line', function () {
			ui.confirm.hidden = true;
		});
		ui.confirm.appendChild(yes);
		ui.confirm.appendChild(no);
		ui.confirm.hidden = false;
		no.focus();
	}

	function requestClose() {
		if (busy) {
			return;
		}
		if (isDirty()) {
			ask('You have changes that are not saved.', 'Close and lose them', reallyClose, 'Keep editing');
			return;
		}
		reallyClose();
	}

	function reallyClose() {
		ui.root.hidden = true;
		document.documentElement.classList.remove('twd-sk-shop-ed-open');
		if (savedSomething) {
			// Show the saved product on the page.
			window.location.reload();
			return;
		}
		if (lastFocus && lastFocus.focus) {
			lastFocus.focus();
		}
	}

	// -- Add a product (shop and category pages) ------------------------------------------

	function addProduct(btn) {
		btn.disabled = true;
		btn.textContent = 'Making a draft...';
		api('POST', 'products', { category: cfg.category || 0 }).then(function (d) {
			if (d.url) {
				var sep = d.url.indexOf('?') === -1 ? '?' : '&';
				window.location.href = d.url + sep + 'twd_sk_shop_open=1';
			}
		}).catch(function (e) {
			btn.disabled = false;
			btn.textContent = 'Add a product';
			btn.title = e.message;
		});
	}

	// -- Start ----------------------------------------------------------------------------

	/* A launcher: a floating button (when floating is true) and an item in the WordPress admin bar.
	 * Both call onClick with the floating button, or with the admin bar link when there is none. */
	function launcher(text, key, floating, onClick) {
		var b = null;
		if (floating) {
			b = el('button', 'twd-sk-shop-launch', text);
			b.type = 'button';
			b.addEventListener('click', function () {
				onClick(b);
			});
			document.body.appendChild(b);
		}
		var bar = document.getElementById('wp-admin-bar-root-default');
		if (bar) {
			var li = el('li', '');
			li.id = 'wp-admin-bar-twd-sk-shop-' + key;
			var a = el('a', 'ab-item', text);
			a.href = '#';
			a.setAttribute('role', 'button');
			a.addEventListener('click', function (e) {
				e.preventDefault();
				onClick(b || a);
			});
			li.appendChild(a);
			bar.appendChild(li);
		}
	}

	if (cfg.mode === 'edit' && cfg.product) {
		launcher('Edit product', 'edit', true, function () {
			open();
		});
		launcher('Add a product', 'add', false, addProduct);
		// A new draft opens straight into the window.
		if (/[?&]twd_sk_shop_open=1/.test(window.location.search)) {
			open();
		}
	} else if (cfg.mode === 'add') {
		launcher('Add a product', 'add', true, addProduct);
	} else if (cfg.mode === 'bar') {
		launcher('Add a product', 'add', false, addProduct);
	}
}());
