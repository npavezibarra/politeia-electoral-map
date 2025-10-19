<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
namespace Politeia\Core;

class Kernel {
    public const PLEM_VERSION = '0.2.6';

    /** @var object[] */
    private array $modules = [];
    private string $version;

    public function __construct(string $version = self::PLEM_VERSION) { $this->version = $version; }

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
        Upgrader::maybe_upgrade();
    }
}
