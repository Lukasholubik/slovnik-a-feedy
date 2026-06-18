<?php
/**
 * Exportér pojmů do CSV nebo XML (round-trip kompatibilní s importem).
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Exporter;

use SlovnikAFeedy\Importer\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exportuje příspěvky z CPT streamu do CSV nebo XML.
 * Schéma odpovídá importnímu – round-trip (export → úprava → re-import).
 */
final class Exporter {

	/** @var array  Konfigurace cílového streamu. */
	private array $stream;

	public function __construct( array $stream ) {
		$this->stream = $stream;
	}

	// -------------------------------------------------------------------------
	// CSV export.

	/**
	 * Odešle CSV soubor jako HTTP download response.
	 * Volat z admin handleru (před odesláním headers).
	 */
	public function export_csv( int $limit = 0 ): void {
		$rows   = $this->get_rows( $limit );
		$fields = array_keys( $rows[0] ?? [] );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="export-' . sanitize_file_name( $this->stream['name'] ) . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM pro Excel.
		fwrite( $out, "\xEF\xBB\xBF" );
		// Hlavička.
		fputcsv( $out, $fields );
		// Data.
		foreach ( $rows as $row ) {
			fputcsv( $out, array_values( $row ) );
		}
		fclose( $out );
	}

	/**
	 * Odešle XML soubor jako HTTP download response.
	 */
	public function export_xml( int $limit = 0 ): void {
		$rows = $this->get_rows( $limit );

		header( 'Content-Type: text/xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="export-' . sanitize_file_name( $this->stream['name'] ) . '-' . gmdate( 'Y-m-d' ) . '.xml"' );
		header( 'Pragma: no-cache' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<items>' . "\n";
		foreach ( $rows as $row ) {
			echo '  <item>' . "\n";
			foreach ( $row as $key => $val ) {
				$tag = preg_replace( '/[^a-z0-9_-]/i', '_', $key );
				echo '    <' . esc_html( $tag ) . '>' . esc_html( $val ) . '</' . esc_html( $tag ) . ">\n";
			}
			echo '  </item>' . "\n";
		}
		echo '</items>';
	}

	// -------------------------------------------------------------------------
	// Datová vrstva.

	/**
	 * Vrátí pole řádků pro export.
	 * Schéma: všechna pole Mapperu + vlastní meta.
	 *
	 * @return list<array<string, string>>
	 */
	public function get_rows( int $limit = 0 ): array {
		global $wpdb;

		$cpt  = $this->stream['cpt'];
		$args = [
			'post_type'      => $cpt,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		];

		$posts  = get_posts( $args );
		$rows   = [];

		foreach ( $posts as $post ) {
			$external_id = (string) get_post_meta( $post->ID, Importer::META_EXTERNAL_ID, true );

			// Taxonomie.
			$letters = wp_get_object_terms( $post->ID, $cpt . '_letter', [ 'fields' => 'slugs' ] );
			$cats    = wp_get_object_terms( $post->ID, $cpt . '_cat',    [ 'fields' => 'slugs' ] );
			$letters = is_wp_error( $letters ) ? [] : $letters;
			$cats    = is_wp_error( $cats )    ? [] : $cats;

			// SEO meta (Rank Math).
			$seo_title  = get_post_meta( $post->ID, 'rank_math_title',         true );
			$seo_desc   = get_post_meta( $post->ID, 'rank_math_description',   true );
			$seo_kw     = get_post_meta( $post->ID, 'rank_math_focus_keyword', true );

			$rows[] = [
				'external_id'     => $external_id ?: (string) $post->ID,
				'title'           => $post->post_title,
				'slug'            => $post->post_name,
				'status'          => $post->post_status,
				'excerpt'         => $post->post_excerpt,
				'content'         => wp_strip_all_tags( $post->post_content ),
				'letter'          => is_array( $letters ) ? implode( ',', $letters ) : '',
				'category'        => is_array( $cats ) ? implode( ',', $cats ) : '',
				'seo_title'       => $seo_title ?: '',
				'seo_description' => $seo_desc  ?: '',
				'seo_keyword'     => $seo_kw    ?: '',
			];
		}

		return $rows;
	}

	/**
	 * Vrátí počet příspěvků pro preview.
	 */
	public function count(): int {
		return (int) wp_count_posts( $this->stream['cpt'] )->publish
			 + (int) wp_count_posts( $this->stream['cpt'] )->draft;
	}
}
