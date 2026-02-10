<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "--- DEEP SEARCH FOR CONCEJALES --- \n";

// 1. Find ANY jurisdiction that has Concejales
$jur_with_conc = $wpdb->get_results("
    SELECT j.id, j.official_name, COUNT(*) as count 
    FROM {$wpdb->prefix}politeia_candidacies c
    JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
    JOIN {$wpdb->prefix}politeia_jurisdictions j ON j.id = c.jurisdiction_id
    WHERE o.code = 'CONCEJAL'
    GROUP BY j.id
    LIMIT 20
");
echo "Sample Jurisdictions with Concejales:\n";
foreach ($jur_with_conc as $j) {
    echo "- ID: {$j->id} | Name: {$j->official_name} | Count: {$j->count}\n";
}

// 2. Search for Catalina San Martin in PEOPLE table
$person = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}politeia_people WHERE paternal_surname LIKE '%SAN MARTIN%' AND given_names LIKE '%CATALINA%'");
if ($person) {
    echo "\nFound Catalina San Martin: ID {$person->id}\n";
    // Check all candidacies for her
    $cands = $wpdb->get_results("SELECT c.*, o.code, e.election_date 
        FROM {$wpdb->prefix}politeia_candidacies c 
        JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
        JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
        WHERE c.person_id = {$person->id}");
    foreach ($cands as $c) {
        echo "- Office: {$c->code} | Date: {$c->election_date} | Photo URL: " . ($c->profile_photo_url ?: "EMPTY") . " | Jurisdiction ID: {$c->jurisdiction_id}\n";
    }
} else {
    echo "\nCatalina San Martin NOT FOUND in PEOPLE table.\n";
}
