/**
 * SAF Import Preview – živý náhled obsahu šablony.
 * Renderuje první řádek dat přes REST endpoint a zobrazí v iframe.
 */
(function () {
	'use strict';

	if ( typeof safImportPreview === 'undefined' ) return;

	var cfg      = safImportPreview;
	var iframe   = document.getElementById( 'saf-preview-iframe' );
	var btn      = document.getElementById( 'saf-preview-btn' );
	var statusEl = document.getElementById( 'saf-preview-status' );
	var loading  = false;

	if ( ! iframe || ! btn ) return;

	// ── Načtení náhledu ────────────────────────────────────────────────────────

	function loadPreview() {
		if ( loading ) return;

		// Zjisti vybraný template_id.
		var templateSel = document.getElementById( 'saf-template-select' );
		var templateId  = templateSel ? parseInt( templateSel.value, 10 ) : cfg.templateId;

		if ( ! templateId ) {
			showStatus( 'Nejdřív vyber nebo vytvoř šablonu.', 'warning' );
			return;
		}

		loading = true;
		btn.disabled = true;
		showStatus( 'Načítám náhled…', 'info' );

		fetch( cfg.restUrl, {
			method:  'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   cfg.nonce,
			},
			body: JSON.stringify( {
				template_id: templateId,
				macro_data:  cfg.macroData || {},
			} ),
		} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( data ) {
			if ( data.code ) {
				showStatus( 'Chyba: ' + ( data.message || 'Nepodařilo se načíst náhled.' ), 'error' );
				return;
			}

			// Vlož HTML do iframe s theme CSS.
			var doc = iframe.contentDocument || iframe.contentWindow.document;
			doc.open();
			doc.write( buildPreviewPage( data.html || '' ) );
			doc.close();

			iframe.style.display = 'block';
			iframe.style.height  = Math.max( 200, doc.body ? doc.body.scrollHeight + 40 : 400 ) + 'px';

			// Resizuj iframe po načtení obrázků/fontů.
			iframe.contentWindow.addEventListener( 'load', function () {
				iframe.style.height = ( doc.body.scrollHeight + 40 ) + 'px';
			} );

			showStatus( 'Náhled: 1. řádek dat, šablona „' + data.template_title + '"', 'ok' );
		} )
		.catch( function ( err ) {
			showStatus( 'Síťová chyba: ' + err.message, 'error' );
		} )
		.finally( function () {
			loading = false;
			btn.disabled = false;
		} );
	}

	// ── HTML obálka pro iframe ─────────────────────────────────────────────────

	function buildPreviewPage( content ) {
		var styles = cfg.themeStyles || [];
		var styleLinks = styles.map( function ( url ) {
			return '<link rel="stylesheet" href="' + url + '">';
		} ).join( '\n' );

		return '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8">' +
			'<meta name="viewport" content="width=device-width, initial-scale=1">' +
			styleLinks +
			'<style>' +
			'body{margin:0;padding:20px 24px;font-family:inherit;background:#fff}' +
			'.entry-content{max-width:760px;margin:0 auto}' +
			'.wp-block-heading{margin-top:1.4em;margin-bottom:.5em}' +
			'p{margin:0 0 1em;line-height:1.7}' +
			'</style>' +
			'</head><body class="single-post">' +
			'<article class="entry-content">' + content + '</article>' +
			'</body></html>';
	}

	// ── Status zpráva ──────────────────────────────────────────────────────────

	function showStatus( msg, type ) {
		if ( ! statusEl ) return;
		statusEl.textContent = msg;
		statusEl.style.color = type === 'error' ? '#e94560' : type === 'ok' ? '#2d7738' : '#888';
	}

	// ── Event listenery ────────────────────────────────────────────────────────

	btn.addEventListener( 'click', loadPreview );

	// Auto-refresh pokud se změní šablona.
	var templateSel = document.getElementById( 'saf-template-select' );
	if ( templateSel ) {
		templateSel.addEventListener( 'change', function () {
			if ( iframe.style.display !== 'none' ) {
				loadPreview(); // obnoví náhled při změně šablony
			}
		} );
	}

}() );
