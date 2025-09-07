<?php
namespace Politeia\Core;

class Activator {
    public static function activate(): void {
        // Ejecuta instalación inicial (tablas, opciones)
        if (class_exists('\\Politeia\\Modules\\Database\\Installer')) {
            (new \\Politeia\\Modules\\Database\\Installer())->install();
        }
        update_option('politeia-electoral-map_version', _VER);
    }
}
