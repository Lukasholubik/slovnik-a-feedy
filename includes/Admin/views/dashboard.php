<?php
/**
 * Admin dashboard – přehled pluginu Slovník a Feedy.
 *
 * @package SlovnikAFeedy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SlovnikAFeedy\Support\Helpers;
use SlovnikAFeedy\Support\Logger;

$stats        = Helpers::get_post_counts();
$letter_count = Helpers::get_term_count( 'glossary_letter' );
$cat_count    = Helpers::get_term_count( 'glossary_cat' );
$feed_rss     = Helpers::get_archive_feed_url( 'rss2' );
$feed_atom    = Helpers::get_archive_feed_url( 'atom' );
$archive_url  = get_post_type_archive_link( 'glossary' );
$rest_url     = rest_url( 'wp/v2/glossary' );
$log_errors   = Logger::count( Logger::ERROR );
$log_warnings = Logger::count( Logger::WARNING );
?>
<div class="wrap saf-wrap">

	<!-- Hlavička Grou.cz -->
	<div class="saf-header">
		<div class="saf-header__brand">
			<span class="saf-logo">Grou<span>.cz</span></span>
			<span class="saf-header__sep">|</span>
			<h1 class="saf-header__title">
				<?php esc_html_e( 'Slovník a Feedy', 'slovnik-a-feedy' ); ?>
			</h1>
		</div>
		<span class="saf-header__version">v<?php echo esc_html( SAF_VERSION ); ?></span>
	</div>

	<!-- Statistiky -->
	<div class="saf-cards">

		<div class="saf-card">
			<div class="saf-card__icon dashicons dashicons-book-alt"></div>
			<div class="saf-card__body">
				<strong class="saf-card__num"><?php echo esc_html( $stats['published'] ); ?></strong>
				<span class="saf-card__label"><?php esc_html_e( 'Publikovaných pojmů', 'slovnik-a-feedy' ); ?></span>
				<?php if ( $stats['draft'] > 0 ) : ?>
					<span class="saf-card__sub"><?php echo esc_html( $stats['draft'] ); ?> <?php esc_html_e( 'konceptů', 'slovnik-a-feedy' ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<div class="saf-card">
			<div class="saf-card__icon dashicons dashicons-translation"></div>
			<div class="saf-card__body">
				<strong class="saf-card__num"><?php echo esc_html( $letter_count ); ?></strong>
				<span class="saf-card__label"><?php esc_html_e( 'Písmen A–Z', 'slovnik-a-feedy' ); ?></span>
			</div>
		</div>

		<div class="saf-card">
			<div class="saf-card__icon dashicons dashicons-category"></div>
			<div class="saf-card__body">
				<strong class="saf-card__num"><?php echo esc_html( $cat_count ); ?></strong>
				<span class="saf-card__label"><?php esc_html_e( 'Kategorií', 'slovnik-a-feedy' ); ?></span>
			</div>
		</div>

		<div class="saf-card <?php echo $log_errors > 0 ? 'saf-card--error' : ''; ?>">
			<div class="saf-card__icon dashicons dashicons-warning"></div>
			<div class="saf-card__body">
				<strong class="saf-card__num"><?php echo esc_html( $log_errors ); ?></strong>
				<span class="saf-card__label"><?php esc_html_e( 'Chyb v logu', 'slovnik-a-feedy' ); ?></span>
				<?php if ( $log_warnings > 0 ) : ?>
					<span class="saf-card__sub"><?php echo esc_html( $log_warnings ); ?> <?php esc_html_e( 'varování', 'slovnik-a-feedy' ); ?></span>
				<?php endif; ?>
			</div>
		</div>

	</div><!-- /.saf-cards -->

	<div class="saf-columns">

		<!-- Feedy -->
		<div class="saf-panel">
			<h2 class="saf-panel__title">
				<span class="dashicons dashicons-rss"></span>
				<?php esc_html_e( 'RSS Feedy', 'slovnik-a-feedy' ); ?>
			</h2>
			<p class="saf-panel__desc">
				<?php esc_html_e( 'Každý CPT stream generuje vlastní RSS feedy. Použijte je pro automatizaci, Zapier, Make nebo napojení dalších nástrojů.', 'slovnik-a-feedy' ); ?>
			</p>
			<table class="saf-feed-table">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'RSS 2.0 archiv', 'slovnik-a-feedy' ); ?></strong></td>
						<td>
							<?php if ( $feed_rss ) : ?>
								<a href="<?php echo esc_url( $feed_rss ); ?>" target="_blank" rel="noopener">
									<?php echo esc_html( $feed_rss ); ?>
								</a>
							<?php else : ?>
								<em><?php esc_html_e( 'Přepisovací pravidla ještě nejsou aktivní.', 'slovnik-a-feedy' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Atom archiv', 'slovnik-a-feedy' ); ?></strong></td>
						<td>
							<?php if ( $feed_atom ) : ?>
								<a href="<?php echo esc_url( $feed_atom ); ?>" target="_blank" rel="noopener">
									<?php echo esc_html( $feed_atom ); ?>
								</a>
							<?php else : ?>
								<em>—</em>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'REST API', 'slovnik-a-feedy' ); ?></strong></td>
						<td>
							<a href="<?php echo esc_url( $rest_url ); ?>" target="_blank" rel="noopener">
								<?php echo esc_html( $rest_url ); ?>
							</a>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Archiv webu', 'slovnik-a-feedy' ); ?></strong></td>
						<td>
							<?php if ( $archive_url ) : ?>
								<a href="<?php echo esc_url( $archive_url ); ?>" target="_blank" rel="noopener">
									<?php echo esc_html( $archive_url ); ?>
								</a>
							<?php else : ?>
								<em><?php esc_html_e( 'Není dostupné – přegenerujte přepisovací pravidla.', 'slovnik-a-feedy' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Feed písmene A', 'slovnik-a-feedy' ); ?></strong></td>
						<td>
							<code><?php echo esc_html( home_url( '/pismeno/a/feed/' ) ); ?></code>
							<br><small><?php esc_html_e( 'Nahraď "a" jiným slug písmene.', 'slovnik-a-feedy' ); ?></small>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Navigace – přehled menu -->
		<div class="saf-panel">
			<h2 class="saf-panel__title">
				<span class="dashicons dashicons-menu-alt3"></span>
				<?php esc_html_e( 'Kde co najdeš', 'slovnik-a-feedy' ); ?>
			</h2>

			<div class="saf-nav-grid">

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-import' ) ); ?>" class="saf-nav-item">
					<span class="saf-nav-item__icon dashicons dashicons-upload"></span>
					<span class="saf-nav-item__title"><?php esc_html_e( 'Import', 'slovnik-a-feedy' ); ?></span>
					<span class="saf-nav-item__desc"><?php esc_html_e( 'Nový import z CSV / XML / Google Sheets', 'slovnik-a-feedy' ); ?></span>
				</a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-sablony' ) ); ?>" class="saf-nav-item saf-nav-item--highlight">
					<span class="saf-nav-item__icon dashicons dashicons-editor-table"></span>
					<span class="saf-nav-item__title"><?php esc_html_e( 'Šablony', 'slovnik-a-feedy' ); ?></span>
					<span class="saf-nav-item__desc"><?php esc_html_e( 'Správa import šablon + uložené presety (opakovaný import)', 'slovnik-a-feedy' ); ?></span>
				</a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-logy&context=import' ) ); ?>" class="saf-nav-item saf-nav-item--highlight">
					<span class="saf-nav-item__icon dashicons dashicons-list-view"></span>
					<span class="saf-nav-item__title"><?php esc_html_e( 'Historie importů', 'slovnik-a-feedy' ); ?></span>
					<span class="saf-nav-item__desc"><?php esc_html_e( 'Logy – co bylo vytvořeno/aktualizováno/přeskočeno v každém importu', 'slovnik-a-feedy' ); ?></span>
				</a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-analytics' ) ); ?>" class="saf-nav-item">
					<span class="saf-nav-item__icon dashicons dashicons-chart-line"></span>
					<span class="saf-nav-item__title"><?php esc_html_e( 'Analytics', 'slovnik-a-feedy' ); ?></span>
					<span class="saf-nav-item__desc"><?php esc_html_e( 'Statistiky zobrazení a kliknutí na stránky', 'slovnik-a-feedy' ); ?></span>
				</a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-streamy' ) ); ?>" class="saf-nav-item">
					<span class="saf-nav-item__icon dashicons dashicons-book-alt"></span>
					<span class="saf-nav-item__title"><?php esc_html_e( 'Streamy (CPT)', 'slovnik-a-feedy' ); ?></span>
					<span class="saf-nav-item__desc"><?php esc_html_e( 'Správa Custom Post Types – vytvořit nový stream/blog', 'slovnik-a-feedy' ); ?></span>
				</a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-nastaveni' ) ); ?>" class="saf-nav-item">
					<span class="saf-nav-item__icon dashicons dashicons-admin-settings"></span>
					<span class="saf-nav-item__title"><?php esc_html_e( 'Nastavení', 'slovnik-a-feedy' ); ?></span>
					<span class="saf-nav-item__desc"><?php esc_html_e( 'Výchozí status, Google Sheets URL, log retention', 'slovnik-a-feedy' ); ?></span>
				</a>

			</div>

			<div class="saf-builder-divider" style="margin:16px 0 12px"></div>

			<!-- Rychlé akce -->
			<div style="display:flex;flex-wrap:wrap;gap:8px">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=slovnik-a-feedy-import' ) ); ?>" class="button button-primary">
					<span class="dashicons dashicons-upload" style="margin-top:3px"></span>
					<?php esc_html_e( 'Spustit import', 'slovnik-a-feedy' ); ?>
				</a>
				<?php
				// Odkaz na první aktivní stream.
				$streams = \SlovnikAFeedy\StreamManager::get_all();
				$first_stream = reset( $streams );
				$first_cpt    = $first_stream['cpt'] ?? 'glossary';
				?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $first_cpt ) ); ?>" class="button">
					<?php esc_html_e( 'Zobrazit záznamy', 'slovnik-a-feedy' ); ?>
				</a>
				<?php if ( $archive_url ) : ?>
				<a href="<?php echo esc_url( $archive_url ); ?>" class="button" target="_blank" rel="noopener">
					<?php esc_html_e( 'Archiv na webu', 'slovnik-a-feedy' ); ?>
				</a>
				<?php endif; ?>
			</div>
		</div>

	</div><!-- /.saf-columns -->

	<!-- Dokumentace -->
	<div class="saf-panel saf-panel--full">
		<h2 class="saf-panel__title">
			<span class="dashicons dashicons-info"></span>
			<?php esc_html_e( 'Jak plugin funguje', 'slovnik-a-feedy' ); ?>
		</h2>
		<div class="saf-docs">
			<div class="saf-docs__col">
				<h3><?php esc_html_e( 'Co je to CPT stream?', 'slovnik-a-feedy' ); ?></h3>
				<p><?php esc_html_e( 'Plugin registruje vlastní typ příspěvku (CPT) „glossary", který funguje jako samostatný blog – má vlastní archiv, single stránky i RSS feedy, ale je zcela oddělen od klasických příspěvků.', 'slovnik-a-feedy' ); ?></p>

				<h3><?php esc_html_e( 'Proč jsou feedy důležité?', 'slovnik-a-feedy' ); ?></h3>
				<p><?php esc_html_e( 'RSS feed umožňuje napojit obsah na Zapier, Make, IFTTT nebo vlastní skripty. Každé písmeno A–Z má vlastní feed, takže lze automatizovat i filtrovací scénáře.', 'slovnik-a-feedy' ); ?></p>
			</div>
			<div class="saf-docs__col">
				<h3><?php esc_html_e( 'Elementor Theme Builder', 'slovnik-a-feedy' ); ?></h3>
				<p><?php esc_html_e( 'CPT je plně kompatibilní s Elementorem. V Theme Builderu vytvoř šablonu pro „Archiv" nebo „Singl příspěvek" a nastav podmínku: Post Type → Pojmy (glossary).', 'slovnik-a-feedy' ); ?></p>

				<h3><?php esc_html_e( 'Rank Math SEO', 'slovnik-a-feedy' ); ?></h3>
				<p><?php esc_html_e( 'Plugin automaticky přidává CPT do Rank Math sitemapy a na single stránkách generuje DefinedTerm schema.org markup pro lepší pochopení obsahu Googlem.', 'slovnik-a-feedy' ); ?></p>
			</div>
			<div class="saf-docs__col">
				<h3><?php esc_html_e( 'Přepisovací pravidla', 'slovnik-a-feedy' ); ?></h3>
				<p><?php esc_html_e( 'Pokud archiv nebo feed nefunguje, přejdi do Nastavení → Permalinks a klikni na Uložit. Tím se přegenerují přepisovací pravidla.', 'slovnik-a-feedy' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Přejít na Permalinks', 'slovnik-a-feedy' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Patička Grou.cz -->
	<div class="saf-footer">
		<span><?php esc_html_e( 'Plugin Slovník a Feedy je součástí rodiny nástrojů', 'slovnik-a-feedy' ); ?> <a href="https://grou.cz" target="_blank" rel="noopener">Grou.cz</a></span>
	</div>

</div><!-- /.wrap -->
