<?php
/**
 * Thumbnail Sync – zkopíruje náhledové obrázky ze zdrojového CPT do cílového CPT podle shodného slugu.
 *
 * Podporuje dva formáty zdrojového meta pole:
 *  - standardní: _thumbnail_id → plain integer (attachment ID)
 *  - JetEngine:  grafika → serialized array a:2:{s:2:"id";i:1302;s:3:"url";s:...}
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Admin;

use SlovnikAFeedy\StreamManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ThumbnailSync {

	/**
	 * Zkopíruje thumbnaily ze $source_cpt do $target_cpt podle shodného slugu.
	 *
	 * @param string $source_field  Meta klíč ve zdrojovém CPT (výchozí: 'grafika').
	 * @return array{synced: int, skipped: int, no_source: int, errors: int}
	 */
	public static function sync( string $source_cpt, string $target_cpt, string $source_field = 'grafika' ): array {
		$synced    = 0;
		$skipped   = 0;
		$no_source = 0;
		$errors    = 0;

		// Načti všechny cílové posty bez thumbnailu.
		$targets = get_posts( [
			'post_type'      => $target_cpt,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				],
			],
		] );

		if ( empty( $targets ) ) {
			return compact( 'synced', 'skipped', 'no_source', 'errors' );
		}

		// Předpočítej mapu: slug → attachment_id ze zdrojového CPT (jeden dotaz).
		global $wpdb;
		$source_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.post_name, pm.meta_value AS raw_value
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm
				   ON pm.post_id = p.ID AND pm.meta_key = %s
				 WHERE p.post_type = %s
				   AND p.post_status IN ('publish','draft')
				   AND pm.meta_value != ''",
				$source_field,
				$source_cpt
			),
			ARRAY_A
		);

		if ( empty( $source_rows ) ) {
			return [ 'synced' => 0, 'skipped' => 0, 'no_source' => count( $targets ), 'errors' => 0 ];
		}

		// Postav mapu slug → attachment_id.
		$source_map = [];
		foreach ( $source_rows as $row ) {
			$attachment_id = static::extract_attachment_id( $row['raw_value'] );
			if ( $attachment_id > 0 ) {
				$source_map[ $row['post_name'] ] = $attachment_id;
			}
		}

		wp_suspend_cache_invalidation( true );

		foreach ( $targets as $post_id ) {
			$slug = get_post_field( 'post_name', $post_id );
			if ( ! $slug ) {
				$errors++;
				continue;
			}

			if ( ! isset( $source_map[ $slug ] ) ) {
				$no_source++;
				continue;
			}

			$thumb_id = $source_map[ $slug ];

			if ( ! wp_attachment_is_image( $thumb_id ) ) {
				$no_source++;
				continue;
			}

			$ok = set_post_thumbnail( $post_id, $thumb_id );
			if ( $ok ) {
				$synced++;
			} else {
				$errors++;
			}
		}

		wp_suspend_cache_invalidation( false );

		return compact( 'synced', 'skipped', 'no_source', 'errors' );
	}

	/**
	 * Extrahuje attachment ID z raw meta hodnoty.
	 *
	 * Podporuje:
	 *  - plain integer:  "1302"
	 *  - JetEngine serialized: a:2:{s:2:"id";i:1302;s:3:"url";s:91:"...";}
	 *  - JetEngine array (already unserialized): ['id' => 1302, 'url' => '...']
	 */
	private static function extract_attachment_id( string $raw ): int {
		// Pokus o přímý integer.
		if ( is_numeric( $raw ) && (int) $raw > 0 ) {
			return (int) $raw;
		}

		// Pokus o unserialize (JetEngine / ACF serialized).
		$data = @unserialize( $raw ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( is_array( $data ) ) {
			// JetEngine formát: ['id' => 1302, 'url' => '...']
			if ( isset( $data['id'] ) && (int) $data['id'] > 0 ) {
				return (int) $data['id'];
			}
			// ACF image formát: první prvek může být attachment ID
			$first = reset( $data );
			if ( is_numeric( $first ) && (int) $first > 0 ) {
				return (int) $first;
			}
		}

		// Pokus o JSON.
		$json = json_decode( $raw, true );
		if ( is_array( $json ) && isset( $json['id'] ) && (int) $json['id'] > 0 ) {
			return (int) $json['id'];
		}

		return 0;
	}

	/**
	 * Registrace AJAX handleru.
	 */
	public static function register_ajax(): void {
		add_action( 'wp_ajax_saf_sync_thumbnails', [ static::class, 'ajax_sync' ] );
	}

	public static function ajax_sync(): void {
		check_ajax_referer( 'saf_sync_thumbnails', 'nonce' );
		if ( ! current_user_can( 'manage_glossary' ) ) {
			wp_send_json_error( 'Nedostatečná oprávnění.' );
			return;
		}

		$source_cpt   = sanitize_key( $_POST['source_cpt'] ?? '' );
		$target_cpt   = sanitize_key( $_POST['target_cpt'] ?? '' );
		$source_field = sanitize_key( $_POST['source_field'] ?? 'grafika' );

		if ( ! $target_cpt || ! StreamManager::find_by_cpt( $target_cpt ) ) {
			wp_send_json_error( 'Cílový CPT není platný SAF stream.' );
			return;
		}
		if ( ! $source_cpt || ! post_type_exists( $source_cpt ) ) {
			wp_send_json_error( 'Zdrojový CPT neexistuje.' );
			return;
		}
		if ( $source_cpt === $target_cpt ) {
			wp_send_json_error( 'Zdrojový a cílový CPT musí být různé.' );
			return;
		}
		if ( ! $source_field ) {
			wp_send_json_error( 'Zadej název zdrojového meta pole.' );
			return;
		}

		$result = static::sync( $source_cpt, $target_cpt, $source_field );

		wp_send_json_success( [
			'message' => sprintf(
				'Zkopírováno %d náhledů. Přeskočeno (již má): %d. Nenalezeno ve zdroji: %d. Chyby: %d.',
				$result['synced'],
				$result['skipped'],
				$result['no_source'],
				$result['errors']
			),
			'result'  => $result,
		] );
	}
}
