<?php
/**
 * Hlavní orchestrátor pluginu – singleton.
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registruje všechny WordPress háky a koordinuje moduly pluginu.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private function __construct() {}

	public static function get_instance(): static {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	public function run(): void {
		$this->load_textdomain();
		$this->register_hooks();
	}

	private function load_textdomain(): void {
		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain(
					'slovnik-a-feedy',
					false,
					dirname( SAF_BASENAME ) . '/languages'
				);
			}
		);
	}

	private function register_hooks(): void {
		// Jednorázový capability grant – spustí se jen pokud administrátor
		// manage_glossary ještě nemá (např. po ruční deaktivaci capability).
		// Použití admin_init místo user_has_cap filtru zabraňuje volání
		// na každý current_user_can() call (stovky za stránku).
		add_action( 'admin_init', static function (): void {
			if ( ! current_user_can( 'manage_glossary' ) && current_user_can( 'manage_options' ) ) {
				$role = get_role( 'administrator' );
				if ( $role instanceof \WP_Role ) {
					$role->add_cap( 'manage_glossary' );
				}
			}
		} );

		// CPT a taxonomie.
		$cpt      = new PostType\Cpt();
		$taxonomy = new PostType\Taxonomy();
		add_action( 'init', [ $cpt, 'register' ] );
		add_action( 'init', [ $taxonomy, 'register' ] );

		// Rank Math – zařazení CPT do sitemapy.
		add_filter(
			'rank_math/sitemap/post_types',
			static function ( array $post_types ): array {
				if ( ! in_array( 'glossary', $post_types, true ) ) {
					$post_types[] = 'glossary';
				}
				return $post_types;
			}
		);

		// Schema DefinedTerm na singulárních stránkách pojmu.
		$schema = new SEO\Schema();
		add_filter( 'rank_math/json_ld', [ $schema, 'add_defined_term' ], 10, 2 );

		// Admin UI.
		if ( is_admin() ) {
			$admin_menu = new Admin\AdminMenu();
			add_action( 'admin_menu', [ $admin_menu, 'register' ] );
			add_action( 'admin_enqueue_scripts', [ $admin_menu, 'enqueue_assets' ] );
		}
	}
}
