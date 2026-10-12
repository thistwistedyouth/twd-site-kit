/* TWD Site Kit: the setup page (Settings > Set up site). Vanilla JS, no build step.
 *
 * Six steps: site type, style, brief, pages (the list of pages and the sections on each), pictures, build. Each Next saves the choices on the server
 * (so leaving and coming back resumes), and the build runs one request per step so nothing runs
 * long. Every page is made as a draft. Pictures are chosen with the WordPress media library; only
 * their IDs are sent. Text is set as text only, and there are no browser pop-up dialogs. */
(function () {
	'use strict';

	var cfg = window.TWD_SK_SETUP;
	var root = document.getElementById('twd-sk-setup');
	if (!cfg || !root) {
		return;
	}

	var STEPS = [
		{ id: 'type', label: 'Site type' },
		{ id: 'style', label: 'Style' },
		{ id: 'brief', label: 'Brief' },
		{ id: 'pages', label: 'Pages' },
		{ id: 'pictures', label: 'Pictures' },
		{ id: 'build', label: 'Build' }
	];
	var S = { data: null, step: 'type', building: false, results: null };
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

	function show(node, yes) {
		if (yes) {
			node.removeAttribute('hidden');
		} else {
			node.setAttribute('hidden', '');
		}
	}

	function button(label, primary, onClick) {
		var b = el('button', { type: 'button', className: 'button' + (primary ? ' button-primary' : ''), text: label });
		b.addEventListener('click', onClick);
		return b;
	}

	function message(text, ok) {
		clear(ui.msg);
		if (!text) {
			show(ui.msg, false);
			return;
		}
		ui.msg.className = 'notice inline ' + (ok ? 'notice-success' : 'notice-error') + ' twd-sk-setup__msg';
		ui.msg.appendChild(el('p', { text: text }));
		show(ui.msg, true);
	}

	function list(items, className) {
		var ul = el('ul', { className: className || 'twd-sk-setup__list' });
		(items || []).forEach(function (t) {
			ul.appendChild(el('li', { text: t }));
		});
		return ul;
	}

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
					var msg = 'Something went wrong (error ' + res.status + '). Try again.';
					if (res.status === 401) {
						msg = 'Your sign-in has timed out. Reload the page and sign in again.';
					} else if (res.status === 429) {
						msg = 'Too many requests. Wait a minute and try again.';
					} else if (data && typeof data.message === 'string' && data.message) {
						msg = data.message;
					}
					var err = new Error(msg);
					err.status = res.status;
					throw err;
				}
				return data;
			});
		}, function () {
			throw new Error('Could not reach the site. Check your connection and try again.');
		});
	}

	function confirmInline(host, text, yesLabel, onYes) {
		var box = el('div', { className: 'notice notice-warning inline twd-sk-setup__confirm', role: 'group', 'aria-label': 'Please confirm' });
		box.appendChild(el('p', { text: text }));
		var yes = button(yesLabel, true, function () {
			host.removeChild(box);
			onYes();
		});
		var no = button('Cancel', false, function () {
			host.removeChild(box);
		});
		box.appendChild(el('p', {}, [yes, el('span', { text: ' ' }), no]));
		host.appendChild(box);
		yes.focus();
	}

	function copyText(text, box, okText) {
		var copied = false;
		try {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text);
				copied = true;
			}
		} catch (e) {
			copied = false;
		}
		box.value = text;
		show(box, !copied);
		if (!copied) {
			box.focus();
			box.select();
		}
		message(copied ? okText : 'Press Ctrl+C (Cmd+C on a Mac) to copy the selected text.', true);
	}

	function radios(name, options, current, describe) {
		var group = el('fieldset', { className: 'twd-sk-setup__radios' });
		Object.keys(options).forEach(function (key) {
			var id = 'twd-sk-setup-' + name + '-' + key;
			var input = el('input', { type: 'radio', id: id, name: 'twd-sk-setup-' + name, value: key });
			input.checked = key === current;
			group.appendChild(el('label', { className: 'twd-sk-setup__radio', 'for': id }, [
				input,
				el('strong', { text: describe(options[key]).title }),
				el('span', { text: describe(options[key]).help })
			]));
		});
		return group;
	}

	function checked(name) {
		var r = root.querySelector('input[name="twd-sk-setup-' + name + '"]:checked');
		return r ? r.value : '';
	}

	/* ---- loading and saving ---- */

	function load() {
		api('GET', '/site/build').then(function (data) {
			S.data = data;
			S.step = data.draft.step || 'type';
			render();
		}, function (err) {
			clear(root);
			root.appendChild(el('div', { className: 'notice notice-error inline' }, [el('p', { text: err.message })]));
		});
	}

	function save(values) {
		return api('POST', '/site/build/draft', values).then(function (data) {
			S.data = data;
			if (data.errors && data.errors.length) {
				var e = new Error(data.errors.join(' '));
				e.kept = true;
				throw e;
			}
			return data;
		});
	}

	/* What each step sends when leaving it. */
	function valuesOf(step) {
		if (step === 'type') {
			return { mode: checked('mode') };
		}
		if (step === 'style') {
			return { pack: checked('pack') };
		}
		if (step === 'brief') {
			return { brief_text: ui.brief ? ui.brief.value : '' };
		}
		if (step === 'pictures') {
			return { pictures: picturesValue() };
		}
		if (step === 'pages' && ui.pagesDirty) {
			return { pages: ui.pages };
		}
		return {};
	}

	function go(step) {
		if (S.building) {
			return;
		}
		var values = valuesOf(S.step);
		values.step = step;
		message('Saving...', true);
		save(values).then(function () {
			S.step = step;
			render();
			message('', true);
		}, function (err) {
			// Stay on this step, with everything as typed, so the person can fix what was refused.
			message(err.message, false);
		});
	}

	/* ---- the page ---- */

	function render() {
		clear(root);
		var nav = el('ol', { className: 'twd-sk-setup__steps', 'aria-label': 'Steps' });
		STEPS.forEach(function (s, i) {
			var b = el('button', { type: 'button', className: 'twd-sk-setup__stepbtn', text: (i + 1) + '. ' + s.label });
			if (s.id === S.step) {
				b.setAttribute('aria-current', 'step');
			}
			b.addEventListener('click', function () {
				if (s.id !== S.step) {
					go(s.id);
				}
			});
			nav.appendChild(el('li', {}, [b]));
		});
		root.appendChild(nav);
		ui.msg = el('div', { role: 'status', 'aria-live': 'polite', hidden: '' });
		var body = el('div', { className: 'twd-sk-setup__body' });
		root.appendChild(body);
		root.appendChild(ui.msg);
		if (S.data.safe) {
			body.appendChild(el('div', { className: 'notice notice-warning inline' }, [el('p', { text: 'Safe mode is on, so the site cannot be built from here. Turn safe mode off first.' })]));
			return;
		}
		if (S.data.done) {
			body.appendChild(el('div', { className: 'notice notice-info inline' }, [el('p', { text: 'This site was last built on ' + S.data.done + ' (UTC). Building again makes new versions of the pages it made; it never touches other pages.' })]));
		}
		var builders = { type: buildType, style: buildStyle, brief: buildBrief, pages: buildPages, pictures: buildPictures, build: buildBuild };
		builders[S.step](body);

		var idx = 0;
		STEPS.forEach(function (s, i) {
			if (s.id === S.step) {
				idx = i;
			}
		});
		var nextRow = el('p', { className: 'twd-sk-setup__nav' });
		if (idx > 0) {
			nextRow.appendChild(button('Back', false, function () {
				go(STEPS[idx - 1].id);
			}));
		}
		if (idx < STEPS.length - 1) {
			nextRow.appendChild(button('Save and continue', true, function () {
				go(STEPS[idx + 1].id);
			}));
		}
		body.appendChild(nextRow);
	}

	function heading(body, title, help) {
		body.appendChild(el('h2', { text: title }));
		if (help) {
			body.appendChild(el('p', { className: 'description', text: help }));
		}
	}

	/* 1. Site type */
	function buildType(body) {
		heading(body, 'What kind of site is this?', 'This sets the wording the editor and the AI use, the facts outline, the page types and the search engine details. You can change it later in the Site tab.');
		body.appendChild(radios('mode', S.data.modes, S.data.draft.mode, function (m) {
			return { title: m.label, help: m.help };
		}));
	}

	/* 2. Style */
	function buildStyle(body) {
		heading(body, 'Choose a style', 'Colours, fonts and corners. Every style is checked so text stays easy to read. You can fine-tune it later in the Site tab.');
		body.appendChild(radios('pack', S.data.packs, S.data.draft.pack, function (p) {
			return { title: p.name, help: p.description };
		}));
	}

	/* 3. Brief */
	function looksLikeJson(text) {
		var t = text.replace(/^\s*```[a-z]*\s*/i, '').trim();
		return t.charAt(0) === '{';
	}

	function planSummary(host) {
		clear(host);
		var plan = S.data.plan;
		host.appendChild(el('h3', { text: 'What will be made' }));
		var rows = plan.pages.map(function (p) {
			var what = p.action === 'skip' ? 'already exists, left alone' : (p.action === 'rebuild' ? 'made again as a new version' : 'new draft');
			return p.title + ' (' + p.type + '): ' + what;
		});
		host.appendChild(list(rows));
		if (plan.errors.length || plan.ignored.length || plan.blocking.length) {
			host.appendChild(el('h3', { text: 'Things to look at in the brief' }));
			host.appendChild(list(plan.errors.concat(plan.ignored).concat(plan.blocking.map(function (b) {
				return 'Hard to read: ' + b;
			}))));
		}
	}

	function buildBrief(body) {
		heading(body, 'The brief', 'Paste a site brief (the JSON file from the interview prompt), or write plain notes and turn them into a brief with AI. Leave it empty to make a simple Home, About and Contact.');
		ui.brief = el('textarea', { id: 'twd-sk-setup-brief', className: 'large-text code', rows: '16', spellcheck: 'false', 'aria-label': 'The brief or your notes' });
		ui.brief.value = S.data.draft.brief_text || '';
		body.appendChild(ui.brief);
		var summary = el('div', { className: 'twd-sk-setup__summary' });
		var promptBox = el('textarea', { className: 'large-text', rows: '4', readonly: '', hidden: '', 'aria-label': 'The interview prompt, selected so you can copy it' });
		var check = button('Check the brief', true, function () {
			if (ui.brief.value.trim() && !looksLikeJson(ui.brief.value)) {
				message('That looks like notes rather than a brief. ' + (S.data.ai ? 'Use "Turn my notes into a brief" first.' : 'Run the interview prompt in a Claude conversation and paste the JSON it gives you.'), false);
				return;
			}
			message('Checking...', true);
			save({ brief_text: ui.brief.value }).then(function () {
				planSummary(summary);
				message(S.data.plan.errors.length ? 'Checked. Some parts need a look (listed below); everything else is fine.' : 'Checked. Nothing is saved to the site until you build it.', !S.data.plan.errors.length);
			}, function (err) {
				message(err.message, false);
			});
		});
		var notes = button('Turn my notes into a brief (AI)', false, function () {
			if (!ui.brief.value.trim()) {
				message('Write or paste some notes first.', false);
				return;
			}
			notes.disabled = true;
			message('Asking the AI. This can take a minute...', true);
			api('POST', '/site/build/notes', { notes: ui.brief.value }).then(function (data) {
				notes.disabled = false;
				ui.brief.value = data.json;
				message('Here is the brief. Read it, change anything that is wrong or missing, then press Check the brief. Nothing has been saved to the site.', true);
			}, function (err) {
				notes.disabled = false;
				message(err.message, false);
			});
		});
		show(notes, !!S.data.ai);
		var prompt = button('Copy the interview prompt', false, function () {
			save({ mode: S.data.draft.mode }).then(function () {
				return api('GET', '/site/brief');
			}).then(function (data) {
				copyText(data.prompt, promptBox, 'Copied. Paste it into a Claude conversation. It finishes with a brief to paste here.');
			}, function (err) {
				message(err.message, false);
			});
		});
		body.appendChild(el('p', { className: 'twd-sk-setup__actions' }, [check, notes, prompt]));
		body.appendChild(promptBox);
		body.appendChild(summary);
		if (S.data.draft.brief_text) {
			planSummary(summary);
		}
	}

	/* 4. Pages: the list of pages and the sections on each */
	function copyPages(list) {
		return list.map(function (p) {
			return {
				type: p.type,
				title: p.title,
				topic: p.topic || '',
				notes: p.notes || '',
				seo: p.seo || {},
				parts: (p.parts || []).map(function (s) {
					return { component: s.component, variant: s.variant || '' };
				})
			};
		});
	}

	function select(id, options, value, label) {
		var s = el('select', { id: id, 'aria-label': label || '' });
		Object.keys(options).forEach(function (k) {
			s.appendChild(el('option', { value: k, text: options[k] }));
		});
		s.value = value;
		return s;
	}

	function move(list, i, by) {
		var j = i + by;
		if (j < 0 || j >= list.length) {
			return false;
		}
		var t = list[i];
		list[i] = list[j];
		list[j] = t;
		return true;
	}

	function buildPages(body) {
		heading(body, 'Pages', 'These pages will be made, in this order. Change their titles and the sections on each page before anything is built. Every page starts with a hero section, which holds its main heading.');
		ui.pages = copyPages(S.data.plan.pages);
		ui.pagesDirty = false;
		ui.addPage = null;
		if (S.data.plan.source === 'edited') {
			body.appendChild(el('div', { className: 'notice notice-info inline' }, [el('p', { text: 'You have changed the page list, so it is used instead of the brief\'s. ' }, [button('Use the brief\'s pages again', false, function () {
				message('Saving...', true);
				save({ reset_pages: true }).then(function () {
					render();
					message('The brief\'s pages are used again.', true);
				}, function (err) {
					message(err.message, false);
				});
			})])]));
		}
		var types = {};
		Object.keys(S.data.recipes).forEach(function (k) {
			types[k] = S.data.recipes[k].label;
		});
		var compLabels = {};
		Object.keys(S.data.components).forEach(function (k) {
			compLabels[k] = S.data.components[k].label;
		});
		var host = el('div', { className: 'twd-sk-setup__pages' });
		body.appendChild(host);

		function changed() {
			ui.pagesDirty = true;
		}

		function draw() {
			clear(host);
			ui.pages.forEach(function (pg, i) {
				var n = i + 1;
				var card = el('fieldset', { className: 'twd-sk-setup__page' });
				card.appendChild(el('legend', { text: 'Page ' + n + ': ' + (pg.title || 'untitled') }));
				var title = el('input', { type: 'text', className: 'regular-text', id: 'twd-sk-setup-page-title-' + n, maxlength: '120', value: pg.title });
				title.addEventListener('input', function () {
					pg.title = title.value;
					changed();
				});
				var type = select('twd-sk-setup-page-type-' + n, types, pg.type);
				type.addEventListener('change', function () {
					var r = S.data.recipes[type.value];
					pg.type = type.value;
					pg.parts = r.parts.map(function (s) {
						return { component: s.component, variant: s.variant };
					});
					if (!pg.title && r.title) {
						pg.title = r.title;
					}
					changed();
					draw();
				});
				var topic = el('input', { type: 'text', className: 'regular-text', id: 'twd-sk-setup-page-topic-' + n, maxlength: '120', value: pg.topic });
				topic.addEventListener('input', function () {
					pg.topic = topic.value;
					changed();
				});
				var topicBox = el('div', {}, [el('label', { 'for': topic.id, text: 'What is the page about?' }), topic]);
				show(topicBox, !!S.data.recipes[pg.type].needs_topic);
				var notes = el('textarea', { id: 'twd-sk-setup-page-notes-' + n, className: 'large-text', rows: '2', maxlength: '1500' });
				notes.value = pg.notes;
				notes.addEventListener('input', function () {
					pg.notes = notes.value;
					changed();
				});
				card.appendChild(el('div', { className: 'twd-sk-setup__pagefields' }, [
					el('div', {}, [el('label', { 'for': title.id, text: 'Title' }), title]),
					el('div', {}, [el('label', { 'for': type.id, text: 'Type' }), type]),
					topicBox,
					el('div', {}, [el('label', { 'for': notes.id, text: 'Notes for this page (optional)' }), notes])
				]));

				var ol = el('ol', { className: 'twd-sk-setup__sections' });
				pg.parts.forEach(function (part, j) {
					var m = j + 1;
					var comp = select('twd-sk-setup-sec-' + n + '-' + m, compLabels, part.component, 'Section ' + m + ' of page ' + n);
					var variants = S.data.components[part.component] ? S.data.components[part.component].variants : { '': 'Standard' };
					var variant = select('twd-sk-setup-var-' + n + '-' + m, variants, part.variant, 'Layout of section ' + m + ' of page ' + n);
					comp.addEventListener('change', function () {
						part.component = comp.value;
						part.variant = '';
						changed();
						draw();
					});
					variant.addEventListener('change', function () {
						part.variant = variant.value;
						changed();
					});
					var up = button('Up', false, function () {
						if (move(pg.parts, j, -1)) {
							changed();
							draw();
						}
					});
					var down = button('Down', false, function () {
						if (move(pg.parts, j, 1)) {
							changed();
							draw();
						}
					});
					var del = button('Remove', false, function () {
						pg.parts.splice(j, 1);
						changed();
						draw();
					});
					up.disabled = j === 0;
					down.disabled = j === pg.parts.length - 1;
					del.disabled = pg.parts.length <= 1;
					ol.appendChild(el('li', {}, [comp, variant, up, down, del]));
				});
				card.appendChild(el('p', { className: 'description', text: 'Sections, top to bottom:' }));
				card.appendChild(ol);
				var addSec = button('Add a section', false, function () {
					pg.parts.push({ component: 'text', variant: '' });
					changed();
					draw();
				});
				addSec.disabled = pg.parts.length >= S.data.max_sections;
				var pUp = button('Move page up', false, function () {
					if (move(ui.pages, i, -1)) {
						changed();
						draw();
					}
				});
				var pDown = button('Move page down', false, function () {
					if (move(ui.pages, i, 1)) {
						changed();
						draw();
					}
				});
				var pDel = button('Remove page', false, function () {
					ui.pages.splice(i, 1);
					changed();
					draw();
				});
				pUp.disabled = i === 0;
				pDown.disabled = i === ui.pages.length - 1;
				pDel.disabled = ui.pages.length <= 1;
				card.appendChild(el('p', { className: 'twd-sk-setup__actions' }, [addSec, pUp, pDown, pDel]));
				host.appendChild(card);
			});
			if (ui.addPage) {
				ui.addPage.disabled = ui.pages.length >= S.data.max_pages;
			}
		}
		draw();
		var addPage = button('Add a page', false, function () {
			var r = S.data.recipes.service || S.data.recipes[Object.keys(S.data.recipes)[0]];
			ui.pages.push({ type: S.data.recipes.service ? 'service' : Object.keys(S.data.recipes)[0], title: '', topic: '', notes: '', seo: {}, parts: r.parts.map(function (s) {
				return { component: s.component, variant: s.variant };
			}) });
			changed();
			draw();
			var last = host.querySelector('#twd-sk-setup-page-title-' + ui.pages.length);
			if (last) {
				last.focus();
			}
		});
		ui.addPage = addPage;
		addPage.disabled = ui.pages.length >= S.data.max_pages;
		body.appendChild(el('p', {}, [addPage]));
	}

	/* 5. Pictures */
	function media(multiple, done) {
		if (!(window.wp && window.wp.media)) {
			message('The media library is not available on this page. Reload and try again.', false);
			return;
		}
		var frame = window.wp.media({ title: multiple ? 'Choose pictures' : 'Choose a picture', button: { text: 'Use ' + (multiple ? 'these pictures' : 'this picture') }, multiple: multiple, library: { type: 'image' } });
		frame.on('select', function () {
			done(frame.state().get('selection').toJSON());
		});
		frame.open();
	}

	function thumb(url) {
		return url ? el('img', { className: 'twd-sk-setup__thumb', src: url, alt: '' }) : el('span', { className: 'twd-sk-setup__thumb twd-sk-setup__thumb--empty', text: 'No picture' });
	}

	function slugify(text) {
		return String(text || '').toLowerCase().replace(/\.[a-z0-9]+$/, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
	}

	function picturesValue() {
		var out = { slots: {}, custom: [] };
		(ui.slotRows || []).forEach(function (r) {
			if (r.id) {
				out.slots[r.slot] = { id: r.id, description: r.desc.value };
			}
		});
		(ui.customRows || []).forEach(function (r) {
			out.custom.push({ id: r.id, name: r.name.value.trim(), description: r.desc.value, use_on: r.use.value });
		});
		return out;
	}

	function buildPictures(body) {
		heading(body, 'Pictures', 'Choose pictures from the media library, or upload new ones there. Slot pictures go to a fixed place. Named pictures can be placed by the AI where they fit, and their description becomes the alt text that screen readers and search engines read.');
		ui.slotRows = [];
		ui.customRows = [];
		var saved = S.data.draft.pictures || { slots: {}, custom: [] };

		body.appendChild(el('h3', { text: 'Slots' }));
		var slotTable = el('div', { className: 'twd-sk-setup__pics' });
		Object.keys(S.data.slots).forEach(function (slot) {
			var info = S.data.slots[slot];
			var have = saved.slots[slot] || null;
			var row = { slot: slot, id: have ? have.id : 0 };
			var holder = el('div', { className: 'twd-sk-setup__thumbbox' }, [thumb(have ? have.url : '')]);
			row.desc = el('input', { type: 'text', className: 'regular-text', maxlength: '200', id: 'twd-sk-setup-slot-' + slot, value: have ? have.description : '' });
			var pick = button(have ? 'Change' : 'Choose picture', false, function () {
				media(false, function (sel) {
					if (!sel.length) {
						return;
					}
					row.id = sel[0].id;
					clear(holder);
					holder.appendChild(thumb(sel[0].sizes && sel[0].sizes.thumbnail ? sel[0].sizes.thumbnail.url : sel[0].url));
					if (!row.desc.value) {
						row.desc.value = sel[0].alt || '';
					}
					pick.textContent = 'Change';
				});
			});
			var remove = button('Remove', false, function () {
				row.id = 0;
				clear(holder);
				holder.appendChild(thumb(''));
				pick.textContent = 'Choose picture';
			});
			ui.slotRows.push(row);
			slotTable.appendChild(el('div', { className: 'twd-sk-setup__pic' }, [
				holder,
				el('div', {}, [
					el('strong', { text: info.label }),
					el('p', { className: 'description', text: info.help }),
					el('label', { 'for': 'twd-sk-setup-slot-' + slot, text: 'Description (alt text)' }),
					row.desc,
					el('p', {}, [pick, el('span', { text: ' ' }), remove])
				])
			]));
		});
		body.appendChild(slotTable);

		body.appendChild(el('h3', { text: 'Named pictures' }));
		body.appendChild(el('p', { className: 'description', text: 'Give each picture a short name (letters, numbers and dashes, for example front-view), say what it shows, and where it may be used. Up to ' + S.data.max_pictures + '.' }));
		var customBox = el('div', { className: 'twd-sk-setup__pics' });
		body.appendChild(customBox);
		var pages = S.data.plan.pages;

		function addRow(p) {
			var n = ui.customRows.length + 1;
			var row = { id: p.id };
			row.name = el('input', { type: 'text', className: 'regular-text', maxlength: '40', id: 'twd-sk-setup-pic-name-' + n, value: p.name || '' });
			row.desc = el('input', { type: 'text', className: 'regular-text', maxlength: '200', id: 'twd-sk-setup-pic-desc-' + n, value: p.description || '' });
			row.use = el('select', { id: 'twd-sk-setup-pic-use-' + n });
			row.use.appendChild(el('option', { value: 'any', text: 'Any page' }));
			pages.forEach(function (pg) {
				row.use.appendChild(el('option', { value: pg.slug, text: 'Only on ' + pg.title }));
			});
			row.use.value = p.use_on || 'any';
			if (row.use.value !== (p.use_on || 'any')) {
				row.use.value = 'any';
			}
			var box = el('div', { className: 'twd-sk-setup__pic' });
			var remove = button('Remove', false, function () {
				customBox.removeChild(box);
				ui.customRows = ui.customRows.filter(function (r) {
					return r !== row;
				});
			});
			box.appendChild(el('div', { className: 'twd-sk-setup__thumbbox' }, [thumb(p.url)]));
			box.appendChild(el('div', {}, [
				el('label', { 'for': row.name.id, text: 'Name' }), row.name,
				el('label', { 'for': row.desc.id, text: 'Description (alt text)' }), row.desc,
				el('label', { 'for': row.use.id, text: 'Use on' }), row.use,
				el('p', {}, [remove])
			]));
			ui.customRows.push(row);
			customBox.appendChild(box);
		}
		saved.custom.forEach(addRow);
		body.appendChild(el('p', {}, [button('Add pictures', false, function () {
			media(true, function (sel) {
				var taken = {};
				ui.customRows.forEach(function (r) {
					taken[r.name.value] = true;
				});
				sel.forEach(function (a) {
					if (ui.customRows.length >= S.data.max_pictures) {
						return;
					}
					var name = slugify(a.title || a.filename || 'picture') || 'picture';
					var base = name;
					var i = 2;
					while (taken[name]) {
						name = base + '-' + i++;
					}
					taken[name] = true;
					addRow({ id: a.id, name: name, description: a.alt || '', use_on: 'any', url: a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url });
				});
			});
		})]));
	}

	/* 6. Build */
	function buildBuild(body) {
		var plan = S.data.plan;
		heading(body, 'Build the site', 'Each step runs on its own, so a slow answer never stops the rest. Every page is made as a draft and nothing is published. You can run it again: pages this setup made get a new version, other pages are never touched.');
		if (plan.errors.length || plan.blocking.length) {
			body.appendChild(el('div', { className: 'notice notice-warning inline' }, [el('p', { text: 'The brief has parts that will be skipped:' }), list(plan.errors.concat(plan.blocking))]));
		}
		var aiId = 'twd-sk-setup-ai';
		var aiBox = el('input', { type: 'checkbox', id: aiId });
		aiBox.checked = !!plan.ai;
		aiBox.disabled = !plan.ai;
		body.appendChild(el('p', {}, [el('label', { 'for': aiId }, [aiBox, el('span', { text: plan.ai ? ' Write the pages with AI from the facts (otherwise outlines with [PLACEHOLDER] markers)' : ' Writing with AI needs a key in the Articles plugin. Without it, pages are made as outlines to fill in.' })])]));
		body.appendChild(el('p', { className: 'description', text: 'Pictures: ' + plan.pictures.slots + ' in slots, ' + plan.pictures.custom + ' named.' }));

		var rows = [];
		var ol = el('ol', { className: 'twd-sk-setup__run' });
		plan.steps.forEach(function (s) {
			var state = el('span', { className: 'twd-sk-setup__state', text: 'Waiting' });
			var detail = el('div', { className: 'twd-sk-setup__detail' });
			var retry = button('Try again', false, function () {
				runFrom(rows.indexOf(row), true);
			});
			show(retry, false);
			var li = el('li', {}, [el('span', { text: s.label + ' ' }), state, detail, retry]);
			var row = { step: s, li: li, state: state, detail: detail, retry: retry };
			rows.push(row);
			ol.appendChild(li);
		});
		body.appendChild(ol);
		var results = el('div', { className: 'twd-sk-setup__results' });
		var buildBtn = button('Build the site', true, function () {
			runFrom(0, false);
		});
		var reset = button('Start again', false, function () {
			confirmInline(body, 'Forget the choices on this page (type, style, brief and pictures)? Pages already made stay as drafts.', 'Yes, start again', function () {
				api('POST', '/site/build/reset', { confirm: true }).then(function (data) {
					S.data = data;
					S.step = 'type';
					render();
					message('Started again.', true);
				}, function (err) {
					message(err.message, false);
				});
			});
		});
		body.appendChild(el('p', { className: 'twd-sk-setup__actions' }, [buildBtn, reset]));
		body.appendChild(results);

		function setState(row, text, cls) {
			row.state.textContent = text;
			row.state.className = 'twd-sk-setup__state ' + (cls || '');
		}

		function runFrom(start, single) {
			if (S.building) {
				return;
			}
			S.building = true;
			buildBtn.disabled = true;
			var i = start;
			function next() {
				if (i >= rows.length || (single && i > start)) {
					S.building = false;
					buildBtn.disabled = false;
					return;
				}
				var row = rows[i];
				setState(row, 'Working...', 'is-working');
				show(row.retry, false);
				clear(row.detail);
				api('POST', '/site/build/step', { step: row.step.id, index: row.step.index, ai: aiBox.checked }).then(function (out) {
					setState(row, 'Done', 'is-done');
					row.detail.appendChild(list(out.report));
					if (out.pages) {
						showResults(out.pages);
					}
					i++;
					next();
				}, function (err) {
					setState(row, 'Failed', 'is-failed');
					row.detail.appendChild(el('p', { text: err.message }));
					show(row.retry, true);
					if (row.step.id === 'settings') {
						S.building = false;
						buildBtn.disabled = false;
						message('The first step failed, so no pages were made. Fix the problem and try again.', false);
						return;
					}
					i++;
					next();
				});
			}
			message(single ? 'Running that step again...' : 'Building. Keep this page open until every step says Done.', true);
			next();
		}

		function showResults(pages) {
			clear(results);
			results.appendChild(el('h3', { text: 'Your draft pages' }));
			var ul = el('ul', { className: 'twd-sk-setup__list' });
			pages.forEach(function (p) {
				var a = el('a', { href: p.url, text: p.title || ('Page ' + p.id) });
				ul.appendChild(el('li', {}, [a, el('span', { text: ': ' + (p.must ? p.must + ' to fix' : 'nothing marked as missing') + (p.check ? ', ' + p.check + ' to check' : '') })]));
			});
			results.appendChild(ul);
			results.appendChild(el('p', { text: 'Open a page and use Edit with AI to change it, replace anything marked [PLACEHOLDER], then publish it from the Pages tab. ' }, [el('a', { href: cfg.homeUrl, text: 'Open the site' })]));
			message('Built. Every page is a draft.', true);
		}
	}

	load();
})();
