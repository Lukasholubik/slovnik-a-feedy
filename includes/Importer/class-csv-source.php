<?php
/**
 * CSV zdroj dat – streamované čtení přes fgetcsv.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Načítá CSV soubor řádek po řádku (nepotřebuje celý soubor v RAM).
 * Podporuje UTF-8 BOM, auto-detekci oddělovače (,  ;  \t  |).
 */
final class CsvSource implements Source {

	private string $delimiter;

	/** @var list<string> */
	private array $columns = [];

	public function __construct(
		private readonly string $file_path,
		string $delimiter = ''
	) {
		$this->delimiter = $delimiter ?: $this->detect_delimiter();
	}

	// -------------------------------------------------------------------------

	public function get_columns(): array {
		if ( $this->columns ) {
			return $this->columns;
		}

		$handle = $this->open();
		if ( ! $handle ) {
			return [];
		}

		$row = fgetcsv( $handle, 0, $this->delimiter );
		fclose( $handle );

		if ( ! is_array( $row ) ) {
			return [];
		}

		// Odstraň UTF-8 BOM z prvního pole (Excel export).
		$row[0] = ltrim( $row[0], "\xEF\xBB\xBF" );

		$this->columns = array_map( 'trim', $row );
		return $this->columns;
	}

	public function get_rows(): iterable {
		$columns = $this->get_columns();
		if ( ! $columns ) {
			return;
		}

		$handle = $this->open();
		if ( ! $handle ) {
			return;
		}

		fgetcsv( $handle, 0, $this->delimiter ); // přeskoč hlavičku

		while ( ( $row = fgetcsv( $handle, 0, $this->delimiter ) ) !== false ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			// Doplň chybějící sloupce prázdným řetězcem.
			$row = array_pad( $row, count( $columns ), '' );
			yield array_combine( $columns, array_slice( $row, 0, count( $columns ) ) );
		}

		fclose( $handle );
	}

	// -------------------------------------------------------------------------

	/**
	 * Otevře soubor a ověří, že jde o čitelný soubor (ne symlink mimo povolený adresář).
	 *
	 * @return resource|false
	 */
	private function open() {
		$real = realpath( $this->file_path );
		if ( ! $real || ! is_file( $real ) || ! is_readable( $real ) ) {
			return false;
		}

		// Ověř, že soubor leží uvnitř uploads adresáře (zabránění path traversal).
		$upload_base = realpath( wp_upload_dir()['basedir'] );
		if ( $upload_base && ! str_starts_with( $real, $upload_base ) ) {
			return false;
		}

		return fopen( $real, 'r' );
	}

	/**
	 * Odhadne oddělovač z první řádky souboru.
	 */
	private function detect_delimiter(): string {
		$handle = fopen( $this->file_path, 'r' );
		if ( ! $handle ) {
			return ',';
		}

		$line = (string) fgets( $handle );
		fclose( $handle );

		$candidates = [ ',' => 0, ';' => 0, "\t" => 0, '|' => 0 ];
		foreach ( $candidates as $char => $_ ) {
			$candidates[ $char ] = substr_count( $line, $char );
		}

		arsort( $candidates );
		$best = array_key_first( $candidates );
		return ( $candidates[ $best ] > 0 ) ? $best : ',';
	}
}
