<?php
/**
 * Admin view – správa import šablon.
 *
 * @package SlovnikAFeedy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SlovnikAFeedy\TemplateManager;
use SlovnikAFeedy\Admin\Settings;

/** @var string $notice */
/** @var string $error */

$templates = get_posts( [
	'post_type'      => TemplateManager::POST_TYPE,
	'post_status'    => [ 'publish', 'draft', 'auto-draft' ],
	'posts_per_page' => 50,
	'orderby'        => 'modified',
	'order'          => 'DESC',
] );

$presets = Settings::get_import_presets();
?>
<div class="wrap saf-wrap">

	<div class="saf-header">
		<div class="saf-header__brand">
			<span class="saf-logo">Grou<span>.cz</span></span>
			<span class="saf-header__sep">|</span>
			<h1 class="saf-header__title"><?php esc_html_e( 'Šablony importu', 'slovnik-a-feedy' ); ?></h1>
		</div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-import' ) ); ?>" class="button button-primary">
			<?php esc_html_e( '+ Nový import', 'slovnik-a-feedy' ); ?>
		</a>
	</div>

	<?php if ( $notice ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
	<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<!-- Import šablony -->
	<div class="saf-panel">
		<h2 class="saf-panel__title">
			<span class="dashicons dashicons-editor-table"></span>
			<?php esc_html_e( 'Šablony Gutenberg obsahu', 'slovnik-a-feedy' ); ?>
		</h2>
		<p class="saf-panel__desc">
			<?php esc_html_e( 'Každá šablona definuje strukturu obsahu importovaných příspěvků. Edituje se v normálním WordPress editoru s makry {{makro}}.', 'slovnik-a-feedy' ); ?>
		</p>

		<?php if ( $templates ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Název šablony', 'slovnik-a-feedy' ); ?></th>
					<th style="width:180px"><?php esc_html_e( 'Naposledy upravena', 'slovnik-a-feedy' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Stav', 'slovnik-a-feedy' ); ?></th>
					<th style="width:220px"><?php esc_html_e( 'Akce', 'slovnik-a-feedy' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $templates as $tpl ) :
				$edit_url    = get_edit_post_link( $tpl->ID, 'raw' );
				$macros      = TemplateManager::get_macro_names( $tpl->ID );
				$macro_count = count( $macros );
			?>
			<tr>
				<td>
					<strong><?php echo esc_html( $tpl->post_title ?: __( '(Bez názvu)', 'slovnik-a-feedy' ) ); ?></strong>
					<?php if ( $macro_count > 0 ) : ?>
					<br><span style="font-size:11px;color:#888">
						<?php printf( esc_html__( '%d maker: ', 'slovnik-a-feedy' ), $macro_count ); ?>
						<?php echo esc_html( implode( ', ', array_map( fn($m) => '{{' . $m . '}}', array_values( $macros ) ) ) ); ?>
					</span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $tpl->post_modified ) ) ); ?></td>
				<td>
					<span class="saf-badge <?php echo $tpl->post_status === 'publish' ? 'saf-badge--info' : 'saf-badge--warning'; ?>">
						<?php echo $tpl->post_status === 'publish' ? esc_html__( 'Aktivní', 'slovnik-a-feedy' ) : esc_html__( 'Koncept', 'slovnik-a-feedy' ); ?>
					</span>
				</td>
				<td>
					<?php if ( $edit_url ) : ?>
					<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small" target="_blank">
						✏️ <?php esc_html_e( 'Upravit v editoru', 'slovnik-a-feedy' ); ?>
					</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( wp_nonce_url(
						admin_url( 'admin.php?page=slovnik-a-feedy-sablony&action=delete&tpl=' . $tpl->ID ),
						'saf_delete_tpl_' . $tpl->ID
					) ); ?>"
						class="button button-small"
						style="color:#e94560;margin-left:4px"
						onclick="return confirm('<?php esc_attr_e( 'Smazat šablonu? Tato akce je nevratná.', 'slovnik-a-feedy' ); ?>')">
						🗑 <?php esc_html_e( 'Smazat', 'slovnik-a-feedy' ); ?>
					</a>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php else : ?>
		<p class="saf-empty"><?php esc_html_e( 'Zatím žádné šablony. Šablonu vytvoříš při prvním importu.', 'slovnik-a-feedy' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Import presety -->
	<?php if ( $presets ) : ?>
	<div class="saf-panel">
		<h2 class="saf-panel__title">
			<span class="dashicons dashicons-saved"></span>
			<?php esc_html_e( 'Uložené import presety', 'slovnik-a-feedy' ); ?>
		</h2>
		<p class="saf-panel__desc">
			<?php esc_html_e( 'Preset = uložené kompletní nastavení importu (stream, makra, šablona). Načteš ho při novém importu.', 'slovnik-a-feedy' ); ?>
		</p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Název presetu', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'Stream', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'Šablona', 'slovnik-a-feedy' ); ?></th>
					<th style="width:100px"><?php esc_html_e( 'Akce', 'slovnik-a-feedy' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $presets as $pid => $preset ) :
				$tpl_title = '';
				if ( ! empty( $preset['template_id'] ) ) {
					$tpl_post  = get_post( $preset['template_id'] );
					$tpl_title = $tpl_post ? $tpl_post->post_title : __( '(smazána)', 'slovnik-a-feedy' );
				}
			?>
			<tr>
				<td><strong><?php echo esc_html( $preset['name'] ); ?></strong></td>
				<td><?php echo esc_html( $preset['stream_name'] ?? '—' ); ?></td>
				<td><?php echo esc_html( $tpl_title ?: '—' ); ?></td>
				<td>
					<a href="<?php echo esc_url( wp_nonce_url(
						admin_url( 'admin.php?page=slovnik-a-feedy-sablony&action=delete_preset&preset=' . $pid ),
						'saf_del_preset_' . $pid
					) ); ?>"
						class="button button-small"
						style="color:#e94560"
						onclick="return confirm('<?php esc_attr_e( 'Smazat preset?', 'slovnik-a-feedy' ); ?>')">
						<?php esc_html_e( 'Smazat', 'slovnik-a-feedy' ); ?>
					</a>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

</div><!-- /.wrap -->
