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
		<span class="saf-header__version">
			v<?php echo esc_html( SAF_VERSION ); ?>
			&nbsp;·&nbsp;
			<a href="<?php echo esc_url( admin_url( 'update-core.php?force-check=1' ) ); ?>" style="font-size:12px;font-weight:400">
				<?php esc_html_e( '↻ Zkontrolovat aktualizace', 'slovnik-a-feedy' ); ?>
			</a>
		</span>
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

	<!-- Stav funkcí -->
	<div class="saf-panel saf-panel--full">
		<h2 class="saf-panel__title">
			<span class="dashicons dashicons-yes-alt"></span>
			<?php esc_html_e( 'Co plugin umí (aktuální stav)', 'slovnik-a-feedy' ); ?>
		</h2>
		<div class="saf-docs">
			<div class="saf-docs__col">
				<h3>📥 <?php esc_html_e( 'Import obsahu', 'slovnik-a-feedy' ); ?></h3>
				<ul style="margin:0;padding-left:16px;font-size:13px;color:#555;line-height:1.8">
					<li><?php esc_html_e( 'CSV, XML, Google Sheets (auto-konverze URL)', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Přejmenování maker (snake_case), aliasy (kw, sug_url)', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Jeden sloupec → více polí pluginu (title + slug + focus KW)', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Gutenberg šablona s makry {{makro}} editovatelná vizuálně', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Upsert bez duplicit (_saf_external_id), Rank Math podmíněný zápis', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Dry-run, batch pro velké soubory (WP-Cron), session 7 dní', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Historie importů + resume nedokončeného importu', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Import presety (uložené nastavení pro opakování)', 'slovnik-a-feedy' ); ?></li>
				</ul>
			</div>
			<div class="saf-docs__col">
				<h3>📊 <?php esc_html_e( 'Streamy & SEO', 'slovnik-a-feedy' ); ?></h3>
				<ul style="margin:0;padding-left:16px;font-size:13px;color:#555;line-height:1.8">
					<li><?php esc_html_e( 'Více CPT streamů (slovníček, blog, katalog…)', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'RSS/Atom feedy pro každý stream + taxonomie A–Z + kategorie', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Rank Math sitemap, DefinedTerm schema.org', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Elementor & Crocoblock/JetEngine kompatibilita', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'REST API pro každý stream (Gutenberg + Elementor Loop)', 'slovnik-a-feedy' ); ?></li>
				</ul>
				<h3 style="margin-top:12px">📈 <?php esc_html_e( 'Analytics', 'slovnik-a-feedy' ); ?></h3>
				<ul style="margin:0;padding-left:16px;font-size:13px;color:#555;line-height:1.8">
					<li><?php esc_html_e( 'Sledování zobrazení, kliknutí, průměrné doby na stránce', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Top 10 nejlepších / Bottom 10 nejslabších stránek', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Trend vs. předchozí období, sparklines, filtry', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Boti filtrováni, admini volitelně (Nastavení)', 'slovnik-a-feedy' ); ?></li>
				</ul>
			</div>
			<div class="saf-docs__col">
				<h3>⚠️ <?php esc_html_e( 'Připravuje se', 'slovnik-a-feedy' ); ?></h3>
				<ul style="margin:0;padding-left:16px;font-size:13px;color:#888;line-height:1.8">
					<li><?php esc_html_e( 'Export CSV/XML (round-trip)', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Plánovaný re-import z Google Sheets (Cron)', 'slovnik-a-feedy' ); ?></li>
					<li><?php esc_html_e( 'Google Sheets API v4 (OAuth, soukromé sheety)', 'slovnik-a-feedy' ); ?></li>
				</ul>
				<h3 style="margin-top:12px">🔧 <?php esc_html_e( 'Rychlé tipy', 'slovnik-a-feedy' ); ?></h3>
				<p style="font-size:12px;color:#666">
					<?php esc_html_e( 'Nový stream nebo feed nefunguje?', 'slovnik-a-feedy' ); ?><br>
				</p>
				<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>" class="button button-secondary" style="font-size:12px">
					<?php esc_html_e( '→ Nastavení → Permalinks → Uložit', 'slovnik-a-feedy' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Nástroje / Oprava dat -->
	<div class="saf-panel saf-panel--full">
		<h2 class="saf-panel__title">
			<span class="dashicons dashicons-admin-tools"></span>
			<?php esc_html_e( 'Nástroje', 'slovnik-a-feedy' ); ?>
		</h2>
		<div class="saf-docs">
			<div class="saf-docs__col">
				<h3><?php esc_html_e( 'Oprava Rank Math FAQ bloků', 'slovnik-a-feedy' ); ?></h3>
				<p style="font-size:13px;color:#555">
					<?php esc_html_e( 'Po importu mohou FAQ bloky zobrazovat chybu "neplatný obsah". Toto tlačítko odstraní nesprávné HTML a nechá Rank Math vygenerovat obsah správně.', 'slovnik-a-feedy' ); ?>
				</p>
				<?php
				$streams_for_tools = \SlovnikAFeedy\StreamManager::get_all();
				$faq_nonce = wp_create_nonce( 'saf_fix_faq' );
				?>
				<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">
					<?php foreach ( $streams_for_tools as $stream ) : ?>
					<button type="button"
						class="button saf-faq-fix-btn"
						data-cpt="<?php echo esc_attr( $stream['cpt'] ); ?>"
						data-nonce="<?php echo esc_attr( $faq_nonce ); ?>"
						data-ajax="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">
						🔧 <?php echo esc_html( $stream['name'] ); ?>
					</button>
					<?php endforeach; ?>
				</div>
				<p id="saf-faq-fix-status" style="margin-top:8px;font-size:13px;color:#2d7738;display:none"></p>
				<script>
				document.querySelectorAll('.saf-faq-fix-btn').forEach(function(btn){
					btn.addEventListener('click', function(){
						btn.disabled = true;
						var origFaqLabel = '🔧 ' + btn.dataset.cpt;
						btn.textContent = '⏳ Opravuji...';
						var status = document.getElementById('saf-faq-fix-status');
						fetch(btn.dataset.ajax, {
							method:'POST',
							headers:{'Content-Type':'application/x-www-form-urlencoded'},
							body:'action=saf_fix_faq&nonce='+btn.dataset.nonce+'&cpt='+btn.dataset.cpt
						}).then(function(r){return r.json();}).then(function(d){
							btn.disabled = false;
							btn.textContent = origFaqLabel;
							if(d.success){
								status.textContent = '✓ ' + d.data.message;
								status.style.display = '';
								status.style.color = '#2d7738';
							} else {
								status.textContent = '✗ ' + (d.data || 'Chyba');
								status.style.display = '';
								status.style.color = '#e94560';
							}
						}).catch(function(e){
							btn.disabled = false;
							btn.textContent = origFaqLabel;
							status.textContent = '✗ Chyba sítě: ' + e.message;
							status.style.display = '';
							status.style.color = '#e94560';
						});
					});
				});
				</script>
			</div>

			<div class="saf-docs__col">
				<h3><?php esc_html_e( 'Oprava JSON-LD schema (viditelný text)', 'slovnik-a-feedy' ); ?></h3>
				<p style="font-size:13px;color:#555">
					<?php esc_html_e( 'Po importu CSV se JSON-LD FAQ schema zobrazuje jako viditelný text na stránce – import odstranil &lt;script&gt; tagy. Toto tlačítko je obalí zpět.', 'slovnik-a-feedy' ); ?>
				</p>
				<?php
				$jsonld_nonce = wp_create_nonce( 'saf_fix_json_ld' );
				?>
				<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">
					<?php foreach ( $streams_for_tools as $stream ) : ?>
					<button type="button"
						class="button saf-jsonld-fix-btn"
						data-cpt="<?php echo esc_attr( $stream['cpt'] ); ?>"
						data-nonce="<?php echo esc_attr( $jsonld_nonce ); ?>"
						data-ajax="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">
						📄 <?php echo esc_html( $stream['name'] ); ?>
					</button>
					<?php endforeach; ?>
				</div>
				<p id="saf-jsonld-fix-status" style="margin-top:8px;font-size:13px;color:#2d7738;display:none"></p>
				<script>
				document.querySelectorAll('.saf-jsonld-fix-btn').forEach(function(btn){
					btn.addEventListener('click', function(){
						btn.disabled = true;
						var origLabel = btn.textContent;
						btn.textContent = '⏳ Opravuji...';
						var status = document.getElementById('saf-jsonld-fix-status');
						fetch(btn.dataset.ajax, {
							method:'POST',
							headers:{'Content-Type':'application/x-www-form-urlencoded'},
							body:'action=saf_fix_json_ld&nonce='+btn.dataset.nonce+'&cpt='+btn.dataset.cpt
						}).then(function(r){return r.json();}).then(function(d){
							btn.disabled = false;
							btn.textContent = origLabel;
							if(d.success){
								status.textContent = '✓ ' + d.data.message;
								status.style.display = '';
								status.style.color = '#2d7738';
							} else {
								status.textContent = '✗ ' + (d.data || 'Chyba');
								status.style.display = '';
								status.style.color = '#e94560';
							}
						}).catch(function(e){
							btn.disabled = false;
							btn.textContent = origLabel;
							status.textContent = '✗ Chyba sítě: ' + e.message;
							status.style.display = '';
							status.style.color = '#e94560';
						});
					});
				});
				</script>
			</div>
		</div>
	</div>

	<!-- Synchronizace náhledových obrázků -->
	<div class="saf-panel saf-panel--full">
		<h2 class="saf-panel__title">
			<span class="dashicons dashicons-format-image"></span>
			<?php esc_html_e( 'Synchronizace náhledových obrázků', 'slovnik-a-feedy' ); ?>
		</h2>
		<p style="font-size:13px;color:#555;max-width:640px">
			<?php esc_html_e( 'Zkopíruje náhledové obrázky (featured image) ze zdrojového CPT do cílového CPT podle shodného slugu. Přeskočí posty, které thumbnail již mají.', 'slovnik-a-feedy' ); ?>
		</p>
		<?php
		$thumb_nonce   = wp_create_nonce( 'saf_sync_thumbnails' );
		$streams_thumb = \SlovnikAFeedy\StreamManager::get_all();
		// Načti všechny registrované veřejné CPT jako možné zdroje.
		$all_cpts = get_post_types( [ 'public' => true ], 'objects' );
		unset( $all_cpts['attachment'] );
		?>
		<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-top:12px">
			<label style="font-size:13px">
				<?php esc_html_e( 'Zdroj (má obrázky):', 'slovnik-a-feedy' ); ?><br>
				<select id="saf-thumb-source" style="margin-top:4px">
					<?php foreach ( $all_cpts as $cpt_obj ) : ?>
						<option value="<?php echo esc_attr( $cpt_obj->name ); ?>"
							<?php selected( $cpt_obj->name, 'slovicek-pojmu' ); ?>>
							<?php echo esc_html( $cpt_obj->label . ' (' . $cpt_obj->name . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<label style="font-size:13px">
				<?php esc_html_e( 'Cíl (chybí obrázky):', 'slovnik-a-feedy' ); ?><br>
				<select id="saf-thumb-target" style="margin-top:4px">
					<?php foreach ( $streams_thumb as $stream ) : ?>
						<option value="<?php echo esc_attr( $stream['cpt'] ); ?>">
							<?php echo esc_html( $stream['name'] . ' (' . $stream['cpt'] . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="button" id="saf-thumb-sync-btn" class="button button-primary"
				data-nonce="<?php echo esc_attr( $thumb_nonce ); ?>"
				data-ajax="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">
				🖼️ <?php esc_html_e( 'Spustit synchronizaci', 'slovnik-a-feedy' ); ?>
			</button>
		</div>
		<p id="saf-thumb-sync-status" style="margin-top:10px;font-size:13px;display:none"></p>
		<script>
		(function(){
			var btn = document.getElementById('saf-thumb-sync-btn');
			if (!btn) return;
			btn.addEventListener('click', function(){
				var source = document.getElementById('saf-thumb-source').value;
				var target = document.getElementById('saf-thumb-target').value;
				var status = document.getElementById('saf-thumb-sync-status');
				if (source === target) {
					status.textContent = '✗ Zdrojový a cílový CPT musí být různé.';
					status.style.color = '#e94560';
					status.style.display = '';
					return;
				}
				btn.disabled = true;
				btn.textContent = '⏳ Synchronizuji...';
				status.style.display = 'none';
				fetch(btn.dataset.ajax, {
					method: 'POST',
					headers: {'Content-Type': 'application/x-www-form-urlencoded'},
					body: 'action=saf_sync_thumbnails&nonce=' + btn.dataset.nonce
					    + '&source_cpt=' + encodeURIComponent(source)
					    + '&target_cpt=' + encodeURIComponent(target)
				}).then(function(r){ return r.json(); }).then(function(d){
					btn.disabled = false;
					btn.textContent = '🖼️ Spustit synchronizaci';
					if (d.success) {
						status.textContent = '✓ ' + d.data.message;
						status.style.color = '#2d7738';
					} else {
						status.textContent = '✗ ' + (d.data || 'Chyba');
						status.style.color = '#e94560';
					}
					status.style.display = '';
				}).catch(function(e){
					btn.disabled = false;
					btn.textContent = '🖼️ Spustit synchronizaci';
					status.textContent = '✗ Chyba sítě: ' + e.message;
					status.style.color = '#e94560';
					status.style.display = '';
				});
			});
		}());
		</script>
	</div>

	<!-- Patička Grou.cz -->
	<div class="saf-footer">
		<span><?php esc_html_e( 'Plugin Slovník a Feedy je součástí rodiny nástrojů', 'slovnik-a-feedy' ); ?> <a href="https://grou.cz" target="_blank" rel="noopener">Grou.cz</a></span>
	</div>

</div><!-- /.wrap -->
