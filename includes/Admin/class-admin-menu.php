<?php
/**
 * Admin menu pluginu Slovník a Feedy.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registruje admin menu a enqueue assets pro stránky pluginu.
 * Zařazen do skupiny Grou.cz v admin menu (pozice 33).
 */
final class AdminMenu {

	public const MENU_SLUG     = 'slovnik-a-feedy';
	public const LOGS_SLUG     = 'slovnik-a-feedy-logy';
	public const CAP           = 'manage_glossary';
	public const MENU_POSITION = 33;

	public function register(): void {
		// Sdílená logika skupiny Grou.cz (bundlováno, chráněno function_exists).
		require_once SAF_DIR . 'includes/grou-admin-group.php';

		add_menu_page(
			__( 'Slovník a Feedy', 'slovnik-a-feedy' ),
			__( 'Slovník a Feedy', 'slovnik-a-feedy' ),
			self::CAP,
			self::MENU_SLUG,
			[ $this, 'render_dashboard' ],
			'dashicons-rss',
			self::MENU_POSITION
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Přehled', 'slovnik-a-feedy' ),
			__( 'Přehled', 'slovnik-a-feedy' ),
			self::CAP,
			self::MENU_SLUG,
			[ $this, 'render_dashboard' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Logy', 'slovnik-a-feedy' ),
			__( 'Logy', 'slovnik-a-feedy' ),
			self::CAP,
			self::LOGS_SLUG,
			[ $this, 'render_logs' ]
		);

		// Zařadit do sekce Grou.cz (priorita 999 – po registraci menu).
		add_action( 'admin_menu', static function (): void {
			grou_register_admin_menu_group( self::MENU_POSITION );
		}, 999 );

		// CSS pro skupinové separátory Grou.cz.
		add_action( 'admin_head', static function (): void {
			grou_output_admin_group_css();
		} );
	}

	/**
	 * Načítá assets jen na stránkách tohoto pluginu.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'saf-admin',
			SAF_URL . 'assets/css/admin.css',
			[],
			SAF_VERSION
		);
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění pro zobrazení této stránky.', 'slovnik-a-feedy' ) );
		}
		require SAF_DIR . 'includes/Admin/views/dashboard.php';
	}

	public function render_logs(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění pro zobrazení logů.', 'slovnik-a-feedy' ) );
		}
		require SAF_DIR . 'includes/Admin/views/logs.php';
	}
}
