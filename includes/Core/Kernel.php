<?php
namespace Politeia\Core;

class Kernel {
    /** @var object[] */
    private array $modules = [];
    private string $version;

    public function __construct(string $version) { $this->version = $version; }

    public function register_modules(array $modules): void { $this->modules = $modules; }

    public function boot(): void {
        foreach ($this->modules as $m) {
            if (method_exists($m, 'register')) { $m->register(); }
        }
        add_action('init', function () {
            foreach ($this->modules as $m) {
                if (method_exists($m, 'boot')) { $m->boot(); }
            }
        });
        (new Upgrader($this->version))->maybe_upgrade();
    }
}
