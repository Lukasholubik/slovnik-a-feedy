<?php
/**
 * ArraySource – zdroj dat z PHP pole (používá BatchRunner pro dávky).
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adapter umožňující importovat libovolné PHP pole jako Source.
 */
final class ArraySource implements Source {

	/** @param list<array<string,string>> $rows */
	public function __construct( private readonly array $rows ) {}

	public function get_rows(): iterable {
		yield from $this->rows;
	}

	public function get_columns(): array {
		return array_keys( $this->rows[0] ?? [] );
	}
}
