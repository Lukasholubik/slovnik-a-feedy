<?php
/**
 * XML zdroj dat.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Načítá XML soubor a mapuje každý child element kořene jako jeden řádek.
 *
 * Bezpečnost: LIBXML_NONET zabraňuje XXE (External Entity) útokům –
 * parser nesmí načítat externí soubory nebo síťové zdroje.
 */
final class XmlSource implements Source {

	/** @var list<string> */
	private array $columns = [];

	/** Název tagu pro řádky (auto-detekován z první položky). */
	private string $row_tag = '';

	public function __construct(
		private readonly string $file_path
	) {}

	// -------------------------------------------------------------------------

	public function get_columns(): array {
		if ( $this->columns ) {
			return $this->columns;
		}

		$xml = $this->load();
		if ( ! $xml ) {
			return [];
		}

		$first = $xml->children()[0] ?? null;
		if ( ! $first ) {
			return [];
		}

		$this->row_tag = $first->getName();

		foreach ( $first->children() as $child ) {
			$this->columns[] = $child->getName();
		}

		// Atributy jako sloupce (např. <term id="1" letter="A">).
		foreach ( $first->attributes() as $name => $_ ) {
			$this->columns[] = '@' . $name;
		}

		return $this->columns;
	}

	public function get_rows(): iterable {
		$columns = $this->get_columns();
		if ( ! $columns ) {
			return;
		}

		$xml = $this->load();
		if ( ! $xml ) {
			return;
		}

		foreach ( $xml->children() as $item ) {
			$row = [];
			foreach ( $columns as $col ) {
				if ( str_starts_with( $col, '@' ) ) {
					// Atribut.
					$attr_name  = substr( $col, 1 );
					$row[ $col ] = isset( $item->attributes()->$attr_name )
						? (string) $item->attributes()->$attr_name
						: '';
				} else {
					$row[ $col ] = isset( $item->$col ) ? (string) $item->$col : '';
				}
			}
			yield $row;
		}
	}

	// -------------------------------------------------------------------------

	/**
	 * Načte a vrátí SimpleXMLElement s XXE ochranou.
	 *
	 * @return \SimpleXMLElement|false
	 */
	private function load() {
		$real = realpath( $this->file_path );
		if ( ! $real || ! is_file( $real ) || ! is_readable( $real ) ) {
			return false;
		}

		$upload_base = realpath( wp_upload_dir()['basedir'] );
		if ( $upload_base && ! str_starts_with( $real, $upload_base ) ) {
			return false;
		}

		// LIBXML_NONET – zakáže síťové requesty při parsování (XXE ochrana).
		return simplexml_load_file( $real, 'SimpleXMLElement', LIBXML_NONET );
	}
}
