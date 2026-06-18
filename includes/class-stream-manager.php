<?php
/**
 * StreamManager – správa streamů (dynamických CPT) pluginu Slovník a Feedy.
 *
 * Každý stream = jeden Custom Post Type s vlastním archivem, feedy, taxonomiemi.
 * Streamy jsou uloženy v WP option saf_streams.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD pro streamy. Všechny metody jsou statické – žádné závislosti.
 *
 * Schéma streamu:
 *   id          string   unikátní klíč (saf_xxx)
 *   name        string   zobrazovaný název (česky)
 *   cpt         string   registrovaný post_type slug (max 20 znaků)
 *   url_slug    string   slug pro URL archivu a single (/slovnik/)
 *   icon        string   dashicons třída
 *   tax_letter  bool     zapnout A–Z taxonomii
 *   tax_cat     bool     zapnout kategoriové taxonomii
 *   active      bool     registrovat CPT/tax (false = skrytý, data zůstanou)
 *   is_default  bool     výchozí stream – nelze smazat
 */
final class StreamManager {

	private const OPTION = 'saf_streams';

	// -------------------------------------------------------------------------
	// Čtení.

	/** @return array<string, array> */
	public static function get_all(): array {
		return (array) get_option( self::OPTION, [] );
	}

	/** @return array|null */
	public static function get( string $id ): ?array {
		return self::get_all()[ $id ] ?? null;
	}

	/**
	 * Vrátí streamy vhodné pro použití v selectu (id => name).
	 *
	 * @return array<string, string>
	 */
	public static function get_options(): array {
		$result = [];
		foreach ( self::get_all() as $id => $stream ) {
			if ( $stream['active'] ?? true ) {
				$result[ $id ] = $stream['name'];
			}
		}
		return $result;
	}

	/**
	 * Najde stream dle post_type slugu.
	 */
	public static function find_by_cpt( string $cpt ): ?array {
		foreach ( self::get_all() as $stream ) {
			if ( ( $stream['cpt'] ?? '' ) === $cpt ) {
				return $stream;
			}
		}
		return null;
	}

	// -------------------------------------------------------------------------
	// Zápis.

	/**
	 * Vytvoří nový stream a vrátí jeho ID.
	 *
	 * @throws \InvalidArgumentException  při kolizi CPT slugu
	 */
	public static function create( array $data ): string {
		$id   = 'saf_' . substr( md5( uniqid( '', true ) ), 0, 8 );
		$data = self::sanitize( array_merge( $data, [ 'id' => $id, 'is_default' => false ] ) );

		self::assert_cpt_unique( $data['cpt'], $id );

		$streams        = self::get_all();
		$streams[ $id ] = $data;
		update_option( self::OPTION, $streams, false );

		flush_rewrite_rules();
		return $id;
	}

	/** @throws \InvalidArgumentException */
	public static function update( string $id, array $data ): bool {
		$streams = self::get_all();
		if ( ! isset( $streams[ $id ] ) ) {
			return false;
		}
		$merged = self::sanitize( array_merge( $streams[ $id ], $data ) );

		self::assert_cpt_unique( $merged['cpt'], $id );

		$streams[ $id ] = $merged;
		update_option( self::OPTION, $streams, false );
		flush_rewrite_rules();
		return true;
	}

	/**
	 * Smaže stream. Výchozí stream (is_default) nelze smazat.
	 * Data (příspěvky) zůstanou v DB – musí je smazat uživatel ručně.
	 */
	public static function delete( string $id ): bool {
		$streams = self::get_all();
		if ( ! isset( $streams[ $id ] ) ) {
			return false;
		}
		if ( $streams[ $id ]['is_default'] ?? false ) {
			return false; // Výchozí stream je chráněn.
		}
		unset( $streams[ $id ] );
		update_option( self::OPTION, $streams, false );
		flush_rewrite_rules();
		return true;
	}

	// -------------------------------------------------------------------------
	// Inicializace.

	/**
	 * Vytvoří výchozí stream pokud žádné streamy ještě neexistují.
	 * Používá CPT 'glossary' pro zpětnou kompatibilitu s Fází 1.
	 */
	public static function create_default(): void {
		if ( ! empty( self::get_all() ) ) {
			return;
		}
		$default = [
			'id'         => 'saf_default',
			'name'       => 'Slovníček pojmů',
			'cpt'        => 'glossary',
			'url_slug'   => 'slovnik',
			'icon'       => 'dashicons-book-alt',
			'tax_letter' => true,
			'tax_cat'    => true,
			'active'     => true,
			'is_default' => true,
		];
		update_option( self::OPTION, [ 'saf_default' => $default ], false );
	}

	// -------------------------------------------------------------------------
	// Pomocné.

	/** @return array s garantovanými klíči a sanitizovanými hodnotami */
	public static function sanitize( array $data ): array {
		$cpt = sanitize_key( $data['cpt'] ?? '' );
		// WP max post_type délka = 20 znaků.
		$cpt = substr( $cpt, 0, 20 );

		return [
			'id'         => sanitize_key( $data['id'] ?? '' ),
			'name'       => sanitize_text_field( $data['name'] ?? '' ),
			'cpt'        => $cpt,
			'url_slug'   => sanitize_title( $data['url_slug'] ?? $cpt ),
			'icon'       => sanitize_html_class( $data['icon'] ?? 'dashicons-list-view' ),
			'tax_letter' => (bool) ( $data['tax_letter'] ?? true ),
			'tax_cat'    => (bool) ( $data['tax_cat'] ?? true ),
			'active'     => (bool) ( $data['active'] ?? true ),
			'is_default' => (bool) ( $data['is_default'] ?? false ),
		];
	}

	/**
	 * Vrátí slug taxonomie pro písmena daného streamu.
	 * Např. stream s CPT 'glossary' → 'glossary_letter'
	 */
	public static function tax_letter( array $stream ): string {
		return $stream['cpt'] . '_letter';
	}

	/**
	 * Vrátí slug taxonomie kategorií daného streamu.
	 */
	public static function tax_cat( array $stream ): string {
		return $stream['cpt'] . '_cat';
	}

	// -------------------------------------------------------------------------

	/** @throws \InvalidArgumentException pokud CPT slug koliduje s jiným streamem */
	private static function assert_cpt_unique( string $cpt, string $current_id ): void {
		foreach ( self::get_all() as $id => $stream ) {
			if ( $id !== $current_id && ( $stream['cpt'] ?? '' ) === $cpt ) {
				throw new \InvalidArgumentException(
					sprintf( 'CPT slug „%s" je již použit streamem „%s".', $cpt, $stream['name'] )
				);
			}
		}
	}
}
