<?php
/**
 * Admin view – Analytics dashboard.
 *
 * @package SlovnikAFeedy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SlovnikAFeedy\Admin\AnalyticsPage;
use SlovnikAFeedy\StreamManager;

// Proměnné z AnalyticsPage::render().
$range           = $range           ?? '30';
$from            = $from            ?? '';
$to              = $to              ?? '';
$cpt             = $cpt             ?? '';
$order           = $order           ?? 'views';
$dir             = $dir             ?? 'DESC';
$paged           = $paged           ?? 1;
$summary         = $summary         ?? [ 'views' => 0, 'clicks' => 0, 'posts' => 0 ];
$prev_summary    = $prev_summary    ?? [ 'views' => 0, 'clicks' => 0 ];
$series          = $series          ?? [];
$pages           = $pages           ?? [];
$total_pages_count = $total_pages_count ?? 0;
$sparklines      = $sparklines      ?? [];
$streams         = $streams         ?? [];
$top_pages       = $top_pages       ?? [];
$bottom_pages    = $bottom_pages    ?? [];
$tracking_active = $tracking_active ?? false;

// Trend vs. předchozí období.
$t = static function ( int $now, int $prev ): float {
	if ( $prev > 0 ) return round( ( ( $now - $prev ) / $prev ) * 100, 1 );
	return $now > 0 ? 100.0 : 0.0;
};
$trend_views  = $t( $summary['views'],  $prev_summary['views']  ?? 0 );
$trend_clicks = $t( $summary['clicks'], $prev_summary['clicks'] ?? 0 );

// Data pro graf.
wp_localize_script( 'saf-analytics', 'safAnalytics', [
	'labels' => array_column( $series, 'date' ),
	'views'  => array_column( $series, 'views' ),
	'clicks' => array_column( $series, 'clicks' ),
	'i18n'   => [
		'views'  => esc_html__( 'Zobrazení', 'slovnik-a-feedy' ),
		'clicks' => esc_html__( 'Kliknutí', 'slovnik-a-feedy' ),
	],
] );

$base_url = admin_url( 'admin.php?page=' . AnalyticsPage::PAGE_SLUG );
$filters  = compact( 'range', 'cpt', 'order', 'dir' );

// Pomocná formátovací funkce pro sekundy → "1 min 30 s".
$fmt_time = static function ( int $s ): string {
	if ( $s <= 0 ) return '—';
	if ( $s < 60 ) return $s . ' s';
	return floor( $s / 60 ) . ' min ' . ( $s % 60 ) . ' s';
};

// Šipka trendu pro inline řádky.
$trend_icon = static function ( int $now, int $prev ): string {
	if ( $prev <= 0 ) return '';
	$pct = round( ( ( $now - $prev ) / $prev ) * 100 );
	if ( $pct > 0 ) return '<span style="color:#2d7738">▲ ' . $pct . '%</span>';
	if ( $pct < 0 ) return '<span style="color:#e94560">▼ ' . abs( $pct ) . '%</span>';
	return '<span style="color:#888">—</span>';
};
?>
<div class="wrap saf-wrap">

	<!-- Hlavička -->
	<div class="saf-header">
		<div class="saf-header__brand">
			<span class="saf-logo">Grou<span>.cz</span></span>
			<span class="saf-header__sep">|</span>
			<h1 class="saf-header__title"><?php esc_html_e( 'Analytics', 'slovnik-a-feedy' ); ?></h1>
		</div>
		<span class="saf-header__version">
			<?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $from ) ) ); ?> –
			<?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $to ) ) ); ?>
		</span>
	</div>

	<!-- Diagnostika: admin nesledován -->
	<?php if ( ! $tracking_active ) : ?>
	<div class="notice notice-warning inline saf-inline-error" style="display:flex;align-items:center;gap:10px;padding:10px 16px;margin-bottom:12px">
		<span class="dashicons dashicons-warning" style="color:#856404;font-size:20px"></span>
		<div>
			<strong><?php esc_html_e( 'Přihlášení administrátoři se nesledují.', 'slovnik-a-feedy' ); ?></strong>
			<?php esc_html_e( 'Proto vidíš nulová čísla při prohlížení jako admin. Pro testování otevři stránky pojmu v anonymním okně, nebo ', 'slovnik-a-feedy' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-nastaveni' ) ); ?>">
				<?php esc_html_e( 'zapni sledování adminů v Nastavení', 'slovnik-a-feedy' ); ?>
			</a>.
		</div>
	</div>
	<?php endif; ?>

	<!-- Filtry -->
	<div class="saf-analytics-filters">
		<div class="saf-filter-group">
			<span><?php esc_html_e( 'Období:', 'slovnik-a-feedy' ); ?></span>
			<?php foreach ( [ '7' => '7 dní', '30' => '30 dní', '90' => '90 dní', '365' => '1 rok' ] as $val => $label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array_merge( $filters, [ 'range' => $val, 'paged' => 1 ] ), $base_url ) ); ?>"
				class="saf-filter-btn <?php echo $range === $val ? 'saf-filter-btn--active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php endforeach; ?>
		</div>
		<div class="saf-filter-group">
			<span><?php esc_html_e( 'Stream:', 'slovnik-a-feedy' ); ?></span>
			<a href="<?php echo esc_url( add_query_arg( array_merge( $filters, [ 'cpt' => '', 'paged' => 1 ] ), $base_url ) ); ?>"
				class="saf-filter-btn <?php echo ! $cpt ? 'saf-filter-btn--active' : ''; ?>">
				<?php esc_html_e( 'Všechny', 'slovnik-a-feedy' ); ?>
			</a>
			<?php foreach ( $streams as $stream ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array_merge( $filters, [ 'cpt' => $stream['cpt'], 'paged' => 1 ] ), $base_url ) ); ?>"
				class="saf-filter-btn <?php echo $cpt === $stream['cpt'] ? 'saf-filter-btn--active' : ''; ?>">
				<?php echo esc_html( $stream['name'] ); ?>
			</a>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Summary karty -->
	<div class="saf-cards">
		<div class="saf-card">
			<div class="saf-card__icon dashicons dashicons-visibility"></div>
			<div class="saf-card__body">
				<strong class="saf-card__num"><?php echo esc_html( number_format_i18n( $summary['views'] ) ); ?></strong>
				<span class="saf-card__label"><?php esc_html_e( 'Zobrazení celkem', 'slovnik-a-feedy' ); ?></span>
				<span class="saf-card__trend <?php echo $trend_views >= 0 ? 'saf-trend--up' : 'saf-trend--down'; ?>">
					<?php echo $trend_views >= 0 ? '▲' : '▼'; ?> <?php echo esc_html( abs( $trend_views ) ); ?>%
					<small><?php esc_html_e( 'vs. předchozí', 'slovnik-a-feedy' ); ?></small>
				</span>
			</div>
		</div>
		<div class="saf-card">
			<div class="saf-card__icon dashicons dashicons-external"></div>
			<div class="saf-card__body">
				<strong class="saf-card__num"><?php echo esc_html( number_format_i18n( $summary['clicks'] ) ); ?></strong>
				<span class="saf-card__label"><?php esc_html_e( 'Kliknutí celkem', 'slovnik-a-feedy' ); ?></span>
				<span class="saf-card__trend <?php echo $trend_clicks >= 0 ? 'saf-trend--up' : 'saf-trend--down'; ?>">
					<?php echo $trend_clicks >= 0 ? '▲' : '▼'; ?> <?php echo esc_html( abs( $trend_clicks ) ); ?>%
					<small><?php esc_html_e( 'vs. předchozí', 'slovnik-a-feedy' ); ?></small>
				</span>
			</div>
		</div>
		<div class="saf-card">
			<div class="saf-card__icon dashicons dashicons-clock"></div>
			<div class="saf-card__body">
				<?php
				$avg_views = $summary['views'] > 0 && count( $series ) > 0
					? round( $summary['views'] / count( $series ) ) : 0;
				?>
				<strong class="saf-card__num"><?php echo esc_html( number_format_i18n( $avg_views ) ); ?></strong>
				<span class="saf-card__label"><?php esc_html_e( 'Průměr / den', 'slovnik-a-feedy' ); ?></span>
			</div>
		</div>
		<div class="saf-card">
			<div class="saf-card__icon dashicons dashicons-admin-page"></div>
			<div class="saf-card__body">
				<strong class="saf-card__num"><?php echo esc_html( number_format_i18n( $summary['posts'] ) ); ?></strong>
				<span class="saf-card__label"><?php esc_html_e( 'Aktivních stránek', 'slovnik-a-feedy' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Graf -->
	<div class="saf-panel saf-panel--chart">
		<h2 class="saf-panel__title">
			<span class="dashicons dashicons-chart-line"></span>
			<?php esc_html_e( 'Trend výkonu', 'slovnik-a-feedy' ); ?>
		</h2>
		<?php if ( array_sum( array_column( $series, 'views' ) ) > 0 ) : ?>
		<div class="saf-chart-wrap"><canvas id="saf-chart" height="200"></canvas></div>
		<?php else : ?>
		<p class="saf-empty"><?php esc_html_e( 'Zatím žádná data. Navštiv stránky pojmů v anonymním okně.', 'slovnik-a-feedy' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Top 10 + Bottom 10 (vedle sebe) -->
	<div class="saf-columns">

		<!-- TOP 10 nejlepších -->
		<div class="saf-panel">
			<h2 class="saf-panel__title" style="color:#2d7738">
				▲ <?php esc_html_e( 'Top 10 – nejlepší stránky', 'slovnik-a-feedy' ); ?>
			</h2>
			<?php if ( $top_pages ) : ?>
			<table class="wp-list-table widefat striped" style="font-size:12px">
				<thead><tr>
					<th><?php esc_html_e( 'Stránka', 'slovnik-a-feedy' ); ?></th>
					<th style="width:70px;text-align:right"><?php esc_html_e( 'Zobr.', 'slovnik-a-feedy' ); ?></th>
					<th style="width:70px;text-align:right"><?php esc_html_e( 'Klik.', 'slovnik-a-feedy' ); ?></th>
					<th style="width:80px;text-align:right"><?php esc_html_e( 'Ø čas', 'slovnik-a-feedy' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $top_pages as $i => $page ) :
					$url = get_permalink( (int) $page->post_id );
				?>
				<tr>
					<td>
						<strong style="color:#<?php echo $i === 0 ? 'e94560' : '1a1a2e'; ?>">
							<?php if ( $i === 0 ) : ?>🥇<?php elseif ( $i === 1 ) : ?>🥈<?php elseif ( $i === 2 ) : ?>🥉<?php else : echo ( $i + 1 ) . '.'; endif; ?>
						</strong>
						<?php if ( $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>" target="_blank" title="<?php echo esc_attr( $page->title ); ?>">
							<?php echo esc_html( mb_strimwidth( $page->title, 0, 28, '…' ) ); ?>
						</a>
						<?php else : ?>
						<?php echo esc_html( mb_strimwidth( $page->title, 0, 28, '…' ) ); ?>
						<?php endif; ?>
					</td>
					<td style="text-align:right"><strong><?php echo esc_html( number_format_i18n( (int) $page->views ) ); ?></strong></td>
					<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $page->clicks ) ); ?></td>
					<td style="text-align:right"><?php echo esc_html( $fmt_time( (int) ( $page->avg_time ?? 0 ) ) ); ?></td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
			<p class="saf-empty"><?php esc_html_e( 'Zatím žádná data.', 'slovnik-a-feedy' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- BOTTOM 10 nejslabších -->
		<div class="saf-panel">
			<h2 class="saf-panel__title" style="color:#e94560">
				▼ <?php esc_html_e( 'Bottom 10 – slabé stránky', 'slovnik-a-feedy' ); ?>
			</h2>
			<p class="description" style="margin-bottom:8px;font-size:11px">
				<?php esc_html_e( 'Stránky s nejnižší návštěvností – kandidáti na aktualizaci obsahu nebo posílení interními linky.', 'slovnik-a-feedy' ); ?>
			</p>
			<?php if ( $bottom_pages ) : ?>
			<table class="wp-list-table widefat striped" style="font-size:12px">
				<thead><tr>
					<th><?php esc_html_e( 'Stránka', 'slovnik-a-feedy' ); ?></th>
					<th style="width:70px;text-align:right"><?php esc_html_e( 'Zobr.', 'slovnik-a-feedy' ); ?></th>
					<th style="width:70px;text-align:right"><?php esc_html_e( 'Klik.', 'slovnik-a-feedy' ); ?></th>
					<th style="width:80px;text-align:right"><?php esc_html_e( 'Ø čas', 'slovnik-a-feedy' ); ?></th>
					<th style="width:60px"><?php esc_html_e( 'Akce', 'slovnik-a-feedy' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $bottom_pages as $page ) :
					$edit_url = get_edit_post_link( (int) $page->post_id );
				?>
				<tr>
					<td><?php echo esc_html( mb_strimwidth( $page->title, 0, 28, '…' ) ); ?></td>
					<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $page->views ) ); ?></td>
					<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $page->clicks ) ); ?></td>
					<td style="text-align:right"><?php echo esc_html( $fmt_time( (int) ( $page->avg_time ?? 0 ) ) ); ?></td>
					<td>
						<?php if ( $edit_url ) : ?>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">✏</a>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
			<p class="saf-empty"><?php esc_html_e( 'Zatím žádná data.', 'slovnik-a-feedy' ); ?></p>
			<?php endif; ?>
		</div>

	</div><!-- /.saf-columns -->

	<!-- Všechny stránky – kompletní tabulka s řazením -->
	<div class="saf-panel">
		<div class="saf-panel-header">
			<h2 class="saf-panel__title">
				<span class="dashicons dashicons-list-view"></span>
				<?php printf( esc_html__( 'Všechny stránky (%d)', 'slovnik-a-feedy' ), esc_html( $total_pages_count ) ); ?>
			</h2>
			<div class="saf-sort-links">
				<span><?php esc_html_e( 'Řadit:', 'slovnik-a-feedy' ); ?></span>
				<?php
				$next_dir = $dir === 'DESC' ? 'asc' : 'desc';
				foreach ( [ 'views' => 'Zobrazení', 'clicks' => 'Kliknutí', 'avg_time' => 'Ø Čas' ] as $col => $lbl ) :
					$active = $order === $col;
				?>
				<a href="<?php echo esc_url( add_query_arg( array_merge( $filters, [ 'order' => $col, 'dir' => $active ? $next_dir : 'desc', 'paged' => 1 ] ), $base_url ) ); ?>"
					class="saf-sort-btn <?php echo $active ? 'saf-sort-btn--active' : ''; ?>">
					<?php echo esc_html( $lbl ); ?><?php if ( $active ) echo $dir === 'DESC' ? ' ▼' : ' ▲'; ?>
				</a>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( $pages ) : ?>
		<table class="wp-list-table widefat fixed striped saf-analytics-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Stránka / URL', 'slovnik-a-feedy' ); ?></th>
					<th style="width:100px"><?php esc_html_e( 'Stream', 'slovnik-a-feedy' ); ?></th>
					<th style="width:80px;text-align:right"><?php esc_html_e( 'Zobrazení', 'slovnik-a-feedy' ); ?></th>
					<th style="width:70px;text-align:right"><?php esc_html_e( 'Kliknutí', 'slovnik-a-feedy' ); ?></th>
					<th style="width:55px;text-align:right"><?php esc_html_e( 'CTR', 'slovnik-a-feedy' ); ?></th>
					<th style="width:80px;text-align:right"><?php esc_html_e( 'Ø Čas', 'slovnik-a-feedy' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Trend 14d', 'slovnik-a-feedy' ); ?></th>
					<th style="width:55px"><?php esc_html_e( 'Akce', 'slovnik-a-feedy' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $pages as $page ) :
				$ctr       = $page->views > 0 ? round( ( $page->clicks / $page->views ) * 100, 1 ) : 0;
				$stream    = StreamManager::find_by_cpt( $page->cpt );
				$sparkline = wp_json_encode( $sparklines[ $page->post_id ] ?? [] );
				$post_url  = get_permalink( (int) $page->post_id );
				$edit_url  = get_edit_post_link( (int) $page->post_id );
			?>
			<tr>
				<td>
					<strong>
						<?php if ( $post_url ) : ?>
						<a href="<?php echo esc_url( $post_url ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $page->title ); ?>
						</a>
						<?php else : ?>
						<?php echo esc_html( $page->title ); ?>
						<?php endif; ?>
					</strong>
					<br><span class="saf-url-slug">/<?php echo esc_html( $page->slug ); ?>/</span>
				</td>
				<td>
					<?php if ( $stream ) : ?>
					<span class="saf-badge saf-badge--info" style="font-size:10px"><?php echo esc_html( $stream['name'] ); ?></span>
					<?php endif; ?>
				</td>
				<td style="text-align:right"><strong><?php echo esc_html( number_format_i18n( (int) $page->views ) ); ?></strong></td>
				<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $page->clicks ) ); ?></td>
				<td style="text-align:right"><?php echo esc_html( $ctr ); ?>%</td>
				<td style="text-align:right"><?php echo esc_html( $fmt_time( (int) ( $page->avg_time ?? 0 ) ) ); ?></td>
				<td><div data-sparkline="<?php echo esc_attr( $sparkline ); ?>"></div></td>
				<td>
					<?php if ( $edit_url ) : ?>
					<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">✏</a>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		$total_page_pages = (int) ceil( $total_pages_count / AnalyticsPage::PER_PAGE );
		if ( $total_page_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php echo wp_kses_post( paginate_links( [
					'base'      => add_query_arg( array_merge( $filters, [ 'paged' => '%#%' ] ), $base_url ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_page_pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				] ) ); ?>
			</div>
			<div class="displaying-num">
				<?php printf( esc_html__( '%d stránek celkem', 'slovnik-a-feedy' ), esc_html( $total_pages_count ) ); ?>
			</div>
		</div>
		<?php endif; ?>

		<?php else : ?>
		<div style="padding:20px;text-align:center">
			<p class="saf-empty"><?php esc_html_e( 'Zatím žádná data pro vybrané období.', 'slovnik-a-feedy' ); ?></p>
			<p style="font-size:13px;color:#555">
				<?php esc_html_e( 'Jak spustit sledování:', 'slovnik-a-feedy' ); ?><br>
				<strong>1.</strong> <?php esc_html_e( 'Otevři stránku pojmu v anonymním okně (nebo zapni "Sledovat adminy" v Nastavení)', 'slovnik-a-feedy' ); ?><br>
				<strong>2.</strong> <?php esc_html_e( 'Zobrazení se zaznamenají automaticky', 'slovnik-a-feedy' ); ?><br>
				<strong>3.</strong> <?php esc_html_e( 'Klikni na libovolný odkaz na stránce → zaznamená se kliknutí', 'slovnik-a-feedy' ); ?>
			</p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-nastaveni' ) ); ?>" class="button">
				<?php esc_html_e( 'Otevřít Nastavení', 'slovnik-a-feedy' ); ?>
			</a>
		</div>
		<?php endif; ?>
	</div>

</div><!-- /.wrap -->
