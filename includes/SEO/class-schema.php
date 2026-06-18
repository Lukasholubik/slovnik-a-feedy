<?php
/**
 * Schema.org DefinedTerm – pro libovolný aktivní stream.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\SEO;

use SlovnikAFeedy\StreamManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Přidá DefinedTerm node do Rank Math JSON-LD grafu na single stránkách
 * libovolného CPT registrovaného pluginem.
 */
final class Schema {

	/**
	 * @param array<string, mixed> $data
	 * @param object               $jsonld
	 * @return array<string, mixed>
	 */
	public function add_defined_term( array $data, object $jsonld ): array {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return $data;
		}

		// Najdi stream odpovídající aktuálnímu post_type.
		$stream = StreamManager::find_by_cpt( $post->post_type );
		if ( ! $stream ) {
			return $data;
		}

		$archive_url = get_post_type_archive_link( $post->post_type );
		$description = $post->post_excerpt
			? wp_strip_all_tags( $post->post_excerpt )
			: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '' );

		$data['SlovnikDefinedTerm'] = [
			'@type'            => 'DefinedTerm',
			'name'             => esc_html( $post->post_title ),
			'description'      => esc_html( $description ),
			'inDefinedTermSet' => $archive_url ?: home_url( '/' . $stream['url_slug'] . '/' ),
			'url'              => get_permalink( $post ),
		];

		return $data;
	}
}
