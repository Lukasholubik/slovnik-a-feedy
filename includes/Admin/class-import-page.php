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
	private const TRANSIENT_TTL  = WEEK_IN_SECONDS; // 7 dní – session přežije přestávku
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
		// Akce s vlastním nonce musí být zkontrolovány PŘED obecným import nonce.
		$action = sanitize_key( $_POST['saf_action'] ?? '' );
		if ( $action === 'create_template' ) {
			$this->handle_create_template();
			return;
		}
		if ( $action === 'delete_preset' ) {
			$this->handle_delete_preset();
			return;
		}
		if ( $action === 'save_preset' ) {
			$this->handle_save_preset();
			return;
		}
		if ( $action === 'back_step1' ) {
			$this->handle_back_step1();
			return;
		}
		if ( $action === 'repeat_import' ) {
			$this->handle_repeat_import();
			return;
		}
		if ( $action === 'resume_session' ) {
			$this->handle_resume_session();
			return;
		}
		if ( $action === 'delete_session' ) {
			$this->handle_delete_session();
			return;
		}

		// Obecný import nonce.
		$step         = absint( $_POST['saf_step'] ?? 0 );
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

		// Načti preset pokud byl vybrán – doplní URL pokud je pole prázdné.
		$preset_id = sanitize_key( $_POST['preset_id'] ?? '' );
		$preset    = $preset_id ? ( Settings::get_import_presets()[ $preset_id ] ?? null ) : null;

		if ( $preset && empty( $_POST['gsheet_url'] ) && ! empty( $preset['source_url'] ) ) {
			// Uživatel nevložil novou URL → použij z presetu.
			$_POST['gsheet_url']   = $preset['source_url'];
			$_POST['source_type']  = 'gsheet';
			$source_type           = 'gsheet';
		}

		// Načti (starší) profil pokud byl vybrán.
		$profile_id = sanitize_key( $_POST['load_profile'] ?? '' );
		$profile    = $profile_id ? ( Settings::get_profiles()[ $profile_id ] ?? null ) : null;

		try {
			[ $file_path, $source_type ] = $this->resolve_source( $source_type );
			$source  = $this->make_source( $source_type, $file_path );
			$columns = $source->get_columns();

			if ( empty( $columns ) ) {
				throw new \RuntimeException( __( 'Soubor neobsahuje žádné sloupce. Zkontroluj formát.', 'slovnik-a-feedy' ) );
			}

			// Načti prvních 5 řádků pro preview.
			$preview_rows = [];
			$total_rows   = 0;
			foreach ( $source->get_rows() as $row ) {
				if ( count( $preview_rows ) < 5 ) {
					$preview_rows[] = $row;
				}
				$total_rows++;
			}

			// Auto-generuj makro jména z názvů sloupců (col → snake_case).
			$auto_macros  = self::generate_macro_names( $columns );
			$auto_mapping = $profile ? $profile['mapping'] : Mapper::auto_map( $columns );

			$session_id = $this->new_session( [
				'file_path'    => $file_path,
				'source_type'  => $source_type,
				'columns'      => $columns,
				'macro_names'  => $auto_macros,
				'mapping'      => $auto_mapping,
				'template'     => $profile ? $profile['template'] : '',
				'stream'       => $stream,
				'preview_rows' => $preview_rows,
				'total_rows'   => $total_rows,
				'source_url'   => esc_url_raw( wp_unslash( $_POST['gsheet_url'] ?? '' ) ),
			] );

			// Registruj relaci v seznamu aktivních importů.
			$file_name  = sanitize_text_field( wp_unslash( $_FILES['saf_file']['name'] ?? '' ) );
			$source_url = esc_url_raw( wp_unslash( $_POST['gsheet_url'] ?? '' ) );
			if ( ! $file_name ) {
				$file_name = $source_url ?: 'Neznámý zdroj';
			}
			ImportSessionRegistry::register( $session_id, [
				'last_step'   => 1,
				'stream_name' => $stream['name'] ?? '',
				'source_type' => $source_type,
				'file_name'   => $file_name,
				'source_url'  => $source_url,
				'total_rows'  => $total_rows,
				'macro_count' => count( $columns ),
			] );

			$this->view_data = array_merge( $this->view_data, [
				'step'         => 1,
				'session_id'   => $session_id,
				'columns'      => $columns,
				'macro_names'  => $auto_macros,
				'auto_mapping' => $auto_mapping,
				'fields'       => Mapper::FIELDS,
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

		// Ulož makro jména (col → macro_name nebo pole aliasů, sanitizováno).
		// Podpora: "kw, sug_url" → sloupec dostupný jako {{kw}} i {{sug_url}}.
		$raw_macros  = (array) ( $_POST['macro_names'] ?? [] );
		$macro_names = [];
		foreach ( $raw_macros as $col => $macro ) {
			$col   = sanitize_text_field( wp_unslash( $col ) );
			$raw   = sanitize_text_field( wp_unslash( $macro ) );
			if ( ! $col || ! $raw ) {
				continue;
			}
			// Rozdělí čárkou oddělené aliasy.
			$parts = array_values( array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) ) );
			$macro_names[ $col ] = count( $parts ) === 1 ? $parts[0] : $parts;
		}
		// Fallback: auto-generace pokud prázdné.
		if ( empty( $macro_names ) ) {
			$macro_names = self::generate_macro_names( $session['columns'] ?? [] );
		}

		// Sanitizuj mapování polí pluginu (title, slug, seo...).
		// Každý sloupec může mít pole polí: mapping[col][] = ['slug', 'seo_keyword'].
		$raw_mapping = (array) ( $_POST['mapping'] ?? [] );
		$mapping     = [];
		foreach ( $raw_mapping as $col => $raw_fields ) {
			$col        = sanitize_text_field( wp_unslash( $col ) );
			$raw_fields = is_array( $raw_fields ) ? $raw_fields : [ $raw_fields ];

			$valid = array_values( array_filter(
				array_map( 'sanitize_key', $raw_fields ),
				static fn( string $f ): bool => $f !== '' && isset( Mapper::FIELDS[ $f ] )
			) );

			if ( empty( $valid ) ) {
				continue;
			}
			// Jeden výsledek = string, více = pole (zpětná kompatibilita).
			$mapping[ $col ] = count( $valid ) === 1 ? $valid[0] : $valid;
		}

		$session['macro_names'] = $macro_names;
		$session['mapping']     = $mapping;
		$this->save_session( $session_id, $session );

		// Aktualizuj stav v registru.
		ImportSessionRegistry::update( $session_id, [
			'last_step'   => 2,
			'macro_count' => count( $macro_names ),
		] );

		// Připrav preview row s makro klíči (pro JS builder).
		$raw_preview   = $session['preview_rows'][0] ?? [];
		$macro_preview = self::apply_macro_names( $raw_preview, $macro_names );

		$this->view_data = array_merge( $this->view_data, [
			'step'          => 2,
			'session_id'    => $session_id,
			'macro_names'   => $macro_names,
			'macro_preview' => $macro_preview,
			'template'      => $session['template'] ?? '',
			'settings'      => Settings::get_all(),
		] );
	}

	/**
	 * Zopakuje import ze záznamu v historii.
	 * Pokud session stále žije → obnoví krok 1 (makra) k úpravám.
	 * Pokud expirovala → předvyplní krok 0 s URL a streamem.
	 */
	private function handle_repeat_import(): void {
		if ( ! isset( $_POST['saf_repeat_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saf_repeat_nonce'] ) ), 'saf_repeat_import' )
		) {
			$this->view_data['error'] = __( 'Neplatný token.', 'slovnik-a-feedy' );
			return;
		}

		$history_id = sanitize_key( $_POST['history_session_id'] ?? '' );
		$ses        = ImportSessionRegistry::get( $history_id );

		if ( ! $ses ) {
			$this->view_data['error'] = __( 'Záznam nenalezen.', 'slovnik-a-feedy' );
			return;
		}

		// Zkus obnovit živou session.
		$old_session = $this->load_session( $history_id );

		if ( $old_session ) {
			// Session stále žije – obnov krok 1 (makra) pro rychlou úpravu.
			ImportSessionRegistry::update( $history_id, [ 'status' => ImportSessionRegistry::STATUS_ACTIVE ] );
			$macro_preview = self::apply_macro_names(
				$old_session['preview_rows'][0] ?? [],
				$old_session['macro_names'] ?? []
			);
			$this->view_data = array_merge( $this->view_data, [
				'step'         => 1,
				'session_id'   => $history_id,
				'columns'      => $old_session['columns']      ?? [],
				'macro_names'  => $old_session['macro_names']  ?? [],
				'auto_mapping' => $old_session['mapping']      ?? [],
				'fields'       => Mapper::FIELDS,
				'stream'       => $old_session['stream']       ?? [],
				'preview_rows' => $old_session['preview_rows'] ?? [],
				'total_rows'   => $old_session['total_rows']   ?? 0,
				'notice'       => __( '↻ Opakování importu – uprav makra nebo pokračuj přímo na šablonu.', 'slovnik-a-feedy' ),
			] );
		} else {
			// Session expirovala → předvyplň krok 0.
			$this->view_data['repeat_url']         = $ses['source_url']  ?? '';
			$this->view_data['repeat_stream_name']  = $ses['stream_name'] ?? '';
			$this->view_data['notice']              = __( 'Relace vypršela (7 dní). URL je předvyplněna – klikni Nahrát a detekovat sloupce.', 'slovnik-a-feedy' );
		}
	}

	/** Obnoví relaci z historie – zobrazí poslední dostupný krok. */
	private function handle_resume_session(): void {
		if ( ! isset( $_POST['saf_resume_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saf_resume_nonce'] ) ), 'saf_resume' )
		) {
			$this->view_data['error'] = __( 'Neplatný token.', 'slovnik-a-feedy' );
			return;
		}

		$session_id = sanitize_key( $_POST['session_id'] ?? '' );
		$session    = $this->load_session( $session_id );
		$resume_to  = absint( $_POST['resume_to'] ?? 1 );

		if ( ! $session ) {
			ImportSessionRegistry::fail( $session_id, 'Relace vypršela (7 dní).' );
			$this->view_data['error'] = __( 'Relace vypršela (platí 7 dní). Nahraj soubor znovu.', 'slovnik-a-feedy' );
			return;
		}

		// Nastav stav v registru.
		ImportSessionRegistry::update( $session_id, [ 'status' => ImportSessionRegistry::STATUS_ACTIVE ] );

		if ( $resume_to <= 1 ) {
			// Zobraz krok 1 (makra).
			$this->view_data = array_merge( $this->view_data, [
				'step'         => 1,
				'session_id'   => $session_id,
				'columns'      => $session['columns']      ?? [],
				'macro_names'  => $session['macro_names']  ?? [],
				'auto_mapping' => $session['mapping']      ?? [],
				'fields'       => Mapper::FIELDS,
				'stream'       => $session['stream']       ?? [],
				'preview_rows' => $session['preview_rows'] ?? [],
				'total_rows'   => $session['total_rows']   ?? 0,
			] );
		} else {
			// Zobraz krok 2 (šablona).
			$macro_preview = self::apply_macro_names(
				$session['preview_rows'][0] ?? [],
				$session['macro_names'] ?? []
			);
			$this->view_data = array_merge( $this->view_data, [
				'step'          => 2,
				'session_id'    => $session_id,
				'macro_names'   => $session['macro_names']  ?? [],
				'macro_preview' => $macro_preview,
				'template'      => $session['template']     ?? '',
				'settings'      => Settings::get_all(),
			] );
		}
	}

	/** Smaže relaci z registru. */
	private function handle_delete_session(): void {
		if ( ! isset( $_POST['saf_del_ses_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saf_del_ses_nonce'] ) ), 'saf_del_session' )
		) {
			return;
		}
		$session_id = sanitize_key( $_POST['session_id'] ?? '' );
		ImportSessionRegistry::delete( $session_id );
		$this->delete_session( $session_id );
	}

	/** Vrátí uživatele na krok 1 (mapování maker) se zachovanou session. */
	private function handle_back_step1(): void {
		if ( ! isset( $_POST['saf_back_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saf_back_nonce'] ) ), 'saf_back_step1' )
		) {
			$this->view_data['error'] = __( 'Neplatný token.', 'slovnik-a-feedy' );
			return;
		}

		$session_id = sanitize_key( $_POST['session_id'] ?? '' );
		$session    = $this->load_session( $session_id );

		if ( ! $session ) {
			$this->view_data['error'] = __( 'Relace vypršela – nahraj soubor znovu.', 'slovnik-a-feedy' );
			return;
		}

		// Zobraz krok 1 znovu se zachovanými daty.
		$this->view_data = array_merge( $this->view_data, [
			'step'         => 1,
			'session_id'   => $session_id,
			'columns'      => $session['columns']      ?? [],
			'macro_names'  => $session['macro_names']  ?? [],
			'auto_mapping' => $session['mapping']      ?? [],
			'fields'       => Mapper::FIELDS,
			'stream'       => $session['stream']       ?? [],
			'preview_rows' => $session['preview_rows'] ?? [],
			'total_rows'   => $session['total_rows']   ?? 0,
		] );
	}

	/** Uložení import presetu. */
	private function handle_save_preset(): void {
		if ( ! isset( $_POST['saf_preset_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saf_preset_nonce'] ) ), 'saf_save_preset' )
			|| ! current_user_can( self::CAP )
		) {
			$this->view_data['error'] = __( 'Neplatný token.', 'slovnik-a-feedy' );
			return;
		}

		$name = sanitize_text_field( wp_unslash( $_POST['preset_name'] ?? '' ) );
		$data = $_POST['preset_data'] ?? [];

		$preset = [
			'name'        => $name,
			'stream_id'   => sanitize_key( $data['stream_id'] ?? '' ),
			'stream_name' => sanitize_text_field( wp_unslash( $data['stream_name'] ?? '' ) ),
			'template_id' => absint( $data['template_id'] ?? 0 ),
			'source_url'  => esc_url_raw( $data['source_url'] ?? '' ),
			'macro_names' => [],
			'mapping'     => [],
		];

		// Sanitizuj macro_names.
		foreach ( (array) ( $data['macro_names'] ?? [] ) as $col => $macro ) {
			$preset['macro_names'][ sanitize_text_field( wp_unslash( $col ) ) ] = sanitize_key( $macro );
		}
		foreach ( (array) ( $data['mapping'] ?? [] ) as $col => $field ) {
			$preset['mapping'][ sanitize_text_field( wp_unslash( $col ) ) ] = sanitize_key( $field );
		}

		Settings::save_import_preset( sanitize_key( uniqid( 'preset_', true ) ), $preset );
		$this->view_data['notice'] = __( 'Preset byl uložen. Najdeš ho při příštím importu.', 'slovnik-a-feedy' );
	}

	/** Smazání import presetu. */
	private function handle_delete_preset(): void {
		if ( ! isset( $_POST['saf_del_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saf_del_nonce'] ) ), 'saf_delete_preset' )
			|| ! current_user_can( self::CAP )
		) {
			$this->view_data['error'] = __( 'Neplatný token.', 'slovnik-a-feedy' );
			return;
		}
		Settings::delete_import_preset( sanitize_key( $_POST['preset_id'] ?? '' ) );
	}

	/** Vytvoření nové šablony + redirect do Gutenbergu. */
	private function handle_create_template(): void {
		if ( ! isset( $_POST['saf_tpl_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saf_tpl_nonce'] ) ), 'saf_create_template' )
		) {
			$this->view_data['error'] = __( 'Neplatný token.', 'slovnik-a-feedy' );
			return;
		}

		$title      = sanitize_text_field( wp_unslash( $_POST['template_title'] ?? 'Šablona importu' ) );
		$session_id = sanitize_key( $_POST['session_id'] ?? '' );
		$session    = $this->load_session( $session_id );

		$template_id = \SlovnikAFeedy\TemplateManager::create( $title );
		if ( ! $template_id ) {
			$this->view_data['error'] = __( 'Nepodařilo se vytvořit šablonu.', 'slovnik-a-feedy' );
			return;
		}

		// Ulož makra jako post meta – budou viditelná v Gutenberg sidebaru.
		if ( $session && ! empty( $session['macro_names'] ) ) {
			\SlovnikAFeedy\TemplateManager::save_macro_names( $template_id, $session['macro_names'] );
		}

		// Zapamatuj si template_id pro tento session.
		update_option( 'saf_last_template_id', $template_id );

		// Přidej session_id do URL editoru – sidebar ho použije pro načtení maker.
		$edit_url = add_query_arg(
			'saf_session', $session_id,
			\SlovnikAFeedy\TemplateManager::get_edit_url( $template_id )
		);

		wp_safe_redirect( $edit_url );
		exit;
	}

	/** Krok 2 – dry-run nebo spuštění importu. */
	private function handle_step2(): void {
		$session_id = sanitize_key( $_POST['session_id'] ?? '' );
		$session    = $this->load_session( $session_id );
		if ( ! $session ) {
			$this->view_data['error'] = __( 'Relace vypršela. Začni import znovu.', 'slovnik-a-feedy' );
			return;
		}

		// Načti šablonu z Gutenberg postu.
		$template_post_id = absint( $_POST['template_id'] ?? 0 );
		if ( $template_post_id ) {
			$template = \SlovnikAFeedy\TemplateManager::get_content( $template_post_id );
			update_option( 'saf_last_template_id', $template_post_id );
			// Aktualizuj makra v post meta (pro případ změny mapování).
			if ( ! empty( $session['macro_names'] ) ) {
				\SlovnikAFeedy\TemplateManager::save_macro_names( $template_post_id, $session['macro_names'] );
			}
		} else {
			$template = '';
		}

		if ( ! $template ) {
			$this->view_data['error'] = __( 'Vyber šablonu a ujisti se, že v ní je obsah.', 'slovnik-a-feedy' );
			return;
		}
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

		// Přelož klíče field mappingu: originální název sloupce → makro jméno.
		// (Řádky jsou překlíčovány na makra, ale mapping měl původní názvy sloupců.)
		// Př.: {'Titulek' => 'title'} → {'h1_titulek' => 'title'}
		$macro_names        = $session['macro_names'] ?? [];
		$original_mapping   = $session['mapping']     ?? [];
		$translated_mapping = [];
		foreach ( $original_mapping as $orig_col => $field ) {
			$macro_val = $macro_names[ $orig_col ] ?? $orig_col;
			// Pokud je macro_val pole (multi-makro aliasy), použij první alias jako klíč.
			// Pole jako PHP klíč → "Array to string conversion" crash.
			$keys = is_array( $macro_val ) ? $macro_val : [ $macro_val ];
			foreach ( $keys as $macro_key ) {
				if ( $macro_key ) {
					$translated_mapping[ (string) $macro_key ] = $field;
				}
			}
		}

		$config = [
			'mapping'         => $translated_mapping,
			'macro_names'     => $macro_names,
			'template'        => $template,
			'stream'          => $session['stream'] ?? [],
			'default_status'  => $default_status,
			'dry_run'         => $is_dry_run,
			'force_overwrite' => $force_overwrite,
		];

		// Zajisti dostupnost zdrojového souboru.
		$file_path   = $session['file_path'] ?? '';
		$source_type = $session['source_type'] ?? 'csv';

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			// Temp soubor expiroval nebo byl smazán.
			// Poznámka: GSheets import ukládá source_type='csv' protože data
			// stahuje jako CSV. Proto nekontrolujeme source_type ale source_url.
			$source_url = $session['source_url'] ?? '';

			if ( ! empty( $source_url ) ) {
				// Je dostupná URL – stáhni znovu (funguje pro GSheets i jakoukoliv CSV URL).
				try {
					$upload_dir = $this->ensure_upload_dir();
					$file_path  = $this->download_url( $source_url, $upload_dir );
					// Ulož novou cestu do session.
					$session['file_path'] = $file_path;
					$this->save_session( $session_id, $session );
				} catch ( \Throwable $e ) {
					$this->view_data['error'] = sprintf(
						__( 'Soubor expiroval a automatické stažení ze zdroje selhalo: %s', 'slovnik-a-feedy' ),
						$e->getMessage()
					);
					return;
				}
			} else {
				// CSV/XML upload – soubor nelze obnovit automaticky.
				$this->view_data['error'] = __( 'Zdrojový soubor importu expiroval a byl smazán (temp soubory jsou dočasné). Vrať se krok zpět – nahraj soubor znovu nebo použij Google Sheets URL která se obnoví automaticky.', 'slovnik-a-feedy' );
				return;
			}
		}

		// Načti všechny řádky a překlíčuj na makro jména.
		$macro_names = $session['macro_names'] ?? [];
		$source      = $this->make_source( $source_type, $file_path );
		$rows        = [];
		foreach ( $source->get_rows() as $row ) {
			// Překlíčuj řádek: originální sloupce → makro jména.
			$rows[] = $macro_names
				? self::apply_macro_names( $row, $macro_names )
				: $row;
		}

		// Pokud nejsou žádné řádky, vrať chybu.
		if ( empty( $rows ) ) {
			$this->view_data['error'] = __( 'Zdrojový soubor neobsahuje žádné řádky nebo je poškozený. Zkontroluj URL/soubor a zkus znovu.', 'slovnik-a-feedy' );
			return;
		}

		$result = BatchRunner::start( $rows, $config );

		// Zaznamenej výsledek v registru.
		if ( ! $is_dry_run ) {
			$stats = $result['stats'] ?? [];
			ImportSessionRegistry::complete(
				$session_id,
				(int) ( $stats['created'] ?? 0 ),
				(int) ( $stats['updated'] ?? 0 ),
				(int) ( $stats['skipped'] ?? 0 )
			);
		}

		// Vyčisti temp soubor pokud import dokončen (ne async).
		if ( $result['mode'] === 'sync' && ! $is_dry_run ) {
			$this->cleanup_temp_file( $session['file_path'] );
		}

		$this->view_data = array_merge( $this->view_data, [
			'step'       => 3,
			'result'     => $result,
			'is_dry_run' => $is_dry_run,
			'total_rows' => count( $rows ),
			'stream_cpt' => $session['stream']['cpt'] ?? 'glossary',
			'session_for_preset' => [
				'stream_id'   => $session['stream']['id']   ?? '',
				'stream_name' => $session['stream']['name'] ?? '',
				'macro_names' => $session['macro_names'] ?? [],
				'template_id' => $template_post_id,
				'mapping'     => $session['mapping'] ?? [],
				'source_url'  => $session['source_url'] ?? '',
			],
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
		// SSRF ochrana – parsuj URL bezpečně bez userinfo bypass triku.
		// 'https://docs.google.com@evil.com/' → host = evil.com (správně odmítne).
		$parsed = wp_parse_url( $url );
		$host   = strtolower( $parsed['host'] ?? '' );
		$scheme = strtolower( $parsed['scheme'] ?? '' );

		// Povolená schémata.
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			throw new \RuntimeException( __( 'Povoleny jsou pouze http/https URL.', 'slovnik-a-feedy' ) );
		}

		// Whitelist hosts.
		$allowed_hosts = [ 'docs.google.com', 'spreadsheets.google.com', 'drive.google.com' ];
		if ( ! in_array( $host, $allowed_hosts, true ) ) {
			throw new \RuntimeException(
				__( 'Povoleny jsou pouze Google Sheets URL (docs.google.com).', 'slovnik-a-feedy' )
			);
		}

		// Auto-konverze Google Sheets edit/view URL → CSV export URL.
		$url = $this->normalize_gsheet_url( $url );

		// Zakázat automatické sledování přesměrování – každý Location header
		// ověříme ručně (ochrana před SSRF bypass přes redirect na non-whitelisted host).
		$response = wp_remote_get( $url, [ 'timeout' => 30, 'sslverify' => true, 'redirection' => 0 ] );
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				__( 'Nepodařilo se stáhnout Google Sheet: ', 'slovnik-a-feedy' ) . $response->get_error_message()
			);
		}

		// Ruční ošetření 3xx přesměrování s validací cílového hosta.
		$http_code = (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $http_code, [ 301, 302, 303, 307, 308 ], true ) ) {
			$location    = wp_remote_retrieve_header( $response, 'location' );
			$loc_parsed  = wp_parse_url( $location );
			$loc_host    = strtolower( $loc_parsed['host'] ?? '' );
			$loc_scheme  = strtolower( $loc_parsed['scheme'] ?? '' );

			if ( ! in_array( $loc_scheme, [ 'http', 'https' ], true ) || ! in_array( $loc_host, $allowed_hosts, true ) ) {
				throw new \RuntimeException(
					__( 'Google Sheet přesměroval na nepovolený server. Import přerušen.', 'slovnik-a-feedy' )
				);
			}

			$response = wp_remote_get( $location, [ 'timeout' => 30, 'sslverify' => true, 'redirection' => 0 ] );
			if ( is_wp_error( $response ) ) {
				throw new \RuntimeException(
					__( 'Nepodařilo se stáhnout přesměrovaný Google Sheet: ', 'slovnik-a-feedy' ) . $response->get_error_message()
				);
			}
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

	// -------------------------------------------------------------------------
	// Statické helper metody.

	/**
	 * Vygeneruje makro jméno z názvu sloupce (snake_case, ASCII, max 40 znaků).
	 * "Short definice" → "short_definice"
	 */
	public static function column_to_macro( string $col ): string {
		$name = mb_strtolower( trim( $col ) );

		// Odstranění diakritiky – vlastní tabulka (iconv//TRANSLIT je na Windows nespolehlivý).
		$diacritics = [
			'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n',
			'ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
			'ä'=>'a','ö'=>'o','ü'=>'u','ß'=>'ss',
		];
		$name = strtr( $name, $diacritics );

		// Pouze a-z, 0-9 → ostatní na podtržítko.
		$name = (string) preg_replace( '/[^a-z0-9]+/', '_', $name );
		$name = trim( $name, '_' );

		return substr( $name ?: 'col', 0, 40 );
	}

	/**
	 * Vygeneruje makro jména pro všechny sloupce (zajistí unikátnost).
	 *
	 * @param  list<string>          $columns
	 * @return array<string, string> col → macro_name
	 */
	public static function generate_macro_names( array $columns ): array {
		$macros = [];
		$used   = [];
		foreach ( $columns as $col ) {
			$base   = self::column_to_macro( $col );
			$macro  = $base;
			$suffix = 2;
			while ( in_array( $macro, $used, true ) ) {
				$macro = $base . '_' . $suffix++;
			}
			$macros[ $col ] = $macro;
			$used[]         = $macro;
		}
		return $macros;
	}

	/**
	 * Překlíčuje řádek z originálních názvů sloupců na makro jména.
	 *
	 * @param  array<string, string> $row         Originální řádek (col → value)
	 * @param  array<string, string> $macro_names col → macro_name
	 * @return array<string, string> macro_name → value
	 */
	public static function apply_macro_names( array $row, array $macro_names ): array {
		$result = [];
		foreach ( $macro_names as $col => $macro ) {
			$val = $row[ $col ] ?? '';
			if ( is_array( $macro ) ) {
				// Aliasy – stejná hodnota pod více makro jmény.
				foreach ( $macro as $m ) {
					$result[ $m ] = $val;
				}
			} else {
				$result[ $macro ] = $val;
			}
		}
		return $result;
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
