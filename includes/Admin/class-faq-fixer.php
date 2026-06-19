<?php
/**
 * FAQ Fixer – oprava Rank Math FAQ bloků po importu.
 *
 * Problém: po importu HTML v FAQ bloku neodpovídá co Gutenberg save() generuje
 * (chybí <strong> kolem titulku, jiné formátování atd.) → chyba validace.
 *
 * Řešení: Odstranit HTML content mezi block komentáři a ponechat jen JSON.
 * Rank Math FAQ blok je dynamický – renderuje se ze server-side z JSON atributů.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FaqFixer {

	/**
	 * Opraví FAQ bloky v jednom příspěvku.
	 * Odstraní HTML content mezi block komentáři – Rank Math ho vygeneruje sám.
	 *
	 * @return int  Počet opravených bloků.
	 */
	public static function fix_post( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 0;
		}

		$original = $post->post_content;

		// Odstraní HTML content uvnitř rank-math FAQ bloku a ponechá jen JSON komentáře.
		$fixed = (string) preg_replace_callback(
			'/(<!--\s*wp:rank-math\/faq-block[^>]*-->)\s*([\s\S]*?)\s*(<!--\s*\/wp:rank-math\/faq-block\s*-->)/i',
			static function ( array $m ): string {
				$opening = trim( $m[1] );
				$closing = trim( $m[3] );
				// Ověř, že JSON v block komentáři je platný.
				preg_match( '/<!--\s*wp:rank-math\/faq-block\s+(\{.*?\})\s*-->/si', $opening, $j );
				if ( ! empty( $j[1] ) && json_decode( $j[1] ) === null ) {
					// JSON je poškozený – neupravuj.
					return $m[0];
				}
				// Vrať jen otevírací + zavírací komentář bez HTML obsahu.
				return $opening . "\n" . $closing;
			},
			$original
		);

		if ( $fixed === $original ) {
			return 0; // Žádná změna.
		}

		// Ulož bez wp_kses_post – blokové komentáře musí zůstat neporušené.
		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => $fixed,
		] );

		return substr_count( $fixed, 'wp:rank-math/faq-block' ) - substr_count( $original, 'wp:rank-math/faq-block' ) >= 0
			? substr_count( $original, 'wp:rank-math/faq-block' )
			: 0;
	}

	/**
	 * Opraví FAQ bloky ve všech postech daného CPT streamu.
	 *
	 * @return array{posts: int, blocks: int}
	 */
	public static function fix_stream( string $cpt ): array {
		$posts = get_posts( [
			'post_type'      => $cpt,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$fixed_posts  = 0;
		$fixed_blocks = 0;

		foreach ( $posts as $post_id ) {
			$n = static::fix_post( (int) $post_id );
			if ( $n > 0 ) {
				$fixed_posts++;
				$fixed_blocks += $n;
			}
		}

		return [ 'posts' => $fixed_posts, 'blocks' => $fixed_blocks ];
	}

	/**
	 * Registrace AJAX handleru.
	 */
	public static function register_ajax(): void {
		add_action( 'wp_ajax_saf_fix_faq', [ static::class, 'ajax_fix_faq' ] );
	}

	public static function ajax_fix_faq(): void {
		check_ajax_referer( 'saf_fix_faq', 'nonce' );
		if ( ! current_user_can( 'manage_glossary' ) ) {
			wp_send_json_error( 'Nedostatečná oprávnění.' );
		}

		$cpt    = sanitize_key( $_POST['cpt'] ?? '' );
		$result = static::fix_stream( $cpt );

		wp_send_json_success( [
			'message' => sprintf(
				'Opraveno %d příspěvků (%d FAQ bloků). Rank Math vygeneruje HTML při načtení stránky.',
				$result['posts'],
				$result['blocks']
			),
			'result'  => $result,
		] );
	}
}
