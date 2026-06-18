<?php
/**
 * Registrace taxonomií pro jeden stream – dynamická dle konfigurace.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\PostType;

use SlovnikAFeedy\StreamManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registruje taxonomie glossary_letter a glossary_cat pro daný stream.
 * Slugy taxonomií = {cpt}_letter a {cpt}_cat, takže jsou unikátní per-stream.
 */
final class Taxonomy {

	/** @param array $stream  Konfigurace ze StreamManager::get() */
	public function __construct( private readonly array $stream ) {}

	public function register(): void {
		if ( $this->stream['tax_letter'] ) {
			$this->register_letter();
		}
		if ( $this->stream['tax_cat'] ) {
			$this->register_cat();
		}
	}

	// -------------------------------------------------------------------------

	private function register_letter(): void {
		$tax  = StreamManager::tax_letter( $this->stream );
		$name = $this->stream['name'];

		register_taxonomy(
			$tax,
			$this->stream['cpt'],
			[
				'labels'             => [
					'name'          => sprintf( _x( 'Písmena – %s', 'taxonomy general name', 'slovnik-a-feedy' ), $name ),
					'singular_name' => _x( 'Písmeno', 'taxonomy singular name', 'slovnik-a-feedy' ),
					'search_items'  => __( 'Hledat písmena', 'slovnik-a-feedy' ),
					'all_items'     => __( 'Všechna písmena', 'slovnik-a-feedy' ),
					'edit_item'     => __( 'Upravit písmeno', 'slovnik-a-feedy' ),
					'update_item'   => __( 'Aktualizovat písmeno', 'slovnik-a-feedy' ),
					'add_new_item'  => __( 'Přidat písmeno', 'slovnik-a-feedy' ),
					'menu_name'     => __( 'Písmena (A–Z)', 'slovnik-a-feedy' ),
					'not_found'     => __( 'Žádná písmena nenalezena.', 'slovnik-a-feedy' ),
				],
				'public'             => true,   // Nutné pro Elementor/Crocoblock podmínky.
				'hierarchical'       => false,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_nav_menus'  => true,   // Elementor čte tento flag při generování podmínek.
				'show_tagcloud'      => false,
				'query_var'          => true,
				'rewrite'            => [
					'slug'       => $this->stream['url_slug'] . '/pismeno',
					'with_front' => false,
					'feeds'      => true,
				],
				'show_in_rest'       => true,
				'rest_base'          => $this->stream['cpt'] . '-letter',
				// Bez custom capabilities – standardní WP práva.
				// Custom capabilities blokovaly Elementor při detekci taxonomie.
			]
		);
	}

	private function register_cat(): void {
		$tax  = StreamManager::tax_cat( $this->stream );
		$name = $this->stream['name'];

		register_taxonomy(
			$tax,
			$this->stream['cpt'],
			[
				'labels'             => [
					'name'              => sprintf( _x( 'Kategorie – %s', 'taxonomy general name', 'slovnik-a-feedy' ), $name ),
					'singular_name'     => _x( 'Kategorie', 'taxonomy singular name', 'slovnik-a-feedy' ),
					'search_items'      => __( 'Hledat kategorie', 'slovnik-a-feedy' ),
					'all_items'         => __( 'Všechny kategorie', 'slovnik-a-feedy' ),
					'parent_item'       => __( 'Nadřazená kategorie', 'slovnik-a-feedy' ),
					'parent_item_colon' => __( 'Nadřazená kategorie:', 'slovnik-a-feedy' ),
					'edit_item'         => __( 'Upravit kategorii', 'slovnik-a-feedy' ),
					'update_item'       => __( 'Aktualizovat kategorii', 'slovnik-a-feedy' ),
					'add_new_item'      => __( 'Přidat novou kategorii', 'slovnik-a-feedy' ),
					'menu_name'         => sprintf( __( 'Kategorie – %s', 'slovnik-a-feedy' ), $name ),
					'not_found'         => __( 'Žádné kategorie nenalezeny.', 'slovnik-a-feedy' ),
				],
				'hierarchical'       => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_nav_menus'  => true,
				'query_var'          => true,
				'rewrite'            => [
					'slug'         => $this->stream['url_slug'] . '/kategorie',
					'with_front'   => false,
					'hierarchical' => true,
					'feeds'        => true,
				],
				'show_in_rest'       => true,
				'rest_base'          => $this->stream['cpt'] . '-cat',
			]
		);
	}

	// -------------------------------------------------------------------------

	/**
	 * Předvyplní taxon _letter písmeny A–Z a skupinou 0–9.
	 * Volá se při aktivaci nebo vytvoření nového streamu.
	 */
	public static function seed_letters( array $stream ): void {
		$tax     = StreamManager::tax_letter( $stream );
		$letters = array_merge( range( 'A', 'Z' ), [ '0–9' ] );

		foreach ( $letters as $letter ) {
			if ( ! term_exists( $letter, $tax ) ) {
				wp_insert_term(
					$letter,
					$tax,
					[ 'slug' => sanitize_title( $letter ) ]
				);
			}
		}
	}
}
