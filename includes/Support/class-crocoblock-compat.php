<?php
/**
 * Kompatibilita s Crocoblock sadou pluginů.
 *
 * Pokrývá:
 *   – JetThemeCore   (Theme Builder podmínky pro archive/single/taxonomy)
 *   – JetEngine      (Listings, Dynamic Content, Meta Boxes)
 *   – JetSmartFilters (filtrovací widgety dle taxonomie a meta polí)
 *
 * Proč je to potřeba:
 *   WordPress sám o sobě nestačí pro plnou integraci. JetEngine potřebuje
 *   meta pole registrovaná přes register_post_meta() aby je nabízel jako
 *   dynamické tagy. JetSmartFilters potřebuje vědět o query_var taxonomií.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy\Support;

use SlovnikAFeedy\StreamManager;
use SlovnikAFeedy\Importer\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Crocoblock / JetPlugins integrace.
 * Volej CrococblockCompat::register_hooks() z Plugin::register_hooks().
 */
final class CrococblockCompat {

	public static function register_hooks(): void {
		// Meta pole zaregistruj po init (CPT a taxonomie musí být hotové).
		add_action( 'init', [ static::class, 'register_meta_fields' ], 20 );

		// JetEngine – přidej naše CPT do zdrojů Listingů.
		add_filter( 'jet-engine/listings/allowed-post-types', [ static::class, 'add_cpts_to_jet_engine' ] );

		// JetSmartFilters – přidej naše taxonomie do výchozích zdrojů dat.
		add_filter( 'jet-smart-filters/data-store/post-types', [ static::class, 'add_cpts_to_jsf' ] );

		// JetThemeCore – ujisti se, že archiv je rozpoznán jako archive location.
		add_filter( 'jet-theme-core/locations/archive/condition-post-types', [ static::class, 'add_cpts_to_jet_archive' ] );

		// JetEngine Query Builder – zahrň naše CPT do výchozí nabídky.
		add_filter( 'jet-engine/query-builder/types/wp-query/post-types', [ static::class, 'add_cpts_to_query_builder' ] );
	}

	// -------------------------------------------------------------------------
	// Meta pole.

	/**
	 * Registruje meta pole každého streamu přes WP register_post_meta().
	 *
	 * Proč: JetEngine Dynamic Content Tags čtou pole registrovaná přes
	 * register_post_meta(). Rank Math SEO pole a _saf_external_id tak budou
	 * dostupná jako dynamické tagy v Elementoru/Crococblock editoru.
	 */
	public static function register_meta_fields(): void {
		foreach ( StreamManager::get_all() as $stream ) {
			if ( ! ( $stream['active'] ?? true ) ) {
				continue;
			}
			self::register_for_cpt( $stream['cpt'] );
		}
	}

	private static function register_for_cpt( string $cpt ): void {
		$common_args = [
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
		];

		// Import unikátní klíč.
		register_post_meta( $cpt, Importer::META_EXTERNAL_ID, array_merge( $common_args, [
			'type'              => 'string',
			'description'       => 'Unikátní klíč z importu (external ID)',
			'sanitize_callback' => 'sanitize_text_field',
		] ) );

		// Rank Math SEO pole – přístupné v JetEngine dynamic tags.
		$seo_fields = [
			'rank_math_title'         => 'SEO titulek (Rank Math)',
			'rank_math_description'   => 'SEO popis (Rank Math)',
			'rank_math_focus_keyword' => 'Focus keyword (Rank Math)',
		];
		foreach ( $seo_fields as $key => $desc ) {
			register_post_meta( $cpt, $key, array_merge( $common_args, [
				'type'              => 'string',
				'description'       => $desc,
				'sanitize_callback' => 'sanitize_text_field',
			] ) );
		}
	}

	// -------------------------------------------------------------------------
	// JetEngine.

	/**
	 * Přidá všechny aktivní CPT streamů do JetEngine Listing zdrojů.
	 *
	 * @param  array<string, string>  $types  post_type => label
	 * @return array<string, string>
	 */
	public static function add_cpts_to_jet_engine( array $types ): array {
		foreach ( StreamManager::get_all() as $stream ) {
			if ( $stream['active'] ?? true ) {
				$types[ $stream['cpt'] ] = $stream['name'];
			}
		}
		return $types;
	}

	/**
	 * JetEngine Query Builder – nabídne naše CPT v select boxu post_type.
	 *
	 * @param  array<string, string>  $types
	 * @return array<string, string>
	 */
	public static function add_cpts_to_query_builder( array $types ): array {
		foreach ( StreamManager::get_all() as $stream ) {
			if ( $stream['active'] ?? true ) {
				$types[ $stream['cpt'] ] = $stream['name'];
			}
		}
		return $types;
	}

	// -------------------------------------------------------------------------
	// JetSmartFilters.

	/**
	 * Přidá naše CPT do seznamu post_types v JetSmartFilters data store.
	 * Tím se naše taxonomie a meta pole nabídnou jako zdroje filtrovacích widgetů.
	 *
	 * @param  array<int|string, mixed>  $post_types
	 * @return array<int|string, mixed>
	 */
	public static function add_cpts_to_jsf( array $post_types ): array {
		foreach ( StreamManager::get_all() as $stream ) {
			if ( ! ( $stream['active'] ?? true ) ) {
				continue;
			}
			$cpt = $stream['cpt'];
			if ( ! in_array( $cpt, $post_types, true ) ) {
				$post_types[] = $cpt;
			}
		}
		return $post_types;
	}

	// -------------------------------------------------------------------------
	// JetThemeCore.

	/**
	 * Přidá naše CPT do seznamu typů pro Archive location v JetThemeCore.
	 * Zajistí, že archivní šablona se zobrazí pro /slovnik/ (nebo jiný URL slug).
	 *
	 * @param  array<string>  $post_types
	 * @return array<string>
	 */
	public static function add_cpts_to_jet_archive( array $post_types ): array {
		foreach ( StreamManager::get_all() as $stream ) {
			if ( ( $stream['active'] ?? true ) && ! in_array( $stream['cpt'], $post_types, true ) ) {
				$post_types[] = $stream['cpt'];
			}
		}
		return $post_types;
	}
}
