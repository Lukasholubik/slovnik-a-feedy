<?php
/**
 * JSON-LD Fixer – oprava chybějících <script type="application/ld+json"> tagů po importu.
 *
 * Problém: import CSV/XML odstraní <script> tagy (WP sanitizace) a JSON-LD schema
 * se zobrazí jako viditelný text na stránce uvnitř <!-- wp:html --> bloku.
 *
 * Řešení: najít JSON-LD objekty uvnitř wp:html bloků bez <script> obálky a obalit je.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JsonLdFixer {

	/**
	 * Opraví JSON-LD ve všech postech daného CPT.
	 *
	 * @return array{posts: int, fixed: int, skipped: int}
	 */
	public static function fix_stream( string $cpt ): array {
		$posts = get_posts( [
			'post_type'      => $cpt,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$fixed_posts   = 0;
		$skipped_posts = 0;

		foreach ( $posts as $post_id ) {
			$result = static::fix_post( (int) $post_id );
			if ( $result === 1 ) {
				$fixed_posts++;
			} elseif ( $result === 0 ) {
				$skipped_posts++;
			}
		}

		return [
			'posts'   => $fixed_posts,
			'fixed'   => $fixed_posts,
			'skipped' => $skipped_posts,
		];
	}

	/**
	 * Opraví JSON-LD v jednom příspěvku.
	 *
	 * @return int  1 = opraveno, 0 = přeskočeno (nic k opravě nebo už OK), -1 = chyba
	 */
	public static function fix_post( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return -1;
		}

		$original = $post->post_content;

		// Rychlá kontrola: pokud obsah neobsahuje schema.org, přeskočit.
		if ( strpos( $original, '"@context"' ) === false ) {
			return 0;
		}

		// Pokud <script type="application/ld+json"> již existuje, přeskočit.
		if ( strpos( $original, '<script type="application/ld+json">' ) !== false ) {
			return 0;
		}

		// Regex: najde JSON-LD objekt uvnitř <!-- wp:html --> bloku za </section>.
		// Zachytí: blok od <section> po </section>, pak JSON objekt, pak uzavírací komentář.
		$fixed = (string) preg_replace_callback(
			'/(<!-- wp:html -->[\s\S]*?<\/section>)\s*\n(\s*\{\s*"@context"\s*:[\s\S]*?\n\})\s*\n(<!-- \/wp:html -->)/i',
			static function ( array $m ): string {
				$before  = $m[1];
				$json    = trim( $m[2] );
				$after   = $m[3];

				// Ověř, že JSON je platný.
				$decoded = json_decode( $json, true );
				if ( $decoded === null ) {
					return $m[0]; // Poškozený JSON – neupravuj.
				}

				return $before . "\n\n\n<script type=\"application/ld+json\">\n" . $json . "\n</script>\n\n" . $after;
			},
			$original
		);

		if ( $fixed === $original ) {
			return 0;
		}

		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => $fixed,
		] );

		return 1;
	}

	/**
	 * Registrace AJAX handleru.
	 */
	public static function register_ajax(): void {
		add_action( 'wp_ajax_saf_fix_json_ld', [ static::class, 'ajax_fix_json_ld' ] );
	}

	public static function ajax_fix_json_ld(): void {
		check_ajax_referer( 'saf_fix_json_ld', 'nonce' );
		if ( ! current_user_can( 'manage_glossary' ) ) {
			wp_send_json_error( 'Nedostatečná oprávnění.' );
		}

		$cpt    = sanitize_key( $_POST['cpt'] ?? '' );
		$result = static::fix_stream( $cpt );

		wp_send_json_success( [
			'message' => sprintf(
				'Opraveno %d příspěvků (přeskočeno %d – již OK nebo bez JSON-LD).',
				$result['fixed'],
				$result['skipped']
			),
			'result'  => $result,
		] );
	}
}
