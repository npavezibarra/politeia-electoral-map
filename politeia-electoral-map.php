<?php
/**
 * Plugin Name: Politeia Electoral Map
 * Description: Boilerplate modular de plugin.
 * Version: 0.1.0
 * Author: Nicolás Pavez - Almaden SpA
 * License: GPL2+
 * Text Domain: politeia-electoral-map
 * Domain Path: /languages
 */

if ( ! defined('ABSPATH') ) exit;

define('_PATH', plugin_dir_path(__FILE__));
define('_URL',  plugin_dir_url(__FILE__));
define('_VER',  '0.1.0');
define('_SLUG', 'politeia-electoral-map');

require __DIR__ . '/vendor/autoload.php';

register_activation_hook(__FILE__, ['\\Politeia\\Core\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\\Politeia\\Core\\Deactivator', 'deactivate']);

add_action('plugins_loaded', function () {
    $kernel = new \\Politeia\\Core\\Kernel(_VER);
    $kernel->register_modules([
        new \\Politeia\\Modules\\I18n\\I18n(),
        new \\Politeia\\Modules\\Assets\\Assets(),
        // Agrega tus módulos aquí: REST, Database, Admin, Cron, etc.
        new \\Politeia\\Modules\\Database\\Installer()
    ]);
    $kernel->boot();
});
