<?php
/**
 * FAQ Fixer – oprava Rank Math FAQ bloků po importu.
 *
 * Problém: po importu HTML v FAQ bloku neodpovídá co Gutenberg save() generuje
 * (chybí <strong> kolem titulku, jiné formátování atd.) → chyba validace.
 *
 * Řešení: Odstranit HTML content mezi block komentáři a ponechat jen JSON.
 * Rank Math FAQ blok je dynamický – renderuje se ze server-side z JSON atributů.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FaqFixer {

	/**
	 * Opraví FAQ bloky v jednom příspěvku.
	 * Vygeneruje správné HTML z JSON atributů – přesně tak jak Rank Math save() funkce.
	 *
	 * Rank Math FAQ block save() funkce (z blocks.js):
	 * <div class="rank-math-faq-item">
	 *   <h3 class="rank-math-question">{title}</h3>
	 *   <div class="rank-math-answer">{content}</div>
	 * </div>
	 *
	 * @return int  Počet opravených bloků.
	 */
	public static function fix_post( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 0;
		}

		$original  = $post->post_content;
		$fixed_n   = 0;

		$fixed = (string) preg_replace_callback(
			'/(<!--\s*wp:rank-math\/faq-block\s+(\{.*?\})\s*-->)\s*[\s\S]*?\s*(<!--\s*\/wp:rank-math\/faq-block\s*-->)/i',
			static function ( array $m ) use ( &$fixed_n ): string {
				$opening = trim( $m[1] );
				$json    = $m[2];
				$closing = trim( $m[3] );

				// Parsuj JSON z block komentáře.
				$attrs = json_decode( $json, true );
				if ( ! $attrs || json_last_error() !== JSON_ERROR_NONE ) {
					return $m[0]; // Poškozený JSON – neupravuj.
				}

				$questions = $attrs['questions'] ?? [];
				if ( empty( $questions ) ) {
					return $m[0];
				}

				// Vygeneruj HTML identické s Rank Math save() funkce.
				$inner = '';
				foreach ( $questions as $q ) {
					if ( isset( $q['visible'] ) && ! $q['visible'] ) {
						continue; // Skryté položky nepřidávej.
					}
					$tag     = isset( $attrs['titleWrapper'] ) ? esc_attr( $attrs['titleWrapper'] ) : 'h3';
					$title   = wp_kses_post( $q['title']   ?? '' );
					$content = wp_kses_post( $q['content'] ?? '' );
					$inner  .= '<div class="rank-math-faq-item">';
					$inner  .= '<' . $tag . ' class="rank-math-question">' . $title . '</' . $tag . '>';
					$inner  .= '<div class="rank-math-answer">' . $content . '</div>';
					$inner  .= '</div>';
				}

				$html = '<div class="wp-block-rank-math-faq-block">' . $inner . '</div>';

				$fixed_n++;
				return $opening . "\n" . $html . "\n" . $closing;
			},
			$original
		);

		if ( $fixed === $original || $fixed_n === 0 ) {
			return 0;
		}

		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => $fixed,
		] );

		// Pročisti WP Rocket cache pro tento post.
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
		}

		return $fixed_n;
	}

	/**
	 * Opraví FAQ bloky ve všech postech daného CPT streamu.
	 *
	 * @return array{posts: int, blocks: int}
	 */
	public static function fix_stream( string $cpt ): array {
		$posts = get_posts( [
			'post_type'      => $cpt,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$fixed_posts  = 0;
		$fixed_blocks = 0;

		foreach ( $posts as $post_id ) {
			$n = static::fix_post( (int) $post_id );
			if ( $n > 0 ) {
				$fixed_posts++;
				$fixed_blocks += $n;
			}
		}

		return [ 'posts' => $fixed_posts, 'blocks' => $fixed_blocks ];
	}

	/**
	 * Registrace AJAX handleru.
	 */
	public static function register_ajax(): void {
		add_action( 'wp_ajax_saf_fix_faq', [ static::class, 'ajax_fix_faq' ] );
	}

	public static function ajax_fix_faq(): void {
		check_ajax_referer( 'saf_fix_faq', 'nonce' );
		if ( ! current_user_can( 'manage_glossary' ) ) {
			wp_send_json_error( 'Nedostatečná oprávnění.' );
		}

		$cpt    = sanitize_key( $_POST['cpt'] ?? '' );
		$result = static::fix_stream( $cpt );

		wp_send_json_success( [
			'message' => sprintf(
				'Opraveno %d příspěvků (%d FAQ bloků). Rank Math vygeneruje HTML při načtení stránky.',
				$result['posts'],
				$result['blocks']
			),
			'result'  => $result,
		] );
	}
}
