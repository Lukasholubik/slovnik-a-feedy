<?php
/**
 * Admin stránka – Analytics přehled.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Admin;

use SlovnikAFeedy\Analytics\AnalyticsStore;
use SlovnikAFeedy\StreamManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AnalyticsPage {

	public const PAGE_SLUG = 'slovnik-a-feedy-analytics';
	public const CAP       = AdminMenu::CAP;
	public const PER_PAGE  = 20;

	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}

		// Filtry z GET.
		$range   = sanitize_key( $_GET['range']  ?? '30' );
		$cpt     = sanitize_key( $_GET['cpt']    ?? '' );
		$order   = sanitize_key( $_GET['order']  ?? 'views' );
		$dir     = ( sanitize_key( $_GET['dir'] ?? 'desc' ) === 'asc' ) ? 'ASC' : 'DESC';
		$paged   = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$search  = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );

		$allowed_ranges = [ '7', '30', '90', '365' ];
		if ( ! in_array( $range, $allowed_ranges, true ) ) {
			$range = '30';
		}

		$to   = current_time( 'Y-m-d' );
		$from = gmdate( 'Y-m-d', strtotime( "-{$range} days", strtotime( $to ) ) );

		// Data.
		$summary    = AnalyticsStore::get_summary( $from, $to, $cpt );
		$series     = AnalyticsStore::get_daily_series( $from, $to, $cpt );
		$pages      = AnalyticsStore::get_pages( $from, $to, $cpt, $order, $dir, self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE );
		$total_pages_count = AnalyticsStore::get_pages_count( $from, $to, $cpt );
		$streams    = StreamManager::get_all();

		// Sparklines pro každou stránku.
		$sparklines = [];
		foreach ( $pages as $page ) {
			$sparklines[ $page->post_id ] = AnalyticsStore::get_sparkline( (int) $page->post_id, 14 );
		}

		// Předchozí období pro trend srovnání.
		$prev_to   = gmdate( 'Y-m-d', strtotime( $from . ' -1 day' ) );
		$prev_from = gmdate( 'Y-m-d', strtotime( "-{$range} days", strtotime( $prev_to ) ) );
		$prev_summary = AnalyticsStore::get_summary( $prev_from, $prev_to, $cpt );

		require SAF_DIR . 'includes/Admin/views/analytics.php';
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		// Chart.js z CDN (stable v4).
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			[],
			'4.4.0',
			true
		);
		wp_enqueue_script(
			'saf-analytics',
			SAF_URL . 'assets/js/saf-analytics.js',
			[ 'chartjs' ],
			SAF_VERSION,
			true
		);
		wp_enqueue_style( 'saf-admin', SAF_URL . 'assets/css/admin.css', [], SAF_VERSION );
	}
}
