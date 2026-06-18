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
	// Gutenberg sidebar.

	/**
	 * Registruje sidebar skript na edit stránkách saf_template.
	 * Volat z Plugin::register_hooks() přes admin_enqueue_scripts.
	 */
	public static function enqueue_sidebar_script(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== self::POST_TYPE || $screen->base !== 'post' ) {
			return;
		}

		// Načti makra uložená v post meta.
		$post_id    = absint( $_GET['post'] ?? get_the_ID() );
		$raw_macros = get_post_meta( $post_id, '_saf_macro_names', true );
		$macros_js  = [];

		if ( is_array( $raw_macros ) ) {
			foreach ( $raw_macros as $col => $macro ) {
				$macros_js[] = [ 'macro' => $macro, 'col' => $col ];
			}
		}

		wp_enqueue_script(
			'saf-gutenberg-sidebar',
			SAF_URL . 'assets/js/saf-gutenberg-sidebar.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-notices', 'wp-i18n' ],
			SAF_VERSION,
			true
		);

		wp_localize_script( 'saf-gutenberg-sidebar', 'safGutenbergData', [
			'macros' => $macros_js,
			'i18n'   => [
				'panelTitle' => __( 'Makra importu', 'slovnik-a-feedy' ),
				'hint'       => __( 'Klikni na makro → zkopíruje se, pak vlož Ctrl+V do bloku.', 'slovnik-a-feedy' ),
				'search'     => __( 'Hledat makro...', 'slovnik-a-feedy' ),
				'copied'     => __( 'Zkopírováno:', 'slovnik-a-feedy' ),
				'noResults'  => __( 'Žádná makra nenalezena.', 'slovnik-a-feedy' ),
				'tip'        => __( '💡 H1 = Titulek příspěvku (pole pluginu). V obsahu začínej od H2.', 'slovnik-a-feedy' ),
			],
		] );
	}

	/**
	 * Uloží makra jako post meta na šabloně (aby byla dostupná v editoru).
	 *
	 * @param array<string, string> $macro_names col → macro_name
	 */
	public static function save_macro_names( int $template_id, array $macro_names ): void {
		update_post_meta( $template_id, '_saf_macro_names', $macro_names );
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
