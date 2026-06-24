<?php
/**
 * Plugin Name:       Slovník a Feedy
 * Plugin URI:        https://grou.cz
 * Description:       Spravuje streamy slovníčku pojmů s hromadným importem z CSV/XML/Google Sheets a generuje RSS feedy. Součást rodiny pluginů Grou.cz.
 * Version:           1.2.1
 * Author:            Grou.cz
 * Author URI:        https://grou.cz
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       slovnik-a-feedy
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAF_VERSION',  '1.2.1' );
define( 'SAF_FILE',     __FILE__ );
define( 'SAF_DIR',      plugin_dir_path( __FILE__ ) );
define( 'SAF_URL',      plugin_dir_url( __FILE__ ) );
define( 'SAF_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader – namespace SlovnikAFeedy\ → includes/
 * CamelCase názvy tříd převádí na WP file naming: class-kebab-case.php
 * Automaticky zkouší prefix class-, interface- i trait-.
 */
spl_autoload_register( static function ( string $class ): void {
	$prefix = 'SlovnikAFeedy\\';
	$len    = strlen( $prefix );

	if ( 0 !== strncmp( $prefix, $class, $len ) ) {
		return;
	}

	$relative   = substr( $class, $len );
	$parts      = explode( '\\', $relative );
	$class_name = array_pop( $parts );

	// CamelCase → kebab-case (např. AdminMenu → admin-menu, CrococblockCompat → crocoblock-compat).
	// Použití lcfirst() + /[A-Z]/ místo (?<!^) lookbehind – spolehlivější na všech PHP verzích.
	$kebab    = strtolower( (string) preg_replace( '/[A-Z]/', '-$0', lcfirst( $class_name ) ) );
	$base_dir = SAF_DIR . 'includes/' . ( $parts ? implode( '/', $parts ) . '/' : '' );

	foreach ( [ 'class-', 'interface-', 'trait-' ] as $type_prefix ) {
		$file = $base_dir . $type_prefix . $kebab . '.php';
		if ( file_exists( $file ) ) {
			require $file;
			return;
		}
	}
} );

// Háky aktivace / deaktivace (musí být registrovány před plugins_loaded).
register_activation_hook( SAF_FILE, [ 'SlovnikAFeedy\\Activator', 'activate' ] );
register_deactivation_hook( SAF_FILE, [ 'SlovnikAFeedy\\Deactivator', 'deactivate' ] );

// GitHub auto-updater (kontroluje releases na github.com/Lukasholubik/slovnik-a-feedy).
SlovnikAFeedy\GithubUpdater::register();

// Spuštění pluginu po načtení všech pluginů.
add_action( 'plugins_loaded', static function (): void {
	SlovnikAFeedy\Plugin::get_instance()->run();
} );
