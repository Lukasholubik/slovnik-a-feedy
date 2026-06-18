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

final class Activator {

	public static function activate(): void {
		// Vytvoř výchozí stream pokud žádný neexistuje.
		StreamManager::create_default();

		// Registruj CPT/tax pro všechny streamy (nutné před flush_rewrite_rules).
		foreach ( StreamManager::get_all() as $stream ) {
			( new PostType\Cpt( $stream ) )->register();
			$tax = new PostType\Taxonomy( $stream );
			$tax->register();
			// Předvyplň písmena A–Z pro každý stream s letter taxonomií.
			if ( $stream['tax_letter'] ) {
				PostType\Taxonomy::seed_letters( $stream );
			}
		}

		// Výchozí options.
		add_option( 'saf_version',        SAF_VERSION );
		add_option( 'saf_default_status', 'publish' );
		add_option( 'saf_gsheet_url',     '' );

		// DB tabulka pro logy.
		Support\Logger::create_table();

		// Capability pro administrátory.
		$role = get_role( 'administrator' );
		if ( $role instanceof \WP_Role ) {
			$role->add_cap( 'manage_glossary' );
		}

		flush_rewrite_rules();
	}
}
