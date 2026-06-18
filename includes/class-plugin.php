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
 * Registruje všechny WordPress háky pro všechny aktivní streamy.
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

	// -------------------------------------------------------------------------

	private function load_textdomain(): void {
		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain( 'slovnik-a-feedy', false, dirname( SAF_BASENAME ) . '/languages' );
			}
		);
	}

	private function register_hooks(): void {
		// Jednorázový capability grant pro administrátory.
		add_action( 'admin_init', static function (): void {
			if ( ! current_user_can( 'manage_glossary' ) && current_user_can( 'manage_options' ) ) {
				$role = get_role( 'administrator' );
				if ( $role instanceof \WP_Role ) {
					$role->add_cap( 'manage_glossary' );
				}
			}
		} );

		// Registrace CPT a taxonomií – priorita 1 (před ostatními pluginy na init).
		add_action( 'init', static function (): void {
			// Import šablony CPT.
			TemplateManager::register();

			foreach ( StreamManager::get_all() as $stream ) {
				if ( ! ( $stream['active'] ?? true ) ) {
					continue;
				}
				( new PostType\Cpt( $stream ) )->register();
				( new PostType\Taxonomy( $stream ) )->register();
			}
		}, 1 );

		// Rank Math – všechny aktivní CPT streamy do sitemapy.
		add_filter(
			'rank_math/sitemap/post_types',
			static function ( array $post_types ): array {
				foreach ( StreamManager::get_all() as $stream ) {
					if ( $stream['active'] ?? true ) {
						$post_types[] = $stream['cpt'];
					}
				}
				return array_unique( $post_types );
			}
		);

		// Schema DefinedTerm na singulárních stránkách libovolného streamu.
		$schema = new SEO\Schema();
		add_filter( 'rank_math/json_ld', [ $schema, 'add_defined_term' ], 10, 2 );

		// Crocoblock / JetPlugins kompatibilita.
		// Přímý require – spolehlivější než autoloader pro tuto třídu.
		require_once SAF_DIR . 'includes/Support/class-crocoblock-compat.php';
		Support\CrococblockCompat::register_hooks();

		// Analytics – tracking zobrazení a kliknutí.
		Analytics\Tracker::register_hooks();

		// REST endpoint pro live náhled šablony.
		add_action( 'rest_api_init', static function (): void {
			register_rest_route( 'saf/v1', '/preview-template', [
				'methods'             => 'POST',
				'callback'            => static function ( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
					if ( ! current_user_can( 'manage_glossary' ) ) {
						return new \WP_Error( 'forbidden', 'Nedostatečná oprávnění.', [ 'status' => 403 ] );
					}

					$template_id = absint( $request->get_param( 'template_id' ) );
					$macro_data  = (array) $request->get_param( 'macro_data' );

					$template = \SlovnikAFeedy\TemplateManager::get_content( $template_id );
					if ( ! $template ) {
						return new \WP_Error( 'no_template', 'Šablona nenalezena.', [ 'status' => 404 ] );
					}

					$engine   = new Importer\TemplateEngine();
					$rendered = $engine->render( $template, $macro_data );

					// Renderuj Gutenberg bloky do HTML.
					$html = do_blocks( $rendered );

					return new \WP_REST_Response( [
						'html'     => $html,
						'raw'      => $rendered,
					], 200 );
				},
				'permission_callback' => static fn() => current_user_can( 'manage_glossary' ),
				'args'                => [
					'template_id' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
					'macro_data'  => [ 'required' => false, 'type' => 'object', 'default' => [] ],
				],
			] );
		} );

		// Batch import Cron hook.
		Importer\BatchRunner::register_hooks();

		// Admin-post handler pro smazání importního profilu.
		add_action( 'admin_post_saf_delete_profile', [ $this, 'handle_delete_profile' ] );

		// Admin UI.
		if ( is_admin() ) {
			$admin_menu = new Admin\AdminMenu();
			add_action( 'admin_menu', [ $admin_menu, 'register' ] );
			add_action( 'admin_enqueue_scripts', [ $admin_menu, 'enqueue_assets' ] );

			// Gutenberg sidebar s makry na edit stránce šablony.
			add_action( 'admin_enqueue_scripts', [ 'SlovnikAFeedy\TemplateManager', 'enqueue_sidebar_script' ] );
		}
	}

	// -------------------------------------------------------------------------

	public function handle_delete_profile(): void {
		if ( ! current_user_can( 'manage_glossary' ) ) {
			wp_die( esc_html__( 'Nedostatečná oprávnění.', 'slovnik-a-feedy' ) );
		}
		$profile_id = sanitize_key( $_GET['profile_id'] ?? '' );
		if ( ! $profile_id
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ),
				'saf_delete_profile_' . $profile_id
			)
		) {
			wp_die( esc_html__( 'Neplatný token.', 'slovnik-a-feedy' ) );
		}
		Admin\Settings::delete_profile( $profile_id );
		wp_safe_redirect( admin_url( 'admin.php?page=slovnik-a-feedy-nastaveni&deleted=1' ) );
		exit;
	}
}
