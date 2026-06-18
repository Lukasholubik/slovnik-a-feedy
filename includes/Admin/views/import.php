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

	<?php
	// ── KROK 1 – Mapování sloupců (spreadsheet UI) ────────────────────────────
	elseif ( $step === 1 ) :
		$columns      = $view_data['columns']      ?? [];
		$auto_mapping = $view_data['auto_mapping']  ?? [];
		$fields       = $view_data['fields']        ?? Mapper::FIELDS;
	?>
	<div class="saf-panel">
		<form method="post">
			<?php wp_nonce_field( 'saf_import_step_1', 'saf_import_nonce' ); ?>
			<input type="hidden" name="saf_step" value="1">
			<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>">

			<div class="saf-mapping-header">
				<div>
					<h2 class="saf-panel__title" style="margin-bottom:4px"><?php esc_html_e( 'Mapování sloupců', 'slovnik-a-feedy' ); ?></h2>
					<p class="description">
						<?php
						printf(
							esc_html__( 'Detekováno %d sloupců, %d řádků dat. Pro každý sloupec vyber odpovídající pole pluginu.', 'slovnik-a-feedy' ),
							count( $columns ),
							esc_html( $total_rows )
						);
						?>
					</p>
				</div>
				<div class="saf-mapping-legend">
					<span class="saf-legend-item saf-legend-item--mapped"><?php esc_html_e( 'Namapováno', 'slovnik-a-feedy' ); ?></span>
					<span class="saf-legend-item saf-legend-item--unmapped"><?php esc_html_e( 'Nenamapováno', 'slovnik-a-feedy' ); ?></span>
				</div>
			</div>

			<!-- Spreadsheet tabulka -->
			<div class="saf-spreadsheet-wrap">
				<table class="saf-spreadsheet" id="saf-mapping-table">
					<thead>
						<!-- Řádek 1: Selecty pro mapování -->
						<tr class="saf-mapping-selects">
							<?php foreach ( $columns as $col ) :
								$mapped = $auto_mapping[ $col ] ?? '';
							?>
							<th class="saf-col-header <?php echo $mapped ? 'saf-col--mapped' : 'saf-col--unmapped'; ?>"
								data-col="<?php echo esc_attr( $col ); ?>">
								<select name="mapping[<?php echo esc_attr( $col ); ?>]"
									class="saf-col-select"
									onchange="this.closest('th').className='saf-col-header '+(this.value?'saf-col--mapped':'saf-col--unmapped')">
									<option value=""><?php esc_html_e( '— nepřiřazovat —', 'slovnik-a-feedy' ); ?></option>
									<?php foreach ( $fields as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"
										<?php selected( $mapped, $slug ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
									<?php endforeach; ?>
								</select>
							</th>
							<?php endforeach; ?>
						</tr>
						<!-- Řádek 2: Název sloupce ze zdroje -->
						<tr class="saf-col-names">
							<?php foreach ( $columns as $col ) : ?>
							<th class="saf-col-name-cell">
								<span title="<?php echo esc_attr( $col ); ?>">
									<?php echo esc_html( mb_strimwidth( $col, 0, 25, '…' ) ); ?>
								</span>
							</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php if ( $preview_rows ) : ?>
						<?php foreach ( $preview_rows as $row_idx => $row ) : ?>
						<tr class="<?php echo $row_idx % 2 === 0 ? 'saf-row-even' : 'saf-row-odd'; ?>">
							<?php foreach ( $columns as $col ) : ?>
							<td class="saf-data-cell"
								title="<?php echo esc_attr( $row[ $col ] ?? '' ); ?>">
								<?php echo esc_html( mb_strimwidth( $row[ $col ] ?? '', 0, 60, '…' ) ); ?>
							</td>
							<?php endforeach; ?>
						</tr>
						<?php endforeach; ?>
						<?php else : ?>
						<tr>
							<td colspan="<?php echo esc_attr( count( $columns ) ); ?>" class="saf-empty">
								<?php esc_html_e( 'Žádná datová řádka k náhledu.', 'slovnik-a-feedy' ); ?>
							</td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div><!-- /.saf-spreadsheet-wrap -->

			<!-- Profil -->
			<div class="saf-form-row saf-form-row--inline" style="margin-top:16px">
				<label>
					<input type="checkbox" name="save_profile" value="1">
					<?php esc_html_e( 'Uložit mapování jako profil:', 'slovnik-a-feedy' ); ?>
				</label>
				<input type="text" name="profile_name"
					placeholder="<?php esc_attr_e( 'Název profilu', 'slovnik-a-feedy' ); ?>"
					style="width:200px">
			</div>

			<div class="saf-form-row">
				<button type="submit" class="button button-primary button-large">
					<?php esc_html_e( 'Pokračovat na šablonu →', 'slovnik-a-feedy' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-import' ) ); ?>" class="button">
					<?php esc_html_e( '← Zpět', 'slovnik-a-feedy' ); ?>
				</a>
			</div>
		</form>
	</div>

	<?php
	// ── KROK 2 – Šablona + volby ──────────────────────────────────────────────
	elseif ( $step === 2 ) :
		$template    = $view_data['template']    ?? TemplateEngine::default_template();
		$preview_row = $view_data['preview_row'] ?? null;
		$syntax_help = $view_data['syntax_help'] ?? [];
		$settings    = $view_data['settings']    ?? [];
	?>
	<div class="saf-columns">
		<div class="saf-panel">
			<form method="post" id="saf-import-form">
				<?php wp_nonce_field( 'saf_import_step_2', 'saf_import_nonce' ); ?>
				<input type="hidden" name="saf_step" value="2">
				<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>">

				<h2 class="saf-panel__title"><?php esc_html_e( 'Šablona obsahu (Gutenberg bloky)', 'slovnik-a-feedy' ); ?></h2>
				<p class="saf-panel__desc">
					<?php esc_html_e( 'Šablona definuje obsah každého pojmu. Piš blokové komentáře Gutenbergu + makra {{sloupec}} pro data.', 'slovnik-a-feedy' ); ?>
				</p>

				<textarea name="template" id="saf-template" rows="14" class="large-text code"><?php echo esc_textarea( $template ); ?></textarea>

				<details class="saf-syntax-help">
					<summary><?php esc_html_e( 'Nápověda k syntaxi maker', 'slovnik-a-feedy' ); ?></summary>
					<table class="widefat" style="margin-top:8px">
						<tbody>
						<?php foreach ( $syntax_help as $macro => $desc ) : ?>
							<tr>
								<td style="width:300px"><code><?php echo esc_html( $macro ); ?></code></td>
								<td><?php echo esc_html( $desc ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</details>

				<h3 style="margin-top:20px"><?php esc_html_e( 'Nastavení importu', 'slovnik-a-feedy' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><label><?php esc_html_e( 'Výchozí status', 'slovnik-a-feedy' ); ?></label></th>
						<td>
							<select name="default_status">
								<option value="publish" <?php selected( $settings['default_status'] ?? 'publish', 'publish' ); ?>><?php esc_html_e( 'Publikováno', 'slovnik-a-feedy' ); ?></option>
								<option value="draft"   <?php selected( $settings['default_status'] ?? 'publish', 'draft' ); ?>><?php esc_html_e( 'Koncept', 'slovnik-a-feedy' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Rank Math SEO', 'slovnik-a-feedy' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="force_overwrite" value="1">
								<?php esc_html_e( 'Přepsat i ručně zadané SEO hodnoty', 'slovnik-a-feedy' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<div class="saf-form-row saf-form-row--inline" style="margin-top:8px">
					<label>
						<input type="checkbox" name="save_profile" value="1">
						<?php esc_html_e( 'Uložit profil:', 'slovnik-a-feedy' ); ?>
					</label>
					<input type="text" name="profile_name" placeholder="<?php esc_attr_e( 'Název profilu', 'slovnik-a-feedy' ); ?>" style="width:180px">
				</div>

				<div class="saf-import-actions">
					<button type="submit" name="dry_run" value="1" class="button button-large">
						<?php esc_html_e( '👁 Dry-run (náhled bez zápisu)', 'slovnik-a-feedy' ); ?>
					</button>
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( '▶ Spustit import', 'slovnik-a-feedy' ); ?>
					</button>
				</div>
			</form>
		</div>

		<?php if ( $preview_row ) : ?>
		<div class="saf-panel">
			<h2 class="saf-panel__title"><?php esc_html_e( 'Náhled prvního řádku', 'slovnik-a-feedy' ); ?></h2>
			<div class="saf-preview-data">
				<table class="widefat">
					<tbody>
					<?php foreach ( $preview_row as $col => $val ) : ?>
						<?php if ( trim( $val ) === '' ) continue; ?>
						<tr>
							<th style="width:30%;font-weight:600"><code><?php echo esc_html( $col ); ?></code></th>
							<td><?php echo esc_html( mb_strimwidth( $val, 0, 100, '…' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="saf-preview-rendered" style="margin-top:16px">
				<h4 style="margin-bottom:8px;color:#555"><?php esc_html_e( 'Vyrenderovaný obsah:', 'slovnik-a-feedy' ); ?></h4>
				<pre id="saf-preview-output" class="saf-preview-pre"><?php
					$engine = new TemplateEngine();
					echo esc_html( $engine->render( $template, $preview_row ) );
				?></pre>
			</div>
		</div>
		<?php endif; ?>
	</div>

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
