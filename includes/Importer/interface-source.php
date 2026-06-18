<?php
/**
 * Rozhraní pro zdroje dat importu.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Každý zdroj (CSV, XML, Google Sheets) musí implementovat toto rozhraní.
 * get_rows() je iterátor – nenačítá celý soubor do paměti najednou.
 */
interface Source {

	/**
	 * Vrátí iterátor řádků; každý řádek = asociativní pole [název_sloupce => hodnota].
	 *
	 * @return iterable<array<string, string>>
	 */
	public function get_rows(): iterable;

	/**
	 * Vrátí seznam názvů sloupců (z hlavičky souboru / první řady).
	 *
	 * @return list<string>
	 */
	public function get_columns(): array;
}
