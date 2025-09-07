<?php
namespace Politeia\Core;

use Politeia\Modules\Database\Installer;

class Upgrader {
    private string $version;
    private string $opt_key = 'politeia-electoral-map_version';

    public function __construct(string $version) { $this->version = $version; }

    public function maybe_upgrade(): void {
        $installed = get_option($this->opt_key);
        if ($installed === $this->version) { return; }

        // Migraciones por versión
        if (version_compare($installed ?: '0.0.0', '0.1.0', '<=')) {
            if (class_exists(Installer::class)) {
                (new Installer())->migrate_010();
            }
        }
        update_option($this->opt_key, $this->version);
    }
}
