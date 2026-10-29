/* TWD Site Kit: reading controls (0.15.0). Loaded only on a page that asks for them, for visitors.
 * A small Reading button opens three choices: text size, line spacing and higher contrast. The choice is kept in
 * this browser only (no cookie, nothing sent anywhere) and put on the page as classes. ASCII only, no addresses. */
(function () {
	'use strict';
	var KEY = 'twd_sk_reading';
	var state = { size: 0, loose: false, contrast: false };
	function read() {
		try {
			var raw = window.localStorage.getItem(KEY);
			if (raw) {
				var s = JSON.parse(raw);
				state.size = s.size === 1 || s.size === 2 ? s.size : 0;
				state.loose = !!s.loose;
				state.contrast = !!s.contrast;
			}
		} catch (e) {
			// Storage may be blocked: the controls still work for this page view.
		}
	}
	function keep() {
		try {
			window.localStorage.setItem(KEY, JSON.stringify(state));
		} catch (e) {
			// Nothing to do: the page is already updated.
		}
	}
	function apply() {
		var pages = document.querySelectorAll('.twd-sk-page');
		for (var i = 0; i < pages.length; i++) {
			pages[i].classList.toggle('twd-sk-read-lg', state.size === 1);
			pages[i].classList.toggle('twd-sk-read-xl', state.size === 2);
			pages[i].classList.toggle('twd-sk-read-loose', state.loose);
			pages[i].classList.toggle('twd-sk-read-contrast', state.contrast);
		}
	}
	function el(tag, text, cls, attrs) {
		var n = document.createElement(tag);
		if (cls) {
			n.className = cls;
		}
		if (text) {
			n.appendChild(document.createTextNode(text));
		}
		if (attrs) {
			for (var k in attrs) {
				if (Object.prototype.hasOwnProperty.call(attrs, k)) {
					n.setAttribute(k, attrs[k]);
				}
			}
		}
		return n;
	}
	function build() {
		var host = el('div', '', 'twd-sk-page twd-sk-reading');
		var btn = el('button', 'Reading', 'twd-sk-reading__btn', { type: 'button', 'aria-expanded': 'false', 'aria-haspopup': 'true' });
		var panel = el('div', '', 'twd-sk-reading__panel', { role: 'group', 'aria-label': 'Reading controls', hidden: '' });
		function ctl(label, run, pressed) {
			var b = el('button', label, 'twd-sk-reading__ctl', { type: 'button' });
			if (pressed) {
				b.setAttribute('aria-pressed', pressed() ? 'true' : 'false');
			}
			b.addEventListener('click', function () {
				run();
				apply();
				keep();
				if (pressed) {
					b.setAttribute('aria-pressed', pressed() ? 'true' : 'false');
				}
			});
			return b;
		}
		var sizeRow = el('div', '', 'twd-sk-reading__row');
		sizeRow.appendChild(el('span', 'Text size'));
		sizeRow.appendChild(ctl('Smaller', function () { state.size = Math.max(0, state.size - 1); }));
		sizeRow.appendChild(ctl('Larger', function () { state.size = Math.min(2, state.size + 1); }));
		var spaceRow = el('div', '', 'twd-sk-reading__row');
		spaceRow.appendChild(el('span', 'Line spacing'));
		spaceRow.appendChild(ctl('More space', function () { state.loose = !state.loose; }, function () { return state.loose; }));
		var contrastRow = el('div', '', 'twd-sk-reading__row');
		contrastRow.appendChild(el('span', 'Contrast'));
		contrastRow.appendChild(ctl('Higher contrast', function () { state.contrast = !state.contrast; }, function () { return state.contrast; }));
		var resetRow = el('div', '', 'twd-sk-reading__row');
		resetRow.appendChild(ctl('Back to normal', function () { state.size = 0; state.loose = false; state.contrast = false; }));
		panel.appendChild(sizeRow);
		panel.appendChild(spaceRow);
		panel.appendChild(contrastRow);
		panel.appendChild(resetRow);
		btn.addEventListener('click', function () {
			var open = panel.hasAttribute('hidden');
			if (open) {
				panel.removeAttribute('hidden');
			} else {
				panel.setAttribute('hidden', '');
			}
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !panel.hasAttribute('hidden')) {
				panel.setAttribute('hidden', '');
				btn.setAttribute('aria-expanded', 'false');
				btn.focus();
			}
		});
		host.appendChild(btn);
		host.appendChild(panel);
		document.body.appendChild(host);
	}
	function start() {
		if (!document.querySelector('.twd-sk-page .twd-sk-reading') || document.body.classList.contains('twd-sk-inline-on')) {
			return;
		}
		read();
		apply();
		build();
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
