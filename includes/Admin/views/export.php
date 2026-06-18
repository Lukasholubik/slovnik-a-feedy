<?php
/**
 * Admin view – Export pojmů do CSV nebo XML.
 *
 * @package SlovnikAFeedy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SlovnikAFeedy\Exporter\Exporter;
?>
<div class="wrap saf-wrap">

	<div class="saf-header">
		<div class="saf-header__brand">
			<span class="saf-logo">Grou<span>.cz</span></span>
			<span class="saf-header__sep">|</span>
			<h1 class="saf-header__title"><?php esc_html_e( 'Export dat', 'slovnik-a-feedy' ); ?></h1>
		</div>
	</div>

	<div class="saf-panel">
		<h2 class="saf-panel__title"><?php esc_html_e( 'Exportovat pojmy', 'slovnik-a-feedy' ); ?></h2>
		<p class="saf-panel__desc">
			<?php esc_html_e( 'Export vytvoří soubor ve stejném schématu jako import – round-trip (export → úprava v Excelu → re-import bez ztráty dat).', 'slovnik-a-feedy' ); ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'saf_export', 'saf_export_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="saf-export-stream"><?php esc_html_e( 'Stream (CPT)', 'slovnik-a-feedy' ); ?></label></th>
					<td>
						<select name="stream_id" id="saf-export-stream" class="regular-text">
							<?php foreach ( $streams as $sid => $stream ) :
								$exporter = new Exporter( $stream );
								$count    = $exporter->count();
							?>
							<option value="<?php echo esc_attr( $sid ); ?>">
								<?php echo esc_html( $stream['name'] ); ?>
								(<?php echo esc_html( $count ); ?> <?php esc_html_e( 'záznamů', 'slovnik-a-feedy' ); ?>)
							</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Formát', 'slovnik-a-feedy' ); ?></th>
					<td>
						<label style="margin-right:16px">
							<input type="radio" name="format" value="csv" checked>
							CSV <?php esc_html_e( '(Excel, Google Sheets)', 'slovnik-a-feedy' ); ?>
						</label>
						<label>
							<input type="radio" name="format" value="xml">
							XML
						</label>
					</td>
				</tr>
			</table>

			<p>
				<button type="submit" class="button button-primary button-large">
					<span class="dashicons dashicons-download" style="margin-top:3px"></span>
					<?php esc_html_e( 'Stáhnout export', 'slovnik-a-feedy' ); ?>
				</button>
			</p>
		</form>
	</div>

	<!-- Schéma sloupců -->
	<div class="saf-panel saf-panel--full">
		<h2 class="saf-panel__title"><?php esc_html_e( 'Schéma exportovaných sloupců', 'slovnik-a-feedy' ); ?></h2>
		<table class="wp-list-table widefat fixed striped" style="max-width:700px">
			<thead><tr>
				<th style="width:160px"><?php esc_html_e( 'Sloupec', 'slovnik-a-feedy' ); ?></th>
				<th><?php esc_html_e( 'Obsah', 'slovnik-a-feedy' ); ?></th>
			</tr></thead>
			<tbody>
				<tr><td><code>external_id</code></td><td><?php esc_html_e( 'Unikátní ID pro re-import (upsert bez duplicit)', 'slovnik-a-feedy' ); ?></td></tr>
				<tr><td><code>title</code></td><td><?php esc_html_e( 'Titulek příspěvku (H1)', 'slovnik-a-feedy' ); ?></td></tr>
				<tr><td><code>slug</code></td><td><?php esc_html_e( 'URL slug (post_name)', 'slovnik-a-feedy' ); ?></td></tr>
				<tr><td><code>status</code></td><td><?php esc_html_e( 'publish / draft', 'slovnik-a-feedy' ); ?></td></tr>
				<tr><td><code>excerpt</code></td><td><?php esc_html_e( 'Stručný výpis / Excerpt', 'slovnik-a-feedy' ); ?></td></tr>
				<tr><td><code>content</code></td><td><?php esc_html_e( 'Obsah (text bez HTML bloků)', 'slovnik-a-feedy' ); ?></td></tr>
				<tr><td><code>letter</code></td><td><?php esc_html_e( 'Písmeno A–Z (taxonomie slug)', 'slovnik-a-feedy' ); ?></td></tr>
				<tr><td><code>category</code></td><td><?php esc_html_e( 'Kategorie (slug, více odděleno čárkou)', 'slovnik-a-feedy' ); ?></td></tr>
				<tr><td><code>seo_title</code></td><td><?php esc_html_e( 'SEO titulek (Rank Math)', 'slovnik-a-feedy' ); ?></td></tr>
				<tr><td><code>seo_description</code></td><td><?php esc_html_e( 'SEO popis (Rank Math)', 'slovnik-a-feedy' ); ?></td></tr>
				<tr><td><code>seo_keyword</code></td><td><?php esc_html_e( 'Focus keyword (Rank Math)', 'slovnik-a-feedy' ); ?></td></tr>
			</tbody>
		</table>
	</div>

</div><!-- /.wrap -->
