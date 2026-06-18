<?php
/**
 * Admin view – Nastavení pluginu Slovník a Feedy.
 *
 * @package SlovnikAFeedy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SlovnikAFeedy\Admin\Settings;

$settings = Settings::get_all();
$profiles = Settings::get_profiles();
$saved    = $view_data['saved'] ?? false;
$error    = $view_data['error'] ?? '';
?>
<div class="wrap saf-wrap">

	<div class="saf-header">
		<div class="saf-header__brand">
			<span class="saf-logo">Grou<span>.cz</span></span>
			<span class="saf-header__sep">|</span>
			<h1 class="saf-header__title"><?php esc_html_e( 'Nastavení – Slovník a Feedy', 'slovnik-a-feedy' ); ?></h1>
		</div>
	</div>

	<?php if ( $saved ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Nastavení bylo uloženo.', 'slovnik-a-feedy' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
	<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'saf_save_settings', 'saf_settings_nonce' ); ?>

		<div class="saf-columns">

			<!-- Import -->
			<div class="saf-panel">
				<h2 class="saf-panel__title">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Import', 'slovnik-a-feedy' ); ?>
				</h2>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="saf-default-status"><?php esc_html_e( 'Výchozí status příspěvku', 'slovnik-a-feedy' ); ?></label></th>
						<td>
							<select name="default_status" id="saf-default-status">
								<option value="publish" <?php selected( $settings['default_status'], 'publish' ); ?>><?php esc_html_e( 'Publikováno', 'slovnik-a-feedy' ); ?></option>
								<option value="draft"   <?php selected( $settings['default_status'], 'draft' ); ?>><?php esc_html_e( 'Koncept', 'slovnik-a-feedy' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="saf-gsheet-url"><?php esc_html_e( 'Výchozí Google Sheets URL', 'slovnik-a-feedy' ); ?></label></th>
						<td>
							<input type="url" id="saf-gsheet-url" name="gsheet_url" class="regular-text"
								value="<?php echo esc_attr( $settings['gsheet_url'] ); ?>">
							<p class="description"><?php esc_html_e( 'URL CSV exportu z Google Sheets (Soubor → Publikovat na web → CSV). Předvyplní se na import stránce.', 'slovnik-a-feedy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="saf-reimport"><?php esc_html_e( 'Plánovaný re-import', 'slovnik-a-feedy' ); ?></label></th>
						<td>
							<select name="reimport_schedule" id="saf-reimport">
								<option value="off"    <?php selected( $settings['reimport_schedule'], 'off' ); ?>><?php esc_html_e( 'Vypnuto', 'slovnik-a-feedy' ); ?></option>
								<option value="daily"  <?php selected( $settings['reimport_schedule'], 'daily' ); ?>><?php esc_html_e( 'Denně', 'slovnik-a-feedy' ); ?></option>
								<option value="weekly" <?php selected( $settings['reimport_schedule'], 'weekly' ); ?>><?php esc_html_e( 'Týdně', 'slovnik-a-feedy' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Automaticky znovu importuje z výchozí Google Sheets URL. Aktivní od Fáze 4.', 'slovnik-a-feedy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="saf-batch-size"><?php esc_html_e( 'Velikost dávky (batch)', 'slovnik-a-feedy' ); ?></label></th>
						<td>
							<input type="number" id="saf-batch-size" name="batch_size" min="10" max="500"
								value="<?php echo esc_attr( $settings['batch_size'] ); ?>" style="width:80px">
							<span><?php esc_html_e( 'řádků za Cron tick', 'slovnik-a-feedy' ); ?></span>
							<p class="description"><?php esc_html_e( 'Pro soubory > 200 řádků se import dávkuje přes WP-Cron. Výchozí: 50.', 'slovnik-a-feedy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rank Math SEO', 'slovnik-a-feedy' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="force_overwrite" value="1"
									<?php checked( $settings['force_overwrite'], '1' ); ?>>
								<?php esc_html_e( 'Globálně přepisovat ručně zadané SEO hodnoty (force overwrite)', 'slovnik-a-feedy' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Výchozí chování zachovává ručně zadané hodnoty. Toto nastavení jde přebít i na úrovni každého importu.', 'slovnik-a-feedy' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Logy -->
			<div class="saf-panel">
				<h2 class="saf-panel__title">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Logy', 'slovnik-a-feedy' ); ?>
				</h2>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="saf-log-retention"><?php esc_html_e( 'Uchovávat záznamy', 'slovnik-a-feedy' ); ?></label></th>
						<td>
							<input type="number" id="saf-log-retention" name="log_retention" min="1" max="365"
								value="<?php echo esc_attr( $settings['log_retention'] ); ?>" style="width:80px">
							<span><?php esc_html_e( 'dní', 'slovnik-a-feedy' ); ?></span>
							<p class="description"><?php esc_html_e( 'Záznamy starší než tento počet dní budou automaticky smazány. Výchozí: 30.', 'slovnik-a-feedy' ); ?></p>
						</td>
					</tr>
				</table>

				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-logy' ) ); ?>" class="button">
						<?php esc_html_e( 'Zobrazit logy', 'slovnik-a-feedy' ); ?>
					</a>
				</p>
			</div>

		</div>

		<!-- Import profily -->
		<?php if ( $profiles ) : ?>
		<div class="saf-panel saf-panel--full">
			<h2 class="saf-panel__title">
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e( 'Uložené profily importu', 'slovnik-a-feedy' ); ?>
			</h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Název profilu', 'slovnik-a-feedy' ); ?></th>
						<th><?php esc_html_e( 'Namapovaná pole', 'slovnik-a-feedy' ); ?></th>
						<th style="width:120px"><?php esc_html_e( 'Akce', 'slovnik-a-feedy' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $profiles as $pid => $profile ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $profile['name'] ); ?></strong></td>
						<td>
							<?php
							foreach ( $profile['mapping'] ?? [] as $src => $field ) {
								echo '<code>' . esc_html( $src ) . '</code> → <code>' . esc_html( $field ) . '</code><br>';
							}
							?>
						</td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=saf_delete_profile&profile_id=' . $pid ), 'saf_delete_profile_' . $pid ) ); ?>"
								class="button button-small"
								onclick="return confirm('<?php esc_attr_e( 'Smazat profil?', 'slovnik-a-feedy' ); ?>')">
								<?php esc_html_e( 'Smazat', 'slovnik-a-feedy' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<?php submit_button( __( 'Uložit nastavení', 'slovnik-a-feedy' ) ); ?>
	</form>

</div><!-- /.wrap -->
