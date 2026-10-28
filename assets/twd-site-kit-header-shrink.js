/* TWD Site Kit: shrink the fixed header after a small scroll. Vanilla JS, no libraries, no inline handlers.
 *
 * Loaded only when "Shrink the header when scrolling" is on and the header is fixed; with no script it is a normal fixed header.
 * One class is added after 60px and taken off only under 20px, so it cannot flicker. The size change is CSS only. */
(function () {
	'use strict';

	var header = document.querySelector('.twd-sk-header--sticky.twd-sk-header--shrink');
	if (!header) {
		return;
	}
	var SHRINK_AT = 60;
	var GROW_AT = 20;
	var COMPACT = 56;
	var shrunk = false;
	var waiting = false;

	function position() {
		return Math.max(0, window.pageYOffset || document.documentElement.scrollTop || 0);
	}

	/* The compact row is the symbol plus 20px (56px with the standard 36px symbol). */
	function compact() {
		var name = window.matchMedia && window.matchMedia('(max-width: 900px)').matches ? '-sm' : '';
		var s = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--twd-site-wordmark-symbol' + name));
		return Math.max(COMPACT, s + 20 || 0);
	}

	/* The text wordmark header is much taller than the compact row it becomes, so it waits until its spacer has scrolled up to that row (no blank band). Every other header shrinks after 60px. */
	function shrinkAt() {
		var wrap = header.parentNode;
		if (wrap && wrap.classList && wrap.classList.contains('twd-sk-chrome--wordmark')) {
			return Math.max(SHRINK_AT, wrap.offsetHeight - compact());
		}
		return SHRINK_AT;
	}

	function update() {
		waiting = false;
		var y = position();
		if (!shrunk && y > shrinkAt()) {
			shrunk = true;
			header.classList.add('twd-sk-header--shrunk');
		} else if (shrunk && y < GROW_AT) {
			shrunk = false;
			header.classList.remove('twd-sk-header--shrunk');
		}
	}

	window.addEventListener('scroll', function () {
		if (!waiting) {
			waiting = true;
			window.requestAnimationFrame(update);
		}
	}, { passive: true });
	update();
})();
