/**
 * SAF Tracker – sledování kliknutí a doby strávené na stránce.
 */
(function () {
	'use strict';

	if ( typeof safTracker === 'undefined' ) return;

	var config    = safTracker;
	var postId    = parseInt( config.postId, 10 );
	var startTime = Date.now();
	var clickSent = false;

	// ── Odeslání kliknutí ────────────────────────────────────────────────────

	function sendClick() {
		if ( clickSent ) return;
		clickSent = true;
		send( config.restUrl, { post_id: postId, nonce: config.nonce } );
	}

	// ── Odeslání doby na stránce ─────────────────────────────────────────────

	function sendTime() {
		var seconds = Math.round( ( Date.now() - startTime ) / 1000 );
		if ( seconds < 3 || seconds > 3600 ) return; // ignoruj extrémní hodnoty
		send( config.timeUrl, { post_id: postId, nonce: config.nonce, seconds: seconds } );
	}

	// ── Pomocná funkce sendBeacon / fetch ─────────────────────────────────────

	function send( url, data ) {
		var body = JSON.stringify( data );
		if ( navigator.sendBeacon ) {
			navigator.sendBeacon( url, new Blob( [body], { type: 'application/json' } ) );
		} else {
			fetch( url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true } )
				.catch( function () {} );
		}
	}

	// ── Sledování kliknutí na odcházející linky ───────────────────────────────

	document.addEventListener( 'click', function ( e ) {
		var el = e.target;
		while ( el && el.tagName !== 'A' ) el = el.parentElement;
		if ( el && el.href && el.href !== window.location.href && !el.href.startsWith( '#' ) ) {
			sendClick();
		}
	} );

	// ── Sledování odchodu ze stránky ─────────────────────────────────────────

	window.addEventListener( 'pagehide', function () {
		sendTime();
		sendClick(); // klik = odchod (pokud neodeslán dřív)
	} );

	// Fallback pro starší prohlížeče.
	window.addEventListener( 'beforeunload', function () {
		sendTime();
	} );

}() );
