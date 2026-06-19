<?php
/**
 * Template engine – Gutenberg blokový markup s makry.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderuje šablonu s daty z jednoho řádku importu.
 *
 * Syntax:
 *   {{sloupec}}                        – prostá substituce (HTML-escaped)
 *   {{{sloupec}}}                      – raw HTML (pouze pro důvěryhodné zdroje)
 *   {{#if sloupec}}…{{/if}}            – podmíněný blok (renderuj jen pokud neprázdné)
 *   {{#each sloupec}}…{{item}}…{{/each}}          – smyčka, oddělovač čárka
 *   {{#each sloupec|;}}…{{item}}…{{/each}}        – smyčka s vlastním oddělovačem
 *
 * Výstup je platný Gutenberg blokový markup – blokové komentáře jsou součástí šablony,
 * engine pouze plní makra daty.
 */
final class TemplateEngine {

	public function render( string $template, array $row ): string {
		// 0a. Zpracuj makra v Gutenberg block komentářích (JSON context).
		//     "{{macro}}" → json_encode(hodnota) aby JSON zůstal validní.
		$template = (string) preg_replace_callback(
			'/<!--\s*wp:[a-z][^\-].*?-->/s',
			static function ( array $m ) use ( $row ): string {
				return (string) preg_replace_callback(
					'/"(\{\{[\w\-]+\}\})"/',
					static function ( array $n ) use ( $row ): string {
						$macro = trim( $n[1], '{}' );
						$val   = $row[ $macro ] ?? '';
						return (string) json_encode( $val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
					},
					$m[0]
				);
			},
			$template
		);

		// 0b. Zpracuj makra v JSON-LD <script> blocích (application/ld+json).
		//     Hodnoty musí být JSON-escapovány – esc_html() by rozbil JSON a způsobil
		//     JS chybu na stránce (Elementor Loop přestane fungovat).
		$template = (string) preg_replace_callback(
			'/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si',
			static function ( array $m ) use ( $row ): string {
				$inner = (string) preg_replace_callback(
					'/"(\{\{[\w\-]+\}\})"/',
					static function ( array $n ) use ( $row ): string {
						$macro = trim( $n[1], '{}' );
						$val   = $row[ $macro ] ?? '';
						return (string) json_encode( $val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
					},
					$m[1]
				);
				// Obnov původní wrapper <script> tag.
				return substr( $m[0], 0, strpos( $m[0], '>' ) + 1 ) . $inner . '</script>';
			},
			$template
		);

		// 1. Podmíněné bloky {{#if col}}…{{/if}}.
		$template = (string) preg_replace_callback(
			'/\{\{#if\s+([\w\-]+)\}\}(.*?)\{\{\/if\}\}/s',
			static function ( array $m ) use ( $row ): string {
				$value = trim( $row[ $m[1] ] ?? '' );
				return $value !== '' ? $m[2] : '';
			},
			$template
		);

		// 2. Smyčky {{#each col|sep}}…{{item}}…{{/each}}.
		$template = (string) preg_replace_callback(
			'/\{\{#each\s+([\w\-]+)(?:\|([^}]*))?\}\}(.*?)\{\{\/each\}\}/s',
			static function ( array $m ) use ( $row ): string {
				$value = trim( $row[ $m[1] ] ?? '' );
				if ( $value === '' ) {
					return '';
				}
				$sep   = isset( $m[2] ) && $m[2] !== '' ? $m[2] : ',';
				$items = array_filter( array_map( 'trim', explode( $sep, $value ) ) );
				$out   = '';
				foreach ( $items as $item ) {
					$out .= str_replace( '{{item}}', esc_html( $item ), $m[3] );
				}
				return $out;
			},
			$template
		);

		// 3. Raw HTML {{{sloupec}}} – zachová HTML z dat (wp_kses_post pro bezpečnost).
		$template = (string) preg_replace_callback(
			'/\{\{\{([\w\-]+)\}\}\}/',
			static function ( array $m ) use ( $row ): string {
				return wp_kses_post( $row[ $m[1] ] ?? '' );
			},
			$template
		);

		// 4. Prostá substituce {{sloupec}} – HTML escape.
		$template = (string) preg_replace_callback(
			'/\{\{([\w\-]+)\}\}/',
			static function ( array $m ) use ( $row ): string {
				return esc_html( $row[ $m[1] ] ?? '' );
			},
			$template
		);

		// 5. Odstraň prázdné Gutenberg bloky (sloupec v tabulce byl prázdný).
		//    Např. <!-- wp:paragraph --><p></p><!-- /wp:paragraph --> → smazat.
		$template = static::remove_empty_blocks( $template );

		return $template;
	}

	// -------------------------------------------------------------------------

	/**
	 * Odstraní prázdné Gutenberg bloky vzniklé po substituci prázdného makra.
	 *
	 * Odstraňuje vzory jako:
	 *   <!-- wp:paragraph --><p></p><!-- /wp:paragraph -->
	 *   <!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->
	 *   atd.
	 *
	 * Také odstraní řádky které po substituci zůstanou jen s bílými znaky.
	 */
	private static function remove_empty_blocks( string $content ): string {
		// Odstraň bloky s prázdným HTML elementem uvnitř.
		$content = (string) preg_replace(
			'#<!--\s*wp:[a-z\-]+[^>]*-->\s*<[a-z0-9]+[^>]*>\s*</[a-z0-9]+>\s*<!--\s*/wp:[a-z\-]+\s*-->\n?#i',
			'',
			$content
		);

		// Odstraň wp:separator bloky na opakovaných místech (více za sebou).
		$content = (string) preg_replace(
			'/(<!--\s*wp:separator[^>]*-->.*?<!--\s*\/wp:separator\s*-->)\s*\n?\s*\1/si',
			'$1',
			$content
		);

		// Smaž prázdné řádky vzniklé po odstranění bloků (max 2 za sebou).
		$content = (string) preg_replace( '/\n{3,}/', "\n\n", $content );

		return trim( $content );
	}

	/**
	 * Výchozí šablona pro nový import.
	 * Ukazuje základní strukturu Gutenberg bloků s makry.
	 */
	public static function default_template(): string {
		return <<<'TEMPLATE'
{{#if content}}
<!-- wp:paragraph -->
<p>{{content}}</p>
<!-- /wp:paragraph -->
{{/if}}

{{#if excerpt}}
<!-- wp:quote -->
<blockquote class="wp-block-quote">
<p>{{excerpt}}</p>
</blockquote>
<!-- /wp:quote -->
{{/if}}
TEMPLATE;
	}

	// -------------------------------------------------------------------------
	// Auto-generace template z block typů.

	/**
	 * Dostupné typy bloků pro mapování.
	 * Klíč = hodnota selectu, hodnota = zobrazovaný název.
	 *
	 * @return array<string, string>
	 */
	public static function get_block_types(): array {
		return [
			''            => __( '— nezařadit do obsahu —', 'slovnik-a-feedy' ),
			'paragraph'   => __( 'Odstavec (p)', 'slovnik-a-feedy' ),
			'heading-2'   => __( 'Nadpis H2', 'slovnik-a-feedy' ),
			'heading-3'   => __( 'Nadpis H3', 'slovnik-a-feedy' ),
			'heading-4'   => __( 'Nadpis H4', 'slovnik-a-feedy' ),
			'list'        => __( 'Odrážkový seznam (split čárkou)', 'slovnik-a-feedy' ),
			'list-num'    => __( 'Číslovaný seznam (split čárkou)', 'slovnik-a-feedy' ),
			'quote'       => __( 'Citace / blockquote', 'slovnik-a-feedy' ),
			'separator'   => __( 'Oddělovač (HR)', 'slovnik-a-feedy' ),
			'preformatted'=> __( 'Kód / předformátovaný text', 'slovnik-a-feedy' ),
		];
	}

	/**
	 * Vygeneruje Gutenberg blokovou šablonu automaticky z mapování block typů.
	 *
	 * @param array<string, string>  $block_mapping   source_col => block_type
	 * @param list<string>           $column_order    pořadí sloupců (z hlavičky souboru)
	 */
	public static function generate_from_block_types(
		array $block_mapping,
		array $column_order
	): string {
		$parts = [];

		// Projdi sloupce v pořadí jako v souboru.
		foreach ( $column_order as $col ) {
			$block_type = $block_mapping[ $col ] ?? '';
			if ( ! $block_type || $block_type === '' ) {
				continue;
			}

			$macro = '{{' . $col . '}}';
			$part  = static::render_block_type( $col, $macro, $block_type );

			if ( $part !== '' ) {
				// Obal podmíněným blokem – prázdné hodnoty nevygenerují prázdný blok.
				$parts[] = "{{#if {$col}}}\n{$part}\n{{/if}}";
			}
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Vrátí Gutenberg blokový markup pro jeden sloupec + typ bloku.
	 */
	private static function render_block_type( string $col, string $macro, string $block_type ): string {
		return match ( $block_type ) {
			'heading-2' => "<!-- wp:heading {\"level\":2} -->\n<h2 class=\"wp-block-heading\">{$macro}</h2>\n<!-- /wp:heading -->",
			'heading-3' => "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">{$macro}</h3>\n<!-- /wp:heading -->",
			'heading-4' => "<!-- wp:heading {\"level\":4} -->\n<h4 class=\"wp-block-heading\">{$macro}</h4>\n<!-- /wp:heading -->",
			'paragraph' => "<!-- wp:paragraph -->\n<p>{$macro}</p>\n<!-- /wp:paragraph -->",
			'quote'     => "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>{$macro}</p></blockquote>\n<!-- /wp:quote -->",
			'list'      => "<!-- wp:list -->\n<ul class=\"wp-block-list\">{{#each {$col}}}<!-- wp:list-item --><li>{{item}}</li><!-- /wp:list-item -->{{/each}}</ul>\n<!-- /wp:list -->",
			'list-num'  => "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\">{{#each {$col}}}<!-- wp:list-item --><li>{{item}}</li><!-- /wp:list-item -->{{/each}}</ol>\n<!-- /wp:list -->",
			'separator' => "<!-- wp:separator -->\n<hr class=\"wp-block-separator\"/>\n<!-- /wp:separator -->",
			'preformatted' => "<!-- wp:preformatted -->\n<pre class=\"wp-block-preformatted\">{$macro}</pre>\n<!-- /wp:preformatted -->",
			default     => '',
		};
	}

	/**
	 * Vrátí ukázku syntaxe maker pro nápovědu v adminu.
	 *
	 * @return array<string, string>  makro => popis
	 */
	public static function get_syntax_help(): array {
		return [
			'{{název_sloupce}}'                        => 'Vloží hodnotu sloupce (HTML-safe)',
			'{{{název_sloupce}}}'                      => 'Vloží raw HTML z dat (filtrováno přes wp_kses)',
			'{{#if sloupec}}…{{/if}}'                  => 'Renderuje blok jen pokud sloupec není prázdný',
			'{{#each sloupec}}…{{item}}…{{/each}}'     => 'Smyčka přes vícehodnotový sloupec (oddělovač: čárka)',
			'{{#each sloupec|;}}…{{item}}…{{/each}}'   => 'Smyčka s vlastním oddělovačem (tady středník)',
		];
	}
}
