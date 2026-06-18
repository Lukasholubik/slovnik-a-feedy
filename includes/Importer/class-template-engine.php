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

		return $template;
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
