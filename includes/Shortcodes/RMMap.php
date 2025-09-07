<?php
if ( ! defined('ABSPATH') ) exit;

class PLEM_RM_Map_Shortcode {
	public static function register() {
		add_shortcode('plem_rm_map', [__CLASS__, 'render']);
	}

	public static function render($atts = []) {
		$plugin_main = WP_PLUGIN_DIR . '/politeia-electoral-map/politeia-electoral-map.php';
		$src = plugins_url('assets/map/rm-map.html', $plugin_main);

		$api_key = get_option('plem_google_maps_api_key', '');
		if ( ! empty($api_key) ) {
			$src = add_query_arg('api_key', rawurlencode($api_key), $src);
		}

		return '<iframe src="'.esc_url($src).'" style="width:100%;height:100vh;border:0;" loading="lazy"></iframe>';
	}
}
PLEM_RM_Map_Shortcode::register();
