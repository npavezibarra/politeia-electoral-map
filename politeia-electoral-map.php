<?php
/**
 * Plugin Name:       Politeia Electoral Map
 * Plugin URI:        https://github.com/npavezibarra/politeia-electoral-map
 * Description:       Visualizador electoral de Chile (mapas + datos). Paso 1: mapa en iframe con búsqueda de comunas RM.
 * Version:           0.2.5
 * Author:            Politeia
 * Author URI:        https://politeia.cl
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       politeia-electoral-map
 * Domain Path:       /languages
 *
 * @package PoliteiaElectoralMap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ======================================================
// Constantes globales del plugin
// ======================================================
if ( ! defined( 'PLEM_FILE' ) ) {
	define( 'PLEM_FILE', __FILE__ );
}
if ( ! defined( 'PLEM_DIR' ) ) {
	define( 'PLEM_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'PLEM_URL' ) ) {
	define( 'PLEM_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'PLEM_VERSION' ) ) {
	define( 'PLEM_VERSION', '0.2.5' );
}
if ( ! defined( 'PLEM_DB_VERSION' ) ) {
	define( 'PLEM_DB_VERSION', '0.2.5' );
}

// ======================================================
/** Autoloader de Composer (opcional) */
$composer = PLEM_DIR . 'vendor/autoload.php';
if ( file_exists( $composer ) ) {
	require_once $composer;
}

// ======================================================
// Carga de archivos del plugin (condicional para evitar fatales)
// ======================================================

/**
 * Admin Settings Page (guardar Google Maps API Key).
 * Ruta: includes/Admin/Settings.php
 */
$settings_file = PLEM_DIR . 'includes/Admin/Settings.php';
if ( file_exists( $settings_file ) ) {
	require_once $settings_file;
}

/**
 * Shortcode del mapa en iframe.
 * Ruta: includes/Shortcodes/RMMap.php
 */
$shortcode_file = PLEM_DIR . 'includes/Shortcodes/RMMap.php';
if ( file_exists( $shortcode_file ) ) {
        require_once $shortcode_file;
}

/**
 * Shortcode del resumen de elecciones.
 * Ruta: includes/shortcodes/election-summary.php
 */
$summary_shortcode = PLEM_DIR . 'includes/shortcodes/election-summary.php';
if ( file_exists( $summary_shortcode ) ) {
        require_once $summary_shortcode;
}

/**
 * Manejador de assets (front/admin). Evita constantes no definidas.
 * Ruta: includes/Modules/Assets/Assets.php
 */
$assets_file = PLEM_DIR . 'includes/Modules/Assets/Assets.php';
if ( file_exists( $assets_file ) ) {
	require_once $assets_file;
}

/**
 * REST controller para obtener información de comunas.
 * Ruta: includes/Modules/REST/class-jurisdictions.php
 */
$rest_juris_file = PLEM_DIR . 'includes/Modules/REST/class-jurisdictions.php';
if ( file_exists( $rest_juris_file ) ) {
        require_once $rest_juris_file;
}

/**
 * Migrations loader (ensures db schema additions run).
 * Ruta: includes/class-politeia-migrations.php
 */
$migrations_loader = PLEM_DIR . 'includes/class-politeia-migrations.php';
if ( file_exists( $migrations_loader ) ) {
        require_once $migrations_loader;
}

// ======================================================
// Activación / Desactivación
// ======================================================

register_activation_hook( PLEM_FILE, array( '\Politeia\Core\Activator', 'activate' ) );
register_deactivation_hook( PLEM_FILE, array( '\Politeia\Core\Deactivator', 'deactivate' ) );
add_action( 'plugins_loaded', array( '\Politeia\Core\Upgrader', 'maybe_upgrade' ) );

// ======================================================
// Internacionalización (por si luego agregas strings traducibles)
// ======================================================
/**
 * Load plugin text domain.
 *
 * @return void
 */
function plem_load_textdomain() {
	load_plugin_textdomain( 'politeia-electoral-map', false, dirname( plugin_basename( PLEM_FILE ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'plem_load_textdomain' );

// ======================================================
// Comprobaciones rápidas en admin (notificaciones)
// ======================================================

/**
 * Aviso si falta la API Key de Google Maps (solo para administradores).
 */
function plem_admin_notice_missing_api_key() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	// Evita saturar todas las pantallas: muestra en Escritorio y en la página del plugin.
	$show = true;
	if ( $screen && isset( $screen->id ) ) {
		$show = in_array( $screen->id, array( 'dashboard', 'toplevel_page_plem-settings' ), true );
	}

	$api_key = get_option( 'plem_google_maps_api_key', '' );
	if ( $show && empty( $api_key ) ) {
		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo esc_html__( 'Politeia Electoral Map: falta configurar la Google Maps API Key. Ve a "Electoral Map" en el menú del administrador para guardarla.', 'politeia-electoral-map' );
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'plem_admin_notice_missing_api_key' );

// ======================================================
// Listo. El shortcode [plem_rm_map] genera el iframe con el HTML de Google Maps,
// tomando la API key desde Ajustes y pasándola por query (?api_key=...).
// Asegúrate de tener el archivo assets/map/rm-map.html y assets/geojson/comunas.geojson.
// ======================================================
