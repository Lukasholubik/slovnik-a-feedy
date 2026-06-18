<?php
/**
 * Logika aktivace pluginu.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spouští se při aktivaci pluginu přes WP Admin.
 */
final class Activator {

	public static function activate(): void {
		// Registruj CPT a taxonomie před flush_rewrite_rules.
		$cpt      = new PostType\Cpt();
		$taxonomy = new PostType\Taxonomy();
		$cpt->register();
		$taxonomy->register();

		// Předvyplň taxon glossary_letter písmeny A–Z + 0–9.
		PostType\Taxonomy::seed_letters();

		// Výchozí nastavení pluginu (přidá jen pokud ještě neexistují).
		add_option( 'saf_version',        SAF_VERSION );
		add_option( 'saf_default_status', 'publish' );
		add_option( 'saf_gsheet_url',     '' );
		add_option( 'saf_reimport_schedule', 'off' );

		// Vytvoření DB tabulky pro logy.
		Support\Logger::create_table();

		// Přidej vlastní capability administrátorům.
		$role = get_role( 'administrator' );
		if ( $role instanceof \WP_Role ) {
			$role->add_cap( 'manage_glossary' );
		}

		flush_rewrite_rules();
	}
}
