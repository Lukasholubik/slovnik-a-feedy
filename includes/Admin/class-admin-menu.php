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
	public const SETTINGS_SLUG = 'slovnik-a-feedy-nastaveni';
	public const LOGS_SLUG     = 'slovnik-a-feedy-logy';
	public const CAP           = 'manage_glossary';
	public const MENU_POSITION = 33;

	public function register(): void {
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
			__( 'Analytics', 'slovnik-a-feedy' ),
			__( 'Analytics', 'slovnik-a-feedy' ),
			self::CAP,
			AnalyticsPage::PAGE_SLUG,
			[ $this, 'render_analytics' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Streamy', 'slovnik-a-feedy' ),
			__( 'Streamy', 'slovnik-a-feedy' ),
			self::CAP,
			StreamsPage::PAGE_SLUG,
			[ $this, 'render_streams' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Import', 'slovnik-a-feedy' ),
			__( 'Import', 'slovnik-a-feedy' ),
			self::CAP,
			ImportPage::PAGE_SLUG,
			[ $this, 'render_import' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Nastavení', 'slovnik-a-feedy' ),
			__( 'Nastavení', 'slovnik-a-feedy' ),
			self::CAP,
			self::SETTINGS_SLUG,
			[ $this, 'render_settings' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Logy', 'slovnik-a-feedy' ),
			__( 'Logy', 'slovnik-a-feedy' ),
			self::CAP,
			self::LOGS_SLUG,
			[ $this, 'render_logs' ]
		);

		add_action( 'admin_menu', static function (): void {
			grou_register_admin_menu_group( self::MENU_POSITION );
		}, 999 );

		add_action( 'admin_head', static function (): void {
			grou_output_admin_group_css();
		} );
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, self::MENU_SLUG ) ) {
			return;
		}
		// Analytics stránka si enqueue řeší sama (Chart.js).
		if ( str_contains( $hook, AnalyticsPage::PAGE_SLUG ) ) {
			( new AnalyticsPage() )->enqueue_assets( $hook );
			return;
		}
		wp_enqueue_style( 'saf-admin', SAF_URL . 'assets/css/admin.css', [], SAF_VERSION );
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}
		require SAF_DIR . 'includes/Admin/views/dashboard.php';
	}

	public function render_analytics(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}
		( new AnalyticsPage() )->render();
	}

	public function render_streams(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}
		( new StreamsPage() )->render();
	}

	public function render_import(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}
		$page      = new ImportPage();
		$view_data = $page->get_view_data(); // data pro view (prázdná při GET)
		$page->render();
	}

	public function render_settings(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}
		$view_data = [];

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			if ( ! isset( $_POST['saf_settings_nonce'] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saf_settings_nonce'] ) ), 'saf_save_settings' )
			) {
				$view_data['error'] = __( 'Neplatný bezpečnostní token.', 'slovnik-a-feedy' );
			} else {
				Settings::save_from_post( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
				$view_data['saved'] = true;
			}
		}

		require SAF_DIR . 'includes/Admin/views/settings.php';
	}

	public function render_logs(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}
		require SAF_DIR . 'includes/Admin/views/logs.php';
	}
}
