<?php
/**
 * Import admin page – controller pro wizard importu.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Admin;

use SlovnikAFeedy\StreamManager;
use SlovnikAFeedy\Importer\{CsvSource, XmlSource, Mapper, TemplateEngine, Importer, BatchRunner};
use SlovnikAFeedy\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Řídí multi-step wizard importu. Stav mezi kroky ukládá do WP transientu.
 *
 * Kroky:
 *   0 – výběr zdroje + nahrání souboru / vložení URL
 *   1 – mapování sloupců na pole pluginu
 *   2 – šablona obsahu + volby importu
 *   3 – výsledky (nebo dry-run náhled)
 */
final class ImportPage {

	public const PAGE_SLUG       = 'slovnik-a-feedy-import';
	public const CAP             = AdminMenu::CAP;
	private const TRANSIENT_TTL  = HOUR_IN_SECONDS;
	private const TRANSIENT_KEY  = 'saf_import_session_';
	private const MAX_FILE_SIZE  = 10 * 1024 * 1024; // 10 MB

	/** Výsledky aktuálního requestu předané do view. */
	private array $view_data = [];

	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}

		// Zpracuj POST (navigace wizardem).
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$this->handle_post();
		}

		$this->view_data['profiles'] = Settings::get_profiles();
		// Předej view_data jako lokální proměnnou – require ji vidí v daném scope.
		$view_data = $this->view_data;
		require SAF_DIR . 'includes/Admin/views/import.php';
	}

	// -------------------------------------------------------------------------
	// POST handling.

	private function handle_post(): void {
		$step = absint( $_POST['saf_step'] ?? 0 );
		$nonce_action = 'saf_import_step_' . $step;

		if ( ! isset( $_POST['saf_import_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saf_import_nonce'] ) ), $nonce_action )
		) {
			$this->view_data['error'] = __( 'Neplatný bezpečnostní token. Zkus to znovu.', 'slovnik-a-feedy' );
			return;
		}

		match ( $step ) {
			0 => $this->handle_step0(),
			1 => $this->handle_step1(),
			2 => $this->handle_step2(),
			default => null,
		};
	}

	/** Krok 0 – nahrání souboru nebo URL, detekce sloupců. */
	private function handle_step0(): void {
		$source_type = sanitize_key( $_POST['source_type'] ?? 'csv' );
		$stream_id   = sanitize_key( $_POST['stream_id'] ?? '' );
		$stream      = $stream_id ? StreamManager::get( $stream_id ) : null;
		if ( ! $stream ) {
			// Fallback na první aktivní stream.
			$all    = StreamManager::get_all();
			$stream = reset( $all ) ?: [];
		}

		// Načti profil pokud byl vybrán.
		$profile_id = sanitize_key( $_POST['load_profile'] ?? '' );
		$profile    = $profile_id ? ( Settings::get_profiles()[ $profile_id ] ?? null ) : null;

		try {
			[ $file_path, $source_type ] = $this->resolve_source( $source_type );
			$source  = $this->make_source( $source_type, $file_path );
			$columns = $source->get_columns();

			if ( empty( $columns ) ) {
				throw new \RuntimeException( __( 'Soubor neobsahuje žádné sloupce. Zkontroluj formát.', 'slovnik-a-feedy' ) );
			}

			// Načti prvních 5 řádků pro spreadsheet preview.
			$preview_rows = [];
			$total_rows   = 0;
			foreach ( $source->get_rows() as $row ) {
				if ( count( $preview_rows ) < 5 ) {
					$preview_rows[] = $row;
				}
				$total_rows++;
			}

			$auto_mapping = $profile
				? $profile['mapping']
				: Mapper::auto_map( $columns );

			$session_id = $this->new_session( [
				'file_path'    => $file_path,
				'source_type'  => $source_type,
				'columns'      => $columns,
				'mapping'      => $auto_mapping,
				'template'     => $profile ? $profile['template'] : TemplateEngine::default_template(),
				'stream'       => $stream,
				'preview_rows' => $preview_rows,
				'total_rows'   => $total_rows,
			] );

			$this->view_data = array_merge( $this->view_data, [
				'step'         => 1,
				'session_id'   => $session_id,
				'columns'      => $columns,
				'auto_mapping' => $auto_mapping,
				'fields'       => Mapper::FIELDS,
				'profile'      => $profile,
				'stream'       => $stream,
				'preview_rows' => $preview_rows,
				'total_rows'   => $total_rows,
			] );

		} catch ( \Throwable $e ) {
			$this->view_data['error'] = esc_html( $e->getMessage() );
		}
	}

	/** Krok 1 – uložení mapování, přechod na šablonu. */
	private function handle_step1(): void {
		$session_id = sanitize_key( $_POST['session_id'] ?? '' );
		$session    = $this->load_session( $session_id );
		if ( ! $session ) {
			$this->view_data['error'] = __( 'Relace vypršela. Začni import znovu.', 'slovnik-a-feedy' );
			return;
		}

		// Sanitizuj mapování polí – povoleny jen klíče z Mapper::FIELDS.
		$raw_mapping = (array) ( $_POST['mapping'] ?? [] );
		$mapping     = [];
		foreach ( $raw_mapping as $col => $field ) {
			$col   = sanitize_text_field( wp_unslash( $col ) );
			$field = sanitize_key( $field );
			if ( $field && isset( Mapper::FIELDS[ $field ] ) ) {
				$mapping[ $col ] = $field;
			}
		}

		// Sanitizuj mapování block typů – povoleny jen klíče z TemplateEngine::get_block_types().
		$raw_block_types  = (array) ( $_POST['block_type'] ?? [] );
		$allowed_blocks   = array_keys( TemplateEngine::get_block_types() );
		$block_mapping    = [];
		foreach ( $raw_block_types as $col => $bt ) {
			$col = sanitize_text_field( wp_unslash( $col ) );
			$bt  = sanitize_key( $bt );
			if ( $bt && in_array( $bt, $allowed_blocks, true ) ) {
				$block_mapping[ $col ] = $bt;
			}
		}

		// Auto-generace šablony z block typů (pokud jsou nastaveny).
		$auto_template = '';
		if ( ! empty( $block_mapping ) ) {
			$auto_template = TemplateEngine::generate_from_block_types(
				$block_mapping,
				$session['columns'] ?? []
			);
		}

		// Fallback na existující šablonu pokud block typy nebyly nastaveny.
		$template = $auto_template ?: $session['template'];

		// Ulož profil pokud zaškrtl.
		if ( ! empty( $_POST['save_profile'] ) && ! empty( $_POST['profile_name'] ) ) {
			Settings::save_profile(
				sanitize_key( uniqid( 'profile_', true ) ),
				[
					'name'     => sanitize_text_field( wp_unslash( $_POST['profile_name'] ) ),
					'mapping'  => $mapping,
					'template' => $template,
				]
			);
		}

		$session['mapping']       = $mapping;
		$session['block_mapping'] = $block_mapping;
		$this->save_session( $session_id, $session );

		// Náhled prvního řádku (z session nebo ze souboru).
		$preview_row = $session['preview_rows'][0] ?? null;
		if ( ! $preview_row ) {
			$source = $this->make_source( $session['source_type'], $session['file_path'] );
			foreach ( $source->get_rows() as $row ) {
				$preview_row = $row;
				break;
			}
		}

		$this->view_data = array_merge( $this->view_data, [
			'step'          => 2,
			'session_id'    => $session_id,
			'columns'       => $session['columns'],
			'mapping'       => $mapping,
			'block_mapping' => $block_mapping,
			'template'      => $template,
			'preview_row'   => $preview_row,
			'syntax_help'   => TemplateEngine::get_syntax_help(),
			'settings'      => Settings::get_all(),
			'auto_generated' => ! empty( $auto_template ),
		] );
	}

	/** Krok 2 – dry-run nebo spuštění importu. */
	private function handle_step2(): void {
		$session_id = sanitize_key( $_POST['session_id'] ?? '' );
		$session    = $this->load_session( $session_id );
		if ( ! $session ) {
			$this->view_data['error'] = __( 'Relace vypršela. Začni import znovu.', 'slovnik-a-feedy' );
			return;
		}

		$template       = wp_kses_post( wp_unslash( $_POST['template'] ?? '' ) );
		$default_status = in_array( $_POST['default_status'] ?? '', [ 'publish', 'draft' ], true )
			? $_POST['default_status']
			: 'publish';
		$force_overwrite = ! empty( $_POST['force_overwrite'] );
		$is_dry_run      = ! empty( $_POST['dry_run'] );

		// Ulož template do profilu pokud zaškrtl.
		if ( ! empty( $_POST['save_profile'] ) && ! empty( $_POST['profile_name'] ) ) {
			Settings::save_profile(
				sanitize_key( uniqid( 'profile_', true ) ),
				[
					'name'     => sanitize_text_field( wp_unslash( $_POST['profile_name'] ) ),
					'mapping'  => $session['mapping'],
					'template' => $template,
				]
			);
		}

		$config = [
			'mapping'        => $session['mapping'],
			'template'       => $template,
			'stream'         => $session['stream'] ?? [],
			'default_status' => $default_status,
			'dry_run'        => $is_dry_run,
			'force_overwrite' => $force_overwrite,
		];

		// Načti všechny řádky.
		$source = $this->make_source( $session['source_type'], $session['file_path'] );
		$rows   = iterator_to_array( $source->get_rows(), false );

		$result = BatchRunner::start( $rows, $config );

		// Vyčisti temp soubor pokud import dokončen (ne async).
		if ( $result['mode'] === 'sync' ) {
			$this->cleanup_temp_file( $session['file_path'] );
			$this->delete_session( $session_id );
		}

		$this->view_data = array_merge( $this->view_data, [
			'step'        => 3,
			'result'      => $result,
			'is_dry_run'  => $is_dry_run,
			'total_rows'  => count( $rows ),
		] );
	}

	// -------------------------------------------------------------------------
	// Soubory a zdroje.

	/**
	 * Zpracuje upload souboru nebo stažení URL, vrátí [file_path, source_type].
	 *
	 * @return array{string, string}
	 * @throws \RuntimeException
	 */
	private function resolve_source( string $source_type ): array {
		$upload_dir = $this->ensure_upload_dir();

		if ( 'gsheet' === $source_type ) {
			$url = esc_url_raw( wp_unslash( $_POST['gsheet_url'] ?? '' ) );
			if ( ! $url ) {
				throw new \RuntimeException( __( 'Zadej URL Google Sheets.', 'slovnik-a-feedy' ) );
			}
			$file_path = $this->download_url( $url, $upload_dir );
			return [ $file_path, 'csv' ]; // GSheet jako CSV.
		}

		if ( empty( $_FILES['saf_file']['tmp_name'] ) ) {
			throw new \RuntimeException( __( 'Žádný soubor nebyl nahrán.', 'slovnik-a-feedy' ) );
		}

		return [ $this->handle_upload( $upload_dir, $source_type ), $source_type ];
	}

	/**
	 * @throws \RuntimeException
	 */
	private function handle_upload( string $upload_dir, string $source_type ): string {
		$file     = $_FILES['saf_file']; // phpcs:ignore
		$tmp_path = $file['tmp_name'];
		$orig_name = sanitize_file_name( $file['name'] );

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			throw new \RuntimeException( __( 'Chyba při nahrávání souboru.', 'slovnik-a-feedy' ) );
		}
		if ( $file['size'] > self::MAX_FILE_SIZE ) {
			throw new \RuntimeException( __( 'Soubor je příliš velký (max. 10 MB).', 'slovnik-a-feedy' ) );
		}

		// Whitelist přípon.
		$allowed_exts = [ 'csv' => 'csv', 'xml' => 'xml', 'tsv' => 'csv' ];
		$ext = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );
		if ( ! isset( $allowed_exts[ $ext ] ) ) {
			throw new \RuntimeException( __( 'Nepodporovaný formát souboru. Povoleny: CSV, XML, TSV.', 'slovnik-a-feedy' ) );
		}

		// Ověř MIME (wp_check_filetype_and_ext).
		$check = wp_check_filetype_and_ext( $tmp_path, $orig_name, [
			'csv' => 'text/csv',
			'tsv' => 'text/tab-separated-values',
			'xml' => 'text/xml',
		] );
		if ( ! $check['ext'] ) {
			throw new \RuntimeException( __( 'Neplatný typ souboru.', 'slovnik-a-feedy' ) );
		}

		// Přesuň do bezpečné temp složky.
		$dest = $upload_dir . '/' . wp_unique_filename( $upload_dir, sanitize_file_name( uniqid( 'saf-', true ) . '.' . $ext ) );
		if ( ! move_uploaded_file( $tmp_path, $dest ) ) {
			throw new \RuntimeException( __( 'Soubor se nepodařilo uložit.', 'slovnik-a-feedy' ) );
		}

		return $dest;
	}

	/**
	 * Stáhne URL jako temp soubor (Google Sheets / jiný CSV feed).
	 * Automaticky konvertuje běžnou editační URL na CSV export URL.
	 *
	 * @throws \RuntimeException
	 */
	private function download_url( string $url, string $upload_dir ): string {
		// Whitelist hosts (SSRF ochrana).
		$host          = wp_parse_url( $url, PHP_URL_HOST );
		$allowed_hosts = [ 'docs.google.com', 'spreadsheets.google.com', 'drive.google.com' ];
		if ( ! in_array( $host, $allowed_hosts, true ) ) {
			throw new \RuntimeException(
				__( 'Povoleny jsou pouze Google Sheets URL (docs.google.com).', 'slovnik-a-feedy' )
			);
		}

		// Auto-konverze Google Sheets edit/view URL → CSV export URL.
		$url = $this->normalize_gsheet_url( $url );

		$response = wp_remote_get( $url, [ 'timeout' => 30, 'sslverify' => true ] );
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				__( 'Nepodařilo se stáhnout Google Sheet: ', 'slovnik-a-feedy' ) . $response->get_error_message()
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			throw new \RuntimeException(
				sprintf( __( 'Google Sheet vrátil HTTP %d. Je Sheet publikován jako CSV?', 'slovnik-a-feedy' ), $code )
			);
		}

		$body = wp_remote_retrieve_body( $response );

		// Detekce HTML odpovědi – stažena stránka místo CSV dat.
		if ( str_starts_with( ltrim( $body ), '<!DOCTYPE' ) || str_starts_with( ltrim( $body ), '<html' ) ) {
			throw new \RuntimeException(
				__( 'Google Sheet vrátil HTML místo CSV. Potřebuješ správnou URL CSV exportu. Viz nápověda níže.', 'slovnik-a-feedy' )
			);
		}

		$dest    = $upload_dir . '/' . uniqid( 'saf-gsheet-', true ) . '.csv';
		$written = file_put_contents( $dest, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( $written === false ) {
			throw new \RuntimeException( __( 'Chyba při ukládání staženého souboru.', 'slovnik-a-feedy' ) );
		}

		return $dest;
	}

	/**
	 * Konvertuje libovolnou Google Sheets URL na CSV export URL.
	 *
	 * Vzory:
	 *   /spreadsheets/d/{ID}/edit         → /spreadsheets/d/{ID}/export?format=csv&gid={gid}
	 *   /spreadsheets/d/{ID}/pub?...      → přidá output=csv pokud chybí
	 *   /spreadsheets/d/{ID}/pub?output=csv → beze změny
	 */
	private function normalize_gsheet_url( string $url ): string {
		// Extrahuj spreadsheet ID.
		if ( ! preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m ) ) {
			return $url; // Neznámý formát – vrátit beze změny.
		}

		$sheet_id = $m[1];

		// Extrahuj gid (záložka) z query stringu.
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $params );
		$gid = $params['gid'] ?? '0';

		// Pokud URL již obsahuje output=csv nebo export?format=csv, vrátit beze změny.
		if ( str_contains( $url, 'output=csv' ) || str_contains( $url, 'format=csv' ) ) {
			return $url;
		}

		// Sestav správnou CSV export URL.
		return sprintf(
			'https://docs.google.com/spreadsheets/d/%s/pub?gid=%s&single=true&output=csv',
			$sheet_id,
			$gid
		);
	}

	private function make_source( string $source_type, string $file_path ): CsvSource|XmlSource {
		return match ( $source_type ) {
			'xml'   => new XmlSource( $file_path ),
			default => new CsvSource( $file_path ),
		};
	}

	// -------------------------------------------------------------------------
	// Upload adresář.

	private function ensure_upload_dir(): string {
		$dir = wp_upload_dir()['basedir'] . '/saf-imports';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
			// Zabrání přímému přístupu přes HTTP.
			file_put_contents( $dir . '/.htaccess', "Order allow,deny\nDeny from all\n" ); // phpcs:ignore
			file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' ); // phpcs:ignore
		}
		return $dir;
	}

	private function cleanup_temp_file( string $file_path ): void {
		$real = realpath( $file_path );
		if ( ! $real ) {
			return;
		}
		$upload_base = realpath( wp_upload_dir()['basedir'] );
		if ( $upload_base && str_starts_with( $real, $upload_base ) ) {
			wp_delete_file( $real );
		}
	}

	// -------------------------------------------------------------------------
	// Session management (WP Transients).

	private function new_session( array $data ): string {
		$id = bin2hex( random_bytes( 16 ) );
		set_transient( self::TRANSIENT_KEY . $id, $data, self::TRANSIENT_TTL );
		return $id;
	}

	private function load_session( string $id ): array|false {
		if ( ! $id ) {
			return false;
		}
		return get_transient( self::TRANSIENT_KEY . $id );
	}

	private function save_session( string $id, array $data ): void {
		set_transient( self::TRANSIENT_KEY . $id, $data, self::TRANSIENT_TTL );
	}

	private function delete_session( string $id ): void {
		delete_transient( self::TRANSIENT_KEY . $id );
	}

	// -------------------------------------------------------------------------
	// Přístupový bod pro view.

	public function get_view_data(): array {
		return $this->view_data;
	}
}
