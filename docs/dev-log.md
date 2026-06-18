# Dev Log – Slovník a Feedy

Plugin Grou.cz | Prefix: `saf_` | Namespace: `SlovnikAFeedy` | Textdomain: `slovnik-a-feedy`

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
