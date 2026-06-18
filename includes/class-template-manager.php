<?php
/**
 * Správa import šablon – CPT saf_template.
 *
 * Každá šablona = draft příspěvek editovatelný v Gutenbergu.
 * Obsah šablony může obsahovat makra {{makro}} přímo v blocích.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TemplateManager {

	public const POST_TYPE = 'saf_template';

	// -------------------------------------------------------------------------
	// Registrace CPT.

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'              => [
					'name'          => __( 'Import šablony', 'slovnik-a-feedy' ),
					'singular_name' => __( 'Import šablona', 'slovnik-a-feedy' ),
					'add_new_item'  => __( 'Nová šablona', 'slovnik-a-feedy' ),
					'edit_item'     => __( 'Upravit šablonu', 'slovnik-a-feedy' ),
					'menu_name'     => __( 'Import šablony', 'slovnik-a-feedy' ),
				],
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => false, // Schováno z hlavního menu – přístup přes SAF menu.
				'show_in_rest'        => true,  // Nutné pro Gutenberg editor.
				'rest_base'           => 'saf-templates',
				'capability_type'     => 'post',
				'supports'            => [ 'title', 'editor', 'revisions' ],
				'hierarchical'        => false,
			]
		);
	}

	// -------------------------------------------------------------------------
	// CRUD.

	/**
	 * Vrátí všechny šablony jako id => title.
	 *
	 * @return array<int, string>
	 */
	public static function get_all(): array {
		$posts = get_posts( [
			'post_type'      => self::POST_TYPE,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$result = [];
		foreach ( $posts as $post ) {
			$result[ $post->ID ] = $post->post_title ?: __( '(Bez názvu)', 'slovnik-a-feedy' );
		}
		return $result;
	}

	/**
	 * Vrátí post_content šablony (pro použití v importeru).
	 */
	public static function get_content( int $template_id ): string {
		$post = get_post( $template_id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return '';
		}
		return $post->post_content;
	}

	/**
	 * Vytvoří novou prázdnou šablonu a vrátí její ID.
	 */
	public static function create( string $title = '' ): int {
		$id = wp_insert_post( [
			'post_type'    => self::POST_TYPE,
			'post_title'   => $title ?: __( 'Nová šablona importu', 'slovnik-a-feedy' ),
			'post_status'  => 'draft',
			'post_content' => self::placeholder_content(),
		] );

		return is_wp_error( $id ) ? 0 : $id;
	}

	/**
	 * Vrátí URL pro editaci šablony v Gutenbergu.
	 */
	public static function get_edit_url( int $template_id ): string {
		return get_edit_post_link( $template_id, 'raw' ) ?? '';
	}

	// -------------------------------------------------------------------------

	/**
	 * Výchozí obsah nové šablony – ukazuje jak makra fungují.
	 */
	private static function placeholder_content(): string {
		return <<<'CONTENT'
<!-- wp:paragraph -->
<p><strong>Jak používat makra:</strong> Napiš <code>{{název_makra}}</code> přímo do bloku. Plugin ho při importu nahradí hodnotou ze sloupce.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Příklad: <code>{{short_definice}}</code> se nahradí textem z tvého sloupce "Short definice".</p>
<!-- /wp:paragraph -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:paragraph -->
<p>Sem vlož svůj obsah s makry. Smaž tento text a použij normálně Gutenberg bloky.</p>
<!-- /wp:paragraph -->
CONTENT;
	}
}
