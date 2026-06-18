<?php
/**
 * Registrace taxonomií pro CPT glossary.
 *
 * glossary_letter – A–Z navigace (flat)
 * glossary_cat    – hierarchické kategorie pojmů
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\PostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxonomie slovníčku – písmena (A–Z) a kategorie.
 */
final class Taxonomy {

	public const TAX_LETTER = 'glossary_letter';
	public const TAX_CAT    = 'glossary_cat';

	public function register(): void {
		$this->register_letter();
		$this->register_cat();
	}

	private function register_letter(): void {
		register_taxonomy(
			self::TAX_LETTER,
			Cpt::POST_TYPE,
			[
				'labels'             => [
					'name'              => _x( 'Písmena', 'taxonomy general name', 'slovnik-a-feedy' ),
					'singular_name'     => _x( 'Písmeno', 'taxonomy singular name', 'slovnik-a-feedy' ),
					'search_items'      => __( 'Hledat písmena', 'slovnik-a-feedy' ),
					'all_items'         => __( 'Všechna písmena', 'slovnik-a-feedy' ),
					'edit_item'         => __( 'Upravit písmeno', 'slovnik-a-feedy' ),
					'update_item'       => __( 'Aktualizovat písmeno', 'slovnik-a-feedy' ),
					'add_new_item'      => __( 'Přidat písmeno', 'slovnik-a-feedy' ),
					'new_item_name'     => __( 'Název nového písmene', 'slovnik-a-feedy' ),
					'menu_name'         => __( 'Písmena (A–Z)', 'slovnik-a-feedy' ),
					'not_found'         => __( 'Žádná písmena nenalezena.', 'slovnik-a-feedy' ),
					'back_to_items'     => __( 'Zpět na písmena', 'slovnik-a-feedy' ),
				],
				'hierarchical'       => false,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_nav_menus'  => true,
				'show_tagcloud'      => false,
				'query_var'          => true,
				// feeds => true zajistí /pismeno/a/feed/ pro každé písmeno.
				'rewrite'            => [
					'slug'       => 'pismeno',
					'with_front' => false,
					'feeds'      => true,
				],
				'show_in_rest'       => true,
				'rest_base'          => 'glossary-letter',
				// Správu písmen omezíme na manage_glossary, přiřazení na edit_posts.
				'capabilities'       => [
					'manage_terms' => 'manage_glossary',
					'edit_terms'   => 'manage_glossary',
					'delete_terms' => 'manage_glossary',
					'assign_terms' => 'edit_posts',
				],
			]
		);
	}

	private function register_cat(): void {
		register_taxonomy(
			self::TAX_CAT,
			Cpt::POST_TYPE,
			[
				'labels'             => [
					'name'              => _x( 'Kategorie slovníčku', 'taxonomy general name', 'slovnik-a-feedy' ),
					'singular_name'     => _x( 'Kategorie', 'taxonomy singular name', 'slovnik-a-feedy' ),
					'search_items'      => __( 'Hledat kategorie', 'slovnik-a-feedy' ),
					'all_items'         => __( 'Všechny kategorie', 'slovnik-a-feedy' ),
					'parent_item'       => __( 'Nadřazená kategorie', 'slovnik-a-feedy' ),
					'parent_item_colon' => __( 'Nadřazená kategorie:', 'slovnik-a-feedy' ),
					'edit_item'         => __( 'Upravit kategorii', 'slovnik-a-feedy' ),
					'update_item'       => __( 'Aktualizovat kategorii', 'slovnik-a-feedy' ),
					'add_new_item'      => __( 'Přidat novou kategorii', 'slovnik-a-feedy' ),
					'new_item_name'     => __( 'Název nové kategorie', 'slovnik-a-feedy' ),
					'menu_name'         => __( 'Kategorie', 'slovnik-a-feedy' ),
					'not_found'         => __( 'Žádné kategorie nenalezeny.', 'slovnik-a-feedy' ),
					'back_to_items'     => __( 'Zpět na kategorie', 'slovnik-a-feedy' ),
				],
				'hierarchical'       => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_nav_menus'  => true,
				'show_tagcloud'      => true,
				'query_var'          => true,
				'rewrite'            => [
					'slug'         => 'kategorie-slovniku',
					'with_front'   => false,
					'hierarchical' => true,
					'feeds'        => true,
				],
				'show_in_rest'       => true,
				'rest_base'          => 'glossary-cat',
			]
		);
	}

	/**
	 * Předvyplní taxon glossary_letter písmeny A–Z a skupinou 0–9.
	 * Volá se při aktivaci pluginu – pokud term již existuje, přeskočí ho.
	 */
	public static function seed_letters(): void {
		$letters = array_merge( range( 'A', 'Z' ), [ '0–9' ] );

		foreach ( $letters as $letter ) {
			if ( ! term_exists( $letter, self::TAX_LETTER ) ) {
				wp_insert_term(
					$letter,
					self::TAX_LETTER,
					[ 'slug' => sanitize_title( $letter ) ]
				);
			}
		}
	}
}
