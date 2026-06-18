<?php
/**
 * Admin stránka pro správu streamů (dynamické CPT).
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Admin;

use SlovnikAFeedy\StreamManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD UI pro streamy. Každý stream = jeden CPT s vlastním archivem a feedy.
 */
final class StreamsPage {

	public const PAGE_SLUG = 'slovnik-a-feedy-streamy';
	public const CAP       = AdminMenu::CAP;

	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}

		$notice = '';
		$error  = '';

		// Zpracuj POST akce.
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			[ $notice, $error ] = $this->handle_post();
		}

		// Zpracuj GET akce (smazání).
		if ( isset( $_GET['action'], $_GET['stream_id'] ) && $_GET['action'] === 'delete' ) {
			[ $notice, $error ] = $this->handle_delete();
		}

		$streams     = StreamManager::get_all();
		$edit_stream = null;
		if ( isset( $_GET['action'], $_GET['stream_id'] ) && $_GET['action'] === 'edit' ) {
			$edit_stream = StreamManager::get( sanitize_key( $_GET['stream_id'] ) );
		}

		require SAF_DIR . 'includes/Admin/views/streams.php';
	}

	// -------------------------------------------------------------------------

	private function handle_post(): array {
		if ( ! isset( $_POST['saf_streams_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saf_streams_nonce'] ) ), 'saf_streams_action' )
		) {
			return [ '', __( 'Neplatný bezpečnostní token.', 'slovnik-a-feedy' ) ];
		}

		$action    = sanitize_key( $_POST['saf_action'] ?? '' );
		$stream_id = sanitize_key( $_POST['stream_id'] ?? '' );

		$data = [
			'name'       => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'cpt'        => sanitize_key( $_POST['cpt'] ?? '' ),
			'url_slug'   => sanitize_title( $_POST['url_slug'] ?? '' ),
			'icon'       => sanitize_html_class( $_POST['icon'] ?? 'dashicons-list-view' ),
			'tax_letter' => ! empty( $_POST['tax_letter'] ),
			'tax_cat'    => ! empty( $_POST['tax_cat'] ),
			'active'     => ! empty( $_POST['active'] ),
		];

		if ( empty( $data['name'] ) || empty( $data['cpt'] ) ) {
			return [ '', __( 'Název a CPT slug jsou povinné.', 'slovnik-a-feedy' ) ];
		}

		try {
			if ( $action === 'create' ) {
				StreamManager::create( $data );
				return [ __( 'Stream byl vytvořen. Přejdi do Nastavení → Permalinks a klikni Uložit.', 'slovnik-a-feedy' ), '' ];
			} elseif ( $action === 'update' && $stream_id ) {
				StreamManager::update( $stream_id, $data );
				return [ __( 'Stream byl aktualizován. Přejdi do Nastavení → Permalinks a klikni Uložit.', 'slovnik-a-feedy' ), '' ];
			}
		} catch ( \InvalidArgumentException $e ) {
			return [ '', esc_html( $e->getMessage() ) ];
		}

		return [ '', '' ];
	}

	private function handle_delete(): array {
		$stream_id = sanitize_key( $_GET['stream_id'] ?? '' );
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ),
			'saf_delete_stream_' . $stream_id
		) ) {
			return [ '', __( 'Neplatný token.', 'slovnik-a-feedy' ) ];
		}

		if ( StreamManager::delete( $stream_id ) ) {
			return [ __( 'Stream byl smazán. Data (příspěvky) zůstala zachována.', 'slovnik-a-feedy' ), '' ];
		}
		return [ '', __( 'Stream nelze smazat (je výchozí nebo neexistuje).', 'slovnik-a-feedy' ) ];
	}
}
