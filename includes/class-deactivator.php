<?php
/**
 * Logika deaktivace pluginu.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spouští se při deaktivaci pluginu přes WP Admin.
 * Data se nemažou – k tomu slouží uninstall.php.
 */
final class Deactivator {

	public static function deactivate(): void {
		// Zrušení naplánovaných WP-Cron úloh pluginu.
		$timestamp = wp_next_scheduled( 'saf_scheduled_reimport' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'saf_scheduled_reimport' );
		}

		flush_rewrite_rules();
	}
}
