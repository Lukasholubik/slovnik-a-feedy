<?php
/**
 * Tracker – sledování zobrazení a kliknutí na stránky streamů.
 *
 * Views:  sledujeme server-side na template_redirect (PHP).
 * Clicks: sledujeme client-side přes JS beacon → REST /saf/v1/click.
 *
 * Bot filtrace: základní kontrola User-Agent.
 * Admini: nekontujeme (volitelné přes setting saf_track_admins).
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
	public const TABLE          = 'saf_stats';

	// -------------------------------------------------------------------------
	// Registrace hooků.

	public static function register_hooks(): void {
		// View tracking – server-side.
		add_action( 'template_redirect', [ static::class, 'maybe_track_view' ] );

		// REST endpoint pro click tracking z frontendu.
		add_action( 'rest_api_init', [ static::class, 'register_rest_routes' ] );

		// Frontend tracker script na stránkách streamů.
		add_action( 'wp_enqueue_scripts', [ static::class, 'enqueue_tracker_script' ] );
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

		// Ověř, že post_type patří některému z našich streamů.
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
				"INSERT INTO {$wpdb->prefix}" . self::TABLE . ' (post_id, stat_date, views, clicks)
				 VALUES (%d, %s, 1, 0)
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
				"INSERT INTO {$wpdb->prefix}" . self::TABLE . ' (post_id, stat_date, views, clicks)
				 VALUES (%d, %s, 0, 1)
				 ON DUPLICATE KEY UPDATE clicks = clicks + 1',
				$post_id,
				current_time( 'Y-m-d' )
			)
		);
	}

	// -------------------------------------------------------------------------
	// REST endpoint – příjímá click event z frontendu.

	public static function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			[
				'methods'             => 'POST',
				'callback'            => [ static::class, 'rest_track_click' ],
				'permission_callback' => '__return_true', // Veřejný endpoint – click je veřejná akce.
				'args'                => [
					'post_id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'minimum'           => 1,
					],
					'nonce' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);
	}

	public static function rest_track_click( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		// Nonce ověření – ochrana před CSRF zneužitím endpointu.
		if ( ! wp_verify_nonce( $request->get_param( 'nonce' ), 'saf_track' ) ) {
			return new \WP_Error( 'invalid_nonce', 'Neplatný token.', [ 'status' => 403 ] );
		}

		$post_id = absint( $request->get_param( 'post_id' ) );
		$post    = get_post( $post_id );

		if ( ! $post || ! StreamManager::find_by_cpt( $post->post_type ) ) {
			return new \WP_Error( 'invalid_post', 'Neplatné ID příspěvku.', [ 'status' => 400 ] );
		}

		static::record_click( $post_id );

		return new \WP_REST_Response( [ 'ok' => true ], 200 );
	}

	// -------------------------------------------------------------------------
	// Frontend script.

	public static function enqueue_tracker_script(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! StreamManager::find_by_cpt( $post->post_type ) ) {
			return;
		}

		wp_enqueue_script(
			'saf-tracker',
			SAF_URL . 'assets/js/saf-tracker.js',
			[],
			SAF_VERSION,
			true // footer
		);

		wp_localize_script( 'saf-tracker', 'safTracker', [
			'restUrl' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ),
			'nonce'   => wp_create_nonce( 'saf_track' ),
			'postId'  => $post->ID,
		] );
	}

	// -------------------------------------------------------------------------
	// Pomocné.

	/**
	 * Vrátí true pokud toto zobrazení nemáme počítat.
	 */
	private static function should_skip(): bool {
		// Přeskočit přihlášené administrátory (pokud není povoleno sledování).
		if ( is_user_logged_in()
			&& current_user_can( 'manage_options' )
			&& ! get_option( 'saf_track_admins', false )
		) {
			return true;
		}

		// Přeskočit boty.
		return static::is_bot();
	}

	/**
	 * Detekce common botů dle User-Agent.
	 */
	private static function is_bot(): bool {
		$ua = strtolower( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		if ( ! $ua ) {
			return true;
		}

		$bot_patterns = [
			'bot', 'crawler', 'spider', 'slurp', 'facebookexternalhit',
			'ia_archiver', 'msnbot', 'baiduspider', 'yandexbot',
			'linkedinbot', 'twitterbot', 'ahrefsbot', 'semrushbot',
			'dotbot', 'rogerbot', 'mj12bot', 'pinterestbot',
		];

		foreach ( $bot_patterns as $pattern ) {
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

		$table          = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id        bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id   bigint(20) UNSIGNED NOT NULL,
			stat_date date             NOT NULL,
			views     int(10) UNSIGNED NOT NULL DEFAULT 0,
			clicks    int(10) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY post_date (post_id, stat_date),
			KEY stat_date (stat_date),
			KEY post_id   (post_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function drop_table(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::TABLE ); // phpcs:ignore
	}
}
