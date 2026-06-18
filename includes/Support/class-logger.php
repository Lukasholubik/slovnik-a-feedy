<?php
/**
 * Logger pluginu Slovník a Feedy.
 *
 * Ukládá záznamy do tabulky {prefix}saf_logs.
 * Každý řádek importu (vytvořeno / aktualizováno / přeskočeno) i chyby
 * se logují přes tuto třídu.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Statická fasáda pro logování. Záměrně bez závislostí – lze volat odkudkoli.
 */
final class Logger {

	public const TABLE   = 'saf_logs';
	public const INFO    = 'info';
	public const WARNING = 'warning';
	public const ERROR   = 'error';

	/**
	 * Vytvoří DB tabulku pro logy (idempotentní – bezpečné volat opakovaně).
	 */
	public static function create_table(): void {
		global $wpdb;

		$table          = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			level      varchar(20)         NOT NULL DEFAULT 'info',
			context    varchar(100)        NOT NULL DEFAULT '',
			message    text                NOT NULL,
			data       longtext            DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY level      (level),
			KEY created_at (created_at),
			KEY context    (context)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'saf_db_version', SAF_VERSION );
	}

	/**
	 * Smaže tabulku logů (volá uninstall.php).
	 */
	public static function drop_table(): void {
		global $wpdb;
		// Toto je záměrné smazání schématu při odinstalaci.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::TABLE ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		delete_option( 'saf_db_version' );
	}

	// -------------------------------------------------------------------------
	// Zkrácená rozhraní.

	public static function info( string $message, string $context = '', mixed $data = null ): void {
		static::write( $message, self::INFO, $context, $data );
	}

	public static function warning( string $message, string $context = '', mixed $data = null ): void {
		static::write( $message, self::WARNING, $context, $data );
	}

	public static function error( string $message, string $context = '', mixed $data = null ): void {
		static::write( $message, self::ERROR, $context, $data );
	}

	// -------------------------------------------------------------------------
	// Zápis do DB.

	public static function write(
		string $message,
		string $level   = self::INFO,
		string $context = '',
		mixed  $data    = null
	): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . self::TABLE,
			[
				'level'   => sanitize_key( $level ),
				'context' => sanitize_text_field( $context ),
				'message' => sanitize_textarea_field( $message ),
				'data'    => null !== $data ? wp_json_encode( $data ) : null,
			],
			[ '%s', '%s', '%s', '%s' ]
		);
	}

	// -------------------------------------------------------------------------
	// Čtení logů.

	/**
	 * Vrátí stránkované záznamy logu.
	 *
	 * @return list<object>
	 */
	public static function get_entries(
		string $level   = '',
		string $context = '',
		int    $limit   = 50,
		int    $offset  = 0
	): array {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE;
		$wheres = [ '1=1' ];
		$params = [];

		if ( $level ) {
			$wheres[] = 'level = %s';
			$params[] = $level;
		}
		if ( $context ) {
			$wheres[] = 'context = %s';
			$params[] = $context;
		}

		$where_sql = implode( ' AND ', $wheres );
		$params[]  = absint( $limit );
		$params[]  = absint( $offset );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore
				...$params
			)
		);
	}

	/**
	 * Vrátí celkový počet záznamů.
	 */
	public static function count( string $level = '' ): int {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		if ( $level ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE level = %s", // phpcs:ignore
					$level
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
	}

	/**
	 * Smaže záznamy starší než $days dní.
	 */
	public static function purge( int $days = 30 ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . $wpdb->prefix . self::TABLE . ' WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)', // phpcs:ignore
				$days
			)
		);
	}
}
