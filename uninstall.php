<?php
/**
 * Odinstalace pluginu Slovník a Feedy.
 * Spustí se pouze při kliknutí na "Smazat" v WP Admin → Pluginy.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Smazání DB tabulky logů.
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'saf_logs' ); // phpcs:ignore

// Smazání všech options pluginu.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'saf\_%'" ); // phpcs:ignore

// Odebrání capability manage_glossary ze všech rolí.
foreach ( wp_roles()->roles as $role_slug => $role_data ) {
	$role = get_role( $role_slug );
	if ( $role instanceof WP_Role ) {
		$role->remove_cap( 'manage_glossary' );
	}
}
