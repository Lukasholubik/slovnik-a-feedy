<?php
/**
 * Hlavní importér – upsert pojmů bez duplicit.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Importer;

use SlovnikAFeedy\StreamManager;
use SlovnikAFeedy\Support\Logger;
use SlovnikAFeedy\Support\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zpracuje každý řádek zdroje:
 *   1. Namapuje sloupce na pole pluginu (Mapper).
 *   2. Renderuje obsah přes Template Engine.
 *   3. Vyhledá existující post podle _saf_external_id.
 *   4. Vytvoří nebo aktualizuje post (wp_insert_post).
 *   5. Přiřadí taxonomie (písmeno, kategorie).
 *   6. Zaloguje výsledek.
 */
final class Importer {

	/** Meta klíč pro unikátní identifikátor řádku ze zdroje. */
	public const META_EXTERNAL_ID = '_saf_external_id';

	private int $created = 0;
	private int $updated = 0;
	private int $skipped = 0;

	public function __construct(
		private readonly Mapper          $mapper,
		private readonly TemplateEngine  $engine,
		private readonly string          $template,
		private readonly array           $stream,          // konfigurace cílového streamu
		private readonly string          $default_status  = 'publish',
		private readonly bool            $dry_run         = false,
		private readonly bool            $force_overwrite = false
	) {}

	// -------------------------------------------------------------------------
	// Veřejné API.

	/**
	 * Spustí import z daného zdroje.
	 *
	 * @return array{created: int, updated: int, skipped: int}
	 */
	public function run( Source $source ): array {
		$this->created = $this->updated = $this->skipped = 0;

		foreach ( $source->get_rows() as $raw_row ) {
			$this->process_row( $raw_row );
		}

		$stats = $this->get_stats();
		Logger::info(
			sprintf(
				'Import dokončen – vytvořeno: %d, aktualizováno: %d, přeskočeno: %d%s',
				$stats['created'],
				$stats['updated'],
				$stats['skipped'],
				$this->dry_run ? ' (DRY-RUN)' : ''
			),
			'import'
		);

		return $stats;
	}

	/** @return array{created: int, updated: int, skipped: int} */
	public function get_stats(): array {
		return [
			'created' => $this->created,
			'updated' => $this->updated,
			'skipped' => $this->skipped,
		];
	}

	// -------------------------------------------------------------------------
	// Zpracování jednoho řádku.

	private function process_row( array $raw_row ): void {
		$mapped = $this->mapper->map_row( $raw_row );

		// External ID = klíč pro detekci duplicit.
		$external_id = trim( $mapped['external_id'] ?: $mapped['slug'] ?: $mapped['title'] );
		if ( $external_id === '' ) {
			Logger::warning( 'Řádek přeskočen – chybí external_id, slug i title.', 'import', $raw_row );
			$this->skipped++;
			return;
		}

		// Render Gutenberg obsahu ze šablony.
		// Šablona dostane surový zdrojový řádek SLOUČENÝ s namapovanými poli –
		// uživatel může v šabloně použít jak původní názvy sloupců, tak field slugy.
		$merged_row = array_merge( $raw_row, $mapped );
		$content    = $this->engine->render( $this->template, $merged_row );

		// Slug.
		$slug = sanitize_title( $mapped['slug'] ?: $mapped['title'] ?: $external_id );

		// Status.
		$allowed_statuses = [ 'publish', 'draft', 'pending', 'private' ];
		$status = in_array( $mapped['status'], $allowed_statuses, true )
			? $mapped['status']
			: $this->default_status;

		// Najdi existující post.
		$existing_id = $this->find_by_external_id( $external_id );

		$post_data = [
			'post_type'    => $this->stream['cpt'],
			'post_title'   => sanitize_text_field( $mapped['title'] ),
			'post_content' => wp_kses_post( $content ),
			'post_excerpt' => sanitize_textarea_field( $mapped['excerpt'] ),
			'post_name'    => $slug,
			'post_status'  => $status,
		];

		if ( $existing_id ) {
			$post_data['ID'] = $existing_id;
		}

		// DRY-RUN: zaloguj co by se stalo, ale nezapisuj.
		if ( $this->dry_run ) {
			$action = $existing_id ? 'aktualizace' : 'vytvoření';
			Logger::info(
				sprintf( 'DRY-RUN [%s] "%s" (ext_id: %s)', $action, $post_data['post_title'], $external_id ),
				'import-dry',
				[ 'post_data' => $post_data, 'seo' => $this->extract_seo_fields( $mapped ) ]
			);
			$existing_id ? $this->updated++ : $this->created++;
			return;
		}

		// Zápis do DB.
		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			Logger::error(
				sprintf( 'Chyba pri ukladani "%s": %s', $post_data['post_title'], $post_id->get_error_message() ),
				'import',
				$raw_row
			);
			$this->skipped++;
			return;
		}

		// Uložení external_id.
		update_post_meta( $post_id, self::META_EXTERNAL_ID, sanitize_text_field( $external_id ) );

		// Rank Math SEO meta – podmíněný zápis (ruční hodnota má přednost).
		$this->write_seo_meta( $post_id, $mapped );

		// Taxonomie.
		$this->assign_taxonomies( $post_id, $mapped );

		if ( $existing_id ) {
			Logger::info( sprintf( 'Aktualizovan: "%s" (ID %d)', $post_data['post_title'], $post_id ), 'import' );
			$this->updated++;
		} else {
			Logger::info( sprintf( 'Vytvoren: "%s" (ID %d)', $post_data['post_title'], $post_id ), 'import' );
			$this->created++;
		}
	}

	// -------------------------------------------------------------------------
	// Pomocné metody.

	/**
	 * Vyhledá existující post podle _saf_external_id.
	 * Vrací 0 pokud nenalezen.
	 */
	private function find_by_external_id( string $external_id ): int {
		global $wpdb;

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				self::META_EXTERNAL_ID,
				$external_id
			)
		);

		return $post_id ? (int) $post_id : 0;
	}

	/**
	 * Zapíše SEO meta pro Rank Math – podmíněně (ruční hodnota má přednost).
	 * Pokud force_overwrite = true, přepíše vždy.
	 */
	private function write_seo_meta( int $post_id, array $mapped ): void {
		$seo_fields = $this->extract_seo_fields( $mapped );

		foreach ( $seo_fields as $meta_key => $value ) {
			if ( $value === '' ) {
				continue;
			}
			if ( $this->force_overwrite ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( $value ) );
			} else {
				// Zapiš jen pokud je pole prázdné (ruční zápis má přednost).
				$existing = get_post_meta( $post_id, $meta_key, true );
				if ( empty( $existing ) ) {
					update_post_meta( $post_id, $meta_key, sanitize_text_field( $value ) );
				}
			}
		}
	}

	/** @return array<string, string>  meta_key => hodnota */
	private function extract_seo_fields( array $mapped ): array {
		return [
			'rank_math_title'         => $mapped['seo_title'],
			'rank_math_description'   => $mapped['seo_description'],
			'rank_math_focus_keyword' => $mapped['seo_keyword'],
		];
	}

	/**
	 * Přiřadí taxonomie pojmu (písmeno A–Z, kategorie).
	 */
	private function assign_taxonomies( int $post_id, array $mapped ): void {
		// Písmeno – z mapovaného pole nebo auto-detekce z titulku.
		$letter_slug = sanitize_title( $mapped['letter'] )
			?: Helpers::get_letter_slug( $mapped['title'] );

		if ( $letter_slug && ( $this->stream['tax_letter'] ?? true ) ) {
			$tax_letter  = StreamManager::tax_letter( $this->stream );
			$letter_term = get_term_by( 'slug', $letter_slug, $tax_letter );
			if ( $letter_term instanceof \WP_Term ) {
				wp_set_object_terms( $post_id, $letter_term->term_id, $tax_letter );
			}
		}

		// Kategorie (může být více oddělených čárkou).
		if ( $mapped['category'] !== '' && ( $this->stream['tax_cat'] ?? true ) ) {
			$tax_cat   = StreamManager::tax_cat( $this->stream );
			$cat_slugs = array_filter( array_map( 'trim', explode( ',', $mapped['category'] ) ) );
			$term_ids  = [];
			foreach ( $cat_slugs as $cat_slug ) {
				$term = get_term_by( 'slug', sanitize_title( $cat_slug ), $tax_cat );
				if ( $term instanceof \WP_Term ) {
					$term_ids[] = $term->term_id;
				}
			}
			if ( $term_ids ) {
				wp_set_object_terms( $post_id, $term_ids, $tax_cat );
			}
		}
	}
}
