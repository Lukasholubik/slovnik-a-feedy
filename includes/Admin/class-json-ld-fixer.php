<?php
/**
 * JSON-LD Fixer – oprava chybějících <script type="application/ld+json"> tagů po importu.
 *
 * Problém: import CSV/XML odstraní <script> tagy (WP sanitizace) a JSON-LD schema
 * se zobrazí jako viditelný text na stránce uvnitř <!-- wp:html --> bloku.
 *
 * Řešení: zpracovat každý wp:html blok zvlášť, najít JSON-LD pomocí sledování
 * hloubky závorek (podporuje jednořádkový i víceřádkový JSON), obalit <script> tagem.
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

		if ( strpos( $original, '"@context"' ) === false ) {
			return 0;
		}

		// Zpracuj každý wp:html blok zvlášť.
		$changed = false;
		$fixed   = (string) preg_replace_callback(
			'/<!-- wp:html -->([\s\S]*?)<!-- \/wp:html -->/i',
			static function ( array $m ) use ( &$changed ): string {
				$inner = $m[1];

				// Blok nemá JSON-LD → přeskočit.
				if ( strpos( $inner, '"@context"' ) === false ) {
					return $m[0];
				}

				// Blok již má <script> tag → přeskočit.
				if ( stripos( $inner, '<script type="application/ld+json">' ) !== false ) {
					return $m[0];
				}

				$result = self::wrap_json_ld_in_block( $inner );
				if ( $result === $inner ) {
					return $m[0];
				}

				$changed = true;
				return '<!-- wp:html -->' . $result . '<!-- /wp:html -->';
			},
			$original
		);

		if ( ! $changed || $fixed === null || $fixed === $original ) {
			return 0;
		}

		$update = wp_update_post( [
			'ID'           => $post_id,
			'post_content' => $fixed,
		] );

		if ( is_wp_error( $update ) || $update === 0 ) {
			return -1;
		}

		// Pročisti WP Rocket cache pro tento post.
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
		}

		return 1;
	}

	/**
	 * Najde JSON-LD text v bloku a obalí ho <script> tagem.
	 * Používá sledování hloubky závorek – funguje pro jednořádkový i víceřádkový JSON.
	 */
	private static function wrap_json_ld_in_block( string $inner ): string {
		$len   = strlen( $inner );
		$start = -1;

		// Najdi otevírací { která patří k JSON-LD objektu s "@context".
		for ( $i = 0; $i < $len; $i++ ) {
			if ( $inner[ $i ] !== '{' ) {
				continue;
			}
			// Zkontroluj jestli za touto { někde následuje "@context".
			$rest = substr( $inner, $i );
			if ( strpos( $rest, '"@context"' ) !== false ) {
				$start = $i;
				break;
			}
		}

		if ( $start === -1 ) {
			return $inner;
		}

		// Najdi odpovídající uzavírající } sledováním hloubky závorek.
		$depth     = 0;
		$in_string = false;
		$escape    = false;
		$end       = -1;

		for ( $i = $start; $i < $len; $i++ ) {
			$c = $inner[ $i ];

			if ( $escape ) {
				$escape = false;
				continue;
			}
			if ( $c === '\\' && $in_string ) {
				$escape = true;
				continue;
			}
			if ( $c === '"' ) {
				$in_string = ! $in_string;
				continue;
			}
			if ( $in_string ) {
				continue;
			}
			if ( $c === '{' ) {
				$depth++;
			} elseif ( $c === '}' ) {
				$depth--;
				if ( $depth === 0 ) {
					$end = $i;
					break;
				}
			}
		}

		if ( $end === -1 ) {
			return $inner;
		}

		$json_str = substr( $inner, $start, $end - $start + 1 );
		$decoded  = json_decode( $json_str, true );

		if ( $decoded === null || json_last_error() !== JSON_ERROR_NONE ) {
			return $inner; // Poškozený JSON – neupravuj.
		}

		$safe_json = wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( $safe_json === false ) {
			return $inner;
		}

		$script_tag = '<script type="application/ld+json">' . "\n" . $safe_json . "\n" . '</script>';

		return substr( $inner, 0, $start ) . $script_tag . substr( $inner, $end + 1 );
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
