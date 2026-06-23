<?php
/**
 * Thumbnail Sync – zkopíruje náhledové obrázky ze zdrojového CPT do cílového CPT podle shodného slugu.
 *
 * Použití: slovicek-pojmu (zdroj, má thumbnaily) → glossary (cíl, thumbnaily chybí).
 * Párování: post_name (slug) musí být shodný v obou CPT.
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
	 * @return array{synced: int, skipped: int, no_source: int, errors: int}
	 */
	public static function sync( string $source_cpt, string $target_cpt ): array {
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

		// Předpočítej mapu: slug → thumbnail_id ze zdrojového CPT (jeden dotaz).
		global $wpdb;
		$source_cpt_esc = esc_sql( $source_cpt );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$source_rows = $wpdb->get_results(
			"SELECT p.post_name, pm.meta_value AS thumb_id
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm
			   ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
			 WHERE p.post_type = '{$source_cpt_esc}'
			   AND p.post_status IN ('publish','draft')
			   AND pm.meta_value != ''",
			ARRAY_A
		);

		if ( empty( $source_rows ) ) {
			return [ 'synced' => 0, 'skipped' => 0, 'no_source' => count( $targets ), 'errors' => 0 ];
		}

		// slug → thumb_id mapa.
		$source_map = [];
		foreach ( $source_rows as $row ) {
			$source_map[ $row['post_name'] ] = (int) $row['thumb_id'];
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

			// Ověř, že attachment stále existuje.
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

		$source_cpt = sanitize_key( $_POST['source_cpt'] ?? '' );
		$target_cpt = sanitize_key( $_POST['target_cpt'] ?? '' );

		// Target musí být SAF stream; source může být libovolný registrovaný CPT.
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

		$result = static::sync( $source_cpt, $target_cpt );

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
