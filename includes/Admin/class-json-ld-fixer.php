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

use SlovnikAFeedy\StreamManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JsonLdFixer {

	/**
	 * Opraví JSON-LD ve všech postech daného CPT.
	 *
	 * @return array{fixed: int, skipped: int, errors: int}
	 */
	public static function fix_stream( string $cpt ): array {
		// Mitigate timeout on large datasets; cache invalidation is flushed at end.
		wp_suspend_cache_invalidation( true );

		$posts = get_posts( [
			'post_type'      => $cpt,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$fixed_posts   = 0;
		$skipped_posts = 0;
		$error_posts   = 0;

		foreach ( $posts as $post_id ) {
			$result = static::fix_post( (int) $post_id );
			if ( $result === 1 ) {
				$fixed_posts++;
			} elseif ( $result === 0 ) {
				$skipped_posts++;
			} else {
				$error_posts++;
			}
		}

		wp_suspend_cache_invalidation( false );

		return [
			'fixed'   => $fixed_posts,
			'skipped' => $skipped_posts,
			'errors'  => $error_posts,
		];
	}

	/**
	 * Opraví JSON-LD v jednom příspěvku.
	 *
	 * @return int  1 = opraveno, 0 = přeskočeno (nic k opravě nebo již OK), -1 = chyba
	 */
	public static function fix_post( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return -1;
		}

		$original = $post->post_content;

		// Rychlá kontrola: pokud obsah neobsahuje "@context", přeskočit.
		if ( strpos( $original, '"@context"' ) === false ) {
			return 0;
		}

		// Přeskočit pouze pokud <script> existuje UVNITŘ wp:html bloku – ne globálně.
		// Globální check by přeskočil posty, které mají Rank Math script jinde
		// a zároveň mají rozbité JSON-LD v wp:html bloku.
		if ( preg_match(
			'/(<!-- wp:html -->[\s\S]*?)<script\s+type="application\/ld\+json">([\s\S]*?)<\/script>([\s\S]*?<!-- \/wp:html -->)/i',
			$original
		) ) {
			return 0;
		}

		// Regex: najde JSON-LD objekt uvnitř <!-- wp:html --> bloku za </section>.
		$raw = preg_replace_callback(
			'/(<!-- wp:html -->[\s\S]*?<\/section>)\s*\n(\s*\{\s*"@context"\s*:[\s\S]*?\n\})\s*\n(<!-- \/wp:html -->)/i',
			static function ( array $m ): string {
				$before = $m[1];
				$json   = trim( $m[2] );
				$after  = $m[3];

				// Ověř, že JSON je platný.
				$decoded = json_decode( $json, true );
				if ( $decoded === null || json_last_error() !== JSON_ERROR_NONE ) {
					return $m[0]; // Poškozený JSON – neupravuj.
				}

				// Použij re-enkódovaný JSON aby se předešlo problémům s </script> uvnitř hodnot.
				$safe_json = wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
				if ( $safe_json === false ) {
					return $m[0];
				}

				return $before . "\n\n\n<script type=\"application/ld+json\">\n" . $safe_json . "\n</script>\n\n" . $after;
			},
			$original
		);

		// PCRE chyba (backtrack limit apod.) vrátí null – nesmíme mazat obsah postu.
		if ( $raw === null ) {
			return -1;
		}

		if ( $raw === $original ) {
			return 0;
		}

		$update_result = wp_update_post( [
			'ID'           => $post_id,
			'post_content' => $raw,
		] );

		if ( is_wp_error( $update_result ) || $update_result === 0 ) {
			return -1;
		}

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
			return;
		}

		$cpt = sanitize_key( $_POST['cpt'] ?? '' );

		// Allowlist: pouze CPT registrované tímto pluginem.
		if ( ! $cpt || ! StreamManager::find_by_cpt( $cpt ) ) {
			wp_send_json_error( 'Nepodporovaný nebo neznámý CPT.' );
			return;
		}

		$result = static::fix_stream( $cpt );

		wp_send_json_success( [
			'message' => sprintf(
				'Opraveno %d příspěvků (přeskočeno %d – již OK nebo bez JSON-LD; chyby %d).',
				$result['fixed'],
				$result['skipped'],
				$result['errors']
			),
			'result'  => $result,
		] );
	}
}
