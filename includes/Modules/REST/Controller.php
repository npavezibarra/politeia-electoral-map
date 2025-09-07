<?php
namespace Politeia\Modules\REST;

abstract class Controller {
    abstract public function namespace(): string;
    abstract public function route_base(): string;

    public function register(): void {
        add_action('rest_api_init', function () {
            register_rest_route($this->namespace(), '/' . $this->route_base(), [
                'methods'  => 'GET',
                'callback' => [$this, 'handle_index'],
                'permission_callback' => '__return_true'
            ]);
        });
    }

    public function boot(): void {}

    public function handle_index($req) {
        return new \WP_REST_Response(['ok' => true, 'route' => $this->route_base()], 200);
    }
}
