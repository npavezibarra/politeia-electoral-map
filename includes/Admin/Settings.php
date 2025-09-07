<?php
/**
 * Admin Settings Page – Politeia Electoral Map
 */
if ( ! defined('ABSPATH') ) exit;

class PLEM_Admin_Settings {

	public static function init() {
		add_action('admin_menu',  [__CLASS__, 'add_menu']);
		add_action('admin_init',  [__CLASS__, 'register_settings']);
	}

	/** Menú (nivel superior en el sidebar del admin) */
	public static function add_menu() {
		add_menu_page(
			__('Politeia Electoral Map', 'politeia-electoral-map'),
			__('Electoral Map', 'politeia-electoral-map'),
			'manage_options',
			'plem-settings',
			[__CLASS__, 'render_page'],
			'dashicons-location',
			58
		);
	}

	/** Registro de ajustes */
	public static function register_settings() {
		register_setting(
			'plem_settings_group',
			'plem_google_maps_api_key',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
				'default'           => '',
			]
		);

		add_settings_section(
			'plem_maps_section',
			__('Google Maps', 'politeia-electoral-map'),
			function () {
				echo '<p>'.esc_html__('Configura tu clave de Google Maps JavaScript API. Asegúrate de restringirla por dominios (HTTP referrers) y por API.', 'politeia-electoral-map').'</p>';
			},
			'plem_settings_page'
		);

		add_settings_field(
			'plem_google_maps_api_key',
			__('API Key', 'politeia-electoral-map'),
			[__CLASS__, 'field_api_key'],
			'plem_settings_page',
			'plem_maps_section'
		);
	}

	public static function field_api_key() {
		$val = get_option('plem_google_maps_api_key', '');
		printf(
			'<input type="text" name="plem_google_maps_api_key" value="%s" class="regular-text" style="width:420px;" autocomplete="off" />',
			esc_attr($val)
		);
		echo '<p class="description">'.esc_html__('Solo necesitamos “Maps JavaScript API” (y “Places API” si luego usas Autocomplete).', 'politeia-electoral-map').'</p>';
	}

	/** Render de la página */
	public static function render_page() {
		if ( ! current_user_can('manage_options') ) return;
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Politeia Electoral Map – Ajustes', 'politeia-electoral-map'); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields('plem_settings_group');
				do_settings_sections('plem_settings_page');
				submit_button(__('Guardar cambios', 'politeia-electoral-map'));
				?>
			</form>

			<hr>
			<h2><?php esc_html_e('Consejos de seguridad', 'politeia-electoral-map'); ?></h2>
			<ol>
				<li><?php esc_html_e('Restringe la API key por dominios (HTTP referrers): localhost, *.local, tu dominio en producción.', 'politeia-electoral-map'); ?></li>
				<li><?php esc_html_e('Restringe por API: Maps JavaScript API (y Places API si corresponde).', 'politeia-electoral-map'); ?></li>
			</ol>
		</div>
		<?php
	}
}

PLEM_Admin_Settings::init();
