/* TWD Site Kit: the shop filter bar (Shop module, 0.21.0). Vanilla JS, no libraries, no inline handlers.
 *
 * Without this script every control is a plain link or GET form and the page reloads at the bar.
 * With it, the same address is fetched and the bar, the result count, the grid and the pager are
 * swapped for the fetched page's own (parsed with DOMParser and imported, never set as raw HTML). The
 * address is kept in the history, Back and Forward work, the result count is announced, and the
 * page scrolls so the bar sits just below a fixed or sticky header. Anything unexpected: the
 * browser simply goes to the address, as it would without the script. */
(function () {
	'use strict';

	var ID = 'twd-sk-shop-grid';
	var root = document.getElementById(ID);
	if (!root || !window.fetch || !window.DOMParser || !window.URL || !window.URLSearchParams || !window.FormData || !window.history || !history.pushState || !Element.prototype.closest) {
		return;
	}
	// Swapped as well when the fetched page has them: a category page's own heading and breadcrumb.
	var ALSO = ['.woocommerce-products-header', '.woocommerce-breadcrumb'];
	var pending = null;

	root.classList.add('twd-sk-shop-grid--js');

	function results() {
		return document.querySelector('.twd-sk-shop-grid__results');
	}

	function reduced() {
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	/** The lowest bottom edge of a fixed or sticky bar across the top of the window (the header, the admin bar). */
	function headerBottom() {
		var bottom = 0;
		var xs = [8, Math.round(window.innerWidth / 2), window.innerWidth - 8];
		if (!document.elementsFromPoint) {
			return 0;
		}
		xs.forEach(function (x) {
			document.elementsFromPoint(x, 2).forEach(function (el) {
				for (var n = el; n && n !== document.body && n !== document.documentElement; n = n.parentElement) {
					if (n.contains(root)) {
						break;
					}
					var pos = window.getComputedStyle(n).position;
					if (pos === 'fixed' || pos === 'sticky') {
						var r = n.getBoundingClientRect();
						if (r.top <= 2 && r.bottom > bottom && r.bottom < window.innerHeight / 2) {
							bottom = r.bottom;
						}
					}
				}
			});
		});
		return bottom;
	}

	function target() {
		return Math.max(0, Math.round(root.getBoundingClientRect().top + window.pageYOffset - headerBottom() - 12));
	}

	/** Bring the bar to just below the header; once more after a moment, as a header can shrink on scroll. */
	function align() {
		window.scrollTo({ top: target(), behavior: reduced() ? 'auto' : 'smooth' });
		window.setTimeout(function () {
			var t = target();
			if (Math.abs(t - window.pageYOffset) > 4) {
				window.scrollTo({ top: t, behavior: 'auto' });
			}
		}, reduced() ? 60 : 550);
	}

	function announce(res) {
		var line = root.querySelector('.twd-sk-shop-grid__status');
		if (!line || !res) {
			return;
		}
		var n = parseInt(res.getAttribute('data-total'), 10) || 0;
		line.textContent = '';
		window.setTimeout(function () {
			line.textContent = n === 0 ? 'No results' : (n === 1 ? '1 result' : n + ' results');
		}, 60);
	}

	function replace(oldEl, newEl) {
		var el = document.importNode(newEl, true);
		oldEl.parentNode.replaceChild(el, oldEl);
		return el;
	}

	function byKey(scope, key) {
		var all = scope.querySelectorAll('[data-key]');
		for (var i = 0; i < all.length; i++) {
			if (all[i].getAttribute('data-key') === key) {
				return all[i];
			}
		}
		return null;
	}

	/** Put the fetched page's bar and results in place of ours. False when either is missing. */
	function swap(doc) {
		var newBar = doc.querySelector('#' + ID + ' .twd-sk-shop-grid__bar');
		var newRes = doc.querySelector('.twd-sk-shop-grid__results');
		var oldBar = root.querySelector('.twd-sk-shop-grid__bar');
		var oldRes = results();
		if (!newBar || !newRes || !oldBar || !oldRes) {
			return false;
		}
		var active = document.activeElement;
		var key = active && oldBar.contains(active) ? active.getAttribute('data-key') : null;
		var oldList = oldBar.querySelector('.twd-sk-shop-grid__list');
		var left = oldList ? oldList.scrollLeft : 0;

		var bar = replace(oldBar, newBar);
		replace(oldRes, newRes);
		ALSO.forEach(function (sel) {
			var a = document.querySelector(sel);
			var b = doc.querySelector(sel);
			if (a && b) {
				replace(a, b);
			}
		});

		var list = bar.querySelector('.twd-sk-shop-grid__list');
		if (list) {
			list.scrollLeft = left;
		}
		if (key) {
			var again = byKey(bar, key);
			if (again) {
				again.focus({ preventScroll: true });
			}
		}
		return true;
	}

	function go(url) {
		window.location.assign(url.href);
	}

	/** Fetch the address and swap. from: 'bar', 'results' (a pager link) or 'history'. */
	function load(url, from) {
		if (pending) {
			pending.abort();
		}
		var ctrl = window.AbortController ? new AbortController() : null;
		pending = ctrl;
		var res = results();
		if (res) {
			res.setAttribute('aria-busy', 'true');
			res.classList.add('twd-sk-shop-grid__results--busy');
		}
		fetch(url.href, { credentials: 'same-origin', headers: { Accept: 'text/html' }, signal: ctrl ? ctrl.signal : undefined })
			.then(function (r) {
				if (!r.ok) {
					throw new Error('status ' + r.status);
				}
				return r.text();
			})
			.then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				if (!swap(doc)) {
					go(url);
					return;
				}
				if (from !== 'history') {
					window.history.pushState({ twdShopGrid: true }, '', url.href);
				}
				var title = doc.querySelector('title');
				if (title && title.textContent) {
					document.title = title.textContent;
				}
				var now = results();
				if (from === 'results' && now) {
					now.focus({ preventScroll: true });
				}
				announce(now);
				align();
			})
			.catch(function (e) {
				if (e && e.name === 'AbortError') {
					return;
				}
				go(url);
			});
	}

	function ours(url) {
		return url.origin === window.location.origin && /^https?:$/.test(url.protocol);
	}

	function follow(a, from, e) {
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
			return;
		}
		if (a.target && a.target !== '_self') {
			return;
		}
		var url;
		try {
			url = new URL(a.href, window.location.href);
		} catch (x) {
			return;
		}
		if (!ours(url)) {
			return;
		}
		e.preventDefault();
		load(url, from);
	}

	/** A GET form's address: its action with the filled-in fields as the query (empty ones left out). */
	function formUrl(form) {
		var url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
		var params = new URLSearchParams();
		new FormData(form).forEach(function (v, k) {
			if (typeof v === 'string' && v.trim() !== '') {
				params.append(k, v.trim());
			}
		});
		url.search = params.toString();
		return url;
	}

	document.addEventListener('click', function (e) {
		var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
		if (!a) {
			return;
		}
		if (root.contains(a) && a.closest('.twd-sk-shop-grid__cats')) {
			follow(a, 'bar', e);
			return;
		}
		var res = results();
		if (res && res.contains(a) && a.closest('.woocommerce-pagination, .twd-sk-shop-grid__pager')) {
			follow(a, 'results', e);
		}
	});

	document.addEventListener('submit', function (e) {
		var f = e.target;
		if (!f || !root.contains(f) || !f.matches('.twd-sk-shop-grid__sort, .twd-sk-shop-grid__search')) {
			return;
		}
		var url = formUrl(f);
		if (!ours(url)) {
			return;
		}
		e.preventDefault();
		load(url, 'bar');
	});

	document.addEventListener('change', function (e) {
		var s = e.target;
		if (s && s.matches && s.matches('.twd-sk-shop-grid__select') && root.contains(s) && s.form) {
			var url = formUrl(s.form);
			if (ours(url)) {
				load(url, 'bar');
			}
		}
	});

	// Mark this first entry as ours too, so Back to it swaps the grid (keeping any state already there).
	var first = window.history.state && typeof window.history.state === 'object' ? window.history.state : {};
	first.twdShopGrid = true;
	window.history.replaceState(first, '');
	window.addEventListener('popstate', function (e) {
		if (e.state && e.state.twdShopGrid) {
			load(new URL(window.location.href), 'history');
		}
	});
})();
