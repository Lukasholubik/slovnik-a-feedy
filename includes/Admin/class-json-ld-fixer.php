<?php
/**
 * JSON-LD Fixer – oprava viditelného JSON-LD textu po importu.
 *
 * Problémy:
 *  1. Importér HTML-enkóduje uvozovky → "{"@context"}" je v DB jako &quot;@context&quot;
 *     → strpos('"@context"') nenajde shodu → fixer dříve přeskočil VŠE.
 *  2. JetEngine/Elementor stripuje <script> tagy z obsahu při renderování
 *     → i po přidání <script> tagu do post_content se JSON zobrazí jako text.
 *
 * Řešení:
 *  - Dekódovat HTML entity před hledáním a parsováním JSON.
 *  - Extrahovat JSON-LD z post_content, uložit do post meta (_saf_json_ld).
 *  - Odstranit surový JSON z post_content.
 *  - Vypisovat JSON-LD přes wp_head hook (nezávislé na způsobu renderování obsahu).
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Admin;

use SlovnikAFeedy\StreamManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JsonLdFixer {

	public const META_KEY = '_saf_json_ld';

	/**
	 * Registrace wp_head hooku – volat z Plugin::register_hooks().
	 */
	public static function register_frontend_hook(): void {
		add_action( 'wp_head', [ static::class, 'output_json_ld' ], 5 );
	}

	/**
	 * Vypíše JSON-LD z post meta do <head> pro CPT streamy.
	 */
	public static function output_json_ld(): void {
		if ( ! is_singular() ) {
			return;
		}
		$json_ld = get_post_meta( get_the_ID(), self::META_KEY, true );
		if ( ! $json_ld ) {
			return;
		}
		echo '<script type="application/ld+json">' . "\n" . $json_ld . "\n" . '</script>' . "\n";
	}

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

		// Pročisti celou WP Rocket cache po dokončení dávky.
		// Per-post čištění uvnitř fix_post() nestačí – stránky se rychle re-cacheují.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		return [
			'fixed'   => $fixed_posts,
			'skipped' => $skipped_posts,
			'errors'  => $error_posts,
		];
	}

	/**
	 * Opraví JSON-LD v jednom příspěvku.
	 *
	 * Postup:
	 *  1. Dekóduje HTML entity v post_content (import mohl enkódovat uvozovky).
	 *  2. Najde JSON-LD pomocí sledování hloubky závorek.
	 *  3. Uloží JSON do post meta _saf_json_ld (vypsán přes wp_head).
	 *  4. Odstraní surový JSON text z post_content.
	 *
	 * @return int  1 = opraveno, 0 = přeskočeno, -1 = chyba
	 */
	public static function fix_post( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return -1;
		}

		$original = $post->post_content;

		// Rychlá kontrola: hledáme @context v jakémkoli enkódování.
		if ( strpos( $original, '@context' ) === false ) {
			return 0;
		}

		// Přeskočit pouze pokud meta již obsahuje platný JSON-LD (má @context).
		// Pozor: předchozí verze fixeru mohla uložit špatná data – v tom případě přepsat.
		$existing_meta = get_post_meta( $post_id, self::META_KEY, true );
		if ( $existing_meta && strpos( $existing_meta, '@context' ) !== false ) {
			return 0;
		}

		// Dekóduj HTML entity – importér mohl enkódovat uvozovky na &quot;
		$decoded = html_entity_decode( $original, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Najdi JSON-LD objekt pomocí sledování hloubky závorek.
		$extracted = self::extract_json_ld( $decoded );
		if ( ! $extracted ) {
			return 0;
		}

		[ $json_str, $start, $end ] = $extracted;

		// Validace JSON.
		$decoded_json = json_decode( $json_str, true );
		if ( $decoded_json === null || json_last_error() !== JSON_ERROR_NONE ) {
			return -1;
		}

		$safe_json = wp_json_encode( $decoded_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( $safe_json === false ) {
			return -1;
		}

		// Ulož JSON-LD do post meta.
		update_post_meta( $post_id, self::META_KEY, $safe_json );

		// Odstraň surový JSON z post_content (v dekódované podobě najdeme pozici).
		// Musíme ale odstranit z ORIGINÁLNÍHO content – hledáme odpovídající část.
		$cleaned = self::remove_raw_json_from_content( $original, $decoded, $start, $end );

		if ( $cleaned !== $original ) {
			$update = wp_update_post( [
				'ID'           => $post_id,
				'post_content' => $cleaned,
			] );
			if ( is_wp_error( $update ) || $update === 0 ) {
				return -1;
			}
		}

		// Pročisti WP Rocket cache.
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
		}

		return 1;
	}

	/**
	 * Najde JSON-LD objekt v textu pomocí sledování hloubky závorek.
	 * Vrátí [json_string, start_offset, end_offset] nebo null.
	 *
	 * Strategie: najdi @context, pak skenuj POZPÁTKU k nalezení
	 * otevírací { tohoto JSON objektu. Předchozí přístup (dopředný sken)
	 * chybně nacházel první { kde @context bylo kdekoliv v zbytku textu.
	 *
	 * @return array{0: string, 1: int, 2: int}|null
	 */
	private static function extract_json_ld( string $content ): ?array {
		$len = strlen( $content );

		// Najdi @context (v jakémkoli enkódování).
		$ctx_pos = strpos( $content, '@context' );
		if ( $ctx_pos === false ) {
			return null;
		}

		// Skenuj pozpátku od @context k nalezení otevírací { JSON objektu.
		$start = -1;
		for ( $i = $ctx_pos - 1; $i >= 0; $i-- ) {
			if ( $content[ $i ] === '{' ) {
				$start = $i;
				break;
			}
		}

		if ( $start === -1 ) {
			return null;
		}

		// Sleduj hloubku závorek pro nalezení konce JSON objektu.
		$depth     = 0;
		$in_string = false;
		$escape    = false;
		$end       = -1;

		for ( $i = $start; $i < $len; $i++ ) {
			$c = $content[ $i ];

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
			return null;
		}

		$json_str = substr( $content, $start, $end - $start + 1 );
		return [ $json_str, $start, $end ];
	}

	/**
	 * Odstraní surový JSON text z originálního post_content.
	 * Pracuje tak, že najde odpovídající část v dekódované verzi a odstraní ji z originálu.
	 */
	private static function remove_raw_json_from_content( string $original, string $decoded, int $dec_start, int $dec_end ): string {
		// Zkusíme přímé hledání části z dekódovaného obsahu v originálním.
		$json_in_decoded = substr( $decoded, $dec_start, $dec_end - $dec_start + 1 );

		// Nejprve zkus přímou shodu (pokud originál nebyl enkódován).
		$pos = strpos( $original, $json_in_decoded );
		if ( $pos !== false ) {
			// Odstraň JSON i okolní whitespace/newlines.
			$before = substr( $original, 0, $pos );
			$after  = substr( $original, $pos + strlen( $json_in_decoded ) );
			return rtrim( $before ) . ltrim( $after, "\n\r " );
		}

		// Zkus hledat HTML-enkódovanou verzi prvních 50 znaků JSON (pro lokalizaci).
		$json_start_raw = substr( $json_in_decoded, 0, 20 );
		$json_enc       = htmlspecialchars( $json_start_raw, ENT_QUOTES, 'UTF-8' );

		$pos = strpos( $original, $json_enc );
		if ( $pos === false ) {
			// Nenalezeno – vrátíme bez změny (JSON-LD byl uložen do meta, ale content ponecháme).
			return $original;
		}

		// Najdi konec enkódovaného JSON (hledáme párovou uzavírací }).
		// Zjednodušeně: hledáme první } který po dekódování uzavírá JSON.
		$search_from = $pos;
		$depth       = 0;
		$end_pos     = -1;
		$in_str      = false;
		$esc         = false;
		$decoded_from_pos = substr( $decoded, $dec_start );

		// Projdi originál od nalezené pozice a najdi konec (enkódované }) .
		for ( $i = $dec_start; $i <= $dec_end; $i++ ) {
			// Použijeme offset z dekódované verze k nalezení odpovídajícího místa v originálu.
		}

		// Jednodušší přístup: nahraď celý blok od { do } v enkódované podobě.
		// Rekonstruujeme enkódovaný JSON.
		$json_encoded = htmlspecialchars( $json_in_decoded, ENT_QUOTES, 'UTF-8' );
		$pos2         = strpos( $original, $json_encoded );
		if ( $pos2 !== false ) {
			$before = substr( $original, 0, $pos2 );
			$after  = substr( $original, $pos2 + strlen( $json_encoded ) );
			return rtrim( $before ) . ltrim( $after, "\n\r " );
		}

		return $original;
	}

	/**
	 * Registrace AJAX handleru.
	 */
	public static function register_ajax(): void {
		add_action( 'wp_ajax_saf_fix_json_ld',       [ static::class, 'ajax_fix_json_ld' ] );
		add_action( 'wp_ajax_saf_debug_json_ld_post', [ static::class, 'ajax_debug_post' ] );
	}

	/**
	 * Debug: zobrazí co fixer vidí v konkrétním postu (podle URL slugu).
	 * Volání: /wp-admin/admin-ajax.php?action=saf_debug_json_ld_post&slug=welcome-serie&nonce=XXX
	 */
	public static function ajax_debug_post(): void {
		check_ajax_referer( 'saf_fix_json_ld', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Nedostatečná oprávnění.' );
			return;
		}

		$slug    = sanitize_title( $_GET['slug'] ?? $_POST['slug'] ?? '' );
		$post_id = absint( $_GET['post_id'] ?? $_POST['post_id'] ?? 0 );

		if ( ! $post_id && $slug ) {
			$found = get_posts( [
				'name'           => $slug,
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			] );
			$post_id = $found[0] ?? 0;
		}

		if ( ! $post_id ) {
			wp_send_json_error( 'Post nenalezen. Zadej slug= nebo post_id=' );
			return;
		}

		$post    = get_post( $post_id );
		$decoded = html_entity_decode( $post->post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Najdi @context
		$ctx_pos = strpos( $decoded, '@context' );

		// Extrakce
		$extracted = self::extract_json_ld( $decoded );

		// Aktuální meta
		$current_meta = get_post_meta( $post_id, self::META_KEY, true );

		// Kontext kolem prvního @context
		$ctx_context = '';
		if ( $ctx_pos !== false ) {
			$ctx_context = substr( $decoded, max( 0, $ctx_pos - 100 ), 300 );
		}

		wp_send_json_success( [
			'post_id'            => $post_id,
			'post_title'         => get_the_title( $post_id ),
			'content_length'     => strlen( $post->post_content ),
			'decoded_length'     => strlen( $decoded ),
			'at_context_found'   => $ctx_pos !== false,
			'at_context_pos'     => $ctx_pos,
			'context_around'     => $ctx_context,
			'extracted_json'     => $extracted ? substr( $extracted[0], 0, 500 ) : null,
			'extract_start'      => $extracted[1] ?? null,
			'extract_end'        => $extracted[2] ?? null,
			'json_valid'         => $extracted ? ( json_decode( $extracted[0] ) !== null ) : false,
			'current_meta'       => $current_meta ? substr( $current_meta, 0, 200 ) : null,
			'meta_has_context'   => $current_meta ? str_contains( $current_meta, '@context' ) : false,
			'content_first_200'  => substr( $decoded, 0, 200 ),
		] );
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
				'Opraveno %d příspěvků – JSON-LD uložen do meta, bude vypsán přes &lt;head&gt; (přeskočeno %d – již OK nebo bez JSON-LD; chyby %d).',
				$result['fixed'],
				$result['skipped'],
				$result['errors']
			),
			'result'  => $result,
		] );
	}
}
