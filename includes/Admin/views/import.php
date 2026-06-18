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

	<?php
	// Chybové hlášky se zobrazují UVNITŘ příslušného panelu (ne na vrcholu stránky).
	// Tato proměnná se předá do panelu krok 0, 1 nebo 2 a zobrazí se tam.
	?>

	<?php
	// ── KROK 0 – Zdroj dat ────────────────────────────────────────────────────
	if ( $step === 0 ) :
		$stream_options  = \SlovnikAFeedy\StreamManager::get_options();
		$active_sessions = \SlovnikAFeedy\Admin\ImportSessionRegistry::get_active();
		$all_sessions    = \SlovnikAFeedy\Admin\ImportSessionRegistry::get_all();
	?>

	<?php if ( $active_sessions ) : ?>
	<!-- Panel nedokončených importů -->
	<div class="saf-panel saf-panel--resume">
		<h2 class="saf-panel__title" style="color:#e94560">
			<span class="dashicons dashicons-warning"></span>
			<?php printf( esc_html__( 'Nedokončené importy (%d)', 'slovnik-a-feedy' ), count( $active_sessions ) ); ?>
		</h2>
		<p class="saf-panel__desc">
			<?php esc_html_e( 'Tyto importy byly přerušeny. Klikni "Pokračovat" pro obnovení od místa kde jsi skončil.', 'slovnik-a-feedy' ); ?>
		</p>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Datum / Soubor', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'Stream', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'Stav', 'slovnik-a-feedy' ); ?></th>
					<th style="width:280px"><?php esc_html_e( 'Akce', 'slovnik-a-feedy' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $active_sessions as $sid => $ses ) : ?>
			<tr>
				<td>
					<strong><?php echo esc_html( $ses['file_name'] ?: '—' ); ?></strong><br>
					<span style="font-size:11px;color:#888">
						<?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $ses['created_at'] ) ) ); ?>
						· <?php echo esc_html( $ses['total_rows'] ); ?> <?php esc_html_e( 'řádků', 'slovnik-a-feedy' ); ?>
						· <?php echo esc_html( $ses['macro_count'] ); ?> <?php esc_html_e( 'sloupců', 'slovnik-a-feedy' ); ?>
					</span>
				</td>
				<td><?php echo esc_html( $ses['stream_name'] ?: '—' ); ?></td>
				<td>
					<span class="saf-badge saf-badge--warning">
						<?php echo esc_html( \SlovnikAFeedy\Admin\ImportSessionRegistry::step_label( $ses['last_step'] ) ); ?>
					</span>
				</td>
				<td>
					<!-- Pokračovat na makra (krok 1) -->
					<form method="post" style="display:inline">
						<?php wp_nonce_field( 'saf_resume', 'saf_resume_nonce' ); ?>
						<input type="hidden" name="saf_action" value="resume_session">
						<input type="hidden" name="session_id" value="<?php echo esc_attr( $sid ); ?>">
						<input type="hidden" name="resume_to" value="1">
						<button type="submit" class="button button-primary button-small">
							✏️ <?php esc_html_e( 'Upravit makra', 'slovnik-a-feedy' ); ?>
						</button>
					</form>
					<!-- Pokračovat na šablonu (krok 2) – jen pokud prošel krok 1 -->
					<?php if ( $ses['last_step'] >= 2 ) : ?>
					<form method="post" style="display:inline;margin-left:4px">
						<?php wp_nonce_field( 'saf_resume', 'saf_resume_nonce' ); ?>
						<input type="hidden" name="saf_action" value="resume_session">
						<input type="hidden" name="session_id" value="<?php echo esc_attr( $sid ); ?>">
						<input type="hidden" name="resume_to" value="2">
						<button type="submit" class="button button-small">
							📄 <?php esc_html_e( 'Upravit šablonu', 'slovnik-a-feedy' ); ?>
						</button>
					</form>
					<?php endif; ?>
					<!-- Smazat relaci -->
					<form method="post" style="display:inline;margin-left:4px">
						<?php wp_nonce_field( 'saf_del_session', 'saf_del_ses_nonce' ); ?>
						<input type="hidden" name="saf_action" value="delete_session">
						<input type="hidden" name="session_id" value="<?php echo esc_attr( $sid ); ?>">
						<button type="submit" class="button button-small"
							style="color:#e94560"
							onclick="return confirm('<?php esc_attr_e( 'Smazat relaci?', 'slovnik-a-feedy' ); ?>')">
							🗑
						</button>
					</form>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- Historie dokončených importů (sbalené) -->
	<?php
	$completed = array_filter( $all_sessions, fn($s) => $s['status'] !== \SlovnikAFeedy\Admin\ImportSessionRegistry::STATUS_ACTIVE );
	if ( $completed ) :
	?>
	<details class="saf-panel" style="cursor:pointer">
		<summary style="font-weight:600;font-size:14px;padding:4px 0">
			<span class="dashicons dashicons-list-view"></span>
			<?php printf( esc_html__( 'Historie importů (%d)', 'slovnik-a-feedy' ), count( $completed ) ); ?>
		</summary>
		<table class="wp-list-table widefat striped" style="margin-top:12px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Datum', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'Soubor', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'Stream', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'Výsledek', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'Akce', 'slovnik-a-feedy' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( array_reverse( $completed, true ) as $sid => $ses ) : ?>
			<tr>
				<td style="font-size:12px"><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $ses['updated_at'] ) ) ); ?></td>
				<td><?php echo esc_html( $ses['file_name'] ?: '—' ); ?></td>
				<td><?php echo esc_html( $ses['stream_name'] ?: '—' ); ?></td>
				<td>
					<?php if ( $ses['status'] === 'completed' ) : ?>
						<?php $r = $ses['result'] ?? []; ?>
						<span class="saf-badge saf-badge--info">✓ <?php
							printf( esc_html__( '%d nových, %d aktualizováno', 'slovnik-a-feedy' ),
								esc_html( $r['created'] ?? 0 ), esc_html( $r['updated'] ?? 0 )
							);
						?></span>
					<?php else : ?>
						<span class="saf-badge saf-badge--error" title="<?php echo esc_attr( $ses['error_msg'] ?? '' ); ?>">
							⚠ <?php esc_html_e( 'Chyba', 'slovnik-a-feedy' ); ?>
						</span>
					<?php endif; ?>
				</td>
				<td>
					<form method="post" style="display:inline">
						<?php wp_nonce_field( 'saf_del_session', 'saf_del_ses_nonce' ); ?>
						<input type="hidden" name="saf_action" value="delete_session">
						<input type="hidden" name="session_id" value="<?php echo esc_attr( $sid ); ?>">
						<button type="submit" class="button button-small" style="color:#e94560"
							onclick="return confirm('Smazat záznam?')">🗑</button>
					</form>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</details>
	<?php endif; ?>
	<div class="saf-panel">
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'saf_import_step_0', 'saf_import_nonce' ); ?>
			<input type="hidden" name="saf_step" value="0">

			<!-- Výběr streamu – vždy viditelný -->
			<div class="saf-form-row">
				<label><strong><?php esc_html_e( 'Importovat do streamu (CPT):', 'slovnik-a-feedy' ); ?></strong></label>
				<select name="stream_id" class="regular-text">
					<?php foreach ( $stream_options as $sid => $sname ) : ?>
					<option value="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $sname ); ?></option>
					<?php endforeach; ?>
				</select>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-streamy' ) ); ?>" style="margin-left:8px;font-size:12px">
					<?php esc_html_e( '+ Přidat stream', 'slovnik-a-feedy' ); ?>
				</a>
			</div>

			<!-- Import presety – uložené nastavení importu -->
			<?php
			$presets = \SlovnikAFeedy\Admin\Settings::get_import_presets();
			if ( $presets ) :
			?>
			<div class="saf-form-row">
				<label><strong><?php esc_html_e( 'Načíst uložený preset importu:', 'slovnik-a-feedy' ); ?></strong></label>
				<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
					<select name="preset_id" id="saf-preset-select" class="regular-text">
						<option value=""><?php esc_html_e( '— nový import —', 'slovnik-a-feedy' ); ?></option>
						<?php foreach ( $presets as $pid => $preset ) : ?>
						<option value="<?php echo esc_attr( $pid ); ?>">
							<?php echo esc_html( $preset['name'] ); ?>
							<?php if ( ! empty( $preset['stream_name'] ) ) : ?>
							(<?php echo esc_html( $preset['stream_name'] ); ?>)
							<?php endif; ?>
						</option>
						<?php endforeach; ?>
					</select>
					<button type="button" id="saf-delete-preset" class="button button-small" style="color:#e94560;display:none"
						title="<?php esc_attr_e( 'Smazat preset', 'slovnik-a-feedy' ); ?>">
						✕ <?php esc_html_e( 'Smazat preset', 'slovnik-a-feedy' ); ?>
					</button>
				</div>
				<p class="description"><?php esc_html_e( 'Preset uloží nastavení importu (stream, makra, šablona) pro opakované použití.', 'slovnik-a-feedy' ); ?></p>
			</div>
			<script>
			(function(){
				var sel = document.getElementById('saf-preset-select');
				var del = document.getElementById('saf-delete-preset');
				if(sel && del) {
					sel.addEventListener('change', function(){
						del.style.display = sel.value ? '' : 'none';
					});
					del.addEventListener('click', function(){
						if(!confirm('<?php esc_attr_e( 'Smazat preset?', 'slovnik-a-feedy' ); ?>')) return;
						var f = document.createElement('form');
						f.method = 'post';
						f.innerHTML = '<?php echo wp_nonce_field('saf_delete_preset','saf_del_nonce',true,false); ?>'
							+ '<input name="saf_action" value="delete_preset">'
							+ '<input name="preset_id" value="'+sel.value+'">';
						document.body.appendChild(f);
						f.submit();
					});
				}
			})();
			</script>
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

			<?php if ( $error ) : ?>
			<div class="saf-inline-error notice notice-error" style="margin:12px 0 0;padding:12px 16px;">
				<p><strong><?php echo esc_html( $error ); ?></strong></p>
				<?php if ( str_contains( $error, 'HTML' ) || str_contains( $error, 'CSV' ) || str_contains( $error, '404' ) ) : ?>
				<p style="margin-top:6px">
					<?php esc_html_e( 'Jak získat správnou URL z Google Sheets:', 'slovnik-a-feedy' ); ?><br>
					<code>Soubor → Sdílet → Publikovat na web → vybrat list → CSV → Publikovat → zkopírovat URL</code>
				</p>
				<?php endif; ?>
			</div>
			<?php endif; ?>
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
						<th style="width:25%">
						<?php esc_html_e( 'Makro (použij v šabloně)', 'slovnik-a-feedy' ); ?>
						<br><span style="font-weight:400;font-size:11px;color:#aaa"><?php esc_html_e( 'Více: kw, sug_url', 'slovnik-a-feedy' ); ?></span>
					</th>
						<th style="width:30%"><?php esc_html_e( 'Pole pluginu (volitelné)', 'slovnik-a-feedy' ); ?></th>
						<th><?php esc_html_e( 'Náhled hodnoty', 'slovnik-a-feedy' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				// Připrav HTML pro jeden select (reusable).
				$field_options_html = '<option value="">' . esc_html__( '— jen obsah —', 'slovnik-a-feedy' ) . '</option>';
				foreach ( $fields as $slug => $label ) {
					$field_options_html .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
				}

				foreach ( $columns as $i => $col ) :
					$macro      = $macro_names[ $col ] ?? '';
					$mapped_raw = $auto_mapping[ $col ] ?? '';
					$mapped_arr = is_array( $mapped_raw ) ? $mapped_raw : ( $mapped_raw ? [ $mapped_raw ] : [] );
					$preview    = $preview_rows[0][ $col ] ?? '';
					$row_class  = $i % 2 === 0 ? '' : 'alternate';
					$col_key    = esc_attr( $col );
				?>
				<tr class="<?php echo $row_class; ?>">
					<td>
						<strong><?php echo esc_html( $col ); ?></strong>
					</td>
					<td>
						<div class="saf-macro-input-wrap">
							<span class="saf-macro-brace">{{</span>
							<input type="text"
								name="macro_names[<?php echo $col_key; ?>]"
								value="<?php echo esc_attr( $macro ); ?>"
								class="saf-macro-input"
								pattern="[a-z0-9_,\s]+"
								title="<?php esc_attr_e( 'Jedno nebo více maker oddělených čárkou: kw, sug_url', 'slovnik-a-feedy' ); ?>"
								required>
							<span class="saf-macro-brace">}}</span>
						</div>
					</td>
					<td>
						<!-- Multi-pole: každý sloupec může mít N přiřazení polí pluginu -->
						<div class="saf-multi-field" id="saf-mf-<?php echo $col_key; ?>">
							<?php
							// Zobraz alespoň jeden select, vždy jeden "prázdný" navíc pro +Přidat.
							$show_fields = ! empty( $mapped_arr ) ? $mapped_arr : [ '' ];
							foreach ( $show_fields as $fi => $sel_val ) :
							?>
							<div class="saf-field-row" style="display:flex;align-items:center;gap:4px;margin-bottom:4px">
								<select name="mapping[<?php echo $col_key; ?>][]" class="saf-field-select">
									<?php
									foreach ( $fields as $slug => $label ) {
										$selected = ( $slug === $sel_val ) ? ' selected' : '';
										echo '<option value="' . esc_attr( $slug ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
									}
									echo '<option value=""' . ( ! $sel_val ? ' selected' : '' ) . '>' . esc_html__( '— jen obsah —', 'slovnik-a-feedy' ) . '</option>';
									?>
								</select>
								<?php if ( $fi > 0 ) : ?>
								<button type="button" class="saf-remove-field button-link" style="color:#e94560;font-size:16px;line-height:1" title="Odebrat">×</button>
								<?php endif; ?>
							</div>
							<?php endforeach; ?>
						</div>
						<button type="button"
							class="saf-add-field button-link"
							data-target="saf-mf-<?php echo $col_key; ?>"
							style="font-size:12px;color:#0073aa">
							+ <?php esc_html_e( 'Přidat pole', 'slovnik-a-feedy' ); ?>
						</button>
					</td>
					<td class="saf-preview-val">
						<?php echo esc_html( mb_strimwidth( $preview, 0, 50, '…' ) ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $error ) : ?>
			<div class="saf-inline-error notice notice-error" style="margin:12px 0;padding:12px 16px">
				<p><strong><?php echo esc_html( $error ); ?></strong></p>
			</div>
			<?php endif; ?>

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

	<script>
	/* Multi-pole pluginu – přidání / odebrání dalšího dropdown */
	(function(){
		// Šablona pro nový field row.
		var fieldOptions = <?php
			$opts = [];
			foreach ( $fields as $slug => $label ) {
				$opts[] = [ 'v' => $slug, 'l' => $label ];
			}
			echo json_encode( $opts );
		?>;

		function buildSelect( name ) {
			var sel = document.createElement('select');
			sel.name = name;
			sel.className = 'saf-field-select';
			// Prázdná možnost první.
			var empty = new Option('<?php esc_html_e( '— jen obsah —', 'slovnik-a-feedy' ); ?>', '', true, true );
			sel.appendChild( empty );
			fieldOptions.forEach(function(o){ sel.appendChild( new Option(o.l, o.v) ); });
			return sel;
		}

		document.addEventListener('click', function(e){
			// Přidat pole.
			if ( e.target.classList.contains('saf-add-field') ) {
				var container = document.getElementById( e.target.dataset.target );
				if ( !container ) return;
				// Zjisti name z prvního selectu.
				var firstSel = container.querySelector('select');
				if ( !firstSel ) return;
				var row = document.createElement('div');
				row.className = 'saf-field-row';
				row.style.cssText = 'display:flex;align-items:center;gap:4px;margin-bottom:4px';
				row.appendChild( buildSelect( firstSel.name ) );
				var rm = document.createElement('button');
				rm.type = 'button';
				rm.className = 'saf-remove-field button-link';
				rm.style.cssText = 'color:#e94560;font-size:16px;line-height:1';
				rm.title = '<?php esc_attr_e( 'Odebrat', 'slovnik-a-feedy' ); ?>';
				rm.textContent = '×';
				row.appendChild( rm );
				container.appendChild( row );
			}
			// Odebrat pole.
			if ( e.target.classList.contains('saf-remove-field') ) {
				var row = e.target.closest('.saf-field-row');
				if ( row ) row.remove();
			}
		});
	})();
	</script>

	<?php
	// ── KROK 2 – Výběr šablony (Gutenberg) ───────────────────────────────────
	elseif ( $step === 2 ) :
		$macro_names   = $view_data['macro_names']   ?? [];
		$macro_preview = $view_data['macro_preview'] ?? [];
		$settings      = $view_data['settings']      ?? [];
	?>
	<!-- Tlačítko Zpět na mapování – viditelné hned pod progress barem -->
	<div style="margin-bottom:12px">
		<form method="post" style="display:inline">
			<?php wp_nonce_field( 'saf_back_step1', 'saf_back_nonce' ); ?>
			<input type="hidden" name="saf_action" value="back_step1">
			<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>">
			<button type="submit" class="button">
				← <?php esc_html_e( 'Zpět na mapování maker', 'slovnik-a-feedy' ); ?>
			</button>
		</form>
		<span style="font-size:12px;color:#888;margin-left:10px">
			<?php esc_html_e( 'Oprav chybné makro nebo přiřazení pole a vrať se sem.', 'slovnik-a-feedy' ); ?>
		</span>
	</div>
	<?php

		$templates     = \SlovnikAFeedy\TemplateManager::get_all();
		$template_id   = (int) get_option( 'saf_last_template_id', 0 );
	?>

	<div class="saf-columns">

		<!-- Výběr / vytvoření šablony -->
		<div class="saf-panel">
			<h2 class="saf-panel__title">
				<?php esc_html_e( 'Šablona obsahu', 'slovnik-a-feedy' ); ?>
			</h2>
			<p class="saf-panel__desc">
				<?php esc_html_e( 'Šablonu vytváříš v normálním WordPress editoru (Gutenberg). Do bloků píšeš makra {{makro}} místo textu – plugin je při importu nahradí daty z tabulky.', 'slovnik-a-feedy' ); ?>
			</p>

			<!-- Krok A: vyber nebo vytvoř šablonu -->
			<div class="saf-template-picker">
				<?php if ( $templates ) : ?>
				<div class="saf-form-row" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
					<label><strong><?php esc_html_e( 'Existující šablona:', 'slovnik-a-feedy' ); ?></strong></label>
					<select id="saf-template-select" style="min-width:250px">
						<option value=""><?php esc_html_e( '— vyber šablonu —', 'slovnik-a-feedy' ); ?></option>
						<?php foreach ( $templates as $tid => $ttitle ) : ?>
						<option value="<?php echo esc_attr( $tid ); ?>"
							data-edit="<?php echo esc_attr( \SlovnikAFeedy\TemplateManager::get_edit_url( $tid ) ); ?>"
							<?php selected( $template_id, $tid ); ?>>
							<?php echo esc_html( $ttitle ); ?>
						</option>
						<?php endforeach; ?>
					</select>
					<a id="saf-edit-template-btn" href="#" target="_blank" class="button" style="display:none">
						<?php esc_html_e( '✏️ Otevřít v editoru →', 'slovnik-a-feedy' ); ?>
					</a>
				</div>
				<p class="description" style="margin-top:4px">
					<?php esc_html_e( 'Uprav šablonu v editoru, ulož a vrať se sem. Tlačítko "Spustit import" použije aktuální obsah šablony.', 'slovnik-a-feedy' ); ?>
				</p>
				<div class="saf-builder-divider" style="margin:14px 0"></div>
				<?php endif; ?>

				<!-- Vytvoření nové šablony -->
				<form method="post" id="saf-new-template-form" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
					<?php wp_nonce_field( 'saf_create_template', 'saf_tpl_nonce' ); ?>
					<input type="hidden" name="saf_action" value="create_template">
					<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>">
					<label><strong><?php esc_html_e( $templates ? 'Nebo vytvoř novou:' : 'Vytvoř šablonu:', 'slovnik-a-feedy' ); ?></strong></label>
					<input type="text" name="template_title"
						placeholder="<?php esc_attr_e( 'Název šablony', 'slovnik-a-feedy' ); ?>"
						class="regular-text"
						value="Šablona slovníčku">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( '+ Vytvořit a otevřít v editoru →', 'slovnik-a-feedy' ); ?>
					</button>
				</form>
			</div>

			<!-- Dostupná makra -->
			<?php if ( $macro_names ) : ?>
			<div style="margin-top:20px">
				<strong><?php esc_html_e( 'Dostupná makra – zkopíruj do editoru:', 'slovnik-a-feedy' ); ?></strong>
				<p class="description" style="margin-bottom:8px">
					<?php esc_html_e( 'Klikni na makro pro zkopírování. Vlož ho přímo do bloku v Gutenbergu.', 'slovnik-a-feedy' ); ?>
				</p>
				<div class="saf-macro-chips-inline">
					<?php foreach ( $macro_names as $col => $macro ) : ?>
					<button type="button"
						class="saf-macro-chip-inline"
						onclick="navigator.clipboard.writeText('{{<?php echo esc_js( $macro ); ?>}}').then(function(){var el=this;el.classList.add('copied');setTimeout(function(){el.classList.remove('copied')},1200)}.bind(this))"
						title="<?php echo esc_attr( $col ); ?>">
						<code>{{<?php echo esc_html( $macro ); ?>}}</code>
						<span class="saf-chip-col"><?php echo esc_html( mb_strimwidth( $col, 0, 20, '…' ) ); ?></span>
						<span class="saf-chip-copy">📋</span>
						<span class="saf-chip-copied">✓ Zkopírováno</span>
					</button>
					<?php endforeach; ?>
				</div>
				<p class="description" style="margin-top:8px">
					💡 <strong><?php esc_html_e( 'H1 = Titulek stránky', 'slovnik-a-feedy' ); ?></strong> –
					<?php esc_html_e( 'mapuješ přes "Název pojmu (title)" v kroku 1. V obsahu začínáš od H2.', 'slovnik-a-feedy' ); ?>
				</p>
			</div>
			<?php endif; ?>
		</div>

		<!-- Spuštění importu -->
		<div class="saf-panel">
			<h2 class="saf-panel__title"><?php esc_html_e( 'Nastavení a spuštění', 'slovnik-a-feedy' ); ?></h2>

			<form method="post" id="saf-import-form">
				<?php wp_nonce_field( 'saf_import_step_2', 'saf_import_nonce' ); ?>
				<input type="hidden" name="saf_step" value="2">
				<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>">
				<input type="hidden" name="template_id" id="saf-template-id-field"
					value="<?php echo esc_attr( $template_id ); ?>">

				<table class="form-table" style="margin-top:0">
					<tr>
						<th><label><?php esc_html_e( 'Šablona', 'slovnik-a-feedy' ); ?></label></th>
						<td>
							<?php if ( $template_id && isset( $templates[ $template_id ] ) ) : ?>
							<strong><?php echo esc_html( $templates[ $template_id ] ); ?></strong>
							<?php else : ?>
							<em style="color:#e94560"><?php esc_html_e( '⚠ Vyber nebo vytvoř šablonu vlevo.', 'slovnik-a-feedy' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Status příspěvků', 'slovnik-a-feedy' ); ?></label></th>
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

				<!-- Náhled hodnot z 1. řádku -->
				<?php if ( $macro_preview ) : ?>
				<details style="margin-bottom:16px">
					<summary style="cursor:pointer;font-size:13px;color:#0073aa">
						<?php esc_html_e( 'Hodnoty z 1. řádku (ověř mapování)', 'slovnik-a-feedy' ); ?>
					</summary>
					<table class="widefat" style="margin-top:8px;font-size:12px">
						<tbody>
						<?php foreach ( $macro_preview as $macro => $val ) : ?>
							<?php if ( trim( $val ) === '' ) continue; ?>
							<tr>
								<td style="width:35%;color:#0073aa"><code>{{<?php echo esc_html( $macro ); ?>}}</code></td>
								<td><?php echo esc_html( mb_strimwidth( $val, 0, 80, '…' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</details>
				<?php endif; ?>

				<div class="saf-import-actions">
					<button type="submit" name="dry_run" value="1" class="button button-large"
						<?php echo ! $template_id ? 'disabled' : ''; ?>>
						<?php esc_html_e( '👁 Dry-run (náhled bez zápisu)', 'slovnik-a-feedy' ); ?>
					</button>
					<button type="submit" class="button button-primary button-large"
						<?php echo ! $template_id ? 'disabled' : ''; ?>>
						<?php esc_html_e( '▶ Spustit import', 'slovnik-a-feedy' ); ?>
					</button>
					<?php if ( ! $template_id ) : ?>
					<span style="color:#e94560;font-size:12px"><?php esc_html_e( '← Nejdřív vyber nebo vytvoř šablonu', 'slovnik-a-feedy' ); ?></span>
					<?php endif; ?>
				</div>
			</form>
		</div>

	</div>

	<script>
	(function () {
		// Aktualizuj Edit URL + hidden field při změně selectu šablony.
		var sel     = document.getElementById('saf-template-select');
		var editBtn = document.getElementById('saf-edit-template-btn');
		var idField = document.getElementById('saf-template-id-field');

		if (sel) {
			sel.addEventListener('change', function () {
				var opt = sel.options[sel.selectedIndex];
				if (editBtn) {
					editBtn.href        = opt.dataset.edit || '#';
					editBtn.style.display = opt.value ? '' : 'none';
				}
				if (idField) idField.value = opt.value;
			});
			// Trigger při načtení pokud je předvybrána šablona.
			if (sel.value) sel.dispatchEvent(new Event('change'));
		}
	}());
	</script>

	<?php
	// ── KROK 3 – Výsledky ─────────────────────────────────────────────────────
	elseif ( $step === 3 ) :
		$result            = $view_data['result']     ?? [];
		$is_dry_run        = $view_data['is_dry_run']  ?? false;
		$total_rows_result = $view_data['total_rows']  ?? 0;
		$stats             = $result['stats']          ?? null;
		$batch_id          = $result['batch_id']       ?? null;
		$stream_cpt        = $view_data['stream_cpt']  ?? 'glossary';
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
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $stream_cpt ) ); ?>" class="button">
				<?php esc_html_e( 'Zobrazit importované záznamy', 'slovnik-a-feedy' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-logy&context=import' ) ); ?>" class="button">
				<?php esc_html_e( 'Logy', 'slovnik-a-feedy' ); ?>
			</a>
		</div>

		<!-- Uložit jako preset pro příští import -->
		<?php
		$sfp = $view_data['session_for_preset'] ?? [];
		if ( ! $is_dry_run && ! empty( $sfp ) ) :
		?>
		<div style="margin-top:20px;padding:14px;background:#f8f8ff;border:1px solid #b3c6f0;border-radius:4px">
			<strong><?php esc_html_e( 'Uložit jako preset pro příští import?', 'slovnik-a-feedy' ); ?></strong>
			<p class="description" style="margin-bottom:10px">
				<?php esc_html_e( 'Preset uloží stream, makra a šablonu – příští import spustíš jedním kliknutím.', 'slovnik-a-feedy' ); ?>
			</p>
			<form method="post" style="display:flex;gap:8px;align-items:center">
				<?php wp_nonce_field( 'saf_save_preset', 'saf_preset_nonce' ); ?>
				<input type="hidden" name="saf_action" value="save_preset">
				<?php foreach ( $sfp as $key => $val ) :
					if ( is_array( $val ) ) :
						foreach ( $val as $k => $v ) :
				?>
				<input type="hidden" name="preset_data[<?php echo esc_attr($key); ?>][<?php echo esc_attr($k); ?>]" value="<?php echo esc_attr($v); ?>">
				<?php   endforeach;
					else :
				?>
				<input type="hidden" name="preset_data[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($val); ?>">
				<?php   endif;
				endforeach; ?>
				<input type="text" name="preset_name" class="regular-text"
					placeholder="<?php esc_attr_e( 'Název presetu (např. Slovníček 2024)', 'slovnik-a-feedy' ); ?>"
					required>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Uložit preset', 'slovnik-a-feedy' ); ?></button>
			</form>
		</div>
		<?php endif; ?>
	</div>

	<?php endif; ?>

</div><!-- /.wrap -->
