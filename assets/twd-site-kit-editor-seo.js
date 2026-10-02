/* TWD Site Kit: front-end editor, the Search tab. Vanilla JS, no build step.
 *
 * Loaded for people who can edit a kit page, after the main editor script, and never in safe mode.
 * It shows and saves the page's search title, description, social picture, keep-out-of-search
 * setting and address, lists a few quality checks, and checks the open page for duplicate
 * structured data. Every value is checked again by the server. Nothing here calls any other site. */
(function () {
	'use strict';

	var ED = window.TWD_SK_ED;
	if (!ED || !ED.cfg || !ED.cfg.pageId || !ED.cfg.isKitPage) {
		return;
	}
	var el = ED.el;
	var clear = ED.clear;
	var show = ED.show;
	var button = ED.button;
	var message = ED.message;
	var listInto = ED.listInto;
	var api = ED.api;
	var path = '/pages/' + ED.cfg.pageId + '/seo';

	var TITLE_GOOD = 60;
	var DESC_GOOD = 155;
	var S = { data: null, imageId: 0, loaded: false };
	var ui = {};

	function field(labelText, control, help) {
		var kids = [el('label', { className: 'twd-sk-ed__label', 'for': control.getAttribute('id'), text: labelText })];
		if (help) {
			kids.push(el('p', { className: 'twd-sk-ed__help', text: help }));
		}
		kids.push(control);
		return el('div', { className: 'twd-sk-ed__field' }, kids);
	}

	function counter(input, box, good, max) {
		function update() {
			var n = input.value.length;
			var text = n + ' characters. ';
			if (n === 0) {
				text += 'Empty: the page name is used.';
			} else if (n > max) {
				text += 'Too long, it will be cut at ' + max + '.';
			} else if (n > good) {
				text += 'Search results may cut it short after about ' + good + '.';
			} else {
				text += 'Good length.';
			}
			box.textContent = text;
		}
		input.addEventListener('input', update);
		return update;
	}

	function build(panel) {
		ui.panel = panel;
		panel.appendChild(el('p', { className: 'twd-sk-ed__help', text: 'What search engines and social sites show for this page. Plain text only.' }));
		ui.note = el('p', { className: 'twd-sk-ed__help' });
		ui.msg = el('div', { className: 'twd-sk-ed__msg', role: 'status', hidden: '' });
		ui.leftovers = el('div', { className: 'twd-sk-ed__msg', role: 'status', hidden: '' });

		ui.title = el('input', { id: 'twd-sk-seo-title', type: 'text', className: 'twd-sk-ed__input', autocomplete: 'off', 'aria-describedby': 'twd-sk-seo-title-count' });
		ui.titleCount = el('p', { id: 'twd-sk-seo-title-count', className: 'twd-sk-ed__help', 'aria-live': 'polite' });
		ui.desc = el('textarea', { id: 'twd-sk-seo-desc', className: 'twd-sk-ed__textarea', rows: '3', 'aria-describedby': 'twd-sk-seo-desc-count' });
		ui.descCount = el('p', { id: 'twd-sk-seo-desc-count', className: 'twd-sk-ed__help', 'aria-live': 'polite' });
		ui.updTitle = counter(ui.title, ui.titleCount, TITLE_GOOD, 120);
		ui.updDesc = counter(ui.desc, ui.descCount, DESC_GOOD, 300);

		ui.imgPreview = el('img', { className: 'twd-sk-ed__thumb', alt: '', hidden: '' });
		ui.imgId = el('input', { id: 'twd-sk-seo-image', type: 'text', className: 'twd-sk-ed__input', inputmode: 'numeric', autocomplete: 'off' });
		ui.imgPick = button('Choose from the media library', 'secondary', pick);
		ui.imgClear = button('Remove picture', 'secondary', function () {
			ui.imgId.value = '';
			ui.imgPreview.setAttribute('hidden', '');
		});
		ui.noindex = el('input', { id: 'twd-sk-seo-noindex', type: 'checkbox' });
		ui.slug = el('input', { id: 'twd-sk-seo-slug', type: 'text', className: 'twd-sk-ed__input', autocomplete: 'off', spellcheck: 'false' });
		ui.slugHelp = el('p', { className: 'twd-sk-ed__help' });

		ui.quality = el('div', { className: 'twd-sk-ed__card', hidden: '' });
		ui.schemaOut = el('div', { className: 'twd-sk-ed__msg', role: 'status', hidden: '' });
		ui.confirmHost = el('div');
		ui.save = button('Save search details', 'primary', onSave);

		panel.appendChild(ui.note);
		panel.appendChild(ui.leftovers);
		panel.appendChild(field('Search title', ui.title, 'The blue link in search results.'));
		panel.appendChild(ui.titleCount);
		panel.appendChild(field('Search description', ui.desc, 'The grey text under it.'));
		panel.appendChild(ui.descCount);
		panel.appendChild(field('Picture for sharing', ui.imgId, 'The picture shown when the page is shared. It must be in the media library; type its number or choose it.'));
		panel.appendChild(ui.imgPreview);
		panel.appendChild(el('div', { className: 'twd-sk-ed__actions' }, [ui.imgPick, ui.imgClear]));
		panel.appendChild(el('div', { className: 'twd-sk-ed__field' }, [
			ui.noindex,
			el('label', { className: 'twd-sk-ed__label', 'for': 'twd-sk-seo-noindex', text: ' Keep this page out of search results' })
		]));
		panel.appendChild(field('Address ending (slug)', ui.slug));
		panel.appendChild(ui.slugHelp);
		panel.appendChild(ui.confirmHost);
		panel.appendChild(ui.msg);
		panel.appendChild(el('div', { className: 'twd-sk-ed__actions' }, [ui.save]));
		panel.appendChild(el('h3', { className: 'twd-sk-ed__step-title', text: 'Page checks' }));
		panel.appendChild(ui.quality);
		panel.appendChild(el('h3', { className: 'twd-sk-ed__step-title', text: 'Structured data' }));
		panel.appendChild(el('p', { className: 'twd-sk-ed__help', text: 'Checks this open page for the same kind of structured data being printed twice, which can confuse search engines.' }));
		panel.appendChild(ui.schemaOut);
		panel.appendChild(el('div', { className: 'twd-sk-ed__actions' }, [button('Check structured data', 'secondary', checkSchema)]));

		ED.tabHooks.seo = load;
	}

	function pick() {
		if (!(window.wp && window.wp.media)) {
			message(ui.msg, 'The media library is not available here. Type the picture number instead.', false);
			return;
		}
		var frame = window.wp.media({ title: 'Choose a sharing picture', button: { text: 'Use this picture' }, multiple: false, library: { type: 'image' } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			ui.imgId.value = String(a.id);
			var url = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
			ui.imgPreview.setAttribute('src', url);
			show(ui.imgPreview, true);
		});
		frame.open();
	}

	function fill(d) {
		S.data = d;
		ui.title.value = d.title || '';
		ui.desc.value = d.description || '';
		ui.imgId.value = d.image_id ? String(d.image_id) : '';
		if (d.image_url) {
			ui.imgPreview.setAttribute('src', d.image_url);
			show(ui.imgPreview, true);
		} else {
			show(ui.imgPreview, false);
		}
		ui.noindex.checked = !!d.noindex;
		ui.slug.value = d.slug || '';
		ui.updTitle();
		ui.updDesc();

		var other = d.mode === 'other';
		[ui.title, ui.desc, ui.imgId, ui.noindex].forEach(function (c) {
			c.disabled = other;
		});
		ui.imgPick.disabled = other;
		ui.imgClear.disabled = other;
		var notes = {
			yoast: 'Saved in Yoast SEO, which prints the tags.',
			other: d.plugin + ' looks after the title, description, picture and keep-out setting on this site. Change them there. The address below can still be changed here.',
			none: 'No SEO plugin found, so this plugin saves these details and prints the tags itself.'
		};
		ui.note.textContent = notes[d.mode] || '';
		ui.slugHelp.textContent = d.is_site_page
			? 'This is your front page or posts page, so its address cannot be changed here.'
			: (d.status === 'publish' ? 'This page is live. Changing the address changes its link; you will be asked to confirm.' : 'This page is not live yet, so the address can change freely.');
		ui.slug.disabled = !!d.is_site_page;

		clear(ui.leftovers);
		if (d.leftovers && d.leftovers.length) {
			message(ui.leftovers, 'Example text is still in the title or description. Replace it before publishing.', false);
			listInto(ui.leftovers, d.leftovers.map(function (i) {
				return i.count + ' x ' + i.meaning;
			}));
		} else {
			show(ui.leftovers, false);
		}

		clear(ui.quality);
		if (d.quality && d.quality.length) {
			var lines = d.quality.map(function (q) {
				return q.label;
			});
			listInto(ui.quality, lines);
		} else {
			ui.quality.appendChild(el('p', { text: 'Nothing to fix. Pictures have descriptions, headings are in order and links say where they go.' }));
		}
		show(ui.quality, true);
		S.snap = JSON.stringify(collect(false));
	}

	function load() {
		if (S.loaded) {
			return;
		}
		S.loaded = true;
		api('GET', path).then(fill, function (e) {
			S.loaded = false;
			message(ui.msg, e.message, false);
		});
	}

	function collect(confirm) {
		var f = {};
		if (!ui.title.disabled) {
			f.title = ui.title.value;
			f.description = ui.desc.value;
			f.image_id = ui.imgId.value.replace(/\s+/g, '');
			f.noindex = ui.noindex.checked;
		}
		if (!ui.slug.disabled && S.data && ui.slug.value !== S.data.slug) {
			f.slug = ui.slug.value;
			if (confirm) {
				f.confirm_slug_change = true;
			}
		}
		return f;
	}

	function save(confirm) {
		ui.save.disabled = true;
		message(ui.msg, 'Saving...', true);
		api('POST', path, { fields: collect(confirm) }).then(function (d) {
			ui.save.disabled = false;
			fill(d);
			message(ui.msg, 'Saved.', true);
		}, function (e) {
			ui.save.disabled = false;
			if (e.code === 'twd_sk_slug_confirm') {
				message(ui.msg, '', true);
				askSlug();
				return;
			}
			message(ui.msg, e.message, false);
		});
	}

	function askSlug() {
		clear(ui.confirmHost);
		ED.confirmInline(ui.confirmHost, {
			text: 'This page is live. Changing its address changes its link. Anyone with the old link is sent to the new one. Change it?',
			yesLabel: 'Yes, change the address',
			onYes: function () {
				save(true);
			}
		});
	}

	function onSave() {
		clear(ui.confirmHost);
		var changed = S.data && !ui.slug.disabled && ui.slug.value !== S.data.slug;
		if (changed && S.data.status === 'publish') {
			askSlug();
			return;
		}
		save(false);
	}

	// Looks only at this page's own markup: the same kind of data printed twice is a problem.
	function checkSchema() {
		var nodes = document.querySelectorAll('script[type="application/ld+json"]');
		var counts = {};
		var bad = 0;
		Array.prototype.forEach.call(nodes, function (n) {
			var data;
			try {
				data = JSON.parse(n.textContent);
			} catch (e) {
				bad++;
				return;
			}
			var list = [];
			(function walk(x) {
				if (Array.isArray(x)) {
					x.forEach(walk);
				} else if (x && typeof x === 'object') {
					if (x['@graph']) {
						walk(x['@graph']);
					} else if (x['@type']) {
						list.push(x);
					}
				}
			})(data);
			list.forEach(function (x) {
				var types = Array.isArray(x['@type']) ? x['@type'] : [x['@type']];
				types.forEach(function (t) {
					counts[t] = (counts[t] || 0) + 1;
				});
			});
		});
		var lines = [];
		var dup = false;
		Object.keys(counts).forEach(function (t) {
			if (counts[t] > 1 && ['ProfessionalService', 'Person', 'Organization', 'WebSite', 'LocalBusiness'].indexOf(t) !== -1) {
				dup = true;
				lines.push(t + ' appears ' + counts[t] + ' times.');
			}
		});
		if (bad) {
			dup = true;
			lines.push(bad + ' block' + (bad === 1 ? ' is' : 's are') + ' not readable.');
		}
		clear(ui.schemaOut);
		if (!nodes.length) {
			message(ui.schemaOut, 'No structured data found on this page.', true);
		} else if (dup) {
			message(ui.schemaOut, 'Something to look at:', false);
			listInto(ui.schemaOut, lines);
		} else {
			message(ui.schemaOut, 'Found ' + nodes.length + ' structured data block' + (nodes.length === 1 ? '' : 's') + ', with no duplicates.', true);
		}
	}

	ED.addDirtyCheck(function () {
		return (S.data && S.snap && JSON.stringify(collect(false)) !== S.snap) ? 'search details (title, description, picture or address)' : '';
	}, function () {
		if (S.data) {
			fill(S.data);
		}
	});

	ED.addTab('seo', 'Search', build);
})();
