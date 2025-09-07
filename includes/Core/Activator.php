<?php
namespace Politeia\Core;

use Politeia\Modules\Database\Installer;

class Activator {
    public static function activate(): void {
        // Instala DB si el módulo existe
        if (class_exists(Installer::class)) {
            (new Installer())->install();
        }
        // Guarda versión instalada
        update_option('politeia-electoral-map_version', POLITEIAELECTORALMAP_VER);
    }
}
