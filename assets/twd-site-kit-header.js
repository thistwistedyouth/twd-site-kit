/* TWD Site Kit: the header's mobile menu. Vanilla JS, no build step, no inline handlers.
 *
 * With no script (or if this file is blocked) the menu is simply shown in full, so nothing
 * is lost. Once this runs it adds the menu button, collapses the menu on small screens, and
 * keeps it keyboard and screen reader friendly: aria-expanded, Escape closes, focus returns
 * to the button, and tabbing out of the header closes it. */
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

	var headers = document.querySelectorAll('.twd-sk-header');
	Array.prototype.forEach.call(headers, setup);
})();
