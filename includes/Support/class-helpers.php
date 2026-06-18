<?php
/**
 * Pomocné utility funkce pluginu Slovník a Feedy.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Statická kolekce pomocných metod – bez závislostí, vždy dostupná.
 */
final class Helpers {

	// -------------------------------------------------------------------------
	// Feed URL.

	/**
	 * Vrátí URL RSS feedu pro archiv CPT glossary.
	 *
	 * @param 'rss2'|'atom'|'rdf' $type
	 */
	public static function get_archive_feed_url( string $type = 'rss2' ): string {
		$archive = get_post_type_archive_link( 'glossary' );
		if ( ! $archive ) {
			return '';
		}
		$suffix = ( 'rss2' === $type ) ? '' : $type . '/';
		return esc_url( trailingslashit( $archive ) . 'feed/' . $suffix );
	}

	/**
	 * Vrátí URL RSS feedu pro konkrétní term taxonomie (písmeno nebo kategorie).
	 */
	public static function get_term_feed_url( string $taxonomy, string $term_slug ): string {
		$term = get_term_by( 'slug', $term_slug, $taxonomy );
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return '';
		}
		return esc_url( trailingslashit( $link ) . 'feed/' );
	}

	// -------------------------------------------------------------------------
	// Statistiky.

	/**
	 * Vrátí počty pojmů dle statusu.
	 *
	 * @return array{published: int, draft: int, total: int}
	 */
	public static function get_post_counts(): array {
		$counts = wp_count_posts( 'glossary' );
		return [
			'published' => (int) ( $counts->publish ?? 0 ),
			'draft'     => (int) ( $counts->draft ?? 0 ),
			'total'     => (int) ( $counts->publish ?? 0 ) + (int) ( $counts->draft ?? 0 ),
		];
	}

	/**
	 * Vrátí počet termů v dané taxonomii.
	 */
	public static function get_term_count( string $taxonomy ): int {
		$terms = wp_count_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
		return is_wp_error( $terms ) ? 0 : (int) $terms;
	}

	// -------------------------------------------------------------------------
	// Slug a text.

	/**
	 * Odvodí písmeno z prvního znaku textu a vrátí odpovídající slug v glossary_letter.
	 */
	public static function get_letter_slug( string $title ): string {
		$first = mb_strtoupper( mb_substr( trim( $title ), 0, 1 ) );
		// Číslice mapujeme na speciální term 0-9.
		if ( is_numeric( $first ) ) {
			return sanitize_title( '0-9' );
		}
		return sanitize_title( $first );
	}

	/**
	 * Zkrátí text na $limit slov a přidá …
	 */
	public static function excerpt( string $text, int $limit = 30 ): string {
		return wp_trim_words( wp_strip_all_tags( $text ), $limit, '&hellip;' );
	}
}
