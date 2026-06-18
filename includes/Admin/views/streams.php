<?php
/**
 * Admin view – správa streamů (dynamické CPT).
 *
 * @package SlovnikAFeedy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SlovnikAFeedy\StreamManager;

/** @var array  $streams      Všechny streamy */
/** @var array|null $edit_stream  Stream k editaci (nebo null) */
/** @var string $notice */
/** @var string $error */

$icons = [
	'dashicons-book-alt'    => 'Kniha',
	'dashicons-rss'         => 'RSS / Feed',
	'dashicons-list-view'   => 'Seznam',
	'dashicons-media-text'  => 'Dokument',
	'dashicons-tag'         => 'Štítek',
	'dashicons-category'    => 'Kategorie',
	'dashicons-admin-site'  => 'Web',
	'dashicons-megaphone'   => 'Megafon',
	'dashicons-chart-line'  => 'Graf',
	'dashicons-portfolio'   => 'Portfolio',
];
?>
<div class="wrap saf-wrap">

	<div class="saf-header">
		<div class="saf-header__brand">
			<span class="saf-logo">Grou<span>.cz</span></span>
			<span class="saf-header__sep">|</span>
			<h1 class="saf-header__title"><?php esc_html_e( 'Streamy', 'slovnik-a-feedy' ); ?></h1>
		</div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy' ) ); ?>" class="button">
			&larr; <?php esc_html_e( 'Přehled', 'slovnik-a-feedy' ); ?>
		</a>
	</div>

	<?php if ( $notice ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php echo esc_html( $notice ); ?></p>
		<p>
			<strong>⚠️ <?php esc_html_e( 'Povinný krok:', 'slovnik-a-feedy' ); ?></strong>
			<?php esc_html_e( 'Aby nové URL fungovaly, přejdi do', 'slovnik-a-feedy' ); ?>
			<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>">
				<?php esc_html_e( 'Nastavení → Permalinky', 'slovnik-a-feedy' ); ?>
			</a>
			<?php esc_html_e( 'a klikni na Uložit změny.', 'slovnik-a-feedy' ); ?>
		</p>
	</div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
	<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<div class="saf-panel">
		<p class="saf-panel__desc">
			<?php esc_html_e( 'Každý stream je samostatný Custom Post Type s vlastním archivem, RSS feedem a taxonomiemi. Používej je pro oddělené "blogy", slovníčky, katalogy nebo jiné typy obsahu.', 'slovnik-a-feedy' ); ?>
		</p>

		<!-- Tabulka streamů -->
		<table class="wp-list-table widefat fixed striped saf-stream-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Název', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'CPT slug', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'URL archivu', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'Taxonomie', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'Feedy', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'Status', 'slovnik-a-feedy' ); ?></th>
					<th><?php esc_html_e( 'Akce', 'slovnik-a-feedy' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $streams as $stream_id => $stream ) : ?>
				<tr>
					<td>
						<span class="dashicons <?php echo esc_attr( $stream['icon'] ); ?>" style="vertical-align:middle;margin-right:4px;"></span>
						<strong><?php echo esc_html( $stream['name'] ); ?></strong>
						<?php if ( $stream['is_default'] ) : ?>
							<span class="saf-badge saf-badge--info"><?php esc_html_e( 'výchozí', 'slovnik-a-feedy' ); ?></span>
						<?php endif; ?>
					</td>
					<td><code><?php echo esc_html( $stream['cpt'] ); ?></code></td>
					<td>
						<?php
						$archive = get_post_type_archive_link( $stream['cpt'] );
						if ( $archive ) :
						?>
						<a href="<?php echo esc_url( $archive ); ?>" target="_blank" rel="noopener">
							/<?php echo esc_html( $stream['url_slug'] ); ?>/
						</a>
						<?php else : ?>
							<span style="color:#999">/<code><?php echo esc_html( $stream['url_slug'] ); ?></code>/</span>
						<?php endif; ?>
					</td>
					<td>
						<?php echo $stream['tax_letter'] ? '<span title="A–Z">🔤 A–Z</span>' : ''; ?>
						<?php echo $stream['tax_cat'] ? ' <span title="Kategorie">📂 Kat.</span>' : ''; ?>
					</td>
					<td>
						<?php
						$feed = get_post_type_archive_link( $stream['cpt'] );
						if ( $feed ) :
						?>
						<a href="<?php echo esc_url( trailingslashit( $feed ) . 'feed/' ); ?>" target="_blank" rel="noopener" title="RSS">
							<span class="dashicons dashicons-rss"></span>
						</a>
						<?php else : ?>—<?php endif; ?>
					</td>
					<td>
						<?php if ( $stream['active'] ) : ?>
							<span class="saf-badge saf-badge--info"><?php esc_html_e( 'aktivní', 'slovnik-a-feedy' ); ?></span>
						<?php else : ?>
							<span class="saf-badge saf-badge--warning"><?php esc_html_e( 'neaktivní', 'slovnik-a-feedy' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-streamy&action=edit&stream_id=' . $stream_id ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Upravit', 'slovnik-a-feedy' ); ?>
						</a>
						<?php if ( ! $stream['is_default'] ) : ?>
						&nbsp;
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=slovnik-a-feedy-streamy&action=delete&stream_id=' . $stream_id ), 'saf_delete_stream_' . $stream_id ) ); ?>"
							class="button button-small saf-btn-danger"
							onclick="return confirm('<?php esc_attr_e( 'Smazat stream? Příspěvky zůstanou v DB.', 'slovnik-a-feedy' ); ?>')">
							<?php esc_html_e( 'Smazat', 'slovnik-a-feedy' ); ?>
						</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Formulář – přidat nebo editovat stream -->
	<div class="saf-panel">
		<h2 class="saf-panel__title">
			<?php echo $edit_stream
				? esc_html__( 'Upravit stream', 'slovnik-a-feedy' )
				: esc_html__( 'Přidat nový stream', 'slovnik-a-feedy' );
			?>
		</h2>

		<?php if ( ! $edit_stream ) : ?>
		<p class="saf-panel__desc">
			<?php esc_html_e( 'Každý stream = nový Custom Post Type. Po uložení přejdi do Nastavení → Permalinks a klikni na Uložit, aby se aktivovaly URL pravidla.', 'slovnik-a-feedy' ); ?>
		</p>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'saf_streams_action', 'saf_streams_nonce' ); ?>
			<input type="hidden" name="saf_action" value="<?php echo $edit_stream ? 'update' : 'create'; ?>">
			<?php if ( $edit_stream ) : ?>
			<input type="hidden" name="stream_id" value="<?php echo esc_attr( $edit_stream['id'] ); ?>">
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="saf-stream-name"><?php esc_html_e( 'Název streamu', 'slovnik-a-feedy' ); ?> <span style="color:red">*</span></label></th>
					<td>
						<input type="text" id="saf-stream-name" name="name" class="regular-text"
							value="<?php echo esc_attr( $edit_stream['name'] ?? '' ); ?>"
							placeholder="<?php esc_attr_e( 'např. Slovníček pojmů', 'slovnik-a-feedy' ); ?>">
						<p class="description"><?php esc_html_e( 'Zobrazuje se v admin menu a v Elementor Theme Builder jako název sekce.', 'slovnik-a-feedy' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="saf-stream-cpt"><?php esc_html_e( 'CPT slug (post_type)', 'slovnik-a-feedy' ); ?> <span style="color:red">*</span></label></th>
					<td>
						<input type="text" id="saf-stream-cpt" name="cpt" class="regular-text"
							value="<?php echo esc_attr( $edit_stream['cpt'] ?? '' ); ?>"
							placeholder="<?php esc_attr_e( 'např. slovnicek nebo saf_blog2', 'slovnik-a-feedy' ); ?>"
							maxlength="20"
							<?php echo ( $edit_stream['is_default'] ?? false ) ? 'readonly' : ''; ?>>
						<p class="description">
							<?php esc_html_e( 'Technický identifikátor CPT. Max. 20 znaků, pouze písmena, čísla a _. Doporučený prefix: saf_.', 'slovnik-a-feedy' ); ?>
							<?php if ( $edit_stream['is_default'] ?? false ) : ?>
							<strong><?php esc_html_e( '(Výchozí stream – nelze měnit)', 'slovnik-a-feedy' ); ?></strong>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="saf-stream-slug"><?php esc_html_e( 'URL slug archivu', 'slovnik-a-feedy' ); ?></label></th>
					<td>
						<input type="text" id="saf-stream-slug" name="url_slug" class="regular-text"
							value="<?php echo esc_attr( $edit_stream['url_slug'] ?? '' ); ?>"
							placeholder="<?php esc_attr_e( 'např. slovnik nebo blog2', 'slovnik-a-feedy' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Archiv bude dostupný na: ', 'slovnik-a-feedy' ); ?>
							<code><?php echo esc_html( home_url( '/' ) ); ?><span id="saf-slug-preview"><?php echo esc_html( $edit_stream['url_slug'] ?? 'slug' ); ?></span>/</code>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="saf-stream-icon"><?php esc_html_e( 'Ikona v menu', 'slovnik-a-feedy' ); ?></label></th>
					<td>
						<select id="saf-stream-icon" name="icon">
							<?php foreach ( $icons as $class => $label ) : ?>
							<option value="<?php echo esc_attr( $class ); ?>" <?php selected( ( $edit_stream['icon'] ?? 'dashicons-list-view' ), $class ); ?>>
								<?php echo esc_html( $label ); ?> (<?php echo esc_html( $class ); ?>)
							</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Taxonomie', 'slovnik-a-feedy' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="tax_letter" value="1"
								<?php checked( $edit_stream['tax_letter'] ?? true ); ?>>
							<?php esc_html_e( 'Navigace A–Z (písmena)', 'slovnik-a-feedy' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="tax_cat" value="1"
								<?php checked( $edit_stream['tax_cat'] ?? true ); ?>>
							<?php esc_html_e( 'Kategorie (hierarchické)', 'slovnik-a-feedy' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Aktivní', 'slovnik-a-feedy' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="active" value="1"
								<?php checked( $edit_stream['active'] ?? true ); ?>>
							<?php esc_html_e( 'Registrovat CPT a zobrazovat obsah (zrušení skryje stream, data zachová)', 'slovnik-a-feedy' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php submit_button( $edit_stream
				? __( 'Uložit změny', 'slovnik-a-feedy' )
				: __( 'Vytvořit stream', 'slovnik-a-feedy' )
			); ?>

			<?php if ( $edit_stream ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-streamy' ) ); ?>" class="button">
				<?php esc_html_e( 'Zrušit editaci', 'slovnik-a-feedy' ); ?>
			</a>
			<?php endif; ?>
		</form>
	</div>

	<!-- Nápověda -->
	<div class="saf-panel saf-panel--full">
		<h2 class="saf-panel__title"><?php esc_html_e( 'Proč více streamů?', 'slovnik-a-feedy' ); ?></h2>
		<div class="saf-docs">
			<div class="saf-docs__col">
				<h3><?php esc_html_e( 'Oddělený blog', 'slovnik-a-feedy' ); ?></h3>
				<p><?php esc_html_e( 'Vytvoř stream "Blog 2" s URL /blog2/ – funguje zcela odděleně od klasických příspěvků. Vlastní archiv, feed, Elementor šablona.', 'slovnik-a-feedy' ); ?></p>
			</div>
			<div class="saf-docs__col">
				<h3><?php esc_html_e( 'Více slovníčků', 'slovnik-a-feedy' ); ?></h3>
				<p><?php esc_html_e( 'Např. "Marketingový slovníček" (/marketing/) a "Technický slovník" (/technika/) – každý s vlastními kategoriemi a A–Z navigací.', 'slovnik-a-feedy' ); ?></p>
			</div>
			<div class="saf-docs__col">
				<h3><?php esc_html_e( 'SEO: žádný problém', 'slovnik-a-feedy' ); ?></h3>
				<p><?php esc_html_e( 'Každý stream má jedinečnou URL a vlastní sitemapu v Rank Math. Názvy streamů se nezobrazují v meta tagech ani breadcrumbech – ty řídí Rank Math šablony.', 'slovnik-a-feedy' ); ?></p>
			</div>
		</div>
	</div>

</div><!-- /.wrap -->
<script>
document.getElementById('saf-stream-slug').addEventListener('input', function() {
	document.getElementById('saf-slug-preview').textContent = this.value || 'slug';
});
document.getElementById('saf-stream-name').addEventListener('input', function() {
	var cptInput = document.getElementById('saf-stream-cpt');
	if (!cptInput.readOnly && !cptInput.value) {
		cptInput.value = 'saf_' + this.value.toLowerCase()
			.replace(/[^a-z0-9]/g, '_').replace(/_+/g, '_').substring(0, 16);
	}
	var slugInput = document.getElementById('saf-stream-slug');
	if (!slugInput.value) {
		slugInput.value = this.value.toLowerCase()
			.replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
		document.getElementById('saf-slug-preview').textContent = slugInput.value || 'slug';
	}
});
</script>
