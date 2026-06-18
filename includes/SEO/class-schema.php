<?php
/**
 * Schema.org – DefinedTerm pro singulární stránky pojmu.
 *
 * Přidává node DefinedTerm do Rank Math JSON-LD grafu, čímž zlepšuje
 * srozumitelnost obsahu pro vyhledávače.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Napojení na filtr rank_math/json_ld.
 *
 * Proč DefinedTerm:
 *  - Google jej explicitně doporučuje pro encyklopedie a slovníky.
 *  - inDefinedTermSet odkazuje na archiv → vzájemně propojený graf entit.
 *  - description se plní z excerptу nebo úvodu obsahu (max. 30 slov).
 */
final class Schema {

	/**
	 * Přidá DefinedTerm node do JSON-LD grafu Rank Mathu.
	 *
	 * @param array<string, mixed> $data   Stávající JSON-LD data.
	 * @param object               $jsonld Rank Math JsonLD objekt.
	 * @return array<string, mixed>
	 */
	public function add_defined_term( array $data, object $jsonld ): array {
		if ( ! is_singular( 'glossary' ) ) {
			return $data;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return $data;
		}

		$archive_url = get_post_type_archive_link( 'glossary' );
		$description = $post->post_excerpt
			? wp_strip_all_tags( $post->post_excerpt )
			: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '' );

		$data['SlovnikDefinedTerm'] = [
			'@type'            => 'DefinedTerm',
			'name'             => esc_html( $post->post_title ),
			'description'      => esc_html( $description ),
			'inDefinedTermSet' => $archive_url ?: home_url( '/slovnik/' ),
			'url'              => get_permalink( $post ),
		];

		return $data;
	}
}
