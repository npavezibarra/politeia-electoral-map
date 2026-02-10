<?php
/**
 * Export 2017 Candidates Master List for Scraper Matching
 * Creates candidates_master_2017.json with DB IDs for name matching
 */

require_once('../../../../wp-load.php');
global $wpdb;

$candidacies = $wpdb->prefix . 'politeia_candidacies';
$people = $wpdb->prefix . 'politeia_people';
$elections = $wpdb->prefix . 'politeia_elections';
$offices = $wpdb->prefix . 'politeia_offices';
$jurisdictions = $wpdb->prefix . 'politeia_jurisdictions';

// 2017 Election IDs: SENADOR (10), PRESIDENTE (16), Senatorial Circunscripciones (21-27)
$election_ids = [10, 16, 21, 22, 23, 24, 25, 26, 27];

$results = [];

$query = $wpdb->prepare("
    SELECT 
        c.id as candidacy_id,
        c.person_id,
        p.given_names,
        p.paternal_surname,
        p.maternal_surname,
        e.id as election_id,
        e.title as election_title,
        o.code as office_code,
        j.common_name as jurisdiction_name
    FROM $candidacies c
    JOIN $people p ON c.person_id = p.id
    JOIN $elections e ON c.election_id = e.id
    LEFT JOIN $offices o ON e.office_id = o.id
    LEFT JOIN $jurisdictions j ON c.jurisdiction_id = j.id
    WHERE c.election_id IN (" . implode(',', array_fill(0, count($election_ids), '%d')) . ")
    ORDER BY p.paternal_surname, p.given_names
", $election_ids);

$rows = $wpdb->get_results($query);

// Use WP's remove_accents function (already exists)
function normalize_2017($s)
{
    $s = strtoupper(trim($s));
    $s = remove_accents($s); // WP function
    return $s;
}

foreach ($rows as $row) {
    $full_name = trim($row->given_names . ' ' . $row->paternal_surname . ' ' . ($row->maternal_surname ?? ''));

    // Generate search terms (various name combinations)
    $search_terms = [
        normalize_2017($full_name),
        normalize_2017($row->given_names . ' ' . $row->paternal_surname),
        normalize_2017($row->paternal_surname . ' ' . $row->given_names)
    ];

    // First name + paternal only
    $first = explode(' ', $row->given_names)[0];
    $search_terms[] = normalize_2017($first . ' ' . $row->paternal_surname);

    $results[] = [
        'candidacy_id' => intval($row->candidacy_id),
        'person_id' => intval($row->person_id),
        'db_name' => $full_name,
        'election_id' => intval($row->election_id),
        'election_title' => $row->election_title,
        'office' => $row->office_code ?? 'UNKNOWN',
        'jurisdiction' => $row->jurisdiction_name ?? 'National',
        'search_terms' => array_unique($search_terms)
    ];
}

$output_path = '/Users/nicolasibarra/Local Sites/politeia/app/public/wp-content/plugins/politeia-electoral-map/data/candidates_master_2017.json';

file_put_contents($output_path, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Exported " . count($results) . " candidates to $output_path\n";
