/**
 * SAF Admin – UI vylepšení.
 *
 * 1. WP admin notices přesunuty nad .saf-header.
 * 2. Floating modaly (Elementor license, Rank Math…) přesunuty před .saf-wrap.
 * 3. Chybové hlášky auto-scroll.
 */
(function () {
	'use strict';

	// ── Přesun floating modalů mimo plugin grafiku ────────────────────────────
	// Elementor License Mismatch a podobné fixed/absolute modaly se renderují
	// přes celou stránku a překrývají .saf-header. Přesuneme je PŘED .saf-wrap.

	function relocateFloatingNotifications() {
		var wrap = document.querySelector( '.saf-wrap' );
		if ( ! wrap || ! wrap.parentNode ) return;

		// Přesun standardních WP notices (div.notice, div.updated…) co jsou
		// přímí potomci BODY nebo #wpbody-content – před .saf-wrap.
		var wpbody = document.getElementById( 'wpbody-content' ) || document.body;
		Array.from( wpbody.children ).forEach( function ( el ) {
			if ( el === wrap ) return;
			var tag = el.tagName;
			if ( tag !== 'DIV' && tag !== 'SECTION' ) return;

			// Standardní WP notice třídy.
			if (
				el.classList.contains( 'notice' )   ||
				el.classList.contains( 'updated' )  ||
				el.classList.contains( 'error' )    ||
				el.classList.contains( 'update-nag' )
			) {
				wrap.parentNode.insertBefore( el, wrap );
				return;
			}
		} );

		// WP notices uvnitř .saf-wrap ale VNĚ panelů → před .saf-header.
		var header = wrap.querySelector( '.saf-header' );
		if ( header ) {
			Array.from( wrap.children ).forEach( function ( child ) {
				if ( child === header ) return;
				if (
					child.classList.contains( 'notice' ) ||
					child.classList.contains( 'updated' ) ||
					child.classList.contains( 'update-nag' )
				) {
					wrap.insertBefore( child, header );
				}
			} );
		}
	}

	// ── MutationObserver – zachytí Elementor modal při renderování ─────────────
	// Elementor renderuje License Mismatch přes JavaScript po načtení stránky.
	// Observer ho zachytí a přesune před .saf-wrap (fyzicky v DOMu).

	function setupModalObserver() {
		var wrap = document.querySelector( '.saf-wrap' );
		if ( ! wrap || ! wrap.parentNode ) return;

		var observer = new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				mutation.addedNodes.forEach( function ( node ) {
					if ( node.nodeType !== 1 ) return; // jen elementy

					// Elementor License dialog – detekce dle textu nebo třídy.
					var isElModal = (
						( node.className && typeof node.className === 'string' && (
							node.className.includes( 'elementor' ) ||
							node.className.includes( 'ekit' )
						) ) ||
						( node.innerHTML && (
							node.innerHTML.includes( 'License Mismatch' ) ||
							node.innerHTML.includes( 'license' ) && node.innerHTML.includes( 'Elementor' )
						) )
					);

					if ( isElModal && wrap.parentNode ) {
						// Přesuň před .saf-wrap (nikoliv dovnitř).
						wrap.parentNode.insertBefore( node, wrap );
						// Resetuj případné fixed positioning.
						node.style.setProperty( 'position', 'relative', 'important' );
						node.style.setProperty( 'z-index', '100', 'important' );
					}
				} );
			} );
		} );

		// Sleduj celé body pro nové elementy.
		observer.observe( document.body, { childList: true, subtree: false } );
		// Sleduj i #wpwrap / #wpbody (kde se Elementor může vkládat).
		var wpbody = document.getElementById( 'wpbody-content' );
		if ( wpbody ) observer.observe( wpbody, { childList: true, subtree: false } );
	}

	// ── Auto-scroll na chybové hlášky pluginu ──────────────────────────────────

	function scrollToError() {
		var err = document.querySelector( '.saf-inline-error' );
		if ( err ) {
			setTimeout( function () {
				err.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}, 100 );
		}
	}

	// ── Inicializace ───────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', function () {
		relocateFloatingNotifications();
		setupModalObserver();
		scrollToError();
	} );

}() );
