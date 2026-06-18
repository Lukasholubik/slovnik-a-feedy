<?php
/**
 * Admin view – Import wizard.
 *
 * @package SlovnikAFeedy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SlovnikAFeedy\Importer\{Mapper, TemplateEngine};
use SlovnikAFeedy\Admin\Settings;

$step         = $view_data['step']         ?? 0;
$session_id   = $view_data['session_id']   ?? '';
$error        = $view_data['error']        ?? '';
$profiles     = $view_data['profiles']     ?? [];
$preview_rows = $view_data['preview_rows'] ?? [];
$total_rows   = $view_data['total_rows']   ?? 0;

$step_labels = [
	0 => __( 'Zdroj dat', 'slovnik-a-feedy' ),
	1 => __( 'Mapování sloupců', 'slovnik-a-feedy' ),
	2 => __( 'Šablona a volby', 'slovnik-a-feedy' ),
	3 => __( 'Výsledek', 'slovnik-a-feedy' ),
];
?>
<div class="wrap saf-wrap">

	<div class="saf-header">
		<div class="saf-header__brand">
			<span class="saf-logo">Grou<span>.cz</span></span>
			<span class="saf-header__sep">|</span>
			<h1 class="saf-header__title"><?php esc_html_e( 'Import dat', 'slovnik-a-feedy' ); ?></h1>
		</div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy' ) ); ?>" class="button">
			&larr; <?php esc_html_e( 'Přehled', 'slovnik-a-feedy' ); ?>
		</a>
	</div>

	<!-- Průvodce kroky -->
	<div class="saf-wizard-steps">
		<?php foreach ( $step_labels as $n => $label ) : ?>
			<div class="saf-wizard-step <?php echo $n === $step ? 'saf-wizard-step--active' : ( $n < $step ? 'saf-wizard-step--done' : '' ); ?>">
				<span class="saf-wizard-step__num"><?php echo $n < $step ? '✓' : esc_html( $n + 1 ); ?></span>
				<span class="saf-wizard-step__label"><?php echo esc_html( $label ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $error ) : ?>
	<div class="notice notice-error">
		<p><strong><?php echo esc_html( $error ); ?></strong></p>
		<?php if ( str_contains( $error, 'HTML' ) || str_contains( $error, 'CSV' ) ) : ?>
		<p><?php esc_html_e( 'Jak získat správnou URL z Google Sheets:', 'slovnik-a-feedy' ); ?>
			<code>Soubor → Sdílet → Publikovat na web → vybrat list → CSV → Publikovat → zkopírovat URL</code>
		</p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php
	// ── KROK 0 – Zdroj dat ────────────────────────────────────────────────────
	if ( $step === 0 ) :
		$stream_options = \SlovnikAFeedy\StreamManager::get_options();
	?>
	<div class="saf-panel">
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'saf_import_step_0', 'saf_import_nonce' ); ?>
			<input type="hidden" name="saf_step" value="0">

			<!-- Výběr streamu (jen pokud je víc než jeden) -->
			<?php if ( count( $stream_options ) > 1 ) : ?>
			<div class="saf-form-row">
				<label><strong><?php esc_html_e( 'Importovat do streamu:', 'slovnik-a-feedy' ); ?></strong></label>
				<select name="stream_id" class="regular-text">
					<?php foreach ( $stream_options as $sid => $sname ) : ?>
					<option value="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $sname ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php else : ?>
			<input type="hidden" name="stream_id" value="<?php echo esc_attr( array_key_first( $stream_options ) ); ?>">
			<?php endif; ?>

			<!-- Typ zdroje -->
			<div class="saf-form-row">
				<label><strong><?php esc_html_e( 'Typ zdroje:', 'slovnik-a-feedy' ); ?></strong></label>
				<div class="saf-source-tabs" id="saf-source-tabs">
					<label class="saf-source-tab saf-source-tab--active" data-tab="csv">
						<input type="radio" name="source_type" value="csv" checked>
						<span class="dashicons dashicons-media-spreadsheet"></span>
						<?php esc_html_e( 'CSV soubor', 'slovnik-a-feedy' ); ?>
					</label>
					<label class="saf-source-tab" data-tab="xml">
						<input type="radio" name="source_type" value="xml">
						<span class="dashicons dashicons-media-code"></span>
						<?php esc_html_e( 'XML soubor', 'slovnik-a-feedy' ); ?>
					</label>
					<label class="saf-source-tab" data-tab="gsheet">
						<input type="radio" name="source_type" value="gsheet">
						<span class="dashicons dashicons-table-col-before"></span>
						<?php esc_html_e( 'Google Sheets', 'slovnik-a-feedy' ); ?>
					</label>
				</div>
			</div>

			<!-- Vstup pro soubor -->
			<div id="saf-input-file" class="saf-form-row">
				<label for="saf_file"><strong><?php esc_html_e( 'Soubor (CSV / XML):', 'slovnik-a-feedy' ); ?></strong></label>
				<input type="file" id="saf_file" name="saf_file" accept=".csv,.xml,.tsv">
				<p class="description"><?php esc_html_e( 'Max. 10 MB. První řádek musí být hlavička se jmény sloupců.', 'slovnik-a-feedy' ); ?></p>
			</div>

			<!-- Vstup pro Google Sheets URL -->
			<div id="saf-input-gsheet" class="saf-form-row" style="display:none">
				<label for="saf_gsheet_url"><strong><?php esc_html_e( 'Google Sheets URL:', 'slovnik-a-feedy' ); ?></strong></label>
				<input type="url" id="saf_gsheet_url" name="gsheet_url" class="large-text"
					placeholder="https://docs.google.com/spreadsheets/d/...">

				<!-- Nápověda - jak získat URL -->
				<details class="saf-gsheet-help">
					<summary><?php esc_html_e( '📋 Jak získat správnou URL z Google Sheets?', 'slovnik-a-feedy' ); ?></summary>
					<div class="saf-gsheet-steps">
						<p><?php esc_html_e( 'Můžeš vložit libovolnou Google Sheets URL – plugin ji automaticky převede na CSV formát.', 'slovnik-a-feedy' ); ?></p>
						<ol>
							<li><?php esc_html_e( 'Otevři tabulku v Google Sheets', 'slovnik-a-feedy' ); ?></li>
							<li><?php esc_html_e( 'Zkopíruj URL z adresního řádku (např. .../spreadsheets/d/XXX/edit)', 'slovnik-a-feedy' ); ?></li>
							<li><?php esc_html_e( 'Vlož sem – plugin ji automaticky převede na CSV export', 'slovnik-a-feedy' ); ?></li>
						</ol>
						<p class="description">
							<?php esc_html_e( 'Nebo: Soubor → Sdílet → Publikovat na web → vybrat list → CSV → Publikovat → zkopírovat URL.', 'slovnik-a-feedy' ); ?>
						</p>
						<p class="description">
							⚠️ <?php esc_html_e( 'Tabulka musí být sdílená (alespoň „Kdokoli s odkazem může zobrazit") nebo publikovaná.', 'slovnik-a-feedy' ); ?>
						</p>
					</div>
				</details>
			</div>

			<!-- Profily -->
			<?php if ( $profiles ) : ?>
			<div class="saf-form-row">
				<label for="saf-load-profile"><strong><?php esc_html_e( 'Načíst uložený profil:', 'slovnik-a-feedy' ); ?></strong></label>
				<select id="saf-load-profile" name="load_profile">
					<option value=""><?php esc_html_e( '— nevybráno —', 'slovnik-a-feedy' ); ?></option>
					<?php foreach ( $profiles as $pid => $profile ) : ?>
					<option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $profile['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

			<div class="saf-form-row">
				<button type="submit" class="button button-primary button-large">
					<?php esc_html_e( 'Nahrát a detekovat sloupce →', 'slovnik-a-feedy' ); ?>
				</button>
			</div>
		</form>
	</div>

	<script>
	(function () {
		var tabs    = document.querySelectorAll('.saf-source-tab');
		var fileDiv = document.getElementById('saf-input-file');
		var gDiv    = document.getElementById('saf-input-gsheet');

		function switchTab(val) {
			tabs.forEach(function (t) { t.classList.toggle('saf-source-tab--active', t.dataset.tab === val); });
			fileDiv.style.display = val === 'gsheet' ? 'none' : '';
			gDiv.style.display    = val === 'gsheet' ? ''     : 'none';
		}

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () { switchTab(tab.dataset.tab); });
		});
	}());
	</script>
	<!-- JS pro mapování (krok 1) dostupný globálně -->
	<script>
	function safUpdateColState(sel) {
		var th = sel.closest('th');
		if (!th) return;
		var hasMapped  = th.querySelector('.saf-col-select')   && th.querySelector('.saf-col-select').value;
		var hasBlock   = th.querySelector('.saf-block-select') && th.querySelector('.saf-block-select').value;
		th.classList.toggle('saf-col--mapped', !!hasMapped);
		th.classList.toggle('saf-col--has-block', !!hasBlock);
		th.classList.toggle('saf-col--unmapped', !hasMapped && !hasBlock);
	}
	</script>

	<?php
	// ── KROK 1 – Pojmenování maker ────────────────────────────────────────────
	elseif ( $step === 1 ) :
		$columns      = $view_data['columns']      ?? [];
		$macro_names  = $view_data['macro_names']   ?? [];
		$auto_mapping = $view_data['auto_mapping']  ?? [];
		$fields       = $view_data['fields']        ?? Mapper::FIELDS;
		$preview_rows = $view_data['preview_rows']  ?? [];
	?>
	<div class="saf-panel">
		<h2 class="saf-panel__title"><?php esc_html_e( 'Pojmenování maker', 'slovnik-a-feedy' ); ?></h2>
		<p class="saf-panel__desc">
			<?php printf( esc_html__( 'Detekováno %d sloupců, %d řádků. Každý sloupec dostane makro – použiješ ho v šabloně jako {{makro}}.', 'slovnik-a-feedy' ), count( $columns ), esc_html( $total_rows ) ); ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'saf_import_step_1', 'saf_import_nonce' ); ?>
			<input type="hidden" name="saf_step" value="1">
			<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>">

			<table class="wp-list-table widefat striped saf-macro-table">
				<thead>
					<tr>
						<th style="width:35%"><?php esc_html_e( 'Sloupec v souboru', 'slovnik-a-feedy' ); ?></th>
						<th style="width:25%"><?php esc_html_e( 'Makro (použij v šabloně)', 'slovnik-a-feedy' ); ?></th>
						<th style="width:30%"><?php esc_html_e( 'Pole pluginu (volitelné)', 'slovnik-a-feedy' ); ?></th>
						<th><?php esc_html_e( 'Náhled hodnoty', 'slovnik-a-feedy' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $columns as $i => $col ) :
					$macro   = $macro_names[ $col ]   ?? '';
					$mapped  = $auto_mapping[ $col ]   ?? '';
					$preview = $preview_rows[0][ $col ] ?? '';
					$row_class = $i % 2 === 0 ? '' : 'alternate';
				?>
				<tr class="<?php echo $row_class; ?>">
					<td>
						<strong><?php echo esc_html( $col ); ?></strong>
					</td>
					<td>
						<div class="saf-macro-input-wrap">
							<span class="saf-macro-brace">{{</span>
							<input type="text"
								name="macro_names[<?php echo esc_attr( $col ); ?>]"
								value="<?php echo esc_attr( $macro ); ?>"
								class="saf-macro-input"
								pattern="[a-z0-9_]+"
								title="<?php esc_attr_e( 'Pouze malá písmena, čísla a podtržítko', 'slovnik-a-feedy' ); ?>"
								required>
							<span class="saf-macro-brace">}}</span>
						</div>
					</td>
					<td>
						<select name="mapping[<?php echo esc_attr( $col ); ?>]" class="saf-field-select">
							<option value=""><?php esc_html_e( '— jen obsah —', 'slovnik-a-feedy' ); ?></option>
							<?php foreach ( $fields as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"
								<?php selected( $mapped, $slug ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</td>
					<td class="saf-preview-val">
						<?php echo esc_html( mb_strimwidth( $preview, 0, 50, '…' ) ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div style="margin-top:16px;display:flex;gap:12px;align-items:center">
				<button type="submit" class="button button-primary button-large">
					<?php esc_html_e( 'Pokračovat na template builder →', 'slovnik-a-feedy' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-import' ) ); ?>" class="button">
					<?php esc_html_e( '← Zpět', 'slovnik-a-feedy' ); ?>
				</a>
				<span class="description">
					<?php esc_html_e( 'Pole pluginu vyplň jen pro strukturovaná data (titulek stránky, slug, SEO). Vše ostatní zůstane jako makro.', 'slovnik-a-feedy' ); ?>
				</span>
			</div>
		</form>
	</div>

	<?php
	// ── KROK 2 – Vizuální template builder ───────────────────────────────────
	elseif ( $step === 2 ) :
		$macro_names   = $view_data['macro_names']   ?? [];
		$macro_preview = $view_data['macro_preview'] ?? [];
		$template      = $view_data['template']      ?? '';
		$settings      = $view_data['settings']      ?? [];

		// Předej preview data do JS.
		wp_localize_script( 'saf-template-builder', 'safPreviewRow', $macro_preview );

		$block_btns = [
			'heading-2'    => [ 'label' => 'Nadpis H2',         'color' => '#1a1a2e' ],
			'heading-3'    => [ 'label' => 'Nadpis H3',         'color' => '#1a1a2e' ],
			'heading-4'    => [ 'label' => 'Nadpis H4',         'color' => '#1a1a2e' ],
			'paragraph'    => [ 'label' => 'Odstavec',          'color' => '#0073aa' ],
			'quote'        => [ 'label' => 'Citace',            'color' => '#0073aa' ],
			'list'         => [ 'label' => 'Odrážky (• seznam)','color' => '#2d7738' ],
			'list-num'     => [ 'label' => 'Číslování (1. 2. .)','color' => '#2d7738' ],
			'separator'    => [ 'label' => 'Oddělovač ——',      'color' => '#888' ],
			'preformatted' => [ 'label' => 'Kód / PRE',         'color' => '#888' ],
		];
	?>

	<div class="saf-builder-layout">

		<!-- ── Levý panel: makra + bloky ─────────────────────────── -->
		<div class="saf-builder-sidebar">

			<div class="saf-builder-section">
				<h3 class="saf-builder-section__title">
					1️⃣ <?php esc_html_e( 'Klikni na makro', 'slovnik-a-feedy' ); ?>
				</h3>
				<p class="saf-builder-section__hint">
					<?php esc_html_e( 'Vyber sloupec který chceš vložit do obsahu.', 'slovnik-a-feedy' ); ?>
				</p>
				<div class="saf-macro-chips">
					<?php foreach ( $macro_names as $col => $macro ) : ?>
					<button type="button"
						class="saf-macro-chip"
						data-macro="<?php echo esc_attr( $macro ); ?>"
						title="<?php echo esc_attr( $col ); ?>">
						<span class="saf-macro-chip__brace">{{</span><?php echo esc_html( $macro ); ?><span class="saf-macro-chip__brace">}}</span>
						<?php if ( $col !== $macro ) : ?>
						<small class="saf-macro-chip__orig"><?php echo esc_html( mb_strimwidth( $col, 0, 18, '…' ) ); ?></small>
						<?php endif; ?>
					</button>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="saf-builder-section">
				<h3 class="saf-builder-section__title">
					2️⃣ <?php esc_html_e( 'Zvol typ bloku', 'slovnik-a-feedy' ); ?>
				</h3>
				<p class="saf-builder-section__hint" id="saf-builder-info">
					<?php esc_html_e( 'Nejdřív klikni na makro výše, pak na typ bloku.', 'slovnik-a-feedy' ); ?>
				</p>
				<div class="saf-block-palette">
					<?php foreach ( $block_btns as $block_key => $btn ) : ?>
					<button type="button"
						class="saf-block-btn"
						data-block="<?php echo esc_attr( $block_key ); ?>"
						data-label="<?php echo esc_attr( $btn['label'] ); ?>"
						style="border-left-color:<?php echo esc_attr( $btn['color'] ); ?>"
						disabled>
						<?php echo esc_html( $btn['label'] ); ?>
					</button>
					<?php endforeach; ?>
				</div>

				<div class="saf-builder-divider"></div>
				<p class="saf-builder-section__hint"><?php esc_html_e( 'Nebo vlož jen makro bez bloku:', 'slovnik-a-feedy' ); ?></p>
				<button type="button" class="saf-insert-raw button" disabled>
					<?php esc_html_e( '+ Vložit {{makro}} na pozici kurzoru', 'slovnik-a-feedy' ); ?>
				</button>
			</div>

		</div><!-- /.saf-builder-sidebar -->

		<!-- ── Střed: template editor ────────────────────────────── -->
		<div class="saf-builder-editor">

			<form method="post" id="saf-import-form">
				<?php wp_nonce_field( 'saf_import_step_2', 'saf_import_nonce' ); ?>
				<input type="hidden" name="saf_step" value="2">
				<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>">

				<div class="saf-editor-toolbar">
					<h3 class="saf-builder-section__title" style="margin:0">
						3️⃣ <?php esc_html_e( 'Šablona obsahu', 'slovnik-a-feedy' ); ?>
					</h3>
					<div style="display:flex;gap:6px">
						<button type="button" id="saf-clear-template" class="button button-small">
							<?php esc_html_e( '✕ Smazat vše', 'slovnik-a-feedy' ); ?>
						</button>
					</div>
				</div>

				<textarea name="template" id="saf-template" rows="18" class="large-text code saf-template-area"
					placeholder="<?php esc_attr_e( 'Klikni na makro vlevo + typ bloku → blok se přidá sem automaticky...', 'slovnik-a-feedy' ); ?>"
				><?php echo esc_textarea( $template ); ?></textarea>

				<div class="saf-editor-options">
					<label>
						<?php esc_html_e( 'Status:', 'slovnik-a-feedy' ); ?>
						<select name="default_status">
							<option value="publish" <?php selected( $settings['default_status'] ?? 'publish', 'publish' ); ?>><?php esc_html_e( 'Publikováno', 'slovnik-a-feedy' ); ?></option>
							<option value="draft"   <?php selected( $settings['default_status'] ?? 'publish', 'draft' ); ?>><?php esc_html_e( 'Koncept', 'slovnik-a-feedy' ); ?></option>
						</select>
					</label>
					<label>
						<input type="checkbox" name="force_overwrite" value="1">
						<?php esc_html_e( 'Přepsat SEO', 'slovnik-a-feedy' ); ?>
					</label>
				</div>

				<div class="saf-import-actions">
					<button type="submit" name="dry_run" value="1" class="button button-large">
						<?php esc_html_e( '👁 Dry-run', 'slovnik-a-feedy' ); ?>
					</button>
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( '▶ Spustit import', 'slovnik-a-feedy' ); ?>
					</button>
				</div>
			</form>

		</div><!-- /.saf-builder-editor -->

		<!-- ── Pravý panel: live preview ─────────────────────────── -->
		<div class="saf-builder-preview">
			<h3 class="saf-builder-section__title">
				<?php esc_html_e( 'Náhled (1. řádek)', 'slovnik-a-feedy' ); ?>
			</h3>
			<p class="saf-builder-section__hint">
				<?php esc_html_e( 'Aktualizuje se při psaní.', 'slovnik-a-feedy' ); ?>
			</p>
			<div id="saf-preview-rendered" class="saf-live-preview"></div>

			<?php if ( $macro_preview ) : ?>
			<details class="saf-macro-values" style="margin-top:16px">
				<summary style="font-size:12px;color:#888;cursor:pointer">
					<?php esc_html_e( 'Hodnoty z 1. řádku', 'slovnik-a-feedy' ); ?>
				</summary>
				<table class="widefat" style="margin-top:8px;font-size:12px">
					<tbody>
					<?php foreach ( $macro_preview as $macro => $val ) : ?>
						<?php if ( trim( $val ) === '' ) continue; ?>
						<tr>
							<td style="width:40%;color:#0073aa"><code>{{<?php echo esc_html( $macro ); ?>}}</code></td>
							<td><?php echo esc_html( mb_strimwidth( $val, 0, 60, '…' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</details>
			<?php endif; ?>
		</div><!-- /.saf-builder-preview -->

	</div><!-- /.saf-builder-layout -->

	<?php
	// ── KROK 3 – Výsledky ─────────────────────────────────────────────────────
	elseif ( $step === 3 ) :
		$result     = $view_data['result']    ?? [];
		$is_dry_run = $view_data['is_dry_run'] ?? false;
		$total_rows_result = $view_data['total_rows'] ?? 0;
		$stats      = $result['stats']        ?? null;
		$batch_id   = $result['batch_id']     ?? null;
	?>
	<div class="saf-panel">
		<?php if ( $is_dry_run ) : ?>
		<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'DRY-RUN – žádná data nebyla zapsána do databáze.', 'slovnik-a-feedy' ); ?></strong></p></div>
		<?php endif; ?>

		<?php if ( $result['mode'] === 'sync' && $stats ) : ?>
		<h2 class="saf-panel__title"><?php esc_html_e( 'Import dokončen', 'slovnik-a-feedy' ); ?></h2>
		<div class="saf-result-stats">
			<div class="saf-result-stat saf-result-stat--ok">
				<strong><?php echo esc_html( $stats['created'] ); ?></strong>
				<span><?php esc_html_e( 'Vytvořeno', 'slovnik-a-feedy' ); ?></span>
			</div>
			<div class="saf-result-stat saf-result-stat--update">
				<strong><?php echo esc_html( $stats['updated'] ); ?></strong>
				<span><?php esc_html_e( 'Aktualizováno', 'slovnik-a-feedy' ); ?></span>
			</div>
			<div class="saf-result-stat saf-result-stat--skip">
				<strong><?php echo esc_html( $stats['skipped'] ); ?></strong>
				<span><?php esc_html_e( 'Přeskočeno', 'slovnik-a-feedy' ); ?></span>
			</div>
			<div class="saf-result-stat">
				<strong><?php echo esc_html( $total_rows_result ); ?></strong>
				<span><?php esc_html_e( 'Celkem řádků', 'slovnik-a-feedy' ); ?></span>
			</div>
		</div>
		<?php elseif ( isset( $result['mode'] ) && $result['mode'] === 'async' && $batch_id ) : ?>
		<h2 class="saf-panel__title"><?php esc_html_e( 'Import běží na pozadí', 'slovnik-a-feedy' ); ?></h2>
		<p><?php printf( esc_html__( 'Zpracovává se %d řádků přes WP-Cron.', 'slovnik-a-feedy' ), esc_html( $total_rows_result ) ); ?></p>
		<p><code>Batch ID: <?php echo esc_html( $batch_id ); ?></code></p>
		<?php endif; ?>

		<div class="saf-result-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-import' ) ); ?>" class="button button-primary">
				<?php esc_html_e( '+ Nový import', 'slovnik-a-feedy' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=glossary' ) ); ?>" class="button">
				<?php esc_html_e( 'Zobrazit pojmy', 'slovnik-a-feedy' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-logy&context=import' ) ); ?>" class="button">
				<?php esc_html_e( 'Zobrazit logy', 'slovnik-a-feedy' ); ?>
			</a>
		</div>
	</div>

	<?php endif; ?>

</div><!-- /.wrap -->
