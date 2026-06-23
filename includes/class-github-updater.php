<?php
/**
 * GitHub Updater – automatické aktualizace pluginu z GitHub releases.
 *
 * Funguje pro veřejné repozitáře bez autentizace.
 * Cache výsledku na 12 hodin (WP transient).
 *
 * @package SlovnikAFeedy
 */

namespace SlovnikAFeedy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GithubUpdater {

	private const GITHUB_USER = 'Lukasholubik';
	private const GITHUB_REPO = 'slovnik-a-feedy';
	private const CACHE_KEY   = 'saf_github_update_info';
	private const CACHE_TTL   = 12 * HOUR_IN_SECONDS;

	public static function register(): void {
		$instance = new self();
		add_filter( 'pre_set_site_transient_update_plugins', [ $instance, 'check_update' ] );
		add_filter( 'plugins_api', [ $instance, 'plugin_info' ], 10, 3 );
		add_filter( 'upgrader_post_install', [ $instance, 'fix_folder_name' ], 10, 3 );
		add_action( 'wp_ajax_saf_force_update_check', [ static::class, 'ajax_force_check' ] );
	}

	/**
	 * Injektuje info o dostupné aktualizaci do WP update transientu.
	 */
	public function check_update( \stdClass $transient ): \stdClass {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$latest_version = ltrim( $release['tag_name'] ?? '', 'v' );
		$package        = $this->get_download_url( $release['tag_name'] ?? '' );

		if ( version_compare( $latest_version, SAF_VERSION, '>' ) ) {
			$transient->response[ SAF_BASENAME ] = (object) [
				'slug'         => 'slovnik-a-feedy',
				'plugin'       => SAF_BASENAME,
				'new_version'  => $latest_version,
				'url'          => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
				'package'      => $package,
				'tested'       => '6.8',
				'requires'     => '6.4',
				'requires_php' => '8.1',
			];
		} else {
			$transient->no_update[ SAF_BASENAME ] = (object) [
				'slug'        => 'slovnik-a-feedy',
				'plugin'      => SAF_BASENAME,
				'new_version' => $latest_version,
				'url'         => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
				'package'     => '',
			];
		}

		return $transient;
	}

	/**
	 * Poskytne info o pluginu pro WP popup (Details okno).
	 */
	public function plugin_info( $result, string $action, \stdClass $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}
		if ( ( $args->slug ?? '' ) !== 'slovnik-a-feedy' ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$tag             = $release['tag_name'] ?? '';
		$info            = new \stdClass();
		$info->name      = 'Slovník a Feedy';
		$info->slug      = 'slovnik-a-feedy';
		$info->version   = ltrim( $tag, 'v' );
		$info->author    = '<a href="https://grou.cz">Grou.cz</a>';
		$info->homepage  = 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO;
		$info->requires      = '6.4';
		$info->tested        = '6.8';
		$info->requires_php  = '8.1';
		$info->downloaded    = 0;
		$info->last_updated  = $release['published_at'] ?? '';
		$info->sections      = [
			'description' => 'Slovník pojmů s hromadným importem z CSV/XML/Google Sheets a RSS feedy. Součást rodiny nástrojů <a href="https://grou.cz">Grou.cz</a>.',
			'changelog'   => nl2br( esc_html( $release['body'] ?? '' ) ),
		];
		$info->download_link = $this->get_download_url( $tag );

		return $info;
	}

	/**
	 * Po instalaci přejmenuje složku z "{repo}-{hash}" na správný slug "slovnik-a-feedy".
	 * GitHub archive ZIP vytvoří složku "{repo}-{tag}" – WP pak plugin nenajde.
	 */
	public function fix_folder_name( bool $response, array $hook_extra, array $result ): array {
		global $wp_filesystem;

		if ( ( $hook_extra['plugin'] ?? '' ) !== SAF_BASENAME ) {
			return $result;
		}

		$install_path = $result['destination'] ?? '';
		$proper_dest  = trailingslashit( dirname( $install_path ) ) . 'slovnik-a-feedy/';

		if ( $install_path && $install_path !== $proper_dest && $wp_filesystem instanceof \WP_Filesystem_Base ) {
			$wp_filesystem->move( $install_path, $proper_dest, true );
			$result['destination']      = $proper_dest;
			$result['destination_name'] = 'slovnik-a-feedy';
		}

		return $result;
	}

	/**
	 * AJAX: smaže cache a transient, vynutí čerstvou kontrolu.
	 */
	public static function ajax_force_check(): void {
		check_ajax_referer( 'saf_force_update_check', 'nonce' );
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( 'Nedostatečná oprávnění.' );
			return;
		}

		// Smaž naši cache.
		delete_transient( self::CACHE_KEY );

		// Smaž WP plugin update transient → WP provede čerstvý check při příštím requestu.
		delete_site_transient( 'update_plugins' );

		// Vyvolej čerstvý check hned.
		wp_update_plugins();

		// Načti výsledek.
		$transient      = get_site_transient( 'update_plugins' );
		$has_update     = isset( $transient->response[ SAF_BASENAME ] );
		$latest_version = $has_update
			? $transient->response[ SAF_BASENAME ]->new_version
			: ( $transient->no_update[ SAF_BASENAME ]->new_version ?? SAF_VERSION );

		wp_send_json_success( [
			'has_update'     => $has_update,
			'latest_version' => $latest_version,
			'current'        => SAF_VERSION,
			'message'        => $has_update
				? sprintf( 'Dostupná aktualizace: v%s (aktuální: v%s)', $latest_version, SAF_VERSION )
				: sprintf( 'Plugin je aktuální (v%s).', SAF_VERSION ),
		] );
	}

	/**
	 * Sestaví přímou URL pro stažení ZIP archivu z GitHub.
	 * Používá github.com/archive URL místo api.github.com/zipball – bez přesměrování přes API.
	 */
	private function get_download_url( string $tag_name ): string {
		if ( ! $tag_name ) {
			return '';
		}
		return sprintf(
			'https://github.com/%s/%s/archive/refs/tags/%s.zip',
			self::GITHUB_USER,
			self::GITHUB_REPO,
			$tag_name
		);
	}

	/**
	 * Načte info o nejnovějším release z GitHub API.
	 * Výsledek cachuje na 12 hodin.
	 */
	private function get_latest_release(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url      = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			self::GITHUB_USER,
			self::GITHUB_REPO
		);
		$response = wp_remote_get( $url, [
			'timeout'    => 15,
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			'sslverify'  => true,
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return null;
		}

		set_transient( self::CACHE_KEY, $body, self::CACHE_TTL );

		return $body;
	}
}
