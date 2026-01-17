<?php
/**
 * Migration: Seed Electoral Districts and Memberships
 * Date: 2026-01-16
 * 
 * Creates 'DISTRICT' jurisdictions (Distrito Electoral N° X)
 * and links them to their constituent Communes in wp_politeia_jurisdiction_memberships.
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

/**
 * Execute migration on plugins_loaded after Upgrader has created the tables.
 */
add_action('plugins_loaded', function () {
    global $wpdb;

    $t_jur = $wpdb->prefix . 'politeia_jurisdictions';
    $t_mem = $wpdb->prefix . 'politeia_jurisdiction_memberships';

    // Verify table exists before running (Upgrader runs on priority 10)
    if ($wpdb->get_var("SHOW TABLES LIKE '$t_mem'") != $t_mem) {
        return;
    }


    // Data provided by user
    $districts_data = [
        1 => ['Arica', 'Camarones', 'General Lagos', 'Putre'],
        2 => ['Alto Hospicio', 'Camiña', 'Colchane', 'Huara', 'Iquique', 'Pica', 'Pozo Almonte'],
        3 => ['Antofagasta', 'Calama', 'María Elena', 'Mejillones', 'Ollagüe', 'San Pedro de Atacama', 'Sierra Gorda', 'Taltal', 'Tocopilla'],
        4 => ['Alto del Carmen', 'Caldera', 'Chañaral', 'Copiapó', 'Diego de Almagro', 'Freirina', 'Huasco', 'Tierra Amarilla', 'Vallenar'],
        5 => ['Andacollo', 'Canela', 'Combarbalá', 'Coquimbo', 'Illapel', 'La Higuera', 'La Serena', 'Los Vilos', 'Monte Patria', 'Ovalle', 'Paihuano', 'Punitaqui', 'Río Hurtado', 'Salamanca', 'Vicuña'],
        6 => ['Cabildo', 'Calle Larga', 'Catemu', 'Hijuelas', 'La Calera', 'La Cruz', 'La Ligua', 'Limache', 'Llaillay', 'Los Andes', 'Nogales', 'Olmué', 'Panquehue', 'Papudo', 'Petorca', 'Puchuncaví', 'Putaendo', 'Quillota', 'Quilpué', 'Quintero', 'Rinconada', 'San Esteban', 'San Felipe', 'Santa María', 'Villa Alemana', 'Zapallar'],
        7 => ['Algarrobo', 'Cartagena', 'Casablanca', 'Concón', 'El Quisco', 'El Tabo', 'Isla de Pascua', 'Juan Fernández', 'San Antonio', 'Santo Domingo', 'Valparaíso', 'Viña del Mar'],
        8 => ['Cerrillos', 'Colina', 'Estación Central', 'Lampa', 'Maipú', 'Pudahuel', 'Quilicura', 'Tiltil'],
        9 => ['Cerro Navia', 'Conchalí', 'Huechuraba', 'Independencia', 'Lo Prado', 'Quinta Normal', 'Recoleta', 'Renca'],
        10 => ['La Granja', 'Macul', 'Ñuñoa', 'Providencia', 'San Joaquín', 'Santiago'],
        11 => ['La Reina', 'Las Condes', 'Lo Barnechea', 'Peñalolén', 'Vitacura'],
        12 => ['La Florida', 'La Pintana', 'Pirque', 'Puente Alto', 'San José de Maipo'],
        13 => ['El Bosque', 'La Cisterna', 'Lo Espejo', 'Pedro Aguirre Cerda', 'San Miguel', 'San Ramón'],
        14 => ['Alhué', 'Buin', 'Calera de Tango', 'Curacaví', 'El Monte', 'Isla de Maipo', 'María Pinto', 'Melipilla', 'Padre Hurtado', 'Paine', 'Peñaflor', 'San Bernardo', 'San Pedro', 'Talagante'],
        15 => ['Codegua', 'Coinco', 'Coltauco', 'Doñihue', 'Graneros', 'Machalí', 'Malloa', 'Mostazal', 'Olivar', 'Quinta de Tilcoco', 'Rancagua', 'Rengo', 'Requínoa'],
        16 => ['Chépica', 'Chimbarongo', 'La Estrella', 'Las Cabras', 'Litueche', 'Lolol', 'Marchihue', 'Nancagua', 'Navidad', 'Palmilla', 'Paredones', 'Peralillo', 'Peumo', 'Pichidegua', 'Pichilemu', 'Placilla', 'Pumanque', 'San Fernando', 'Santa Cruz', 'San Vicente'],
        17 => ['Constitución', 'Curepto', 'Curicó', 'Empedrado', 'Hualañé', 'Licantén', 'Maule', 'Molina', 'Pelarco', 'Pencahue', 'Rauco', 'Río Claro', 'Romeral', 'Sagrada Familia', 'San Clemente', 'San Rafael', 'Talca', 'Teno', 'Vichuquén'],
        18 => ['Cauquenes', 'Chanco', 'Colbún', 'Linares', 'Longaví', 'Parral', 'Pelluhue', 'Retiro', 'San Javier', 'Villa Alegre', 'Yerbas Buenas'],
        19 => ['Bulnes', 'Chillán', 'Chillán Viejo', 'Cobquecura', 'Coelemu', 'Coihueco', 'El Carmen', 'Ninhue', 'Ñiquén', 'Pemuco', 'Pinto', 'Portezuelo', 'Quillón', 'Quirihue', 'Ránquil', 'San Carlos', 'San Fabián', 'San Ignacio', 'San Nicolás', 'Treguaco', 'Yungay'],
        20 => ['Chiguayante', 'Concepción', 'Coronel', 'Florida', 'Hualpén', 'Hualqui', 'Penco', 'San Pedro de la Paz', 'Santa Juana', 'Talcahuano', 'Tomé'],
        21 => ['Alto Biobío', 'Antuco', 'Arauco', 'Cabrero', 'Cañete', 'Contulmo', 'Curanilahue', 'Laja', 'Lebu', 'Los Alamos', 'Los Angeles', 'Lota', 'Mulchén', 'Nacimiento', 'Negrete', 'Quilaco', 'Quilleco', 'San Rosendo', 'Santa Bárbara', 'Tirúa', 'Tucapel', 'Yumbel'],
        22 => ['Angol', 'Collipulli', 'Curacautín', 'Ercilla', 'Galvarino', 'Lautaro', 'Lonquimay', 'Los Sauces', 'Lumaco', 'Melipeuco', 'Perquenco', 'Purén', 'Renaico', 'Traiguén', 'Victoria', 'Vilcún'],
        23 => ['Carahue', 'Cholchol', 'Cunco', 'Curarrehue', 'Freire', 'Gorbea', 'Loncoche', 'Nueva Imperial', 'Padre Las Casas', 'Pitrufquén', 'Pucón', 'Saavedra', 'Temuco', 'Teodoro Schmidt', 'Toltén', 'Villarrica'],
        24 => ['Corral', 'Futrono', 'Lago Ranco', 'Lanco', 'La Unión', 'Los Lagos', 'Máfil', 'Mariquina', 'Paillaco', 'Panguipulli', 'Río Bueno', 'Valdivia'],
        25 => ['Fresia', 'Frutillar', 'Llanquihue', 'Los Muermos', 'Osorno', 'Puerto Octay', 'Puerto Varas', 'Purranque', 'Puyehue', 'Río Negro', 'San Juan de la Costa', 'San Pablo'],
        26 => ['Ancud', 'Calbuco', 'Castro', 'Chaitén', 'Chonchi', 'Cochamó', 'Curaco de Vélez', 'Dalcahue', 'Futaleufú', 'Hualaihué', 'Maullín', 'Palena', 'Puerto Montt', 'Puqueldón', 'Queilén', 'Quellón', 'Quemchi', 'Quinchao'],
        27 => ['Aysén', 'Chile Chico', 'Cisnes', 'Cochrane', 'Coyhaique', 'Guaitecas', 'Lago Verde', 'O\'Higgins', 'Río Ibáñez', 'Tortel'],
        28 => ['Antártica', 'Cabo de Hornos', 'Laguna Blanca', 'Natales', 'Porvenir', 'Primavera', 'Punta Arenas', 'Río Verde', 'San Gregorio', 'Timaukel', 'Torres del Paine']
    ];

    foreach ($districts_data as $dist_num => $communes) {
        $dist_name = "Distrito Electoral N° $dist_num";

        // 1. Get or Create District
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $dist_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $t_jur WHERE official_name = %s AND type = 'DISTRICT'",
            $dist_name
        ));

        if (!$dist_id) {
            $wpdb->insert($t_jur, [
                'official_name' => $dist_name,
                'common_name' => $dist_name,
                'type' => 'DISTRICT'
            ]);
            $dist_id = $wpdb->insert_id;
        }

        if (!$dist_id) {
            continue; // Failed to create district
        }

        // 2. Link Communes
        foreach ($communes as $commune_name) {
            $commune_name = trim($commune_name);

            // Try direct match (handling uppercase DB values and 'COMMUNE' type)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $commune_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $t_jur WHERE (UPPER(official_name) = UPPER(%s) OR UPPER(common_name) = UPPER(%s)) AND (type = 'COMMUNE' OR type = 'COMUNA' OR type = 'Comuna')",
                $commune_name,
                $commune_name
            ));

            // Try simple accent replacements if not found 
            if (!$commune_id) {
                // e.g. "Los Angeles" (input) -> "Los Ángeles" (db)
                $alt_name = str_replace(
                    ['Los Angeles', 'Los Alamos', 'Aysen', 'Maria Elena', 'Ollague', 'Maullin', 'Rio Ibañez'],
                    ['Los Ángeles', 'Los Álamos', 'Aysén', 'María Elena', 'Ollagüe', 'Maullín', 'Río Ibáñez'],
                    $commune_name
                );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $commune_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $t_jur WHERE (UPPER(official_name) = UPPER(%s) OR UPPER(common_name) = UPPER(%s)) AND (type = 'COMMUNE' OR type = 'COMUNA' OR type = 'Comuna')",
                    $alt_name,
                    $alt_name
                ));
            }

            if ($commune_id) {
                // INSERT IGNORE to assume uniqueness from table schema
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO $t_mem 
                (parent_jurisdiction_id, child_jurisdiction_id, relationship_type, valid_from)
                VALUES (%d, %d, 'ELECTORAL', '2016-01-01')", // Valid since 2016 reform roughly
                    $dist_id,
                    $commune_id
                ));
            }
        }
    }
}, 20);
