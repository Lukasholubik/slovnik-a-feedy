<?php
/**
 * Tracker – sledování zobrazení, kliknutí a doby strávené na stránce.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Analytics;

use SlovnikAFeedy\StreamManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tracker {

	public const REST_NAMESPACE = 'saf/v1';
	public const REST_ROUTE     = '/click';
	public const REST_TIME      = '/time';
	public const TABLE          = 'saf_stats';

	// -------------------------------------------------------------------------
	// Registrace hooků.

	public static function register_hooks(): void {
		add_action( 'template_redirect', [ static::class, 'maybe_track_view' ] );
		add_action( 'rest_api_init',     [ static::class, 'register_rest_routes' ] );
		add_action( 'wp_enqueue_scripts', [ static::class, 'enqueue_tracker_script' ] );

		// Zajisti existenci tabulky – i pokud plugin nebyl reaktivován.
		add_action( 'admin_init', [ static::class, 'ensure_table' ] );
	}

	// -------------------------------------------------------------------------
	// View tracking.

	public static function maybe_track_view(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! StreamManager::find_by_cpt( $post->post_type ) ) {
			return;
		}

		if ( static::should_skip() ) {
			return;
		}

		static::record_view( $post->ID );
	}

	public static function record_view( int $post_id ): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}" . self::TABLE . ' (post_id, stat_date, views, clicks, time_total, time_count)
				 VALUES (%d, %s, 1, 0, 0, 0)
				 ON DUPLICATE KEY UPDATE views = views + 1',
				$post_id,
				current_time( 'Y-m-d' )
			)
		);
	}

	public static function record_click( int $post_id ): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}" . self::TABLE . ' (post_id, stat_date, views, clicks, time_total, time_count)
				 VALUES (%d, %s, 0, 1, 0, 0)
				 ON DUPLICATE KEY UPDATE clicks = clicks + 1',
				$post_id,
				current_time( 'Y-m-d' )
			)
		);
	}

	/**
	 * Zaznamená dobu strávenou na stránce (v sekundách).
	 */
	public static function record_time( int $post_id, int $seconds ): void {
		global $wpdb;

		// Přidáme čas do running totálu pro výpočet průměru.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}" . self::TABLE . ' (post_id, stat_date, views, clicks, time_total, time_count)
				 VALUES (%d, %s, 0, 0, %d, 1)
				 ON DUPLICATE KEY UPDATE time_total = time_total + %d, time_count = time_count + 1',
				$post_id,
				current_time( 'Y-m-d' ),
				$seconds,
				$seconds
			)
		);
	}

	// -------------------------------------------------------------------------
	// REST endpointy.

	public static function register_rest_routes(): void {
		// Click tracking.
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			[
				'methods'             => 'POST',
				'callback'            => [ static::class, 'rest_track_click' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'post_id' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint', 'minimum' => 1 ],
					'nonce'   => [ 'required' => true, 'type' => 'string' ],
				],
			]
		);

		// Čas na stránce.
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_TIME,
			[
				'methods'             => 'POST',
				'callback'            => [ static::class, 'rest_track_time' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'post_id' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint', 'minimum' => 1 ],
					'nonce'   => [ 'required' => true, 'type' => 'string' ],
					'seconds' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint', 'minimum' => 1, 'maximum' => 3600 ],
				],
			]
		);
	}

	public static function rest_track_click( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! wp_verify_nonce( $request->get_param( 'nonce' ), 'saf_track' ) ) {
			return new \WP_Error( 'invalid_nonce', 'Neplatný token.', [ 'status' => 403 ] );
		}

		$post_id = absint( $request->get_param( 'post_id' ) );
		$post    = get_post( $post_id );

		if ( ! $post || ! StreamManager::find_by_cpt( $post->post_type ) ) {
			return new \WP_Error( 'invalid_post', 'Neplatné ID.', [ 'status' => 400 ] );
		}

		static::record_click( $post_id );
		return new \WP_REST_Response( [ 'ok' => true ], 200 );
	}

	public static function rest_track_time( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! wp_verify_nonce( $request->get_param( 'nonce' ), 'saf_track' ) ) {
			return new \WP_Error( 'invalid_nonce', 'Neplatný token.', [ 'status' => 403 ] );
		}

		$post_id = absint( $request->get_param( 'post_id' ) );
		$seconds = absint( $request->get_param( 'seconds' ) );
		$post    = get_post( $post_id );

		// Realistická hranice: min 3s, max 1h.
		if ( ! $post || ! StreamManager::find_by_cpt( $post->post_type ) || $seconds < 3 ) {
			return new \WP_Error( 'invalid', 'Neplatná data.', [ 'status' => 400 ] );
		}

		static::record_time( $post_id, $seconds );
		return new \WP_REST_Response( [ 'ok' => true ], 200 );
	}

	// -------------------------------------------------------------------------
	// Frontend tracker script.

	public static function enqueue_tracker_script(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! StreamManager::find_by_cpt( $post->post_type ) ) {
			return;
		}

		// Logovat i adminy pokud je povoleno v nastavení.
		if ( static::should_skip() ) {
			return;
		}

		wp_enqueue_script( 'saf-tracker', SAF_URL . 'assets/js/saf-tracker.js', [], SAF_VERSION, true );

		wp_localize_script( 'saf-tracker', 'safTracker', [
			'restUrl'     => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ),
			'timeUrl'     => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_TIME ) ),
			'nonce'       => wp_create_nonce( 'saf_track' ),
			'postId'      => $post->ID,
		] );
	}

	// -------------------------------------------------------------------------
	// Pomocné.

	private static function should_skip(): bool {
		if ( is_user_logged_in()
			&& current_user_can( 'manage_options' )
			&& ! get_option( 'saf_track_admins', false )
		) {
			return true;
		}
		return static::is_bot();
	}

	private static function is_bot(): bool {
		$ua = strtolower( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		if ( ! $ua ) {
			return true;
		}

		$bots = [
			'bot', 'crawler', 'spider', 'slurp', 'facebookexternalhit', 'ia_archiver',
			'msnbot', 'baiduspider', 'yandexbot', 'linkedinbot', 'twitterbot',
			'ahrefsbot', 'semrushbot', 'dotbot', 'rogerbot', 'mj12bot', 'pinterestbot',
		];

		foreach ( $bots as $pattern ) {
			if ( str_contains( $ua, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	// -------------------------------------------------------------------------
	// DB tabulka.

	public static function create_table(): void {
		global $wpdb;

		$table           = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta přidá nové sloupce idempotentně – bezpečné opakovat.
		$sql = "CREATE TABLE {$table} (
			id         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id    bigint(20) UNSIGNED NOT NULL,
			stat_date  date             NOT NULL,
			views      int(10) UNSIGNED NOT NULL DEFAULT 0,
			clicks     int(10) UNSIGNED NOT NULL DEFAULT 0,
			time_total bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			time_count int(10) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY post_date (post_id, stat_date),
			KEY stat_date (stat_date),
			KEY post_id   (post_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'saf_db_version', SAF_VERSION );
	}

	/**
	 * Zajistí existenci tabulky + přidá chybějící sloupce.
	 * Bezpečné volat opakovaně – dbDelta je idempotentní.
	 */
	public static function ensure_table(): void {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		// Zkontroluj existenci time_total (nový sloupec přidaný v aktualizaci).
		$col = $wpdb->get_var( // phpcs:ignore
			"SELECT COLUMN_NAME FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			 AND TABLE_NAME = '{$table}'
			 AND COLUMN_NAME = 'time_total'"
		);

		if ( ! $col ) {
			// Přidej chybějící sloupce ručně (ALTER je rychlejší než dbDelta pro existující tabulku).
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS time_total bigint(20) UNSIGNED NOT NULL DEFAULT 0" ); // phpcs:ignore
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS time_count int(10) UNSIGNED NOT NULL DEFAULT 0" );    // phpcs:ignore
			update_option( 'saf_db_version', SAF_VERSION );
		}

		// Pokud tabulka vůbec neexistuje.
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ); // phpcs:ignore
		if ( ! $exists ) {
			static::create_table();
		}
	}

	/**
	 * Vrátí true pokud sloupce time_total/time_count existují v DB.
	 * Výsledek je cached pro jeden request.
	 */
	public static function has_time_columns(): bool {
		static $cache = null;
		if ( $cache !== null ) {
			return $cache;
		}
		global $wpdb;
		$col    = $wpdb->get_var( // phpcs:ignore
			"SELECT COLUMN_NAME FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			 AND TABLE_NAME = '{$wpdb->prefix}" . self::TABLE . "'
			 AND COLUMN_NAME = 'time_total'"
		);
		$cache = (bool) $col;
		return $cache;
	}

	public static function drop_table(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::TABLE ); // phpcs:ignore
	}
}
