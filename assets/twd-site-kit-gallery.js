/* TWD Site Kit: the gallery (the twd-sk-gallery section). Vanilla JS, no build step.
 *
 * Loaded only on a page that has a gallery. Without it the pictures still show, and the film strip and
 * slideshow still scroll and swipe. This adds: arrows, a count and arrow keys on the slideshow, and an
 * open-bigger view (click or Enter on any picture; arrows, swipe and Esc inside it). Nothing moves on its
 * own, and smooth scrolling is off for visitors who ask for less motion. It never builds HTML from text.
 * ASCII-only source: arrows are written as escapes. */
(function () {
	'use strict';

	var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function makeButton(cls, text, label) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = cls;
		b.textContent = text;
		b.setAttribute('aria-label', label);
		return b;
	}

	// The biggest picture file WordPress made for this image, so the open-bigger view is sharp.
	function bigSource(img) {
		var set = img.getAttribute('srcset');
		var best = img.getAttribute('src') || '';
		var bestW = 0;
		if (set) {
			set.split(',').forEach(function (part) {
				var bits = part.trim().split(/\s+/);
				var w = parseInt(bits[1], 10);
				if (bits[0] && w > bestW) {
					bestW = w;
					best = bits[0];
				}
			});
		}
		return best;
	}

	function captionOf(item) {
		var c = item.querySelector('.twd-sk-gallery__caption');
		return c ? c.textContent : '';
	}

	/* ---- the open-bigger view: one per page, shared by every gallery ---- */
	var box = null;
	var boxImg = null;
	var boxCap = null;
	var boxItems = [];
	var boxAt = 0;
	var opener = null;

	function showAt(i) {
		boxAt = (i + boxItems.length) % boxItems.length;
		var item = boxItems[boxAt];
		var img = item.querySelector('.twd-sk-gallery__img');
		boxImg.src = bigSource(img);
		boxImg.alt = img.getAttribute('alt') || '';
		boxCap.textContent = captionOf(item);
		boxCap.hidden = !boxCap.textContent;
	}

	function closeBox() {
		if (!box || box.hidden) {
			return;
		}
		box.hidden = true;
		boxImg.removeAttribute('src');
		document.body.style.overflow = '';
		document.removeEventListener('keydown', onKey, true);
		if (opener && opener.focus) {
			opener.focus();
		}
	}

	function onKey(e) {
		if (e.key === 'Escape') {
			e.preventDefault();
			closeBox();
		} else if (e.key === 'ArrowRight') {
			e.preventDefault();
			showAt(boxAt + 1);
		} else if (e.key === 'ArrowLeft') {
			e.preventDefault();
			showAt(boxAt - 1);
		} else if (e.key === 'Tab') {
			// Keep the focus inside the view while it is open.
			var btns = box.querySelectorAll('button');
			var first = btns[0];
			var last = btns[btns.length - 1];
			if (e.shiftKey && document.activeElement === first) {
				e.preventDefault();
				last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
		}
	}

	function buildBox(host) {
		box = document.createElement('div');
		box.className = 'twd-sk-gallery__box';
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-modal', 'true');
		box.setAttribute('aria-label', 'Picture, enlarged');
		box.hidden = true;
		var close = makeButton('twd-sk-gallery__box-btn twd-sk-gallery__box-close', String.fromCharCode(215), 'Close');
		var prev = makeButton('twd-sk-gallery__box-btn twd-sk-gallery__box-prev', String.fromCharCode(8249), 'Previous picture');
		var next = makeButton('twd-sk-gallery__box-btn twd-sk-gallery__box-next', String.fromCharCode(8250), 'Next picture');
		boxImg = document.createElement('img');
		boxImg.className = 'twd-sk-gallery__box-img';
		boxCap = document.createElement('p');
		boxCap.className = 'twd-sk-gallery__box-caption';
		box.appendChild(close);
		box.appendChild(prev);
		box.appendChild(next);
		box.appendChild(boxImg);
		box.appendChild(boxCap);
		close.addEventListener('click', closeBox);
		prev.addEventListener('click', function () {
			showAt(boxAt - 1);
		});
		next.addEventListener('click', function () {
			showAt(boxAt + 1);
		});
		// A tap on the dark area closes it; a tap on the picture or a button does not.
		box.addEventListener('click', function (e) {
			if (e.target === box) {
				closeBox();
			}
		});
		// Swipe left or right to move.
		var x0 = null;
		box.addEventListener('touchstart', function (e) {
			x0 = e.touches.length === 1 ? e.touches[0].clientX : null;
		}, { passive: true });
		box.addEventListener('touchend', function (e) {
			if (x0 === null || !e.changedTouches.length) {
				return;
			}
			var dx = e.changedTouches[0].clientX - x0;
			x0 = null;
			if (Math.abs(dx) > 50) {
				showAt(boxAt + (dx < 0 ? 1 : -1));
			}
		}, { passive: true });
		host.appendChild(box);
	}

	function openBox(section, item) {
		// Only the pictures a visitor can see: a category hidden by the filter is left out.
		boxItems = Array.prototype.filter.call(section.querySelectorAll('.twd-sk-gallery__item'), function (it) {
			return it.querySelector('.twd-sk-gallery__img') && !it.closest('[hidden]');
		});
		if (!boxItems.length) {
			return;
		}
		if (!box) {
			// Inside the page wrapper so the kit styles reach it.
			buildBox(section.closest('.twd-sk-page') || document.body);
		}
		opener = item.querySelector('.twd-sk-gallery__img');
		showAt(boxItems.indexOf(item));
		var multi = boxItems.length > 1;
		box.querySelector('.twd-sk-gallery__box-prev').hidden = !multi;
		box.querySelector('.twd-sk-gallery__box-next').hidden = !multi;
		box.hidden = false;
		document.body.style.overflow = 'hidden';
		document.addEventListener('keydown', onKey, true);
		box.querySelector('.twd-sk-gallery__box-close').focus();
	}

	/* ---- the slideshow's own arrows, count and keys ---- */
	function slideshow(section, track) {
		var items = Array.prototype.filter.call(track.children, function (c) {
			return c.classList.contains('twd-sk-gallery__item');
		});
		if (items.length < 2) {
			return;
		}
		track.setAttribute('tabindex', '0');
		track.setAttribute('role', 'region');
		track.setAttribute('aria-roledescription', 'carousel');
		track.setAttribute('aria-label', 'Pictures, one at a time. Use the arrow keys or the buttons below to move.');
		items.forEach(function (c, i) {
			c.setAttribute('role', 'group');
			c.setAttribute('aria-roledescription', 'slide');
			c.setAttribute('aria-label', (i + 1) + ' of ' + items.length);
		});

		function current() {
			var w = track.clientWidth || 1;
			return Math.max(0, Math.min(items.length - 1, Math.round(track.scrollLeft / w)));
		}

		function go(i) {
			var n = (i + items.length) % items.length;
			track.scrollTo({ left: items[n].offsetLeft - items[0].offsetLeft, behavior: calm ? 'auto' : 'smooth' });
		}

		var controls = document.createElement('div');
		controls.className = 'twd-sk-gallery__controls';
		var prev = makeButton('twd-sk-gallery__arrow', String.fromCharCode(8249), 'Previous picture');
		var next = makeButton('twd-sk-gallery__arrow', String.fromCharCode(8250), 'Next picture');
		var count = document.createElement('span');
		count.className = 'twd-sk-gallery__count';
		count.setAttribute('aria-live', 'polite');
		prev.addEventListener('click', function () {
			go(current() - 1);
		});
		next.addEventListener('click', function () {
			go(current() + 1);
		});
		controls.appendChild(prev);
		controls.appendChild(count);
		controls.appendChild(next);
		track.parentNode.insertBefore(controls, track.nextSibling);

		function mark() {
			count.textContent = (current() + 1) + ' of ' + items.length;
		}
		var timer = 0;
		track.addEventListener('scroll', function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(mark, 80);
		}, { passive: true });
		track.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') {
				e.preventDefault();
				go(current() + 1);
			} else if (e.key === 'ArrowLeft') {
				e.preventDefault();
				go(current() - 1);
			}
		});
		mark();
	}

	/* ---- categories: a row of buttons built from the group headings, only when there are two or more ---- */
	function filters(section) {
		var groups = Array.prototype.slice.call(section.querySelectorAll('.twd-sk-gallery__group'));
		if (groups.length < 2) {
			return;
		}
		var bar = document.createElement('div');
		bar.className = 'twd-sk-gallery__filters';
		bar.setAttribute('role', 'group');
		bar.setAttribute('aria-label', 'Show pictures by category');
		var buttons = [];

		function show(which) {
			groups.forEach(function (g, i) {
				g.hidden = which !== -1 && which !== i;
			});
			section.classList.toggle('twd-sk-gallery--filtered', which !== -1);
			buttons.forEach(function (b, i) {
				var on = i - 1 === which;
				b.classList.toggle('twd-sk-gallery__filter--on', on);
				b.setAttribute('aria-pressed', on ? 'true' : 'false');
			});
		}

		function add(label, which) {
			var b = makeButton('twd-sk-gallery__filter', label, label);
			b.removeAttribute('aria-label');
			b.addEventListener('click', function () {
				show(which);
			});
			bar.appendChild(b);
			buttons.push(b);
		}

		add('All', -1);
		groups.forEach(function (g, i) {
			var h = g.querySelector('.twd-sk-gallery__groupname');
			add(h && h.textContent.trim() ? h.textContent.trim() : 'Group ' + (i + 1), i);
		});
		groups[0].parentNode.insertBefore(bar, groups[0]);
		show(-1);
	}

	function setup(section) {
		if (section.getAttribute('data-twd-sk-ready')) {
			return;
		}
		section.setAttribute('data-twd-sk-ready', '1');
		var tracks = section.querySelectorAll('.twd-sk-gallery__track');
		if (!tracks.length) {
			return;
		}
		if (section.classList.contains('twd-sk-gallery--slideshow')) {
			Array.prototype.forEach.call(tracks, function (t) {
				slideshow(section, t);
			});
		}
		filters(section);
		// Every picture opens bigger: click, or Enter or Space when it has the keyboard focus.
		Array.prototype.forEach.call(section.querySelectorAll('.twd-sk-gallery__item'), function (item) {
			var img = item.querySelector('.twd-sk-gallery__img');
			if (!img) {
				return;
			}
			img.setAttribute('data-twd-sk-zoom', '1');
			img.setAttribute('tabindex', '0');
			img.setAttribute('role', 'button');
			img.setAttribute('aria-label', 'Open bigger: ' + (img.getAttribute('alt') || 'picture'));
			img.addEventListener('click', function () {
				openBox(section, item);
			});
			img.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					openBox(section, item);
				}
			});
		});
	}

	function start() {
		Array.prototype.forEach.call(document.querySelectorAll('.twd-sk-gallery'), setup);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
}());
