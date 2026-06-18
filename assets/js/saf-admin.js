/**
 * SAF Admin – minimální JS.
 * Pouze scroll na inline chybové hlášky.
 * WP notices jsou přesunuty přes PHP (in_admin_header hook v class-plugin.php).
 */
(function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// Auto-scroll na chybovou hlášku uvnitř panelu.
		var err = document.querySelector( '.saf-inline-error' );
		if ( err ) {
			setTimeout( function () {
				err.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}, 100 );
		}
	} );

}() );
