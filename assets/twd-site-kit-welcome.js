/* TWD Site Kit: the Welcome screen. Shows and hides the how-to pop-ups the server printed. No HTML is built here. */
( function () {
	'use strict';
	var openDialog = null;
	var opener = null;

	function focusables( box ) {
		return Array.prototype.slice.call( box.querySelectorAll( 'a[href], button:not([disabled])' ) );
	}

	function close() {
		if ( ! openDialog ) {
			return;
		}
		openDialog.hidden = true;
		openDialog = null;
		if ( opener ) {
			opener.focus();
		}
		opener = null;
	}

	function open( btn ) {
		var box = document.getElementById( btn.getAttribute( 'data-twd-sk-open' ) );
		if ( ! box ) {
			return;
		}
		close();
		opener = btn;
		openDialog = box;
		box.hidden = false;
		var items = focusables( box );
		if ( items.length ) {
			items[ items.length - 1 ].focus();
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var t = e.target;
		while ( t && t !== document && t.nodeType === 1 ) {
			if ( t.hasAttribute( 'data-twd-sk-open' ) ) {
				e.preventDefault();
				open( t );
				return;
			}
			if ( t.hasAttribute( 'data-twd-sk-close' ) ) {
				e.preventDefault();
				close();
				return;
			}
			if ( t.classList && t.classList.contains( 'twd-sk-welcome__dialog' ) && t === e.target ) {
				close();
				return;
			}
			t = t.parentNode;
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( ! openDialog ) {
			return;
		}
		if ( e.key === 'Escape' ) {
			e.preventDefault();
			close();
			return;
		}
		if ( e.key === 'Tab' ) {
			var items = focusables( openDialog );
			if ( ! items.length ) {
				return;
			}
			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}
	} );
}() );
