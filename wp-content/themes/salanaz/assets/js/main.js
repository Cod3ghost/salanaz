/**
 * Salanaz theme scripts.
 *
 * Vanilla JS, no dependencies — the site is mobile-first and the audience is
 * mostly on metered connections, so there is no framework payload.
 */
( function () {
	'use strict';

	/** Mobile navigation toggle. */
	function initNavToggle() {
		var toggle = document.querySelector( '.slz-nav-toggle' );
		var nav = document.getElementById( 'slz-primary-nav' );

		if ( ! toggle || ! nav ) {
			return;
		}

		toggle.addEventListener( 'click', function () {
			var isOpen = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		} );

		// Close on Escape so keyboard users are not trapped.
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && nav.classList.contains( 'is-open' ) ) {
				nav.classList.remove( 'is-open' );
				toggle.setAttribute( 'aria-expanded', 'false' );
				toggle.focus();
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', initNavToggle );
}() );
