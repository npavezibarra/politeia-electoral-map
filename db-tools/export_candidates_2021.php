<?php
/**
 * Web Script to export DB Candidates to JSON (2021 Version).
 * Access via browser to generate 'candidates_master_2021.json'.
 */

require_once __DIR__ . '/wp-load.php';

// if (!current_user_can('manage_options')) {
//    die('Access Denied. Admin only.');
// }

global $wpdb;

// Define tables
$jurisdictions = $wpdb->prefix . 'politeia_jurisdictions';
$candidacies = $wpdb->prefix . 'politeia_candidacies';
$elections = $wpdb->prefix . 'politeia_elections';
$people = $wpdb->prefix . 'politeia_people';
$offices = $wpdb->prefix . 'politeia_offices';

header('Content-Type: text/plain');
echo "Exporting candidates for 2021...\n";
flush();

// 1. Build Jurisdiction Map
$jur_rows = $wpdb->get_results("SELECT id, official_name, type, parent_id FROM $jurisdictions");
$jur_map = [];
foreach ($jur_rows as $row) {
    $jur_map[$row->id] = $row;
}
echo "Loaded " . count($jur_map) . " jurisdictions.\n";

function find_region($jur_id, $jur_map)
{
    if (!isset($jur_map[$jur_id]))
        return "DESCONOCIDA";
    $current = $jur_map[$jur_id];
    $depth = 0;
    while ($current && $depth < 5) {
        if (strtoupper($current->type) === 'REGION') {
            return $current->official_name;
        }
        if ($current->parent_id && isset($jur_map[$current->parent_id])) {
            $current = $jur_map[$current->parent_id];
        } else {
            break;
        }
        $depth++;
    }
    return "DESCONOCIDA";
}

function politeia_remove_accents($string)
{
    if (!$string)
        return '';
    $string = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'],
        ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'],
        $string
    );
    return $string;
}

function generate_search_terms($given, $paternal, $maternal)
{
    $given = trim((string) $given);
    $paternal = trim((string) $paternal);
    $maternal = trim((string) $maternal);

    $parts = explode(' ', $given);
    $first = $parts[0];

    $terms = [];
    $terms[] = mb_strtolower(politeia_remove_accents("$given $paternal $maternal"));
    $terms[] = mb_strtolower(politeia_remove_accents("$given $paternal"));
    $terms[] = mb_strtolower(politeia_remove_accents("$first $paternal"));
    $terms[] = mb_strtolower(politeia_remove_accents("$first $paternal $maternal"));
    $terms[] = mb_strtolower(politeia_remove_accents("$paternal $maternal")); // Sometimes listed as surname first? Unlikely but safe.

    return array_values(array_unique($terms));
}

// 2. Fetch Candidates for 2021
$sql = "
    SELECT 
        c.person_id,
        p.given_names,
        p.paternal_surname,
        p.maternal_surname,
        o.code as office_code,
        j.id as jur_id,
        j.official_name as jur_name,
        j.type as jur_type,
        c.elected
    FROM $candidacies c
    JOIN $people p ON c.person_id = p.id
    JOIN $elections e ON c.election_id = e.id
    JOIN $offices o ON e.office_id = o.id
    JOIN $jurisdictions j ON c.jurisdiction_id = j.id
    WHERE YEAR(e.election_date) = 2021
";

$rows = $wpdb->get_results($sql);
if ($wpdb->last_error) {
    echo "SQL ERROR: " . $wpdb->last_error . "\n";
}
echo "Found " . count($rows) . " candidates in 2021.\n";

if (count($rows) == 0) {
    echo "DEBUG: Checking available election years:\n";
    $years = $wpdb->get_results("SELECT YEAR(e.election_date) as yr, COUNT(*) as c FROM $candidacies c JOIN $elections e ON c.election_id = e.id GROUP BY yr");
    foreach ($years as $y) {
        echo "Year {$y->yr}: {$y->c} candidates\n";
    }
}

$output_data = [];
$json_path = WP_PLUGIN_DIR . '/politeia-electoral-map/candidates_master_2021.json';

foreach ($rows as $row) {
    $full_name = trim($row->given_names . ' ' . $row->paternal_surname . ' ' . $row->maternal_surname);

    $region_name = find_region($row->jur_id, $jur_map);
    $region_clean = str_replace("DE ", "", strtoupper($region_name));

    // Normalized Key
    $jur_key = mb_strtoupper(politeia_remove_accents($row->jur_name));

    if (!isset($output_data[$jur_key])) {
        $output_data[$jur_key] = [
            'region_folder' => $region_clean,
            'original_jur_name' => $row->jur_name,
            'candidates' => []
        ];
    }

    $output_data[$jur_key]['candidates'][] = [
        'id' => $row->person_id,
        'db_name' => $full_name,
        'office' => $row->office_code,
        'is_elected' => (bool) $row->elected,
        'search_terms' => generate_search_terms($row->given_names, $row->paternal_surname, $row->maternal_surname)
    ];
}

file_put_contents($json_path, json_encode($output_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Saved master list to: $json_path\n";
echo "DONE.";
