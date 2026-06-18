/**
 * SAF Admin – UI vylepšení.
 * 1. WP notices přesunuty nad .saf-header (necpou se do grafiky pluginu).
 * 2. Chybové hlášky pluginu auto-scrollují do zobrazení (neztrácí se).
 */
(function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap   = document.querySelector( '.saf-wrap' );
		var header = wrap && wrap.querySelector( '.saf-header' );

		// ── 1. WP notices MIMO plugin grafiku ────────────────────────────────
		// Přesuň jakékoli .notice elementy které jsou přímými dětmi .saf-wrap
		// na pozici PŘED .saf-header, aby nepřekrývaly tmavý header blok.
		if ( wrap && header ) {
			// Notices které jsou UVNITŘ wrap ale VNĚ panelů.
			Array.from( wrap.children ).forEach( function ( child ) {
				if (
					child !== header &&
					( child.classList.contains( 'notice' ) ||
					  child.classList.contains( 'updated' ) ||
					  child.classList.contains( 'update-nag' ) ||
					  child.classList.contains( 'error' ) )
				) {
					wrap.insertBefore( child, header );
				}
			} );
		}

		// ── 2. Auto-scroll na chybové hlášky pluginu ──────────────────────────
		// Pokud stránka obsahuje SAF inline chybu, scrolluj k ní plynule.
		var inlineError = document.querySelector( '.saf-inline-error' );
		if ( inlineError ) {
			setTimeout( function () {
				inlineError.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}, 100 );
		}
	} );
}() );
