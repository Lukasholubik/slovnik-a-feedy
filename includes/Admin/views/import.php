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

// Data z ImportPage::render().
$step       = $view_data['step'] ?? 0;
$session_id = $view_data['session_id'] ?? '';
$error      = $view_data['error'] ?? '';
$profiles   = $view_data['profiles'] ?? [];

$step_labels = [
	0 => __( 'Zdroj dat', 'slovnik-a-feedy' ),
	1 => __( 'Mapování sloupců', 'slovnik-a-feedy' ),
	2 => __( 'Šablona a volby', 'slovnik-a-feedy' ),
	3 => __( 'Výsledek', 'slovnik-a-feedy' ),
];
?>
<div class="wrap saf-wrap">

	<!-- Hlavička -->
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
	<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<!-- ── KROK 0 – Zdroj dat ── -->
	<?php if ( $step === 0 ) : ?>
	<?php $stream_options = \SlovnikAFeedy\StreamManager::get_options(); ?>
	<div class="saf-panel">
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'saf_import_step_0', 'saf_import_nonce' ); ?>
			<input type="hidden" name="saf_step" value="0">

			<?php if ( count( $stream_options ) > 1 ) : ?>
			<h2 class="saf-panel__title"><?php esc_html_e( 'Cílový stream', 'slovnik-a-feedy' ); ?></h2>
			<div class="saf-source-inputs">
				<label for="saf-stream-id"><strong><?php esc_html_e( 'Importovat do:', 'slovnik-a-feedy' ); ?></strong></label>
				<select id="saf-stream-id" name="stream_id" class="regular-text">
					<?php foreach ( $stream_options as $sid => $sname ) : ?>
					<option value="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $sname ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php else : ?>
			<input type="hidden" name="stream_id" value="<?php echo esc_attr( array_key_first( $stream_options ) ); ?>">
			<?php endif; ?>

			<h2 class="saf-panel__title"><?php esc_html_e( 'Vyberte zdroj dat', 'slovnik-a-feedy' ); ?></h2>

			<fieldset class="saf-source-select">
				<label class="saf-source-option">
					<input type="radio" name="source_type" value="csv" checked>
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<strong><?php esc_html_e( 'CSV soubor', 'slovnik-a-feedy' ); ?></strong>
					<small><?php esc_html_e( 'Nahrání ze zařízení (UTF-8, oddělovač auto-detekce)', 'slovnik-a-feedy' ); ?></small>
				</label>
				<label class="saf-source-option">
					<input type="radio" name="source_type" value="xml">
					<span class="dashicons dashicons-media-code"></span>
					<strong><?php esc_html_e( 'XML soubor', 'slovnik-a-feedy' ); ?></strong>
					<small><?php esc_html_e( 'Nahrání XML souboru (child elementy = řádky)', 'slovnik-a-feedy' ); ?></small>
				</label>
				<label class="saf-source-option">
					<input type="radio" name="source_type" value="gsheet">
					<span class="dashicons dashicons-table-col-before"></span>
					<strong><?php esc_html_e( 'Google Sheets URL', 'slovnik-a-feedy' ); ?></strong>
					<small><?php esc_html_e( 'Sheet musí být publikován: Soubor → Sdílet → Publikovat na web → CSV', 'slovnik-a-feedy' ); ?></small>
				</label>
			</fieldset>

			<div class="saf-source-inputs">
				<div class="saf-source-input" id="saf-file-input">
					<label for="saf_file"><strong><?php esc_html_e( 'Soubor (CSV / XML / TSV)', 'slovnik-a-feedy' ); ?></strong></label>
					<input type="file" id="saf_file" name="saf_file" accept=".csv,.xml,.tsv">
					<p class="description"><?php esc_html_e( 'Max. 10 MB. První řádek = hlavička sloupců.', 'slovnik-a-feedy' ); ?></p>
				</div>
				<div class="saf-source-input" id="saf-url-input" style="display:none">
					<label for="saf_gsheet_url"><strong><?php esc_html_e( 'Google Sheets URL (CSV export)', 'slovnik-a-feedy' ); ?></strong></label>
					<input type="url" id="saf_gsheet_url" name="gsheet_url" class="regular-text"
						placeholder="https://docs.google.com/spreadsheets/d/...">
				</div>
			</div>

			<?php if ( $profiles ) : ?>
			<div class="saf-profile-load">
				<label for="saf-load-profile"><strong><?php esc_html_e( 'Načíst uložený profil:', 'slovnik-a-feedy' ); ?></strong></label>
				<select id="saf-load-profile" name="load_profile">
					<option value=""><?php esc_html_e( '— nevybráno —', 'slovnik-a-feedy' ); ?></option>
					<?php foreach ( $profiles as $pid => $profile ) : ?>
					<option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $profile['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Profil předvyplní mapování a šablonu z minulého importu.', 'slovnik-a-feedy' ); ?></p>
			</div>
			<?php endif; ?>

			<button type="submit" class="button button-primary button-large">
				<?php esc_html_e( 'Nahrát a detekovat sloupce →', 'slovnik-a-feedy' ); ?>
			</button>
		</form>
	</div>

	<script>
	(function() {
		var radios = document.querySelectorAll('[name="source_type"]');
		var fileIn = document.getElementById('saf-file-input');
		var urlIn  = document.getElementById('saf-url-input');
		radios.forEach(function(r) {
			r.addEventListener('change', function() {
				fileIn.style.display = r.value === 'gsheet' ? 'none' : '';
				urlIn.style.display  = r.value === 'gsheet' ? ''     : 'none';
			});
		});
	})();
	</script>

	<!-- ── KROK 1 – Mapování sloupců ── -->
	<?php elseif ( $step === 1 ) : ?>
	<?php
	$columns      = $view_data['columns'] ?? [];
	$auto_mapping = $view_data['auto_mapping'] ?? [];
	$fields       = $view_data['fields'] ?? Mapper::FIELDS;
	?>
	<div class="saf-panel">
		<form method="post">
			<?php wp_nonce_field( 'saf_import_step_1', 'saf_import_nonce' ); ?>
			<input type="hidden" name="saf_step" value="1">
			<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>">

			<h2 class="saf-panel__title"><?php esc_html_e( 'Mapování sloupců zdroje → pole pluginu', 'slovnik-a-feedy' ); ?></h2>
			<p class="saf-panel__desc">
				<?php
				printf(
					esc_html__( 'Detekováno %d sloupců. Pro každý sloupec zvol odpovídající pole pluginu, nebo „— nepřiřazovat —".', 'slovnik-a-feedy' ),
					count( $columns )
				);
				?>
			</p>

			<table class="saf-mapping-table wp-list-table widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Sloupec zdroje', 'slovnik-a-feedy' ); ?></th>
						<th><?php esc_html_e( 'Pole pluginu', 'slovnik-a-feedy' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $columns as $col ) : ?>
					<tr>
						<td><code><?php echo esc_html( $col ); ?></code></td>
						<td>
							<select name="mapping[<?php echo esc_attr( $col ); ?>]">
								<option value=""><?php esc_html_e( '— nepřiřazovat —', 'slovnik-a-feedy' ); ?></option>
								<?php foreach ( $fields as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"
									<?php selected( $auto_mapping[ $col ] ?? '', $slug ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div class="saf-profile-save">
				<label>
					<input type="checkbox" name="save_profile" value="1">
					<?php esc_html_e( 'Uložit toto mapování jako profil', 'slovnik-a-feedy' ); ?>
				</label>
				<input type="text" name="profile_name" placeholder="<?php esc_attr_e( 'Název profilu', 'slovnik-a-feedy' ); ?>">
			</div>

			<button type="submit" class="button button-primary button-large">
				<?php esc_html_e( 'Pokračovat na šablonu →', 'slovnik-a-feedy' ); ?>
			</button>
		</form>
	</div>

	<!-- ── KROK 2 – Šablona + volby ── -->
	<?php elseif ( $step === 2 ) : ?>
	<?php
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
					<?php esc_html_e( 'Šablona definuje strukturu obsahu každého pojmu. Blokové komentáře (<!-- wp:... -->) vypiš ručně, makra {{sloupec}} doplní data z každého řádku.', 'slovnik-a-feedy' ); ?>
				</p>

				<textarea name="template" id="saf-template" rows="16" class="large-text code"><?php echo esc_textarea( $template ); ?></textarea>

				<!-- Nápověda k syntaxi -->
				<details class="saf-syntax-help">
					<summary><?php esc_html_e( 'Nápověda k syntaxi maker', 'slovnik-a-feedy' ); ?></summary>
					<table class="widefat">
						<tbody>
						<?php foreach ( $syntax_help as $macro => $desc ) : ?>
							<tr>
								<td><code><?php echo esc_html( $macro ); ?></code></td>
								<td><?php echo esc_html( $desc ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</details>

				<h3><?php esc_html_e( 'Nastavení importu', 'slovnik-a-feedy' ); ?></h3>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="saf-default-status"><?php esc_html_e( 'Výchozí status', 'slovnik-a-feedy' ); ?></label></th>
						<td>
							<select name="default_status" id="saf-default-status">
								<option value="publish" <?php selected( $settings['default_status'], 'publish' ); ?>><?php esc_html_e( 'Publikováno', 'slovnik-a-feedy' ); ?></option>
								<option value="draft"   <?php selected( $settings['default_status'], 'draft' ); ?>><?php esc_html_e( 'Koncept', 'slovnik-a-feedy' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Použije se pokud sloupec "status" není namapován nebo je prázdný.', 'slovnik-a-feedy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rank Math SEO pole', 'slovnik-a-feedy' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="force_overwrite" value="1">
								<?php esc_html_e( 'Přepsat i ručně vyplněné SEO hodnoty (force overwrite)', 'slovnik-a-feedy' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Výchozí chování: ručně zadané SEO hodnoty jsou zachovány, import doplní jen prázdná pole.', 'slovnik-a-feedy' ); ?></p>
						</td>
					</tr>
				</table>

				<div class="saf-profile-save">
					<label>
						<input type="checkbox" name="save_profile" value="1">
						<?php esc_html_e( 'Uložit šablonu + mapování jako profil', 'slovnik-a-feedy' ); ?>
					</label>
					<input type="text" name="profile_name" placeholder="<?php esc_attr_e( 'Název profilu', 'slovnik-a-feedy' ); ?>">
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

		<!-- Live preview -->
		<?php if ( $preview_row ) : ?>
		<div class="saf-panel">
			<h2 class="saf-panel__title"><?php esc_html_e( 'Náhled prvního řádku', 'slovnik-a-feedy' ); ?></h2>
			<p class="saf-panel__desc"><?php esc_html_e( 'Takto bude vypadat vyrenderovaný obsah pro první řádek dat.', 'slovnik-a-feedy' ); ?></p>
			<div class="saf-preview-data">
				<h4><?php esc_html_e( 'Data řádku:', 'slovnik-a-feedy' ); ?></h4>
				<table class="widefat">
					<tbody>
					<?php foreach ( $preview_row as $col => $val ) : ?>
						<?php if ( trim( $val ) === '' ) continue; ?>
						<tr>
							<th style="width:30%"><code><?php echo esc_html( $col ); ?></code></th>
							<td><?php echo esc_html( mb_strimwidth( $val, 0, 100, '…' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="saf-preview-rendered">
				<h4><?php esc_html_e( 'Vyrenderovaný obsah (raw markup):', 'slovnik-a-feedy' ); ?></h4>
				<pre id="saf-preview-output" class="saf-preview-pre"><?php
					$engine = new TemplateEngine();
					echo esc_html( $engine->render( $template, $preview_row ) );
				?></pre>
			</div>
		</div>
		<?php endif; ?>

	</div><!-- /.saf-columns -->

	<!-- ── KROK 3 – Výsledky ── -->
	<?php elseif ( $step === 3 ) : ?>
	<?php
	$result     = $view_data['result']     ?? [];
	$is_dry_run = $view_data['is_dry_run'] ?? false;
	$total_rows = $view_data['total_rows'] ?? 0;
	$stats      = $result['stats']         ?? null;
	$batch_id   = $result['batch_id']      ?? null;
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
				<strong><?php echo esc_html( $total_rows ); ?></strong>
				<span><?php esc_html_e( 'Celkem řádků', 'slovnik-a-feedy' ); ?></span>
			</div>
		</div>

		<?php elseif ( $result['mode'] === 'async' && $batch_id ) : ?>
		<h2 class="saf-panel__title"><?php esc_html_e( 'Import běží na pozadí', 'slovnik-a-feedy' ); ?></h2>
		<p><?php printf( esc_html__( 'Zpracovává se %d řádků přes WP-Cron (po %d najednou).', 'slovnik-a-feedy' ), esc_html( $total_rows ), esc_html( \SlovnikAFeedy\Importer\BatchRunner::BATCH_SIZE ) ); ?></p>
		<p><code><?php esc_html_e( 'Batch ID:', 'slovnik-a-feedy' ); ?> <?php echo esc_html( $batch_id ); ?></code></p>
		<p class="description"><?php esc_html_e( 'Výsledky najdeš v sekci Logy po dokončení.', 'slovnik-a-feedy' ); ?></p>
		<?php endif; ?>

		<div class="saf-result-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-import' ) ); ?>" class="button button-primary">
				<?php esc_html_e( '+ Nový import', 'slovnik-a-feedy' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=glossary' ) ); ?>" class="button">
				<?php esc_html_e( 'Zobrazit pojmy', 'slovnik-a-feedy' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-logy&context=import' ) ); ?>" class="button">
				<?php esc_html_e( 'Zobrazit logy importu', 'slovnik-a-feedy' ); ?>
			</a>
		</div>
	</div>

	<?php endif; ?>

</div><!-- /.wrap -->
