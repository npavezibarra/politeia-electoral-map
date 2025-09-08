<?php
namespace Politeia\Modules\Assets;

if ( ! defined('ABSPATH') ) exit;

class Assets {

	public function __construct() {
		add_action('wp_enqueue_scripts', [ $this, 'enqueue_front' ]);
		add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin' ]);
	}

	public function enqueue_front() {
		// Usa constantes globales con prefijo "\" por estar en namespace
		$base_url = \PLEM_URL;

		// Ejemplos de enqueue (ajusta si no tienes estos archivos aún)
                // wp_enqueue_style('plem-map', $base_url . 'assets/css/map.css', [], \PLEM_VERSION);
                // wp_enqueue_script('plem-frontend', $base_url . 'assets/js/frontend.js', [], \PLEM_VERSION, true);
	}

	public function enqueue_admin($hook = '') {
		// Si quieres cargar algo sólo en la página de ajustes:
		// if ($hook !== 'toplevel_page_plem-settings') return;

		$base_url = \PLEM_URL;
                // wp_enqueue_style('plem-admin', $base_url . 'assets/css/admin.css', [], \PLEM_VERSION);
	}
}

// Instancia el módulo (hazlo desde tu contenedor/inicializador si tienes uno)
new Assets();
