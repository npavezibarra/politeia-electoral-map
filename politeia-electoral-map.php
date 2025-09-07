<?php
/**
 * Plugin Name:       Politeia Electoral Map
 * Plugin URI:        https://github.com/npavezibarra/politeia-electoral-map
 * Description:       Visualizador electoral de Chile (mapas + datos). Paso 1: mapa en iframe con búsqueda de comunas RM.
 * Version:           0.1.0
 * Author:            Politeia
 * Author URI:        https://politeia.cl
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       politeia-electoral-map
 * Domain Path:       /languages
 */

if ( ! defined('ABSPATH') ) exit;

// ======================================================
// Constantes globales del plugin
// ======================================================
if ( ! defined('PLEM_FILE') ) define('PLEM_FILE', __FILE__);
if ( ! defined('PLEM_DIR') )  define('PLEM_DIR', plugin_dir_path(__FILE__));
if ( ! defined('PLEM_URL') )  define('PLEM_URL', plugin_dir_url(__FILE__));
if ( ! defined('PLEM_VER') )  define('PLEM_VER', '0.1.0');

// ======================================================
/** Autoloader de Composer (opcional) */
$composer = PLEM_DIR . 'vendor/autoload.php';
if ( file_exists($composer) ) {
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
if ( file_exists($settings_file) ) {
	require_once $settings_file;
}

/**
 * Shortcode del mapa en iframe.
 * Ruta: includes/Shortcodes/RMMap.php
 */
$shortcode_file = PLEM_DIR . 'includes/Shortcodes/RMMap.php';
if ( file_exists($shortcode_file) ) {
	require_once $shortcode_file;
}

/**
 * Manejador de assets (front/admin). Evita constantes no definidas.
 * Ruta: includes/Modules/Assets/Assets.php
 */
$assets_file = PLEM_DIR . 'includes/Modules/Assets/Assets.php';
if ( file_exists($assets_file) ) {
	require_once $assets_file;
}

// ======================================================
// Activación / Desactivación (ganchos mínimos)
// ======================================================

/**
 * Tareas en activación: por ahora, solo vaciar transients si más adelante cachéa endpoints.
 */
function plem_activate() {
	// Resérvate espacio para futuras dbDelta() de tablas, etc.
	flush_rewrite_rules();
}
register_activation_hook(PLEM_FILE, 'plem_activate');

/**
 * Tareas en desactivación.
 */
function plem_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook(PLEM_FILE, 'plem_deactivate');

// ======================================================
// Internacionalización (por si luego agregas strings traducibles)
// ======================================================
function plem_load_textdomain() {
	load_plugin_textdomain('politeia-electoral-map', false, dirname(plugin_basename(PLEM_FILE)) . '/languages');
}
add_action('plugins_loaded', 'plem_load_textdomain');

// ======================================================
// Comprobaciones rápidas en admin (notificaciones)
// ======================================================

/**
 * Aviso si falta la API Key de Google Maps (solo para administradores).
 */
function plem_admin_notice_missing_api_key() {
	if ( ! current_user_can('manage_options') ) return;

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	// Evita saturar todas las pantallas: muestra en Escritorio y en la página del plugin
	$show = true;
	if ( $screen && isset($screen->id) ) {
		$show = in_array($screen->id, ['dashboard', 'toplevel_page_plem-settings'], true);
	}

	$api_key = get_option('plem_google_maps_api_key', '');
	if ( $show && empty($api_key) ) {
		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo esc_html__('Politeia Electoral Map: falta configurar la Google Maps API Key. Ve a "Electoral Map" en el menú del administrador para guardarla.', 'politeia-electoral-map');
		echo '</p></div>';
	}
}
add_action('admin_notices', 'plem_admin_notice_missing_api_key');

// ======================================================
// Listo. El shortcode [plem_rm_map] genera el iframe con el HTML de Google Maps,
// tomando la API key desde Ajustes y pasándola por query (?api_key=...).
// Asegúrate de tener el archivo assets/map/rm-map.html y assets/geojson/comunas.geojson.
// ======================================================
