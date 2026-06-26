# Dev Log – Slovník a Feedy

Plugin Grou.cz | Prefix: `saf_` | Namespace: `SlovnikAFeedy` | Textdomain: `slovnik-a-feedy`

---

## 2026-06-26 – Analytics: deduplikace zobrazení + rozšířená detekce botů

**Problém:** Analytics ukazovaly nafouklá čísla – každé načtení stránky = +1 zobrazení, bez ohledu na to jestli jde o stejného návštěvníka. Zároveň detekce botů nepokrývala výkonnostní nástroje (GTmetrix, Lighthouse) ani headless prohlížeče.

**Změny v `class-tracker.php`:**
- Nová metoda `already_viewed()` – ukládá transient `saf_seen_{ip_hash}_{post_id}_{datum}` na 24h; každá IP se zaznamená max 1× za den na stránku.
- `maybe_track_view()` – voláno před `record_view()` pro kontrolu deduplikace.
- `is_bot()` – rozšířen o: GTmetrix, Pingdom, UptimeRobot, PageSpeed, Lighthouse, WebPageTest, headlesschrome, phantomjs, prerender, selenium, curl, wget, python-requests, go-http-client, okhttp, axios, java/, libwww, perl/.

**Změny v `class-settings.php`:**
- Nový klíč `unique_views` (default `'1'` = zapnuto) v `DEFAULTS` + `save_from_post()`.

**Změny v `views/settings.php`:**
- Nový toggle „Unikátní zobrazení" v sekci Analytics.

---

## 2026-06-23 – v1.0.6 – Thumbnail Sync: párování náhledových obrázků podle slugu

**Nová funkce:** Jednorázové (i opakované) zkopírování `_thumbnail_id` ze zdrojového CPT do cílového CPT podle shodného `post_name` (slugu).

**Použití:** `slovicek-pojmu` (zdroj, 87 postů s obrázky) → `glossary` (cíl, 480 postů bez obrázků)

**Jak funguje:**
1. Načte všechny cílové posty bez `_thumbnail_id` (jeden dotaz s meta_query NOT EXISTS)
2. Předpočítá mapu `slug → thumb_id` ze zdrojového CPT (jeden JOIN dotaz)
3. Pro každý cílový post ověří existenci attachment + zavolá `set_post_thumbnail()`
4. Přeskočí posty, které thumbnail již mají nebo nemají párový zdroj

**Admin UI:** Dashboard → nová sekce "Synchronizace náhledových obrázků" s dropdownem (zdroj/cíl CPT) + tlačítko

**Bezpečnost:**
- Nonce `saf_sync_thumbnails` + cap `manage_glossary` + `return` po každém error
- Target CPT musí být SAF stream (`StreamManager::find_by_cpt()`)
- Source CPT musí být registrovaný (`post_type_exists()`)
- Source ≠ Target guard

**Nové soubory:** `includes/Admin/class-thumbnail-sync.php`
**Upravené soubory:** `class-plugin.php`, `Admin/views/dashboard.php`, `slovnik-a-feedy.php`

---

## 2026-06-23 – v1.0.5 – GitHub Updater: automatické aktualizace z GitHub releases

**Co bylo přidáno:** Nová třída `GithubUpdater` – plugin si teď sám hlídá nové verze na GitHubu.

- Hák `pre_set_site_transient_update_plugins` → injektuje info o nové verzi do WP update systému
- Hák `plugins_api` → WP popup "Details" zobrazí changelog z GitHub release body
- Hák `upgrader_post_install` → přejmenuje složku po upgradu (GitHub zipball má hash v názvu)
- Cache na 12 hodin (transient `saf_github_update_info`)
- Veřejné repo, bez autentizace (GitHub API /releases/latest)

**Registrace:** `GithubUpdater::register()` voláno přímo z `slovnik-a-feedy.php` před `plugins_loaded` – updater běží co nejdříve.

**Nové soubory:** `includes/class-github-updater.php`
**Upravené soubory:** `slovnik-a-feedy.php`

---

## 2026-06-23 – v1.0.4 – Penetrační audit JsonLdFixer: 8 nálezů opraveno

**Audit:** 8 úrovňový code-review (3 correctness + 3 cleanup + altitude + conventions), 9 kandidátů, 9 verifikací.

| # | Závažnost | Nález | Oprava |
|---|-----------|-------|--------|
| 1 | 🔴 Kritická | `(string) preg_replace_callback(...)` → null na PCRE limitu → `wp_update_post('')` vymaže post | Null check před castem; `return -1` |
| 2 | 🔴 Vysoká | Žádný CPT allowlist – `cpt=post` přepíše všechny WP posty | `StreamManager::find_by_cpt()` allowlist + `return` |
| 3 | 🟡 Střední | JS `fetch()` bez `.catch()` – tlačítko trvale zamknuté při síťové chybě | Přidán `.catch()` do obou tlačítek (FAQ i JSON-LD) |
| 4 | 🟡 Střední | `wp_update_post()` return ignorován – DB selhání počítáno jako úspěch | `is_wp_error()` + `=== 0` check; `return -1` na chybu |
| 5 | 🟡 Střední | `strpos` guard příliš široký – přeskočí post s Rank Math `<script>` i když má rozbité JSON-LD | Guard přescopeován na wp:html blok pomocí regex |
| 6 | 🟡 Střední | `posts_per_page=-1` bez timeoutu – riziko OOM/timeout na velkých instalacích | `wp_suspend_cache_invalidation(true/false)` okolo smyčky |
| 7 | 🟠 Nízká | Chybí `return` po `wp_send_json_error()` | `return` přidán za každý `wp_send_json_error()` |
| 8 | 🟠 Nízká | Prázdné `$cpt` → WP fallback na `post_type='post'` | Pokryto allowlistem (#2) – prázdný $cpt `find_by_cpt()` odmítne |
| – | Refuted | json_decode null ambiguity – regex vylučuje false positive | Nevyžaduje opravu |

**Bonus:** JSON re-enkódován přes `wp_json_encode()` aby hodnoty s `</script>` nezlomily markup. Přidán počítač `errors` do response.

**Upravené soubory:** `includes/Admin/class-json-ld-fixer.php`, `includes/Admin/views/dashboard.php`, `slovnik-a-feedy.php`

---

## 2026-06-23 – v1.0.3 – JSON-LD Fixer: oprava viditelného schema textu po importu

**Problém:** Import CSV z live serveru odstranil `<script type="application/ld+json">` tagy (WP sanitizace). JSON-LD FAQ schema se zobrazovalo jako viditelný text na všech 480 stránkách glossary CPT.

**Řešení (dvě části):**

1. **Okamžitá oprava DB** – přímý SQL UPDATE na lokálním prostředí test serveru:
   - Pattern: `</section>\n\n\n{` → `</section>\n\n\n<script type="application/ld+json">\n{`
   - Pattern: `  ]\n}\n\n<!-- /wp:html -->` → `  ]\n}\n</script>\n\n<!-- /wp:html -->`
   - Výsledek: 0 stránek postiženo (all 480 opraveno).

2. **Admin nástroj pro live** – nová třída `JsonLdFixer` + tlačítko v dashboardu:
   - Soubor: `includes/Admin/class-json-ld-fixer.php`
   - AJAX: `wp_ajax_saf_fix_json_ld` (nonce `saf_fix_json_ld`, cap `manage_glossary`)
   - Regex detekce JSON-LD objektu v `<!-- wp:html -->` bez `<script>` obálky + JSON validace
   - Tlačítko v dashboardu → sekce "Nástroje" → "Oprava JSON-LD schema (viditelný text)"
   - Registrace v `class-plugin.php` vedle `FaqFixer::register_ajax()`

**Nové soubory:** `includes/Admin/class-json-ld-fixer.php`
**Upravené soubory:** `class-plugin.php`, `Admin/views/dashboard.php`, `slovnik-a-feedy.php`

---

## 2026-06-22 – v1.0.2 – Kritická oprava: infinite recursion při první instalaci

**Problém:** Plugin nešel aktivovat na live serveru (fresh install). Fatal error: Maximum function nesting level reached.

**Příčina:** `StreamManager::create_default()` volalo `self::get_all()` → `get_all()` vidělo prázdné streamy → volalo `create_default()` → nekonečná rekurze → PHP nesting limit 256 → Fatal error.

Na lokálním prostředí nenastalo protože `saf_streams` option již existovala z předchozí aktivace.

**Oprava:** `create_default()` nyní čte option přímo přes `get_option()` místo přes `get_all()` – odstraní cirkulární závislost.

**Soubor:** `includes/class-stream-manager.php`

**Ověřeno:** wp-cli simulace prázdné DB (smazána `saf_streams` option) → aktivace proběhla úspěšně.

---

## 2026-06-22 – v1.0.1 – Kompletní penetrační audit + opravy

Kompletní bezpečnostní audit všech PHP souborů dle Bezpečnost.txt checklistu.

**Opravené problémy (4 nálezy):**

1. **`docs/faq-debug.php` smazán** *(kritické)*
   - Soubor bez ABSPATH check ani autentizace byl přístupný veřejně a umožňoval číst obsah libovolného postu (i draftu) přes URL parametr `?post_id=N` bez přihlášení. Smazán – šlo o dočasný debug nástroj.

2. **`class-tracker.php` – SQL `prepare()` pro `information_schema` a `SHOW TABLES`** *(kritické)*
   - Tři dotazy používaly interpolaci `'{$table}'` místo `$wpdb->prepare()`. Tabulkové jméno pocházelo z konstanty (nízké reálné riziko), ale porušovalo WPCS standard.
   - Opraveno: všechny dotazy na `information_schema.COLUMNS` a `SHOW TABLES LIKE` nyní přes `$wpdb->prepare()` s `%s` parametry.

3. **`class-tracker.php` – Rate limiting na REST endpointech `/click` a `/time`** *(varování)*
   - Veřejné endpointy (permission_callback = `__return_true`) neměly žádný rate limit – útočník s platnou nonce mohl nafukovat statistiky nebo zatěžovat DB.
   - Opraveno: nová private metoda `check_rate_limit()` – max 60 requestů/min per IP hash (transient `saf_track_rl_{ip_hash}`). Vrací HTTP 429 při překročení.

4. **`class-import-page.php` – SSRF bypass přes redirect při stahování GSheet** *(varování)*
   - `wp_remote_get()` automaticky sledoval přesměrování a nekontroloval, zda cílový host 3xx přesměrování je stále na whitelistu Google domén.
   - Opraveno: `'redirection' => 0` + ruční zpracování 3xx s validací `Location` hosta proti whitelistu před dalším requestem.

**Potvrzeno jako OK (žádné kritické nálezy v ostatním kódu):**
- ABSPATH ve všech zbývajících PHP souborech ✅
- Všechny AJAX handlery: `check_ajax_referer()` + `current_user_can('manage_glossary')` ✅
- `$wpdb->prepare()` všude kde jsou user data ✅
- Output escaping (`esc_html`, `esc_attr`, `esc_url`) ve všech views ✅
- Upload: MIME validace + path traversal ochrana ✅
- Žádné `eval()`, `exec()`, `unserialize()` na user datech ✅
- XXE ochrana: `LIBXML_NONET` při XML parsování ✅
- SSRF (Google Sheets): whitelist hosts + schema check ✅

**PHP lint: OK (žádné chyby na všech PHP souborech)**

---

## 2026-06-19 – Bugfixes, FAQ, Analytics, Import UX

### Import – kritické opravy
- **Array to string crash**: multi-makro aliasy (`kw, sug_url`) fungovaly jako PHP klíč → crash → broken FAQ JSON. Opraveno iterací přes aliasy.
- **Re-import 0 řádků**: temp CSV soubor byl smazán → fopen selhal tiše → 0/0/0 bez chyby. Fix: auto-re-download z `source_url` (Google Sheets) nebo jasná chyba pro CSV.
- **import.php Fatal line 154**: `$all_sessions` null na krocích 2/3 – přesunuto inicializování před `if ($step === 0)`.
- **import.php PHP kód lekal**: chybělo `<?php` po `endif; ?>` u notice bloku.
- **Bílá obrazovka**: `setupModalObserver()` v saf-admin.js spouštěl MutationObserver loop přes DOM insertBefore → React re-render → prázdná stránka. Odstraněno.
- **Re-import ze šablony**: session_id chyběl v URL "Otevřít v editoru" → sidebar neukazoval nová makra. Fix: JS přidává `saf_session` + synchronní AJAX před otevřením editoru.

### FAQ blok – kompletní řešení
- **JSON encoding v block komentářích** (krok 0a): `{{macro}}` uvnitř `<!-- wp:... -->` JSON → `json_encode()` místo `esc_html()`.
- **JSON encoding v JSON-LD skriptech** (krok 0b): `<script type="application/ld+json">` → makra JSON-encodována. Opravuje "Elementor Loop nefunguje" způsobené neplatným JSON-LD → JS error.
- **FaqFixer tool**: Dashboard → Nástroje → tlačítko per stream. Bulk oprava existujících postů – regeneruje HTML z JSON atributů přesně jako Rank Math save().
- **Odkaz na Gutenberg block**: `wp_kses_post()` odstraněno z `post_content` v Importeru (oříznulo block komentáře). Import nyní ukládá raw content přes `wp_insert_post()` s admin capability.

### Analytics – dokončení
- **avg_time sloupce**: `time_total`, `time_count` přidány přes `ALTER TABLE IF NOT EXISTS`. AJAX endpoint `saf_fix_analytics_table` v Dashboard.
- **Tracking admins**: nastavení `saf_track_admins` v Nastavení. Diagnostický banner v Analytics pokud je vypnuto.
- **AnalyticsStore**: bezpečný query pokud sloupce neexistují (`has_time_columns()`).
- **saf-tracker.js**: `timeSent` flag – zabraňuje dvojímu odeslání doby (pagehide + beforeunload).
- **Bottom 10 přepínač**: toggle "zobrazit i s 0 zobrazeními" / "jen 1+".
- **Analytics view přepis**: Top 10, Bottom 10, avg_time sloupec, diagnostiky, prázdný stav s návodem.

### Notifikace
- `saf-admin.js`: odstraněny veškeré DOM přesuny (způsobovaly bílou obrazovku). Ponechán jen auto-scroll na `.saf-inline-error`.
- `admin_head`: CSS + JS skrytí Elementor License Mismatch na SAF stránkách (text-based detection po 800ms).

### Ostatní
- **Export CSV/XML**: `Exporter/class-exporter.php` + Admin stránka Export.
- **Thumbnail mapping**: Mapper::FIELDS + `set_thumbnail()` v Importeru (URL → media_sideload_image).
- **Preset + source_url**: preset importu ukládá URL Google Sheetu pro opakované použití.
- **Import historie – Znovu**: tlačítko ↻ v historii, obnoví session nebo předvyplní URL.
- **A-Z auto-detekce**: fallback na slug/external_id pokud title není namapován.
- **Multi-pole per sloupec**: `mapping[col][]` → Mapper podporuje array field_slug.
- **FaqFixer**: Dashboard → Nástroje → bulk fix FAQ bloků.
- **Makra sidebar v Gutenberg**: `saf_tpl_macros_{template_id}` option, synchronní update před otevřením editoru.

---

## 2026-06-18 – Audit + doplnění chybějících funkcí

### Analytics (kompletní redesign)
- `class-tracker.php` – sledování doby na stránce (`time_total`, `time_count`), REST `/saf/v1/time`
- `class-analytics-store.php` – `avg_time` výpočet v `get_pages()`, řazení i dle avg_time
- `class-analytics-page.php` – přidány `$top_pages`, `$bottom_pages`, `$tracking_active`
- `views/analytics.php` – kompletní přepis: diagnostika (admin nesledován), Top 10, Bottom 10, avg_time sloupec, trendy per řádek, prázdný stav s návodem
- `class-settings.php` + `views/settings.php` – přidán `saf_track_admins` přepínač
- `saf-tracker.js` – `pagehide`/`beforeunload` odesílá dobu strávenu na stránce

### Export CSV/XML (Fáze 5)
- `Exporter/class-exporter.php` – export pojmů do CSV (s UTF-8 BOM) nebo XML, round-trip schéma
- `views/export.php` – export stránka s výběrem streamu a formátu
- `class-admin-menu.php` – přidáno submenu Export

### Import vylepšení
- Více polí pluginu pro jeden sloupec (multi-select, `mapping[col][]`)
- `Mapper::map_row()` – podpora array field_slug
- `class-settings.php` – `get_import_presets()`, `save_import_preset()`, source_url v presetu
- `class-import-session-registry.php` – registr nedokončených importů, resume, mazání
- `views/import.php` – nedokončené importy panel, historie, error v panelu (ne na vrchu)
- `saf-admin.js` – WP notices přesun nad .saf-header, auto-scroll na chybu

### Dashboard
- Aktualizována sekce "Co plugin umí" (aktuální stav místo "Připravuje se")
- Navigační grid – jasné zkratky ke klíčovým sekcím

### Bezpečnostní audit
- Všechny nové formuláře mají nonce + capability check
- Export: `current_user_can('manage_glossary')` před stažením

---

## 2026-06-18 – Fáze 2: Import – CSV, XML, Template Engine, Upsert, Admin wizard

**Co bylo uděláno:**

### Importer moduly
- `interface-source.php` – kontrak pro všechny zdroje (`get_rows(): iterable`, `get_columns(): array`)
- `class-csv-source.php` – streamované `fgetcsv`, UTF-8 BOM, auto-detekce oddělovače (`,;|\t`), path traversal ochrana
- `class-xml-source.php` – `simplexml_load_file` s `LIBXML_NONET` (XXE ochrana), atributy i child elementy jako sloupce
- `class-array-source.php` – adapter pro PHP pole (použit BatchRunnerem)
- `class-mapper.php` – whitelist polí (`title, excerpt, slug, status, seo_*, letter, category, external_id`), auto-mapování dle aliasů, `map_row()`
- `class-template-engine.php` – `{{col}}` escape, `{{{col}}}` raw HTML (wp_kses), `{{#if}}`, `{{#each|sep}}`, výchozí šablona
- `class-importer.php` – upsert dle `_saf_external_id`, `wp_insert_post`, taxonomie auto-assign z titulku (`get_letter_slug`), Rank Math podmíněný zápis, dry-run režim, logování každého řádku
- `class-batch-runner.php` – synchronní import ≤200 řádků, WP-Cron dávky pro větší soubory (configurable batch size), progress tracking

### Admin
- `class-settings.php` – centralizované options (`saf_*`), import profily (uložené v DB option), `save_from_post()` se sanitizací
- `class-import-page.php` – multi-step wizard (krok 0–3), transient session, upload s MIME validací, Google Sheets URL stažení (SSRF whitelist: pouze docs.google.com), temp soubory v `uploads/saf-imports/` (chráněno .htaccess)
- `class-admin-menu.php` – přidána submenu: Import, Nastavení; handler mazání profilů
- `views/import.php` – kompletní wizard UI (zdroj → mapování → šablona s live preview → výsledky)
- `views/settings.php` – nastavení (default status, GSheet URL, schedule, batch size, log retention, profily)
- `class-plugin.php` – registrace `BatchRunner::register_hooks()`, `admin_post_saf_delete_profile`

### Bezpečnost (audit)
- ✅ MIME validace uploadů (`wp_check_filetype_and_ext`)
- ✅ Path traversal ochrana v CsvSource/XmlSource (realpath + upload_dir check)
- ✅ XXE ochrana v XmlSource (LIBXML_NONET)
- ✅ SSRF whitelist pro Google Sheets URL
- ✅ Nonce na každém formuláři (step-specific: `saf_import_step_N`)
- ✅ Whitelist pole Mapperu (nelze mapovat na neznámé klíče)
- ✅ Sanitizace: `sanitize_text_field`, `wp_kses_post`, `esc_url_raw`, `absint` dle typu
- ✅ `current_user_can('manage_glossary')` na každé stránce

**Jak otestovat Fázi 2:**
1. WP Admin → Slovník a Feedy → Import
2. Nahraj CSV soubor (1. řádek = hlavička)
3. Namapuj sloupce → pokračuj na šablonu
4. Klikni Dry-run – zkontroluj logy
5. Spusť import → Slovník a Feedy → Všechny pojmy

---

## 2026-06-18 – perf: Odstranění user_has_cap filtru

**Problém:** `user_has_cap` filtr se spouštěl na každý `current_user_can()` call – stovkykrát za stránku.
**Řešení:** Nahrazeno jednorázovým `admin_init` checkem, který capability doplní jen pokud chybí. Netváří se jako fallback při každém requestu.

---

## 2026-06-18 – fix: Zařazení do skupiny Grou.cz v admin menu

**Co bylo uděláno:**
- Zkopírován sdílený soubor `includes/grou-admin-group.php` (vzor z smartemailing-connect)
- `class-admin-menu.php` – přidáno `require_once grou-admin-group.php`, volání `grou_register_admin_menu_group(33)` na `admin_menu` priorita 999, `grou_output_admin_group_css()` na `admin_head`
- Pozice menu změněna z 26 → **33** (skupina: Emailing 30, SmartEmailing 31, SEO Booster 32, Slovník a Feedy 33)
- `Workflow.txt` doplněn o povinný postup zařazení do Grou.cz při zakládání každého nového pluginu

---

## 2026-06-18 – Fáze 1: Bootstrap + CPT + Taxonomie + Feedy + Sitemapa

**Co bylo uděláno:**

### Struktura pluginu
- Vytvořena kompletní adresářová struktura dle specifikace
- `slovnik-a-feedy.php` – hlavička pluginu, konstanty (`SAF_VERSION`, `SAF_DIR`, `SAF_URL`…), PSR-4 autoloader via `spl_autoload_register`
- `uninstall.php` – maže DB tabulku `saf_logs`, options `saf_*`, odebírá capability
- `composer.json` – devDependencies pro PHPCS/WPCS

### Jádro
- `class-plugin.php` – singleton orchestrátor, registrace všech hooků, capability fallback pro administrátory
- `class-activator.php` – CPT + taxonomie registrace před flush, seed písmen A–Z, vytvoření logger tabulky, přidání `manage_glossary` capability
- `class-deactivator.php` – odplánování WP-Cron úloh, flush rewrite rules

### CPT `glossary`
- URL archiv: `/slovnik/`
- URL single: `/slovnik/{slug}/`
- **Feedy: `/slovnik/feed/` (RSS2), `/slovnik/feed/atom/`** – aktivováno přes `'feeds' => true` v rewrite
- `show_in_rest => true` – plná kompatibilita s Elementor Theme Builder
- REST base: `wp/v2/glossary`
- Supports: title, editor, excerpt, custom-fields, thumbnail, revisions, author

### Taxonomie
- `glossary_letter` (flat) – slug `/pismeno/{a}/`, feed `/pismeno/a/feed/`
  - Při aktivaci automaticky předvyplněna písmeny A–Z + skupina 0–9
  - Správa omezena na capability `manage_glossary`
- `glossary_cat` (hierarchická) – slug `/kategorie-slovniku/{slug}/`, feed aktivní

### SEO
- `class-schema.php` – filtr `rank_math/json_ld`, přidává `DefinedTerm` schema node na single stránkách pojmu (name, description, inDefinedTermSet → archiv URL)
- Filtr `rank_math/sitemap/post_types` – CPT glossary je v Rank Math sitemapě

### Admin
- `class-admin-menu.php` – menu „Slovník a Feedy" (ikona dashicons-rss, pozice 26)
  - Submenu: Přehled (dashboard), Logy
  - Capability: `manage_glossary` (s fallbackem pro `manage_options`)
- `views/dashboard.php` – statistiky, feed URLs, rychlé akce, dokumentace, Grou.cz branding
- `views/logs.php` – tabulka logů s filtrováním (level, context), stránkování, čištění starých záznamů
- `assets/css/admin.css` – vlastní styly v Grou.cz brand colors (#1a1a2e, #e94560)

### Logger
- DB tabulka `{prefix}saf_logs` (id, created_at, level, context, message, data)
- Statické metody: `Logger::info()`, `Logger::warning()`, `Logger::error()`
- `Logger::get_entries()` s filtrováním a stránkováním
- `Logger::purge()` pro mazání starých záznamů

### Helpers
- `Helpers::get_archive_feed_url()` – URL feed archivu (RSS2/Atom)
- `Helpers::get_term_feed_url()` – URL feed taxonu
- `Helpers::get_post_counts()` – statistiky pojmů
- `Helpers::get_letter_slug()` – odvození písmene z titulku

**Bezpečnost (audit před Fází 1):**
- ✅ ABSPATH check v každém PHP souboru
- ✅ `current_user_can('manage_glossary')` v každém admin render callbacku
- ✅ nonce ve formuláři čištění logů (`wp_nonce_field` + `wp_verify_nonce`)
- ✅ Sanitizace všech `$_GET`/`$_POST` vstupů
- ✅ Escapování všech výstupů (`esc_html`, `esc_attr`, `esc_url`)
- ✅ DB queries přes `$wpdb->prepare()` nebo WP API
- ✅ Logger používá `$wpdb->insert()` s typovými formáty

**Jak otestovat:**
1. Aktivovat plugin → zkontrolovat, že nevznikla PHP chyba
2. Přejít na Nastavení → Permalinks → Uložit
3. Ověřit URL: `/slovnik/` (archiv), `/slovnik/feed/` (RSS feed)
4. WP Admin → Slovník a Feedy → Přehled (dashboard)
5. Rank Math → Sitemap → zkontrolovat přítomnost `glossary`
6. Přidat testovací pojem → ověřit DefinedTerm v JSON-LD (DevTools → Network)

---

## Backlog (Fáze 2–5)

- [ ] **Fáze 2:** Source interface + CSV zdroj + Mapper + Template Engine + Importer (upsert bez duplicit)
- [ ] **Fáze 3:** Rank Math Writer (podmíněný zápis) + DefinedTerm doplnění
- [ ] **Fáze 4:** Google Sheets zdroj + plánovaný re-import + WP-Cron dávkování
- [ ] **Fáze 5:** Export CSV/XML + dry-run náhled + admin doladění + statistiky výkonu
