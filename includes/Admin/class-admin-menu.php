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
			__( 'Šablony', 'slovnik-a-feedy' ),
			__( 'Šablony', 'slovnik-a-feedy' ),
			self::CAP,
			'slovnik-a-feedy-sablony',
			[ $this, 'render_templates' ]
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
			__( 'Export', 'slovnik-a-feedy' ),
			__( 'Export', 'slovnik-a-feedy' ),
			self::CAP,
			'slovnik-a-feedy-export',
			[ $this, 'render_export' ]
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

		// Template builder + preview JS jen na import stránce.
		if ( str_contains( $hook, ImportPage::PAGE_SLUG ) ) {
			wp_enqueue_script( 'saf-template-builder', SAF_URL . 'assets/js/saf-template-builder.js', [], SAF_VERSION, true );
			wp_enqueue_script( 'saf-import-preview', SAF_URL . 'assets/js/saf-import-preview.js', [], SAF_VERSION, true );

			// Témata CSS pro iframe náhled (wp_styles bez bloated dependencí).
			global $wp_styles;
			$theme_styles = [];
			if ( $wp_styles ) {
				foreach ( $wp_styles->queue as $handle ) {
					$src = $wp_styles->registered[ $handle ]->src ?? '';
					// Zahrn jen frontend styly (style.css, theme.css…) – ne admin styly.
					if ( $src && str_contains( $src, get_template_directory_uri() ) ) {
						$theme_styles[] = $src;
					}
				}
			}
			// Fallback: načti hlavní theme stylesheet.
			if ( empty( $theme_styles ) ) {
				$theme_styles = [ get_stylesheet_uri() ];
			}

			wp_localize_script( 'saf-import-preview', 'safImportPreview', [
				'restUrl'     => esc_url_raw( rest_url( 'saf/v1/preview-template' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'themeStyles' => $theme_styles,
				'macroData'   => [], // Doplní se přes wp_add_inline_script z view.
				'templateId'  => (int) get_option( 'saf_last_template_id', 0 ),
			] );
		}

		wp_enqueue_script( 'saf-admin-js', SAF_URL . 'assets/js/saf-admin.js', [], SAF_VERSION, true );
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}
		require SAF_DIR . 'includes/Admin/views/dashboard.php';
	}

	public function render_export(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}

		// Zpracuj POST download.
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['saf_export_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saf_export_nonce'] ) ), 'saf_export' ) ) {
				wp_die( esc_html__( 'Neplatný token.', 'slovnik-a-feedy' ) );
			}

			$stream_id = sanitize_key( $_POST['stream_id'] ?? '' );
			$format    = sanitize_key( $_POST['format']    ?? 'csv' );
			$stream    = \SlovnikAFeedy\StreamManager::get( $stream_id );

			if ( $stream ) {
				$exporter = new \SlovnikAFeedy\Exporter\Exporter( $stream );
				if ( $format === 'xml' ) {
					$exporter->export_xml();
				} else {
					$exporter->export_csv();
				}
				exit;
			}
		}

		$streams = \SlovnikAFeedy\StreamManager::get_all();
		require SAF_DIR . 'includes/Admin/views/export.php';
	}

	public function render_templates(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}

		$notice = '';
		$error  = '';

		// Smazání šablony.
		if ( isset( $_GET['action'], $_GET['tpl'] ) && $_GET['action'] === 'delete' ) {
			$tpl_id = absint( $_GET['tpl'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'saf_delete_tpl_' . $tpl_id ) ) {
				wp_delete_post( $tpl_id, true );
				$notice = __( 'Šablona byla smazána.', 'slovnik-a-feedy' );
			} else {
				$error = __( 'Neplatný token.', 'slovnik-a-feedy' );
			}
		}

		// Smazání presetu.
		if ( isset( $_GET['action'], $_GET['preset'] ) && $_GET['action'] === 'delete_preset' ) {
			$pid = sanitize_key( $_GET['preset'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'saf_del_preset_' . $pid ) ) {
				Settings::delete_import_preset( $pid );
				$notice = __( 'Preset byl smazán.', 'slovnik-a-feedy' );
			} else {
				$error = __( 'Neplatný token.', 'slovnik-a-feedy' );
			}
		}

		require SAF_DIR . 'includes/Admin/views/templates.php';
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
		( new ImportPage() )->render();
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
