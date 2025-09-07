<?php
namespace Politeia\Modules\Database;

class Installer {
    public function register(): void {}
    public function boot(): void {}

    public function install(): void {
        // Crea tablas iniciales si aplica
        // require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        // dbDelta($sql);
    }

    public function migrate_010(): void {
        // Cambios de esquema a partir de la 0.1.0
    }
}
