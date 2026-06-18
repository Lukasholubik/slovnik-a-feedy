<?php
/**
 * Registrace Custom Post Type: glossary (Pojmy slovníčku).
 *
 * Archiv: /slovnik/
 * Singl:  /slovnik/{slug}/
 * Feed:   /slovnik/feed/ a /slovnik/feed/atom/
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\PostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPT glossary – samostatný stream pojmů kompatibilní s Gutenbergem i Elementorem.
 */
final class Cpt {

	public const POST_TYPE    = 'glossary';
	public const REWRITE_SLUG = 'slovnik';

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'               => $this->get_labels(),
				'description'          => __( 'Pojmy slovníčku – streamovaný obsah s vlastním RSS feedem.', 'slovnik-a-feedy' ),
				'public'               => true,
				'publicly_queryable'   => true,
				'show_ui'              => true,
				'show_in_menu'         => true,
				'show_in_nav_menus'    => true,
				'show_in_admin_bar'    => true,
				'query_var'            => true,
				/**
				 * feeds => true aktivuje /slovnik/feed/ (RSS2) a /slovnik/feed/atom/.
				 * with_front => false – URL nezačíná /blog/ ani jinou přednastavenou přeponou.
				 */
				'rewrite'              => [
					'slug'       => self::REWRITE_SLUG,
					'with_front' => false,
					'feeds'      => true,
					'pages'      => true,
				],
				'capability_type'      => 'post',
				'map_meta_cap'         => true,
				'has_archive'          => self::REWRITE_SLUG,
				'hierarchical'         => false,
				'menu_position'        => 5,
				'menu_icon'            => 'dashicons-book-alt',
				'supports'             => [
					'title',
					'editor',
					'excerpt',
					'custom-fields',
					'thumbnail',
					'revisions',
					'author',
				],
				'taxonomies'           => [
					Taxonomy::TAX_LETTER,
					Taxonomy::TAX_CAT,
				],
				// REST API – nutné pro Gutenberg i Elementor Theme Builder.
				'show_in_rest'         => true,
				'rest_base'            => 'glossary',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
			]
		);
	}

	/** @return array<string, string|null> */
	private function get_labels(): array {
		return [
			'name'                  => _x( 'Pojmy', 'post type general name', 'slovnik-a-feedy' ),
			'singular_name'         => _x( 'Pojem', 'post type singular name', 'slovnik-a-feedy' ),
			'menu_name'             => __( 'Pojmy slovníčku', 'slovnik-a-feedy' ),
			'name_admin_bar'        => __( 'Pojem', 'slovnik-a-feedy' ),
			'add_new'               => __( 'Přidat nový', 'slovnik-a-feedy' ),
			'add_new_item'          => __( 'Přidat nový pojem', 'slovnik-a-feedy' ),
			'new_item'              => __( 'Nový pojem', 'slovnik-a-feedy' ),
			'edit_item'             => __( 'Upravit pojem', 'slovnik-a-feedy' ),
			'view_item'             => __( 'Zobrazit pojem', 'slovnik-a-feedy' ),
			'view_items'            => __( 'Zobrazit pojmy', 'slovnik-a-feedy' ),
			'all_items'             => __( 'Všechny pojmy', 'slovnik-a-feedy' ),
			'search_items'          => __( 'Hledat pojmy', 'slovnik-a-feedy' ),
			'not_found'             => __( 'Žádné pojmy nenalezeny.', 'slovnik-a-feedy' ),
			'not_found_in_trash'    => __( 'Žádné pojmy v koši.', 'slovnik-a-feedy' ),
			'archives'              => __( 'Archiv pojmů', 'slovnik-a-feedy' ),
			'attributes'            => __( 'Atributy pojmu', 'slovnik-a-feedy' ),
			'featured_image'        => __( 'Náhledový obrázek pojmu', 'slovnik-a-feedy' ),
			'set_featured_image'    => __( 'Nastavit náhledový obrázek', 'slovnik-a-feedy' ),
			'remove_featured_image' => __( 'Odebrat náhledový obrázek', 'slovnik-a-feedy' ),
			'use_featured_image'    => __( 'Použít jako náhledový obrázek', 'slovnik-a-feedy' ),
			'insert_into_item'      => __( 'Vložit do pojmu', 'slovnik-a-feedy' ),
			'uploaded_to_this_item' => __( 'Nahráno k tomuto pojmu', 'slovnik-a-feedy' ),
			'items_list'            => __( 'Seznam pojmů', 'slovnik-a-feedy' ),
			'items_list_navigation' => __( 'Navigace v seznamu pojmů', 'slovnik-a-feedy' ),
			'filter_items_list'     => __( 'Filtrovat seznam pojmů', 'slovnik-a-feedy' ),
			'parent_item_colon'     => null,
		];
	}
}
