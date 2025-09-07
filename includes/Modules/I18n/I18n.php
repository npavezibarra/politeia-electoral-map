<?php
namespace Politeia\Modules\I18n;

class I18n {
    public function register(): void {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }
    public function boot(): void {}
    public function load_textdomain(): void {
        // Carga de traducciones desde /languages
        load_plugin_textdomain('politeia-electoral-map', false, dirname(plugin_basename(__FILE__), 3) . '/languages');
    }
}
