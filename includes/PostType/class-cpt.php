<?php
/**
 * Registrace Custom Post Type – dynamická, řízená konfigurací streamu.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\PostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registruje jeden CPT dle konfigurace streamu.
 * Každý stream dostane vlastní CPT, archiv, RSS feedy a REST endpoint.
 */
final class Cpt {

	/** @param array $stream  Konfigurace ze StreamManager::get() */
	public function __construct( private readonly array $stream ) {}

	public function get_post_type(): string {
		return $this->stream['cpt'];
	}

	public function get_url_slug(): string {
		return $this->stream['url_slug'];
	}

	public function register(): void {
		register_post_type(
			$this->stream['cpt'],
			[
				'labels'               => $this->get_labels(),
				'description'          => sprintf(
					/* translators: %s: název streamu */
					__( 'Stream „%s" – obsah s vlastním RSS feedem a archivem.', 'slovnik-a-feedy' ),
					$this->stream['name']
				),
				'public'               => true,
				'publicly_queryable'   => true,
				'show_ui'              => true,
				'show_in_menu'         => true,
				'show_in_nav_menus'    => true,
				'show_in_admin_bar'    => true,
				'query_var'            => true,
				'rewrite'              => [
					'slug'       => $this->stream['url_slug'],
					'with_front' => false,
					'feeds'      => true,   // /url_slug/feed/ a /url_slug/feed/atom/
					'pages'      => true,
				],
				'capability_type'      => 'post',
				'map_meta_cap'         => true,
				'has_archive'          => $this->stream['url_slug'],
				'hierarchical'         => false,
				'menu_position'        => 5,
				'menu_icon'            => $this->stream['icon'],
				'supports'             => [
					'title',
					'editor',
					'excerpt',
					'custom-fields',
					'thumbnail',
					'revisions',
					'author',
				],
				'taxonomies'           => $this->get_taxonomy_slugs(),
				'show_in_rest'         => true,
				'rest_base'            => $this->stream['cpt'],
				'rest_controller_class' => 'WP_REST_Posts_Controller',
			]
		);
	}

	/** @return list<string> */
	private function get_taxonomy_slugs(): array {
		$taxes = [];
		if ( $this->stream['tax_letter'] ) {
			$taxes[] = $this->stream['cpt'] . '_letter';
		}
		if ( $this->stream['tax_cat'] ) {
			$taxes[] = $this->stream['cpt'] . '_cat';
		}
		return $taxes;
	}

	/** @return array<string, string|null> */
	private function get_labels(): array {
		$name = esc_html( $this->stream['name'] );

		return [
			// name/singular_name záměrně BEZ "Grou.cz" – čtou je Rank Math breadcrumby.
			'name'                  => $name,
			'singular_name'         => $name,
			'menu_name'             => $name,
			'name_admin_bar'        => $name,
			'add_new'               => __( 'Přidat nový', 'slovnik-a-feedy' ),
			'add_new_item'          => sprintf( __( 'Přidat nový – %s', 'slovnik-a-feedy' ), $name ),
			'new_item'              => sprintf( __( 'Nový – %s', 'slovnik-a-feedy' ), $name ),
			'edit_item'             => sprintf( __( 'Upravit – %s', 'slovnik-a-feedy' ), $name ),
			'view_item'             => sprintf( __( 'Zobrazit – %s', 'slovnik-a-feedy' ), $name ),
			'all_items'             => sprintf( __( 'Vše – %s', 'slovnik-a-feedy' ), $name ),
			'search_items'          => sprintf( __( 'Hledat v %s', 'slovnik-a-feedy' ), $name ),
			'not_found'             => __( 'Žádné záznamy nenalezeny.', 'slovnik-a-feedy' ),
			'not_found_in_trash'    => __( 'Žádné záznamy v koši.', 'slovnik-a-feedy' ),
			'archives'              => sprintf( __( 'Archiv – %s', 'slovnik-a-feedy' ), $name ),
			'parent_item_colon'     => null,
		];
	}
}
