<?php
/**
 * Admin view – zobrazení logů pluginu.
 *
 * @package SlovnikAFeedy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SlovnikAFeedy\Support\Logger;

// Parametry filtrování a stránkování.
$filter_level   = isset( $_GET['level'] ) ? sanitize_key( $_GET['level'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$filter_context = isset( $_GET['context'] ) ? sanitize_text_field( wp_unslash( $_GET['context'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$per_page       = 50;
$current_page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
$offset         = ( $current_page - 1 ) * $per_page;

$entries    = Logger::get_entries( $filter_level, $filter_context, $per_page, $offset );
$total      = Logger::count( $filter_level );
$total_pages = (int) ceil( $total / $per_page );

// Akce – smazání logů (s nonce).
if ( isset( $_POST['saf_purge_logs'], $_POST['saf_purge_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saf_purge_nonce'] ) ), 'saf_purge_logs' ) ) {
		wp_die( esc_html__( 'Neplatný bezpečnostní token.', 'slovnik-a-feedy' ) );
	}
	if ( ! current_user_can( 'manage_glossary' ) ) {
		wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
	}
	$days   = absint( $_POST['saf_purge_days'] ?? 30 );
	$purged = Logger::purge( $days );
	echo '<div class="notice notice-success"><p>';
	/* translators: %d: počet smazaných záznamů */
	printf( esc_html__( 'Smazáno %d záznamů starších než %d dní.', 'slovnik-a-feedy' ), esc_html( $purged ), esc_html( $days ) );
	echo '</p></div>';
}

$level_labels = [
	''        => __( 'Všechny úrovně', 'slovnik-a-feedy' ),
	'info'    => __( 'Info', 'slovnik-a-feedy' ),
	'warning' => __( 'Varování', 'slovnik-a-feedy' ),
	'error'   => __( 'Chyba', 'slovnik-a-feedy' ),
];
?>
<div class="wrap saf-wrap">

	<div class="saf-header">
		<div class="saf-header__brand">
			<span class="saf-logo">Grou<span>.cz</span></span>
			<span class="saf-header__sep">|</span>
			<h1 class="saf-header__title"><?php esc_html_e( 'Logy – Slovník a Feedy', 'slovnik-a-feedy' ); ?></h1>
		</div>
	</div>

	<!-- Filtrování -->
	<form method="get" class="saf-log-filter">
		<input type="hidden" name="page" value="slovnik-a-feedy-logy">
		<label for="saf-filter-level"><?php esc_html_e( 'Úroveň:', 'slovnik-a-feedy' ); ?></label>
		<select id="saf-filter-level" name="level">
			<?php foreach ( $level_labels as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_level, $val ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<label for="saf-filter-context"><?php esc_html_e( 'Kontext:', 'slovnik-a-feedy' ); ?></label>
		<input type="text" id="saf-filter-context" name="context"
			value="<?php echo esc_attr( $filter_context ); ?>"
			placeholder="<?php esc_attr_e( 'např. import', 'slovnik-a-feedy' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Filtrovat', 'slovnik-a-feedy' ); ?></button>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-logy' ) ); ?>" class="button button-link">
			<?php esc_html_e( 'Zrušit filtr', 'slovnik-a-feedy' ); ?>
		</a>
		<span class="saf-log-filter__count">
			<?php
			/* translators: %d: celkový počet záznamů */
			printf( esc_html__( 'Celkem %d záznamů', 'slovnik-a-feedy' ), esc_html( $total ) );
			?>
		</span>
	</form>

	<!-- Tabulka logů -->
	<?php if ( $entries ) : ?>
	<table class="wp-list-table widefat fixed striped saf-log-table">
		<thead>
			<tr>
				<th style="width:160px"><?php esc_html_e( 'Datum', 'slovnik-a-feedy' ); ?></th>
				<th style="width:80px"><?php esc_html_e( 'Úroveň', 'slovnik-a-feedy' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'Kontext', 'slovnik-a-feedy' ); ?></th>
				<th><?php esc_html_e( 'Zpráva', 'slovnik-a-feedy' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $entries as $entry ) : ?>
			<tr class="saf-log-row saf-log-row--<?php echo esc_attr( $entry->level ); ?>">
				<td><?php echo esc_html( $entry->created_at ); ?></td>
				<td>
					<span class="saf-badge saf-badge--<?php echo esc_attr( $entry->level ); ?>">
						<?php echo esc_html( $level_labels[ $entry->level ] ?? $entry->level ); ?>
					</span>
				</td>
				<td><?php echo esc_html( $entry->context ); ?></td>
				<td>
					<?php echo esc_html( $entry->message ); ?>
					<?php if ( ! empty( $entry->data ) ) : ?>
						<details class="saf-log-data">
							<summary><?php esc_html_e( 'Data', 'slovnik-a-feedy' ); ?></summary>
							<pre><?php echo esc_html( (string) json_encode( json_decode( $entry->data ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
						</details>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Stránkování -->
	<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav bottom">
		<div class="tablenav-pages">
			<?php
			$page_links = paginate_links( [
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
				'total'     => $total_pages,
				'current'   => $current_page,
			] );
			echo wp_kses_post( $page_links );
			?>
		</div>
	</div>
	<?php endif; ?>

	<?php else : ?>
		<p class="saf-empty"><?php esc_html_e( 'Žádné záznamy v logu.', 'slovnik-a-feedy' ); ?></p>
	<?php endif; ?>

	<!-- Smazání starých logů -->
	<div class="saf-panel saf-panel--purge">
		<h2 class="saf-panel__title"><?php esc_html_e( 'Čištění logů', 'slovnik-a-feedy' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'saf_purge_logs', 'saf_purge_nonce' ); ?>
			<input type="hidden" name="saf_purge_logs" value="1">
			<label for="saf-purge-days">
				<?php esc_html_e( 'Smazat záznamy starší než', 'slovnik-a-feedy' ); ?>
				<input type="number" id="saf-purge-days" name="saf_purge_days" value="30" min="1" max="365" style="width:60px">
				<?php esc_html_e( 'dní', 'slovnik-a-feedy' ); ?>
			</label>
			<button type="submit" class="button button-secondary"
				onclick="return confirm('<?php esc_attr_e( 'Opravdu smazat staré záznamy?', 'slovnik-a-feedy' ); ?>')">
				<?php esc_html_e( 'Smazat', 'slovnik-a-feedy' ); ?>
			</button>
		</form>
	</div>

</div><!-- /.wrap -->
