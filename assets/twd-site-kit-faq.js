/* TWD Site Kit: question groups (0.15.0). Loaded only on a page with a groups section, for visitors.
 * Without it each tab is a link to its group and the page scrolls there. With it, a click sets the group's
 * address and puts the page back where it was in the same frame (no jump), the stylesheet's own :target rules
 * then show that group, and the tab is marked as chosen. A direct
 * link to a group's address still opens that group. Nothing is stored. ASCII only, no addresses. */
(function () {
	'use strict';
	function groupFor(section, link) {
		var href = link.getAttribute('href') || '';
		var id = href.indexOf('#') === 0 ? href.slice(1) : '';
		if (!id) {
			return null;
		}
		var groups = section.querySelectorAll('.twd-sk-faq__group');
		for (var i = 0; i < groups.length; i++) {
			if (groups[i].id === id) {
				return groups[i];
			}
		}
		return null;
	}
	function mark(section, group) {
		var tabs = section.querySelectorAll('.twd-sk-faq__tab');
		for (var t = 0; t < tabs.length; t++) {
			var a = tabs[t].querySelector('.twd-sk-faq__tablink');
			var on = !!(a && group && a.getAttribute('href') === '#' + group.id);
			tabs[t].classList.toggle('twd-sk-faq__tab--on', on);
			if (a) {
				if (on) {
					a.setAttribute('aria-current', 'true');
				} else {
					a.removeAttribute('aria-current');
				}
			}
		}
	}
	function open(section, link) {
		var group = groupFor(section, link);
		if (!group) {
			return false;
		}
		// The address changes for real (so the stylesheet's :target rules show the group and the back button works);
		// the page is put back where it was in the same frame, so nothing appears to move.
		var x = window.pageXOffset;
		var y = window.pageYOffset;
		window.location.hash = '#' + group.id;
		window.scrollTo(x, y);
		mark(section, group);
		return true;
	}
	function current(section) {
		var hash = window.location.hash ? window.location.hash.slice(1) : '';
		var groups = section.querySelectorAll('.twd-sk-faq__group');
		var fallback = null;
		for (var i = 0; i < groups.length; i++) {
			if (hash && groups[i].id === hash) {
				return groups[i];
			}
			if (!fallback && groups[i].classList.contains('twd-sk-faq__group--default')) {
				fallback = groups[i];
			}
		}
		return fallback || groups[0] || null;
	}
	function setUp(section) {
		if (!section.querySelector('.twd-sk-faq__tablink')) {
			return;
		}
		mark(section, current(section));
		section.addEventListener('click', function (e) {
			var link = e.target && e.target.closest ? e.target.closest('.twd-sk-faq__tablink') : null;
			if (!link || !section.contains(link) || document.body.classList.contains('twd-sk-inline-on')) {
				return;
			}
			if (open(section, link)) {
				e.preventDefault();
			}
		});
		window.addEventListener('hashchange', function () {
			mark(section, current(section));
		});
	}
	function start() {
		var sections = document.querySelectorAll('.twd-sk-faq--groups');
		for (var i = 0; i < sections.length; i++) {
			setUp(sections[i]);
		}
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
