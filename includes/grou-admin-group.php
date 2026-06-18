<?php
/**
 * Sdílená logika pro skupinu "Grou.cz" v administračním menu WordPressu.
 *
 * Každý Grou.cz plugin zavolá grou_register_admin_menu_group( $position )
 * ze svého hooku admin_menu (priorita 999). Separátory a CSS se přidají
 * jen jednou, i když je aktivních více pluginů zároveň.
 *
 * Funkce jsou chráněny function_exists(), aby nedošlo ke kolizi když
 * je tento soubor načten z více Grou.cz pluginů najednou.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'grou_register_admin_menu_group' ) ) {
	/**
	 * Zaregistruje skupinové separátory okolo zadané pozice v admin menu.
	 * Bezpečné pro volání z více pluginů – separátory se vloží jen jednou.
	 *
	 * @param int $plugin_position  Pozice, na které je zaregistrováno menu volajícího pluginu.
	 */
	function grou_register_admin_menu_group( int $plugin_position ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $menu;

		// Pokud horní separator již existuje, skupinu přeskočíme – registruje ji jiný plugin.
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && $item[2] === 'grou-group-sep-top' ) {
				return;
			}
		}

		// Horní separator (čára + nadpis Grou.cz) – těsně před první plugin skupiny.
		$top = $plugin_position - 1;
		while ( isset( $menu[ $top ] ) && $top >= 1 ) {
			$top--;
		}
		if ( $top >= 1 ) {
			$menu[ $top ] = [ '', 'manage_options', 'grou-group-sep-top', '', 'wp-menu-separator grou-menu-header' ];
		}

		// Dolní separator (čára) – těsně za pluginy skupiny.
		$bot = $plugin_position + 1;
		while ( isset( $menu[ $bot ] ) && $bot <= 80 ) {
			$bot++;
		}
		if ( $bot <= 80 ) {
			$menu[ $bot ] = [ '', 'manage_options', 'grou-group-sep-bot', '', 'wp-menu-separator grou-menu-footer' ];
		}
	}
}

if ( ! function_exists( 'grou_output_admin_group_css' ) ) {
	/**
	 * Výstup CSS pro skupinové separátory. Zavolat z admin_head.
	 * Bezpečné pro volání z více pluginů – CSS se vypíše jen jednou.
	 */
	function grou_output_admin_group_css(): void {
		static $printed = false;
		if ( $printed || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$printed = true;

		echo '<style>
/* === Grou.cz – horní separator: čára + nadpis === */
#adminmenu .grou-menu-header {
	display: block !important;
	height: auto !important;
	margin: 10px 0 0 !important;
	padding: 0 !important;
	pointer-events: none;
	overflow: visible !important;
}
#adminmenu .grou-menu-header > div { display: none; }
#adminmenu .grou-menu-header::before {
	content: "";
	display: block;
	border-top: 1px solid rgba(220,220,220,.15);
	margin: 0 0 3px;
}
#adminmenu .grou-menu-header::after {
	content: "Grou.cz";
	display: block;
	padding: 3px 16px 3px;
	font-size: 9px;
	font-weight: 700;
	letter-spacing: 0.8px;
	text-transform: uppercase;
	color: #a7aaad;
}
/* === Grou.cz – dolní separator: čára === */
#adminmenu .grou-menu-footer {
	display: block !important;
	height: auto !important;
	margin: 2px 0 8px !important;
	padding: 0 !important;
	pointer-events: none;
	overflow: visible !important;
}
#adminmenu .grou-menu-footer > div { display: none; }
#adminmenu .grou-menu-footer::before {
	content: "";
	display: block;
	border-top: 1px solid rgba(220,220,220,.15);
	margin: 4px 0;
}
</style>';
	}
}
