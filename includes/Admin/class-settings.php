<?php
/**
 * Správa nastavení pluginu.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizovaný přístup k options pluginu.
 * Všechna nastavení mají prefix saf_ v databázi.
 */
final class Settings {

	/** Klíče s výchozími hodnotami. */
	public const DEFAULTS = [
		'default_status'    => 'publish',
		'gsheet_url'        => '',
		'reimport_schedule' => 'off',
		'batch_size'        => 50,
		'log_retention'     => 30,
		'force_overwrite'   => '0',
	];

	// -------------------------------------------------------------------------
	// Čtení.

	public static function get( string $key, mixed $default = null ): mixed {
		$fallback = $default ?? ( self::DEFAULTS[ $key ] ?? null );
		return get_option( 'saf_' . $key, $fallback );
	}

	public static function get_all(): array {
		$result = [];
		foreach ( self::DEFAULTS as $key => $default ) {
			$result[ $key ] = self::get( $key, $default );
		}
		return $result;
	}

	// -------------------------------------------------------------------------
	// Zápis.

	public static function update( string $key, mixed $value ): bool {
		return update_option( 'saf_' . $key, $value );
	}

	/**
	 * Uloží nastavení z POST dat (sanitizuje každou hodnotu dle typu).
	 */
	public static function save_from_post( array $post ): void {
		$allowed_statuses   = [ 'publish', 'draft' ];
		$allowed_schedules  = [ 'off', 'daily', 'weekly' ];

		$default_status = in_array( $post['default_status'] ?? '', $allowed_statuses, true )
			? $post['default_status']
			: 'publish';
		self::update( 'default_status', $default_status );

		self::update( 'gsheet_url', esc_url_raw( $post['gsheet_url'] ?? '' ) );

		$schedule = in_array( $post['reimport_schedule'] ?? '', $allowed_schedules, true )
			? $post['reimport_schedule']
			: 'off';
		self::update( 'reimport_schedule', $schedule );

		$batch_size = max( 10, min( 500, absint( $post['batch_size'] ?? 50 ) ) );
		self::update( 'batch_size', $batch_size );

		$log_retention = max( 1, min( 365, absint( $post['log_retention'] ?? 30 ) ) );
		self::update( 'log_retention', $log_retention );

		self::update( 'force_overwrite', empty( $post['force_overwrite'] ) ? '0' : '1' );
	}

	// -------------------------------------------------------------------------
	// Import profily.

	/**
	 * Vrátí všechny uložené profily importu.
	 *
	 * @return array<string, array{name: string, mapping: array, template: string}>
	 */
	public static function get_profiles(): array {
		return (array) get_option( 'saf_import_profiles', [] );
	}

	/** @param array{name: string, mapping: array, template: string} $profile */
	public static function save_profile( string $id, array $profile ): void {
		$profiles       = self::get_profiles();
		$profiles[ $id ] = [
			'name'     => sanitize_text_field( $profile['name'] ),
			'mapping'  => array_map( 'sanitize_key', (array) ( $profile['mapping'] ?? [] ) ),
			'template' => wp_kses_post( $profile['template'] ?? '' ),
		];
		update_option( 'saf_import_profiles', $profiles, false );
	}

	public static function delete_profile( string $id ): void {
		$profiles = self::get_profiles();
		unset( $profiles[ $id ] );
		update_option( 'saf_import_profiles', $profiles, false );
	}
}
