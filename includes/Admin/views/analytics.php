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

/**
 * Proměnné předané z AnalyticsPage::render():
 * @var string  $range          Počet dní (7/30/90/365)
 * @var string  $from           Od (Y-m-d)
 * @var string  $to             Do (Y-m-d)
 * @var string  $cpt            Filtr stream CPT
 * @var string  $order          views|clicks
 * @var string  $dir            ASC|DESC
 * @var int     $paged
 * @var array   $summary        {views, clicks, posts}
 * @var array   $prev_summary
 * @var array   $series         [{date, views, clicks}]
 * @var array   $pages          [{post_id, title, slug, cpt, views, clicks}]
 * @var int     $total_pages_count
 * @var array   $sparklines     post_id => [int]
 * @var array   $streams        StreamManager::get_all()
 */

// Trend výpočet (+/- % vs předchozí období).
$trend_views  = $prev_summary['views']  > 0
	? round( ( ( $summary['views']  - $prev_summary['views']  ) / $prev_summary['views']  ) * 100, 1 )
	: ( $summary['views'] > 0 ? 100 : 0 );
$trend_clicks = $prev_summary['clicks'] > 0
	? round( ( ( $summary['clicks'] - $prev_summary['clicks'] ) / $prev_summary['clicks'] ) * 100, 1 )
	: ( $summary['clicks'] > 0 ? 100 : 0 );

// Předání dat grafu do JS.
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

function saf_filter_url( array $override, array $base, string $base_url ): string {
	$args = array_merge( $base, $override );
	return esc_url( add_query_arg( $args, $base_url ) );
}

$total_page_pages = (int) ceil( $total_pages_count / AnalyticsPage::PER_PAGE );
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
			<?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $from ) ) ); ?>
			–
			<?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $to ) ) ); ?>
		</span>
	</div>

	<!-- Filtry -->
	<div class="saf-analytics-filters">
		<div class="saf-filter-group">
			<span><?php esc_html_e( 'Období:', 'slovnik-a-feedy' ); ?></span>
			<?php foreach ( [ '7' => '7 dní', '30' => '30 dní', '90' => '90 dní', '365' => '1 rok' ] as $val => $label ) : ?>
			<a href="<?php echo saf_filter_url( [ 'range' => $val, 'paged' => 1 ], $filters, $base_url ); ?>"
				class="saf-filter-btn <?php echo $range === $val ? 'saf-filter-btn--active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php endforeach; ?>
		</div>

		<div class="saf-filter-group">
			<span><?php esc_html_e( 'Stream:', 'slovnik-a-feedy' ); ?></span>
			<a href="<?php echo saf_filter_url( [ 'cpt' => '', 'paged' => 1 ], $filters, $base_url ); ?>"
				class="saf-filter-btn <?php echo ! $cpt ? 'saf-filter-btn--active' : ''; ?>">
				<?php esc_html_e( 'Všechny', 'slovnik-a-feedy' ); ?>
			</a>
			<?php foreach ( $streams as $stream ) : ?>
			<a href="<?php echo saf_filter_url( [ 'cpt' => $stream['cpt'], 'paged' => 1 ], $filters, $base_url ); ?>"
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
					<?php echo $trend_views >= 0 ? '▲' : '▼'; ?>
					<?php echo esc_html( abs( $trend_views ) ); ?>%
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
					<?php echo $trend_clicks >= 0 ? '▲' : '▼'; ?>
					<?php echo esc_html( abs( $trend_clicks ) ); ?>%
					<small><?php esc_html_e( 'vs. předchozí', 'slovnik-a-feedy' ); ?></small>
				</span>
			</div>
		</div>

		<div class="saf-card">
			<div class="saf-card__icon dashicons dashicons-chart-line"></div>
			<div class="saf-card__body">
				<strong class="saf-card__num">
					<?php
					$avg = $summary['views'] > 0 && count( $series ) > 0
						? round( $summary['views'] / count( $series ) )
						: 0;
					echo esc_html( number_format_i18n( $avg ) );
					?>
				</strong>
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

	</div><!-- /.saf-cards -->

	<!-- Graf -->
	<div class="saf-panel saf-panel--chart">
		<h2 class="saf-panel__title">
			<span class="dashicons dashicons-chart-line"></span>
			<?php esc_html_e( 'Trend výkonu', 'slovnik-a-feedy' ); ?>
		</h2>
		<?php if ( array_sum( array_column( $series, 'views' ) ) > 0 ) : ?>
		<div class="saf-chart-wrap">
			<canvas id="saf-chart" height="200"></canvas>
		</div>
		<?php else : ?>
		<p class="saf-empty"><?php esc_html_e( 'Žádná data pro vybrané období. Data se začnou sbírat po prvních návštěvách stránek.', 'slovnik-a-feedy' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Tabulka stránek -->
	<div class="saf-panel">
		<div class="saf-panel-header">
			<h2 class="saf-panel__title">
				<span class="dashicons dashicons-list-view"></span>
				<?php
				printf(
					esc_html__( 'Stránky (%d)', 'slovnik-a-feedy' ),
					esc_html( $total_pages_count )
				);
				?>
			</h2>
			<div class="saf-sort-links">
				<span><?php esc_html_e( 'Řadit:', 'slovnik-a-feedy' ); ?></span>
				<?php
				$next_dir = $dir === 'DESC' ? 'asc' : 'desc';
				foreach ( [ 'views' => 'Zobrazení', 'clicks' => 'Kliknutí' ] as $col => $label ) :
					$is_active = $order === $col;
				?>
				<a href="<?php echo saf_filter_url( [ 'order' => $col, 'dir' => $is_active ? $next_dir : 'desc', 'paged' => 1 ], $filters, $base_url ); ?>"
					class="saf-sort-btn <?php echo $is_active ? 'saf-sort-btn--active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
					<?php if ( $is_active ) : ?>
						<?php echo $dir === 'DESC' ? '▼' : '▲'; ?>
					<?php endif; ?>
				</a>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( $pages ) : ?>
		<table class="wp-list-table widefat fixed striped saf-analytics-table">
			<thead>
				<tr>
					<th class="column-title"><?php esc_html_e( 'Stránka', 'slovnik-a-feedy' ); ?></th>
					<th style="width:120px"><?php esc_html_e( 'Stream', 'slovnik-a-feedy' ); ?></th>
					<th style="width:90px" class="saf-num-col"><?php esc_html_e( 'Zobrazení', 'slovnik-a-feedy' ); ?></th>
					<th style="width:90px" class="saf-num-col"><?php esc_html_e( 'Kliknutí', 'slovnik-a-feedy' ); ?></th>
					<th style="width:60px" class="saf-num-col"><?php esc_html_e( 'CTR', 'slovnik-a-feedy' ); ?></th>
					<th style="width:100px"><?php esc_html_e( 'Trend (14 dní)', 'slovnik-a-feedy' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Akce', 'slovnik-a-feedy' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $pages as $page ) :
				$ctr      = $page->views > 0 ? round( ( $page->clicks / $page->views ) * 100, 1 ) : 0;
				$stream   = \SlovnikAFeedy\StreamManager::find_by_cpt( $page->cpt );
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
					<br>
					<span class="saf-url-slug">/<?php echo esc_html( $page->slug ); ?>/</span>
				</td>
				<td>
					<?php if ( $stream ) : ?>
					<span class="saf-badge saf-badge--info"><?php echo esc_html( $stream['name'] ); ?></span>
					<?php else : ?>
					<span style="color:#999"><?php echo esc_html( $page->cpt ); ?></span>
					<?php endif; ?>
				</td>
				<td class="saf-num-col"><strong><?php echo esc_html( number_format_i18n( (int) $page->views ) ); ?></strong></td>
				<td class="saf-num-col"><?php echo esc_html( number_format_i18n( (int) $page->clicks ) ); ?></td>
				<td class="saf-num-col"><?php echo esc_html( $ctr ); ?>%</td>
				<td>
					<div data-sparkline="<?php echo esc_attr( $sparkline ); ?>"></div>
				</td>
				<td>
					<?php if ( $edit_url ) : ?>
					<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
						<?php esc_html_e( 'Upravit', 'slovnik-a-feedy' ); ?>
					</a>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Stránkování -->
		<?php if ( $total_page_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post( paginate_links( [
					'base'      => add_query_arg( 'paged', '%#%', add_query_arg( $filters, $base_url ) ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_page_pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				] ) );
				?>
			</div>
			<div class="displaying-num">
				<?php printf( esc_html__( '%d stránek celkem', 'slovnik-a-feedy' ), esc_html( $total_pages_count ) ); ?>
			</div>
		</div>
		<?php endif; ?>

		<?php else : ?>
		<p class="saf-empty">
			<?php esc_html_e( 'Žádná data. Statistiky se sbírají automaticky od první návštěvy stránek slovníčku.', 'slovnik-a-feedy' ); ?>
		</p>
		<div class="notice notice-info inline">
			<p>
				<strong><?php esc_html_e( 'Jak spustit sledování?', 'slovnik-a-feedy' ); ?></strong>
				<?php esc_html_e( 'Navštiv libovolnou stránku slovníčku (jako nepřihlášený návštěvník nebo v anonymním okně). Plugin automaticky zaznamená zobrazení.', 'slovnik-a-feedy' ); ?>
			</p>
		</div>
		<?php endif; ?>
	</div>

	<!-- Dokumentace -->
	<div class="saf-panel saf-panel--full">
		<h2 class="saf-panel__title">
			<span class="dashicons dashicons-info"></span>
			<?php esc_html_e( 'O statistikách', 'slovnik-a-feedy' ); ?>
		</h2>
		<div class="saf-docs">
			<div class="saf-docs__col">
				<h3><?php esc_html_e( 'Co sledujeme', 'slovnik-a-feedy' ); ?></h3>
				<p><?php esc_html_e( 'Zobrazení = každá návštěva stránky pojmu (filtrujeme boty, nepočítáme administrátory). Kliknutí = uživatel kliknul na odkaz a opustil stránku.', 'slovnik-a-feedy' ); ?></p>
			</div>
			<div class="saf-docs__col">
				<h3><?php esc_html_e( 'CTR', 'slovnik-a-feedy' ); ?></h3>
				<p><?php esc_html_e( 'Click-through rate = kliknutí / zobrazení × 100. Vyšší CTR = obsah motivuje k akci (kliknutí na odkaz, přechod na jinou stránku).', 'slovnik-a-feedy' ); ?></p>
			</div>
			<div class="saf-docs__col">
				<h3><?php esc_html_e( 'Trend (sparkline)', 'slovnik-a-feedy' ); ?></h3>
				<p><?php esc_html_e( 'Mini graf posledních 14 dní. Rostoucí trend = obsah získává návštěvnost. Klesající = může být vhodné obsah aktualizovat nebo podpořit interními linky.', 'slovnik-a-feedy' ); ?></p>
			</div>
		</div>
	</div>

</div><!-- /.wrap -->
