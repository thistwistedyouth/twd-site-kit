/* TWD Site Kit: the parallax hero (the twd-sk-hero--parallax section). Vanilla JS, no build step.
 *
 * Loaded only on a page that has one. Without it the picture simply stays still. With it the picture
 * moves a little slower than the page by setting one CSS variable on the picture, read by the
 * stylesheet. Off on narrow screens and for visitors who ask for less motion. It never builds HTML. */
(function () {
	'use strict';

	if (!window.matchMedia || !window.requestAnimationFrame) {
		return;
	}
	var heroes = document.querySelectorAll('.twd-sk-hero--parallax');
	if (!heroes.length) {
		return;
	}
	var lessMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
	var wide = window.matchMedia('(min-width: 769px)');
	var waiting = false;
	var FRACTION = 0.1;

	function picture(hero) {
		return hero.querySelector('.twd-sk-hero__bg');
	}

	function clear() {
		for (var i = 0; i < heroes.length; i++) {
			var bg = picture(heroes[i]);
			if (bg) {
				bg.style.removeProperty('--twd-sk-parallax');
			}
		}
	}

	function update() {
		waiting = false;
		if (lessMotion.matches || !wide.matches) {
			clear();
			return;
		}
		var vh = window.innerHeight || 0;
		if (!vh) {
			return;
		}
		for (var i = 0; i < heroes.length; i++) {
			var bg = picture(heroes[i]);
			if (!bg) {
				continue;
			}
			var r = heroes[i].getBoundingClientRect();
			if (r.bottom < 0 || r.top > vh) {
				continue;
			}
			// How far the hero's centre sits from the middle of the window, as a share of the window height.
			var centre = (r.top + r.height / 2 - vh / 2) / vh;
			centre = Math.max(-1, Math.min(1, centre));
			// The picture is a little taller than the hero, so it can slide the other way and stay covered.
			var shift = -centre * r.height * FRACTION;
			bg.style.setProperty('--twd-sk-parallax', shift.toFixed(1) + 'px');
		}
	}

	function ask() {
		if (!waiting) {
			waiting = true;
			window.requestAnimationFrame(update);
		}
	}

	window.addEventListener('scroll', ask, { passive: true });
	window.addEventListener('resize', ask);
	if (lessMotion.addEventListener) {
		lessMotion.addEventListener('change', ask);
	}
	update();
})();
