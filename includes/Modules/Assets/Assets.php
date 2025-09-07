<?php
namespace Politeia\Modules\Assets;

class Assets {
    public function register(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_front']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
    }
    public function boot(): void {}
    public function enqueue_front(): void {
        wp_enqueue_style('politeia-electoral-map-app', _URL . 'assets/css/app.css', [], _VER);
        wp_enqueue_script('politeia-electoral-map-app', _URL . 'assets/js/app.js', ['wp-i18n'], _VER, true);
        wp_localize_script('politeia-electoral-map-app', '', ['rest' => esc_url_raw( rest_url('politeia-electoral-map/v1') )]);
    }
    public function enqueue_admin(): void {}
}
