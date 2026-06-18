/**
 * SAF – Gutenberg sidebar panel s makry pro import šablonu.
 * Přidá panel "Makra importu" do Document sidebaru (záložka "Import šablona").
 */
(function () {
	'use strict';

	// Ověř že jsme v Gutenberg editoru.
	if ( typeof wp === 'undefined' || ! wp.plugins ) {
		return;
	}

	var el            = wp.element.createElement;
	var useState      = wp.element.useState;
	var registerPlugin = wp.plugins.registerPlugin;
	var dispatch      = wp.data && wp.data.dispatch;

	// PluginDocumentSettingPanel – zkus obě lokace (WP verze se lišily).
	var PluginDocumentSettingPanel =
		( wp.editPost  && wp.editPost.PluginDocumentSettingPanel  ) ||
		( wp.editor    && wp.editor.PluginDocumentSettingPanel    ) ||
		null;

	if ( ! PluginDocumentSettingPanel ) {
		console.warn( '[SAF] PluginDocumentSettingPanel nenalezen.' );
		return;
	}

	var data   = window.safGutenbergData || {};
	var macros = data.macros || [];
	var i18n   = data.i18n   || {};

	// Komponenta panelu.
	function MacroPanel() {
		var searchState = useState( '' );
		var search      = searchState[0];
		var setSearch   = searchState[1];

		var filtered = search
			? macros.filter( function ( m ) {
				return m.macro.toLowerCase().indexOf( search.toLowerCase() ) !== -1
					|| m.col.toLowerCase().indexOf( search.toLowerCase() ) !== -1;
			} )
			: macros;

		function copyMacro( macro ) {
			var text = '{{' + macro + '}}';
			( navigator.clipboard
				? navigator.clipboard.writeText( text )
				: Promise.reject()
			).catch( function () {
				// Fallback pro starší prohlížeče.
				var ta = document.createElement( 'textarea' );
				ta.value = text;
				document.body.appendChild( ta );
				ta.select();
				document.execCommand( 'copy' );
				document.body.removeChild( ta );
				return Promise.resolve();
			} ).then( function () {
				if ( dispatch && dispatch( 'core/notices' ) ) {
					dispatch( 'core/notices' ).createNotice(
						'success',
						( i18n.copied || 'Zkopírováno:' ) + ' ' + text,
						{ type: 'snackbar', isDismissible: true }
					);
				}
			} );
		}

		// Panel content.
		return el( PluginDocumentSettingPanel, {
			name:        'saf-macro-panel',
			title:       i18n.panelTitle || 'Makra importu',
			initialOpen: true,
		},
			// Intro text.
			el( 'p', {
				style: { fontSize: '11px', color: '#757575', marginBottom: '10px', lineHeight: '1.5' }
			},
				macros.length
					? ( i18n.hint || 'Klikni na makro → zkopíruje se. Pak Ctrl+V do bloku.' )
					: ( i18n.noMacros || 'Žádná makra. Vrať se na import stránku (krok 1 mapování).' )
			),

			// Vyhledávání (jen při víc než 6 makrech).
			macros.length > 6 && el( 'input', {
				type:        'text',
				placeholder: i18n.search || 'Hledat...',
				value:       search,
				onChange:    function ( e ) { setSearch( e.target.value ); },
				style: {
					width:         '100%',
					marginBottom:  '8px',
					padding:       '4px 8px',
					border:        '1px solid #ddd',
					borderRadius:  '3px',
					fontSize:      '12px',
					boxSizing:     'border-box',
				}
			} ),

			// Makro čipy.
			el( 'div', { style: { display: 'flex', flexDirection: 'column', gap: '4px' } },
				filtered.map( function ( item ) {
					return el( 'button', {
						key:     item.macro,
						type:    'button',
						onClick: function () { copyMacro( item.macro ); },
						style: {
							display:       'flex',
							flexDirection: 'column',
							alignItems:    'flex-start',
							width:         '100%',
							padding:       '6px 10px',
							background:    '#f0f4ff',
							border:        '1px solid #b3c6f0',
							borderRadius:  '4px',
							cursor:        'pointer',
							textAlign:     'left',
							transition:    'background .12s',
						},
						onMouseEnter: function ( e ) { e.currentTarget.style.background = '#dce8ff'; },
						onMouseLeave: function ( e ) { e.currentTarget.style.background = '#f0f4ff'; },
					},
						el( 'span', {
							style: { fontFamily: 'monospace', fontSize: '12px', fontWeight: '600', color: '#0073aa' }
						}, '{{' + item.macro + '}}' ),
						item.col !== item.macro && el( 'span', {
							style: { fontSize: '10px', color: '#999', marginTop: '1px' }
						}, item.col )
					);
				} )
			),

			// Tip H1.
			el( 'p', {
				style: { fontSize: '10px', color: '#aaa', marginTop: '12px', lineHeight: '1.5' }
			}, i18n.tip || '💡 H1 = Titulek příspěvku (pole pluginu, ne blok obsahu). Začínej od H2.' )
		);
	}

	registerPlugin( 'saf-macro-panel', {
		icon:   'database',
		render: MacroPanel,
	} );

}() );
