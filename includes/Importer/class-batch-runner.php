<?php
/**
 * Batch runner – dávkové zpracování velkých importů přes WP-Cron.
 *
 * Pro malé soubory (< SAF_BATCH_DIRECT_LIMIT řádků) proběhne import synchronně.
 * Pro větší soubory se řádky serializují do options a zpracují po dávkách přes Cron
 * bez rizika HTTP timeoutu.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Importer;

use SlovnikAFeedy\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Koordinuje dávkové zpracování importu.
 */
final class BatchRunner {

	public const CRON_HOOK         = 'saf_batch_import_tick';
	public const OPTION_PREFIX     = 'saf_batch_';
	/** Přímý synchronní import do tohoto počtu řádků. */
	public const DIRECT_LIMIT      = 200;
	/** Počet řádků zpracovaných v jedné Cron dávce. */
	public const BATCH_SIZE        = 50;

	/**
	 * Registruje Cron hook. Volat z Plugin::register_hooks().
	 */
	public static function register_hooks(): void {
		add_action( self::CRON_HOOK, [ static::class, 'process_tick' ] );
	}

	/**
	 * Zahájí import – buď synchronně nebo přes Cron.
	 *
	 * @param  list<array<string,string>>  $rows       Všechny řádky zdroje jako pole.
	 * @param  array                       $config     Serializovatelná konfigurace importu (mapping, template, options).
	 * @return array{mode: string, stats?: array, batch_id?: string}
	 */
	public static function start( array $rows, array $config ): array {
		if ( count( $rows ) <= self::DIRECT_LIMIT ) {
			// Synchronní import.
			$importer = self::build_importer( $config );
			$source   = new ArraySource( $rows );
			$stats    = $importer->run( $source );
			return [ 'mode' => 'sync', 'stats' => $stats ];
		}

		// Dávkový import – ulož frontu a naplánuj první tick.
		$batch_id = wp_generate_uuid4();
		update_option( self::OPTION_PREFIX . $batch_id . '_rows',   $rows,   false );
		update_option( self::OPTION_PREFIX . $batch_id . '_config', $config, false );
		update_option( self::OPTION_PREFIX . $batch_id . '_offset', 0,       false );
		update_option( self::OPTION_PREFIX . $batch_id . '_total',  count( $rows ), false );

		wp_schedule_single_event( time() + 5, self::CRON_HOOK, [ $batch_id ] );

		Logger::info(
			sprintf( 'Batch import zahájen: %d řádků, batch_id: %s', count( $rows ), $batch_id ),
			'batch-import'
		);

		return [ 'mode' => 'async', 'batch_id' => $batch_id ];
	}

	/**
	 * Cron callback – zpracuje jednu dávku a naplánuje další (pokud zbývají řádky).
	 */
	public static function process_tick( string $batch_id ): void {
		$rows   = get_option( self::OPTION_PREFIX . $batch_id . '_rows',   [] );
		$config = get_option( self::OPTION_PREFIX . $batch_id . '_config', [] );
		$offset = (int) get_option( self::OPTION_PREFIX . $batch_id . '_offset', 0 );
		$total  = (int) get_option( self::OPTION_PREFIX . $batch_id . '_total',  0 );

		if ( ! $rows || ! $config ) {
			self::cleanup( $batch_id );
			return;
		}

		$batch    = array_slice( $rows, $offset, self::BATCH_SIZE );
		$importer = self::build_importer( $config );
		$source   = new ArraySource( $batch );
		$importer->run( $source );

		$new_offset = $offset + count( $batch );
		update_option( self::OPTION_PREFIX . $batch_id . '_offset', $new_offset, false );

		Logger::info(
			sprintf( 'Batch tick: %d/%d řádků zpracováno (batch_id: %s)', $new_offset, $total, $batch_id ),
			'batch-import'
		);

		if ( $new_offset < $total ) {
			// Naplánuj další dávku.
			wp_schedule_single_event( time() + 2, self::CRON_HOOK, [ $batch_id ] );
		} else {
			Logger::info( "Batch import dokončen (batch_id: {$batch_id})", 'batch-import' );
			self::cleanup( $batch_id );
		}
	}

	/**
	 * Vrátí stav probíhajícího importu.
	 *
	 * @return array{total: int, offset: int, done: bool}|null  null = nenalezen
	 */
	public static function get_status( string $batch_id ): ?array {
		$total  = get_option( self::OPTION_PREFIX . $batch_id . '_total' );
		if ( $total === false ) {
			return null;
		}
		$offset = (int) get_option( self::OPTION_PREFIX . $batch_id . '_offset', 0 );
		return [
			'total'  => (int) $total,
			'offset' => $offset,
			'done'   => $offset >= (int) $total,
		];
	}

	// -------------------------------------------------------------------------

	private static function cleanup( string $batch_id ): void {
		delete_option( self::OPTION_PREFIX . $batch_id . '_rows' );
		delete_option( self::OPTION_PREFIX . $batch_id . '_config' );
		delete_option( self::OPTION_PREFIX . $batch_id . '_offset' );
		delete_option( self::OPTION_PREFIX . $batch_id . '_total' );
	}

	private static function build_importer( array $config ): Importer {
		$mapper  = new Mapper( $config['mapping'] ?? [] );
		$engine  = new TemplateEngine();
		return new Importer(
			$mapper,
			$engine,
			$config['template']        ?? TemplateEngine::default_template(),
			$config['default_status']  ?? 'publish',
			$config['dry_run']         ?? false,
			$config['force_overwrite'] ?? false
		);
	}
}
