/* TWD Site Kit: shrink the fixed header after a small scroll. Vanilla JS, no libraries, no inline handlers.
 *
 * Loaded only when "Shrink the header when scrolling" is on and the header is fixed. With no script the
 * header is simply a normal fixed header. It adds one class after 60px of scroll and takes it off again
 * only when back under 20px, so it cannot flicker at the edge. The size change is CSS only. */
(function () {
	'use strict';

	var header = document.querySelector('.twd-sk-header--sticky.twd-sk-header--shrink');
	if (!header) {
		return;
	}
	var SHRINK_AT = 60;
	var GROW_AT = 20;
	var shrunk = false;
	var waiting = false;

	function position() {
		return Math.max(0, window.pageYOffset || document.documentElement.scrollTop || 0);
	}

	function update() {
		waiting = false;
		var y = position();
		if (!shrunk && y > SHRINK_AT) {
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
