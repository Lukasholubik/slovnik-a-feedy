<?php
/**
 * Registr importních relací – seznam všech aktivních a dokončených importů.
 *
 * Každý import se uloží jako záznam (max 30 záznamů, starší auto-rotují).
 * Samotná data relace jsou v transentu (7 dní), registr uchovává metadata.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ImportSessionRegistry {

	private const OPTION   = 'saf_import_sessions';
	private const MAX_KEEP = 30;

	// Status konstant.
	public const STATUS_ACTIVE    = 'active';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_ERROR     = 'error';

	// ── Čtení ────────────────────────────────────────────────────────────────

	/**
	 * @return array<string, array>  session_id => metadata
	 */
	public static function get_all(): array {
		return (array) get_option( self::OPTION, [] );
	}

	/**
	 * Vrátí jen aktivní (nedokončené) relace.
	 *
	 * @return array<string, array>
	 */
	public static function get_active(): array {
		return array_filter(
			static::get_all(),
			static fn( array $s ): bool => $s['status'] === self::STATUS_ACTIVE
		);
	}

	public static function get( string $session_id ): ?array {
		return static::get_all()[ $session_id ] ?? null;
	}

	// ── Zápis ────────────────────────────────────────────────────────────────

	/**
	 * Zaregistruje novou relaci (voláno při kroku 0).
	 */
	public static function register( string $session_id, array $meta ): void {
		$sessions = static::get_all();

		$sessions[ $session_id ] = array_merge( [
			'session_id'   => $session_id,
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
			'last_step'    => 0,
			'stream_name'  => '',
			'source_type'  => '',
			'file_name'    => '',
			'total_rows'   => 0,
			'macro_count'  => 0,
			'status'       => self::STATUS_ACTIVE,
			'error_msg'    => '',
		], $meta );

		// Auto-rotace: zachovej max MAX_KEEP záznamů (smaž nejstarší).
		if ( count( $sessions ) > self::MAX_KEEP ) {
			uasort( $sessions, static fn( $a, $b ) => strcmp( $a['created_at'], $b['created_at'] ) );
			$sessions = array_slice( $sessions, -self::MAX_KEEP, null, true );
		}

		update_option( self::OPTION, $sessions, false );
	}

	/**
	 * Aktualizuje metadata relace (krok, počet řádků, status...).
	 */
	public static function update( string $session_id, array $meta ): void {
		$sessions = static::get_all();
		if ( ! isset( $sessions[ $session_id ] ) ) {
			return;
		}
		$sessions[ $session_id ] = array_merge(
			$sessions[ $session_id ],
			$meta,
			[ 'updated_at' => current_time( 'mysql' ) ]
		);
		update_option( self::OPTION, $sessions, false );
	}

	/**
	 * Označí relaci jako dokončenou.
	 */
	public static function complete( string $session_id, int $created, int $updated, int $skipped ): void {
		static::update( $session_id, [
			'status'    => self::STATUS_COMPLETED,
			'last_step' => 3,
			'result'    => compact( 'created', 'updated', 'skipped' ),
		] );
	}

	/**
	 * Označí relaci jako chybovou.
	 */
	public static function fail( string $session_id, string $error ): void {
		static::update( $session_id, [
			'status'    => self::STATUS_ERROR,
			'error_msg' => $error,
		] );
	}

	/**
	 * Smaže záznam z registru (data v transietu zůstanou ještě 7 dní).
	 */
	public static function delete( string $session_id ): void {
		$sessions = static::get_all();
		unset( $sessions[ $session_id ] );
		update_option( self::OPTION, $sessions, false );
	}

	// ── Pomocné ──────────────────────────────────────────────────────────────

	/**
	 * Vrátí lidský popis posledního kroku.
	 */
	public static function step_label( int $step ): string {
		return match ( $step ) {
			0 => __( 'Zdroj dat', 'slovnik-a-feedy' ),
			1 => __( 'Makra namapována', 'slovnik-a-feedy' ),
			2 => __( 'Šablona vybrána', 'slovnik-a-feedy' ),
			3 => __( 'Dokončeno', 'slovnik-a-feedy' ),
			default => __( 'Neznámý krok', 'slovnik-a-feedy' ),
		};
	}
}
