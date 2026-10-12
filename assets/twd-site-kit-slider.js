/* TWD Site Kit: the quote slider (the twd-sk-quote--slider section). Vanilla JS, no build step.
 *
 * Loaded only on a page that has a slider. Without it the quotes still swipe and scroll; this adds
 * the arrows, the dots and the arrow keys. Nothing moves on its own, and smooth scrolling is off for
 * visitors who ask for less motion. It never builds HTML from text. */
(function () {
	'use strict';

	function makeButton(cls, text, label) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = cls;
		b.textContent = text;
		b.setAttribute('aria-label', label);
		return b;
	}

	function setup(section) {
		var track = section.querySelector('.twd-sk-quote__track');
		if (!track || track.getAttribute('data-twd-sk-ready')) {
			return;
		}
		var cards = Array.prototype.filter.call(track.children, function (c) {
			return c.classList.contains('twd-sk-quote__card');
		});
		if (cards.length < 2) {
			return;
		}
		track.setAttribute('data-twd-sk-ready', '1');
		track.setAttribute('tabindex', '0');
		track.setAttribute('role', 'region');
		track.setAttribute('aria-roledescription', 'carousel');
		track.setAttribute('aria-label', 'Quotes, one at a time. Use the arrow keys or the buttons below to move.');
		cards.forEach(function (c, i) {
			c.setAttribute('role', 'group');
			c.setAttribute('aria-roledescription', 'slide');
			c.setAttribute('aria-label', (i + 1) + ' of ' + cards.length);
		});
		var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		function current() {
			var w = track.clientWidth || 1;
			return Math.max(0, Math.min(cards.length - 1, Math.round(track.scrollLeft / w)));
		}

		function go(i) {
			var n = (i + cards.length) % cards.length;
			track.scrollTo({ left: cards[n].offsetLeft - cards[0].offsetLeft, behavior: calm ? 'auto' : 'smooth' });
		}

		var controls = document.createElement('div');
		controls.className = 'twd-sk-quote__controls';
		var prev = makeButton('twd-sk-quote__arrow', '\u2039', 'Previous quote');
		var next = makeButton('twd-sk-quote__arrow', '\u203A', 'Next quote');
		var dots = document.createElement('div');
		dots.className = 'twd-sk-quote__dots';
		var dotButtons = cards.map(function (c, i) {
			var d = makeButton('twd-sk-quote__dot', '', 'Show quote ' + (i + 1) + ' of ' + cards.length);
			d.addEventListener('click', function () {
				go(i);
			});
			dots.appendChild(d);
			return d;
		});
		prev.addEventListener('click', function () {
			go(current() - 1);
		});
		next.addEventListener('click', function () {
			go(current() + 1);
		});
		controls.appendChild(prev);
		controls.appendChild(dots);
		controls.appendChild(next);
		track.parentNode.insertBefore(controls, track.nextSibling);

		function mark() {
			var c = current();
			dotButtons.forEach(function (d, i) {
				d.setAttribute('aria-current', i === c ? 'true' : 'false');
			});
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

	function start() {
		Array.prototype.forEach.call(document.querySelectorAll('.twd-sk-quote--slider'), setup);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
}());
