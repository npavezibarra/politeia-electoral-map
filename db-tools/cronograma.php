<?php
/**
 * Cronograma Electoral de Chile (1999 - 2030)
 * Genera una visualización dinámica basada en los datos de la base de datos.
 */

// 1. Cargar WordPress
$wp_load_path = __DIR__ . '/../../../../wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die("Error: No se encontró wp-load.php en $wp_load_path");
}

global $wpdb;

// 2. Parámetros de la Línea de Tiempo
$start_year = 1999;
$end_year = 2030;
$total_years = $end_year - $start_year;

// 3. Obtención de Datos de la DB
$offices_raw = $wpdb->get_results("SELECT id, code, title FROM {$wpdb->prefix}politeia_offices", ARRAY_A);
$offices = [];
foreach ($offices_raw as $o) {
    $offices[$o['code']] = $o['id'];
}

// Mapeo de filas deseadas vs Office Code en DB
$rows_config = [
    'PRESIDENTE' => ['title' => 'PRESIDENTE', 'code' => 'PRESIDENTE', 'type' => 'PRIMARY'],
    'SENADOR_PARES' => ['title' => 'SENADORES (Reg. Pares)', 'code' => 'SENADOR', 'type' => 'SENATE_PARES'],
    'SENADOR_IMP' => ['title' => 'SENADORES (Reg. Imp)', 'code' => 'SENADOR', 'type' => 'SENATE_IMP'],
    'DIPUTADO' => ['title' => 'DIPUTADOS', 'code' => 'DIPUTADO', 'type' => 'PRIMARY'],
    'GOBERNADOR' => ['title' => 'GOBERNADORES', 'code' => 'GOBERNADOR', 'type' => 'PRIMARY'],
    'ALCALDE' => ['title' => 'ALCALDES', 'code' => 'ALCALDE', 'type' => 'PRIMARY'],
    'CORE' => ['title' => 'CORE', 'code' => 'CORE', 'type' => 'PRIMARY'],
    'CONCEJAL' => ['title' => 'CONCEJALES', 'code' => 'CONCEJAL', 'type' => 'PRIMARY'],
];

/**
 * Función para calcular posición % basada en fecha
 */
function get_pos($date, $start_year, $total_years)
{
    $ts = strtotime($date);
    $year = (int) date('Y', $ts);
    $day_of_year = (int) date('z', $ts);
    $days_in_year = (int) date('L', $ts) ? 366 : 365;

    $offset = $year - $start_year;
    $percent = (($offset + ($day_of_year / $days_in_year)) / ($total_years + 1)) * 100;
    return $percent;
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Cronograma Electoral de Chile (1999 - 2030)</title>
    <style>
        :root {
            --year-width: 40px;
            --election-color: #27ae60;
            --start-color: #e74c3c;
            --end-color: #3498db;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fff;
            color: #333;
            margin: 40px;
        }

        h1 {
            text-align: center;
            font-size: 24px;
            text-transform: uppercase;
            margin-bottom: 40px;
            letter-spacing: 1px;
        }

        /* Legend */
        .legend {
            display: flex;
            gap: 20px;
            border: 1px solid #ccc;
            padding: 10px;
            width: fit-content;
            margin-bottom: 30px;
            border-radius: 4px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-style: italic;
            font-size: 13px;
        }

        .legend-line {
            width: 4px;
            height: 18px;
            border-radius: 1px;
        }

        /* Timeline Container */
        .timeline-container {
            position: relative;
            padding-top: 40px;
            overflow-x: auto;
            min-width: 1400px;
            padding-bottom: 60px;
        }

        /* Grid Years */
        .year-axis {
            display: flex;
            border-top: 2px solid #555;
            margin-top: 20px;
            position: relative;
        }

        .year-tick {
            position: absolute;
            top: -5px;
            height: 12px;
            width: 1px;
            background: #555;
        }

        .year-label {
            position: absolute;
            top: 12px;
            transform: translateX(-50%);
            font-size: 11px;
            font-weight: bold;
            color: #333;
        }

        /* Rows */
        .row {
            display: flex;
            align-items: center;
            border-bottom: 1px solid #f0f0f0;
            height: 65px;
            position: relative;
        }

        .row-label {
            width: 220px;
            font-size: 10px;
            font-weight: bold;
            color: #444;
            text-transform: uppercase;
            padding-right: 25px;
            text-align: right;
            letter-spacing: 0.5px;
        }

        .timeline-area {
            flex-grow: 1;
            position: relative;
            height: 100%;
        }

        /* Bars and Markers */
        .term-bar {
            position: absolute;
            top: 18px;
            height: 28px;
            background: #ffffff;
            border: 1px solid #555555;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            box-shadow: 1px 3px 6px rgba(0, 0, 0, 0.15);
            z-index: 5;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 0 5px;
        }

        .marker {
            position: absolute;
            width: 3px;
            height: 42px;
            top: 11px;
            z-index: 10;
            border-radius: 1px;
        }

        .marker.election {
            background: var(--election-color);
            transform: translateX(-180%);
        }

        .marker.start {
            background: var(--start-color);
            transform: translateX(-50%);
        }

        .marker.end {
            background: var(--end-color);
            transform: translateX(50%);
        }

        /* Dots for years background */
        .year-grid-line {
            position: absolute;
            height: 100%;
            width: 1px;
            border-left: 1px solid #f5f5f5;
            top: 0;
            z-index: 0;
        }

        .year-grid-line.bold {
            border-left: 1px solid #eeeeee;
        }
    </style>
</head>

<body>

    <h1>Cronograma Electoral de Chile (1999 - 2030)</h1>

    <div class="legend">
        <div class="legend-item">
            <div class="legend-line" style="background: var(--election-color);"></div> Elección
        </div>
        <div class="legend-item">
            <div class="legend-line" style="background: var(--start-color);"></div> Inicio Periodo
        </div>
        <div class="legend-item">
            <div class="legend-line" style="background: var(--end-color);"></div> Fin de Periodo
        </div>
    </div>

    <div class="timeline-container">
        <!-- Background Grid -->
        <?php for ($i = 0; $i <= $total_years + 1; $i++):
            $left = ($i / ($total_years + 1)) * 100;
            $is_major = ($i % 4 == 0);
            ?>
                <div class="year-grid-line <?= $is_major ? 'bold' : '' ?>" style="left: <?= $left ?>%;"></div>
        <?php endfor; ?>

        <?php foreach ($rows_config as $id_conf => $conf): ?>
            <div class="row">
                <div class="row-label"><?= $conf['title'] ?></div>
                <div class="timeline-area">
                    <?php
                    // ID del cargo
                    $office_id = $offices[$conf['code']] ?? null;
                    if ($office_id) {
                        // 1. Elecciones
                        $elections = $wpdb->get_results($wpdb->prepare("SELECT election_date FROM {$wpdb->prefix}politeia_elections WHERE office_id = %d", $office_id));
                        foreach ($elections as $e) {
                            $pos = get_pos($e->election_date, $start_year, $total_years);
                            if ($pos >= 0 && $pos <= 100) {
                                echo "<div class='marker election' style='left: {$pos}%;' title='Elección: {$e->election_date}'></div>";
                            }
                        }

                        // 2. Periodos (Terms)
                        // Ajustamos la query para ser más robustos
                        $terms = $wpdb->get_results($wpdb->prepare("
                            SELECT t.started_on, t.planned_end_on, p.paternal_surname
                            FROM {$wpdb->prefix}politeia_office_terms t
                            LEFT JOIN {$wpdb->prefix}politeia_people p ON p.id = t.person_id
                            WHERE t.office_id = %d
                            ORDER BY t.started_on ASC
                        ", $office_id));

                        foreach ($terms as $t) {
                            $start_date = $t->started_on;
                            $end_date = $t->planned_end_on;

                            // Si no hay fecha de fin, calculamos una aproximada según el ciclo (4 años default)
                            if (!$end_date) {
                                $end_date = date('Y-m-d', strtotime($start_date . ' + 4 years'));
                            }

                            $start_pos = get_pos($start_date, $start_year, $total_years);
                            $end_pos = get_pos($end_date, $start_year, $total_years);
                            $width = $end_pos - $start_pos;

                            if ($start_pos >= 0 && $start_pos <= 100) {
                                echo "<div class='marker start' style='left: {$start_pos}%;'></div>";

                                if ($width > 0.5) {
                                    // Lógica de Etiquetas Especializada
                                    $label = "1"; // Default
                
                                    if ($conf['code'] == 'PRESIDENTE') {
                                        $label = "1 (" . ($t->paternal_surname ?? "S/N") . ")";
                                    } elseif ($conf['code'] == 'SENADOR') {
                                        $seats = ($start_date < '2017-01-01') ? "18" : "23";
                                        if ($conf['type'] == 'SENATE_IMP')
                                            $seats = ($start_date < '2017-01-01') ? "20" : "27";
                                        $suffix = ($conf['type'] == 'SENATE_PARES') ? " (Pares)" : " (Imp)";
                                        $label = "{$seats}{$suffix}";
                                    } elseif ($conf['code'] == 'DIPUTADO') {
                                        $label = ($start_date < '2017-01-01') ? "120" : "155";
                                    } elseif ($conf['code'] == 'GOBERNADOR') {
                                        $label = "16";
                                    } elseif ($conf['code'] == 'ALCALDE') {
                                        $label = "345";
                                    } elseif ($conf['code'] == 'CORE') {
                                        $label = ($start_date < '2021-01-01') ? "278" : "302";
                                    } elseif ($conf['code'] == 'CONCEJAL') {
                                        $label = ($start_date < '2021-01-01') ? "2240" : "2252";
                                    }

                                    echo "<div class='term-bar' style='left: {$start_pos}%; width: {$width}%;' title='{$start_date} a {$end_date}'>{$label}</div>";
                                }
                            }
                            if ($end_pos >= 0 && $end_pos <= 100) {
                                echo "<div class='marker end' style='left: {$end_pos}%;'></div>";
                            }
                        }
                    }
                    ?>
                    </div>
                </div>
        <?php endforeach; ?>

        <!-- Year Axis -->
        <div class="year-axis">
            <?php for ($i = 0; $i <= $total_years + 1; $i++):
                $left = ($i / ($total_years + 1)) * 100;
                $year = $start_year + $i;
                ?>
                    <div class="year-tick" style="left: <?= $left ?>%;"></div>
                    <div class="year-label" style="left: <?= $left ?>%;">
                        <?= $year ?>
                    </div>
            <?php endfor; ?>
        </div>
    </div>

</body>

</html>