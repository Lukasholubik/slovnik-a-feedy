<?php
/**
 * Mapper – mapuje sloupce zdroje na pole pluginu.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drží mapování source_column → field_slug a provádí transformaci řádku.
 */
final class Mapper {

	/**
	 * Definice cílových polí pluginu (field_slug => český popis).
	 * Whitelist – jiné klíče nelze mapovat.
	 */
	public const FIELDS = [
		'title'           => 'Název pojmu – Titulek (H1)',
		'excerpt'         => 'Stručný výpis / Excerpt (Výtah)',
		'slug'            => 'URL slug (post_name)',
		'status'          => 'Status (publish / draft)',
		'seo_title'       => 'SEO titulek (Rank Math)',
		'seo_description' => 'SEO popis (Rank Math)',
		'seo_keyword'     => 'Focus keyword (Rank Math)',
		'letter'          => 'Písmeno A–Z (slug taxonu glossary_letter)',
		'category'        => 'Kategorie (slug taxonu glossary_cat, více odděleno čárkou)',
		'external_id'     => 'Unikátní klíč pro upsert (external ID)',
	];

	/**
	 * @param array<string, string> $mapping  source_column => field_slug
	 */
	public function __construct( private array $mapping = [] ) {}

	public function get_mapping(): array {
		return $this->mapping;
	}

	/**
	 * Mapuje jeden řádek zdroje na asociativní pole field_slug => hodnota.
	 * Nenamapované sloupce jsou vynechány. Chybějící povinné klíče = prázdný string.
	 *
	 * @param  array<string, string> $source_row
	 * @return array<string, string>
	 */
	public function map_row( array $source_row ): array {
		$result = array_fill_keys( array_keys( self::FIELDS ), '' );

		foreach ( $this->mapping as $source_col => $field_or_fields ) {
			$val    = $source_row[ $source_col ] ?? '';
			$fields = is_array( $field_or_fields ) ? $field_or_fields : [ $field_or_fields ];

			foreach ( $fields as $field_slug ) {
				if ( $field_slug && isset( self::FIELDS[ $field_slug ] ) ) {
					$result[ $field_slug ] = $val;
				}
			}
		}

		return $result;
	}

	/**
	 * Auto-mapování: porovná názvy sloupců zdroje s aliasy polí.
	 * Vrací mapping vhodný jako výchozí stav pro uživatele.
	 *
	 * @param  list<string>          $source_columns
	 * @return array<string, string> source_column => field_slug
	 */
	public static function auto_map( array $source_columns ): array {
		$aliases = [
			'title'           => [ 'title', 'název', 'nazev', 'name', 'pojem', 'term', 'nadpis', 'heading', 'jméno', 'jmeno' ],
			'excerpt'         => [ 'excerpt', 'výtah', 'vytah', 'perex', 'summary', 'short', 'zkrátce', 'kratce' ],
			'slug'            => [ 'slug', 'url', 'permalink', 'alias' ],
			'status'          => [ 'status', 'stav', 'state', 'publish' ],
			'seo_title'       => [ 'seo_title', 'meta_title', 'og_title', 'seo_nadpis' ],
			'seo_description' => [ 'seo_description', 'meta_description', 'meta_desc', 'seo_popis' ],
			'seo_keyword'     => [ 'keyword', 'focus_keyword', 'kw', 'klíčové_slovo', 'klicove_slovo' ],
			'letter'          => [ 'letter', 'písmeno', 'pismeno', 'initial', 'first_letter' ],
			'category'        => [ 'category', 'kategorie', 'cat', 'group', 'skupina' ],
			'external_id'     => [ 'id', 'external_id', 'ext_id', 'uid', 'key', 'klíč', 'klic', 'identifier' ],
		];

		$mapping = [];
		foreach ( $source_columns as $col ) {
			$col_lower = mb_strtolower( trim( $col ) );
			foreach ( $aliases as $field => $names ) {
				if ( in_array( $col_lower, $names, true ) ) {
					$mapping[ $col ] = $field;
					break;
				}
			}
		}

		return $mapping;
	}
}
