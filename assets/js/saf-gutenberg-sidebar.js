/**
 * SAF – Gutenberg sidebar panel s makry pro import šablony.
 * Přidá panel "Makra importu" do Document sidebaru.
 * Kliknutí na makro → zkopíruje {{makro}} do schránky + snackbar notifikace.
 */
(function () {
	'use strict';

	var el                       = wp.element.createElement;
	var registerPlugin           = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var PanelBody                = wp.components.PanelBody;
	var Button                   = wp.components.Button;
	var TextControl              = wp.components.TextControl;
	var Dashicon                 = wp.components.Dashicon;
	var useState                 = wp.element.useState;
	var dispatch                 = wp.data.dispatch;

	// Makra předaná z PHP přes wp_localize_script.
	var macros = window.safGutenbergData ? window.safGutenbergData.macros : [];
	var i18n   = window.safGutenbergData ? window.safGutenbergData.i18n   : {};

	if ( ! macros || ! macros.length ) return;

	registerPlugin( 'saf-macro-panel', {
		icon: 'database',

		render: function () {
			var state = useState( '' );
			var search   = state[0];
			var setSearch = state[1];

			var filtered = search
				? macros.filter( function (m) {
					return m.macro.indexOf( search.toLowerCase() ) !== -1
						|| m.col.toLowerCase().indexOf( search.toLowerCase() ) !== -1;
				  } )
				: macros;

			return el( PluginDocumentSettingPanel, {
				name:  'saf-macro-panel',
				title: i18n.panelTitle || 'Makra importu',
				icon:  el( Dashicon, { icon: 'database' } ),
				initialOpen: true,
			},
				// Nápověda.
				el( 'p', {
					style: {
						fontSize:    '11px',
						color:       '#757575',
						marginBottom: '8px',
						lineHeight:  '1.5',
					}
				}, i18n.hint || 'Klikni na makro → zkopíruje se, pak vlož Ctrl+V do bloku.' ),

				// Vyhledávání (jen pokud je víc než 6 maker).
				macros.length > 6 && el( TextControl, {
					placeholder: i18n.search || 'Hledat makro...',
					value:       search,
					onChange:    setSearch,
					style:       { marginBottom: '8px' },
				} ),

				// Makro čipy.
				el( 'div', { style: { display: 'flex', flexDirection: 'column', gap: '4px' } },
					filtered.length
						? filtered.map( function (item) {
							return el( Button, {
								key:     item.macro,
								variant: 'secondary',
								style: {
									fontFamily:  'monospace',
									fontSize:    '12px',
									padding:     '6px 10px',
									textAlign:   'left',
									width:       '100%',
									display:     'flex',
									flexDirection: 'column',
									alignItems:  'flex-start',
									height:      'auto',
									lineHeight:  '1.4',
								},
								onClick: function () {
									var text = '{{' + item.macro + '}}';
									navigator.clipboard.writeText( text ).then( function () {
										dispatch( 'core/notices' ).createNotice(
											'success',
											( i18n.copied || 'Zkopírováno:' ) + ' ' + text,
											{ type: 'snackbar', isDismissible: true }
										);
									} ).catch( function () {
										// Fallback pro starší prohlížeče.
										var ta = document.createElement( 'textarea' );
										ta.value = text;
										document.body.appendChild( ta );
										ta.select();
										document.execCommand( 'copy' );
										document.body.removeChild( ta );
									} );
								},
							},
								// Makro jméno.
								el( 'span', { style: { color: '#0073aa', fontWeight: '600' } },
									'{{' + item.macro + '}}'
								),
								// Původní název sloupce.
								item.col !== item.macro && el( 'span', {
									style: { color: '#999', fontSize: '10px', marginTop: '1px' }
								}, item.col )
							);
						  } )
						: el( 'p', { style: { color: '#999', fontSize: '12px' } },
							i18n.noResults || 'Žádná makra nenalezena.'
						  )
				),

				// Tip.
				el( 'p', {
					style: {
						fontSize:   '10px',
						color:      '#aaa',
						marginTop:  '12px',
						lineHeight: '1.4',
					}
				}, i18n.tip || '💡 H1 = Titulek příspěvku (pole pluginu, ne blok). V obsahu začínej od H2.' )
			);
		},
	} );

}() );
