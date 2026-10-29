/* TWD Site Kit: the header's mobile menu, and the fixed header's measured spacer. Vanilla JS, no build step, no inline handlers.
 *
 * With no script (or if this file is blocked) the menu is simply shown in full, so nothing
 * is lost. Once this runs it adds the menu button, collapses the menu on small screens, and
 * keeps it keyboard and screen reader friendly: aria-expanded, Escape closes, focus returns
 * to the button, and tabbing out of the header closes it.
 *
 * Measured spacer (0.15.0): a fixed header sits in a wrapper that keeps its height so the page
 * does not slide under it. The stylesheet works that height out from the size tokens; this script
 * then sets the real measured height, so any token (strip thickness, symbol, menu size) or font
 * gives a spacer with no gap and no overlap. Measured on load, when fonts arrive and on resize,
 * never while the header is shrunk (its spacer keeps the full height on purpose). */
(function () {
	'use strict';

	function setup(header) {
		var toggle = header.querySelector('.twd-sk-header__toggle');
		var panel = header.querySelector('.twd-sk-header__panel');
		if (!toggle || !panel) {
			return;
		}
		toggle.removeAttribute('hidden');
		header.classList.add('twd-sk-header--js');

		function setOpen(open) {
			header.classList.toggle('twd-sk-header--open', open);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		}

		toggle.addEventListener('click', function () {
			setOpen(!header.classList.contains('twd-sk-header--open'));
		});

		header.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && header.classList.contains('twd-sk-header--open')) {
				setOpen(false);
				toggle.focus();
			}
		});

		header.addEventListener('focusout', function (e) {
			if (e.relatedTarget && !header.contains(e.relatedTarget)) {
				setOpen(false);
			}
		});

		document.addEventListener('click', function (e) {
			if (header.classList.contains('twd-sk-header--open') && !header.contains(e.target)) {
				setOpen(false);
			}
		});

		panel.addEventListener('click', function (e) {
			if (e.target && e.target.closest && e.target.closest('a')) {
				setOpen(false);
			}
		});

		window.addEventListener('resize', function () {
			if (window.innerWidth > 900 && !header.classList.contains('twd-sk-header--minimal')) {
				setOpen(false);
			}
		});
	}

	function spacer(header) {
		var wrap = header.parentNode;
		if (!wrap || !wrap.classList || !wrap.classList.contains('twd-sk-chrome--sticky') || wrap.classList.contains('twd-sk-chrome--over')) {
			return;
		}
		var queued = false;
		function measure() {
			queued = false;
			if (header.classList.contains('twd-sk-header--shrunk')) {
				return;
			}
			var h = header.offsetHeight;
			if (h > 0) {
				wrap.style.height = h + 'px';
			}
		}
		function queue() {
			if (!queued) {
				queued = true;
				window.requestAnimationFrame(measure);
			}
		}
		measure();
		window.addEventListener('resize', queue);
		window.addEventListener('load', queue);
		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(queue, function () {});
		}
	}

	var headers = document.querySelectorAll('.twd-sk-header');
	Array.prototype.forEach.call(headers, setup);
	Array.prototype.forEach.call(headers, spacer);
	// The Leave quickly link (0.15.0) sits in the top corner; for a signed-in person the admin bar is there, so it moves under it.
	if (document.getElementById('wpadminbar') && document.querySelector('.twd-sk-exit')) {
		document.documentElement.style.setProperty('--twd-sk-exit-top', (document.getElementById('wpadminbar').offsetHeight + 12) + 'px');
	}
})();
