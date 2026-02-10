<?php
/**
 * Web Script to export DB Candidates to JSON.
 * Access via browser to generate 'candidates_master.json'.
 */

require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    die('Access Denied. Admin only.');
}

global $wpdb;

// Define tables
$jurisdictions = $wpdb->prefix . 'politeia_jurisdictions';
$candidacies = $wpdb->prefix . 'politeia_candidacies';
$elections = $wpdb->prefix . 'politeia_elections';
$people = $wpdb->prefix . 'politeia_people';
$offices = $wpdb->prefix . 'politeia_offices';

header('Content-Type: text/plain');
echo "Exporting candidates for 2024...\n";
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
    // If ID not found, return Unknown
    if (!isset($jur_map[$jur_id]))
        return "DESCONOCIDA";

    $current = $jur_map[$jur_id];

    // Optimization: If current is DISTRICT, its parent is REGION (usually).
    // If current is COMMUNE, parent is PROVINCE -> REGION.
    // We walk up max 5 levels.

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

// Helpers
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
    // Clean inputs
    $given = trim($given);
    $paternal = trim($paternal);
    $maternal = trim($maternal);

    $parts = explode(' ', $given);
    $first = $parts[0];

    $terms = [];
    // Full: "Victor Ramon Toro Leiva"
    $terms[] = mb_strtolower(politeia_remove_accents("$given $paternal $maternal"));
    // "Victor Ramon Toro"
    $terms[] = mb_strtolower(politeia_remove_accents("$given $paternal"));
    // "Victor Toro"
    $terms[] = mb_strtolower(politeia_remove_accents("$first $paternal"));
    // "Victor Toro Leiva"
    $terms[] = mb_strtolower(politeia_remove_accents("$first $paternal $maternal"));

    // Surnames only? Scraper usually has name + surname.
    // "Toro Leiva" might be risky but possible.

    return array_values(array_unique($terms));
}

// 2. Fetch Candidates
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
    WHERE YEAR(e.election_date) = 2024
";

$rows = $wpdb->get_results($sql);
echo "Found " . count($rows) . " candidates.\n";

$output_data = [];

// Determine JSON path: plugin dir or absolute?
// User has Python script in Downloads/FIRST-ATTEMPT-FAIL/GhostMouse
// Ideally we save it where Python scraper can see it, OR user downloads it.
// Let's save to: wp-content/plugins/politeia.../candidates_master.json
$json_path = WP_PLUGIN_DIR . '/politeia-electoral-map/candidates_master.json';

foreach ($rows as $row) {
    $full_name = trim($row->given_names . ' ' . $row->paternal_surname . ' ' . $row->maternal_surname);

    $region_name = find_region($row->jur_id, $jur_map);
    $region_clean = str_replace("DE ", "", strtoupper($region_name));

    // Use normalized uppercase jurisdiction key for lookup
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
