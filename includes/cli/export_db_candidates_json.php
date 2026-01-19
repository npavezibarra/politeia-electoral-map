<?php
/**
 * CLI Script to export DB Candidates to JSON for the Python Scraper.
 * Usage: php includes/cli/export_db_candidates_json.php
 */

require_once dirname(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__))))))) . '/wp-load.php';

global $wpdb;

// Define tables
$jurisdictions = $wpdb->prefix . 'politeia_jurisdictions';
$candidacies = $wpdb->prefix . 'politeia_candidacies';
$elections = $wpdb->prefix . 'politeia_elections';
$people = $wpdb->prefix . 'politeia_people';
$offices = $wpdb->prefix . 'politeia_offices';

echo "Exporting candidates for 2024...\n";

// Query to fetch all 2024 candidates
// We need to construct the hierarchy Comuna -> Region to help folder organization
// NOTE: This assumes standard hierarchy: Commune -> Province -> Region OR Commune -> Region
// We'll try to fetch parent recursively or assume max depth.
// For simplification, we will fetch the candidates and their immediate jurisdiction, 
// and then doing a separate pass to resolve Regions for each Jurisdiction.
// Wait, 'wp_politeia_jurisdictions' has 'parent_id'.
// We can cache all jurisdictions first.

// 1. Build Jurisdiction Map (ID -> {name, type, parent_id})
$jur_rows = $wpdb->get_results("SELECT id, official_name, type, parent_id FROM $jurisdictions");
$jur_map = [];
foreach ($jur_rows as $row) {
    $jur_map[$row->id] = $row;
}
echo "Loaded " . count($jur_map) . " jurisdictions.\n";

// Helper to find Region Name for a given jurisdiction ID
function find_region($jur_id, $jur_map)
{
    $current = isset($jur_map[$jur_id]) ? $jur_map[$jur_id] : null;
    $depth = 0;
    while ($current && $depth < 5) {
        // If type is REGION, return name
        if (strtoupper($current->type) === 'REGION') {
            return $current->official_name;
        }
        // Move up
        if ($current->parent_id && isset($jur_map[$current->parent_id])) {
            $current = $jur_map[$current->parent_id];
        } else {
            break;
        }
        $depth++;
    }
    return "DESCONOCIDA"; // Fallback
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

foreach ($rows as $row) {
    $full_name = trim($row->given_names . ' ' . $row->paternal_surname . ' ' . $row->maternal_surname);

    // Normalize names for matching helper
    // We provide search terms for the python scraper to use
    $normalized = mb_strtolower(remove_accents($full_name));

    // Find Regional "Folder" Name
    $region_name = find_region($row->jur_id, $jur_map);

    // Clean Region Name (e.g. "DE TARAPACA" -> "TARAPACA")
    $region_clean = str_replace("DE ", "", strtoupper($region_name));

    // Structure:
    // We group by "Jurisdiction Name" (e.g. "ALHUÉ") because the scraper iterates Pages/Communes.
    // Inside, we list candidates.

    // Scraper Naming: "Alhué", "Santiago", etc. 
    // DB Naming: "ALHUÉ" (Upper). Scraper matches normalized usually.

    $jur_key = mb_strtoupper(remove_accents($row->jur_name));

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

// 3. Save JSON
$json_path = dirname(dirname(dirname(__FILE__))) . '/candidates_master.json';
file_put_contents($json_path, json_encode($output_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Saved master list to: $json_path\n";

// --- Helpers ---

function remove_accents($string)
{
    if (!$string)
        return '';
    $string = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ', ' '],
        ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N', '_'],
        $string
    );
    // Restore spaces for readability in some contexts, but here we used underscore. 
    // Actually for keys we probably want spaces replaced or just consistent.
    // Let's replace underscores back to spaces for 'search terms' if needed, but for ID keys underscore is fine.
    return str_replace('_', ' ', $string);
}

function generate_search_terms($given, $paternal, $maternal)
{
    $parts = explode(' ', trim($given));
    $first = $parts[0];

    $terms = [];
    $terms[] = mb_strtolower(remove_accents("$given $paternal $maternal"));
    $terms[] = mb_strtolower(remove_accents("$given $paternal"));
    $terms[] = mb_strtolower(remove_accents("$first $paternal")); // e.g. "Victor Toro"
    $terms[] = mb_strtolower(remove_accents("$first $paternal $maternal"));

    return array_values(array_unique($terms));
}
