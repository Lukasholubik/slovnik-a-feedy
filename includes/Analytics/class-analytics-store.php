<?php
/**
 * AnalyticsStore – query engine pro čtení statistik.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Analytics;

use SlovnikAFeedy\StreamManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AnalyticsStore {

	private const TABLE = Tracker::TABLE;

	// -------------------------------------------------------------------------
	// Přehledové statistiky.

	/**
	 * Celkové součty za dané období.
	 *
	 * @return array{views: int, clicks: int, posts: int}
	 */
	public static function get_summary( string $from, string $to, string $cpt = '' ): array {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE;
		$where  = 'WHERE s.stat_date BETWEEN %s AND %s';
		$params = [ $from, $to ];

		if ( $cpt ) {
			$where   .= ' AND p.post_type = %s';
			$params[] = $cpt;
		} else {
			// Jen naše streamy.
			$cpts     = array_column( StreamManager::get_all(), 'cpt' );
			if ( $cpts ) {
				$placeholders = implode( ', ', array_fill( 0, count( $cpts ), '%s' ) );
				$where       .= " AND p.post_type IN ({$placeholders})";
				$params       = array_merge( $params, $cpts );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(s.views), 0)  AS views,
					COALESCE(SUM(s.clicks), 0) AS clicks,
					COUNT(DISTINCT s.post_id)  AS posts
				FROM {$table} s
				JOIN {$wpdb->posts} p ON p.ID = s.post_id
				{$where}",
				...$params
			)
		);

		return [
			'views'  => (int) ( $row->views ?? 0 ),
			'clicks' => (int) ( $row->clicks ?? 0 ),
			'posts'  => (int) ( $row->posts ?? 0 ),
		];
	}

	// -------------------------------------------------------------------------
	// Denní data pro graf.

	/**
	 * Vrátí pole denních dat (views + clicks) pro graf.
	 *
	 * @return list<array{date: string, views: int, clicks: int}>
	 */
	public static function get_daily_series( string $from, string $to, string $cpt = '' ): array {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE;
		$where  = 'WHERE s.stat_date BETWEEN %s AND %s';
		$params = [ $from, $to ];

		if ( $cpt ) {
			$where   .= ' AND p.post_type = %s';
			$params[] = $cpt;
		} else {
			$cpts = array_column( StreamManager::get_all(), 'cpt' );
			if ( $cpts ) {
				$placeholders = implode( ', ', array_fill( 0, count( $cpts ), '%s' ) );
				$where       .= " AND p.post_type IN ({$placeholders})";
				$params       = array_merge( $params, $cpts );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					s.stat_date            AS date,
					SUM(s.views)           AS views,
					SUM(s.clicks)          AS clicks
				FROM {$table} s
				JOIN {$wpdb->posts} p ON p.ID = s.post_id
				{$where}
				GROUP BY s.stat_date
				ORDER BY s.stat_date ASC",
				...$params
			)
		);

		// Doplň chybějící dny nulami.
		$result = [];
		$period = new \DatePeriod(
			new \DateTime( $from ),
			new \DateInterval( 'P1D' ),
			( new \DateTime( $to ) )->modify( '+1 day' )
		);

		$by_date = [];
		foreach ( $rows as $row ) {
			$by_date[ $row->date ] = [ 'views' => (int) $row->views, 'clicks' => (int) $row->clicks ];
		}

		foreach ( $period as $day ) {
			$date     = $day->format( 'Y-m-d' );
			$result[] = [
				'date'   => $date,
				'views'  => $by_date[ $date ]['views']  ?? 0,
				'clicks' => $by_date[ $date ]['clicks'] ?? 0,
			];
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Top / bottom stránky.

	/**
	 * Vrátí stránky seřazené dle views (nebo clicks).
	 *
	 * @return list<object>
	 */
	public static function get_pages(
		string $from,
		string $to,
		string $cpt       = '',
		string $order_by  = 'views',
		string $order     = 'DESC',
		int    $limit     = 20,
		int    $offset    = 0,
		bool   $show_zero = false  // true = vrátit i stránky s 0 zobrazeními
	): array {
		global $wpdb;

		$table        = $wpdb->prefix . self::TABLE;
		$allowed_cols = [ 'views', 'clicks', 'avg_time' ];
		$order_by_col = in_array( $order_by, $allowed_cols, true ) ? $order_by : 'views';
		$order_dir    = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		$where  = 'WHERE s.stat_date BETWEEN %s AND %s AND p.post_status = %s';
		$params = [ $from, $to, 'publish' ];

		if ( $cpt ) {
			$where   .= ' AND p.post_type = %s';
			$params[] = $cpt;
		} else {
			$cpts = array_column( StreamManager::get_all(), 'cpt' );
			if ( $cpts ) {
				$placeholders = implode( ', ', array_fill( 0, count( $cpts ), '%s' ) );
				$where       .= " AND p.post_type IN ({$placeholders})";
				$params       = array_merge( $params, $cpts );
			}
		}

		// Pokud show_zero = false, zobrazuj jen stránky které měly alespoň 1 zobrazení.
		if ( ! $show_zero ) {
			$where .= ' AND s.views > 0';
		}

		$params[] = $limit;
		$params[] = $offset;

		// Avg_time sloupce jsou volitelné (přidány aktualizací) – query se přizpůsobí.
		$time_select = \SlovnikAFeedy\Analytics\Tracker::has_time_columns()
			? "CASE WHEN SUM(s.time_count) > 0 THEN ROUND(SUM(s.time_total) / SUM(s.time_count)) ELSE 0 END AS avg_time"
			: '0 AS avg_time';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.ID                              AS post_id,
					p.post_title                      AS title,
					p.post_name                       AS slug,
					p.post_type                       AS cpt,
					COALESCE(SUM(s.views), 0)         AS views,
					COALESCE(SUM(s.clicks), 0)        AS clicks,
					{$time_select}
				FROM {$table} s
				JOIN {$wpdb->posts} p ON p.ID = s.post_id
				{$where}
				GROUP BY s.post_id, p.ID, p.post_title, p.post_name, p.post_type
				ORDER BY {$order_by_col} {$order_dir}
				LIMIT %d OFFSET %d",
				...$params
			)
		);
	}

	/**
	 * Celkový počet stránek (pro stránkování).
	 */
	public static function get_pages_count( string $from, string $to, string $cpt = '' ): int {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE;
		$where  = 'WHERE s.stat_date BETWEEN %s AND %s AND p.post_status = %s';
		$params = [ $from, $to, 'publish' ];

		if ( $cpt ) {
			$where   .= ' AND p.post_type = %s';
			$params[] = $cpt;
		} else {
			$cpts = array_column( StreamManager::get_all(), 'cpt' );
			if ( $cpts ) {
				$placeholders = implode( ', ', array_fill( 0, count( $cpts ), '%s' ) );
				$where       .= " AND p.post_type IN ({$placeholders})";
				$params       = array_merge( $params, $cpts );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT s.post_id)
				FROM {$table} s
				JOIN {$wpdb->posts} p ON p.ID = s.post_id
				{$where}",
				...$params
			)
		);
	}

	// -------------------------------------------------------------------------
	// Sparkline trend pro jednu stránku (posledních N dní).

	/**
	 * @return list<int>  pole denních views (pro sparkline)
	 */
	public static function get_sparkline( int $post_id, int $days = 14 ): array {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		$from  = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$to    = current_time( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT stat_date, views FROM {$table}
				WHERE post_id = %d AND stat_date BETWEEN %s AND %s
				ORDER BY stat_date ASC",
				$post_id, $from, $to
			)
		);

		$by_date = [];
		foreach ( $rows as $row ) {
			$by_date[ $row->stat_date ] = (int) $row->views;
		}

		$result = [];
		$period = new \DatePeriod(
			new \DateTime( $from ),
			new \DateInterval( 'P1D' ),
			( new \DateTime( $to ) )->modify( '+1 day' )
		);
		foreach ( $period as $day ) {
			$result[] = $by_date[ $day->format( 'Y-m-d' ) ] ?? 0;
		}

		return $result;
	}
}
