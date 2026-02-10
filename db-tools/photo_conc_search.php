<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "--- CONCEJAL ELECTION SEARCH ---\n";

// 1. Find all CONCEJAL elections
$conc_elections = $wpdb->get_results("
    SELECT e.id, e.election_date, e.name 
    FROM {$wpdb->prefix}politeia_elections e
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
    WHERE o.code = 'CONCEJAL'
    ORDER BY e.election_date DESC
");
foreach ($conc_elections as $e) {
    echo "- ID: {$e->id} | Date: {$e->election_date} | Name: {$e->name}\n";

    // Check if Las Condes (154) has records for this election
    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}politeia_candidacies WHERE election_id = %d AND jurisdiction_id = 154", $e->id));
    echo "  -> Las Condes Count: $count\n";
}

echo "\n--- PHOTO SEARCH --- \n";
// Check if Catalina San Martin has a photo in ANY candidacy record (maybe not the elected one)
$person_name = 'CATALINA SAN MART%';
$photo_check = $wpdb->get_results($wpdb->prepare("
    SELECT c.id, c.profile_photo_url, e.election_date, o.code
    FROM {$wpdb->prefix}politeia_candidacies c
    JOIN {$wpdb->prefix}politeia_people p ON p.id = c.person_id
    JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
    WHERE p.given_names LIKE 'CATALINA%' AND p.paternal_surname LIKE 'SAN MARTIN%'
"), ARRAY_A);

foreach ($photo_check as $pc) {
    echo "- ID: {$pc['id']} | Date: {$pc['election_date']} | Office: {$pc['code']} | Photo: " . ($pc['profile_photo_url'] ?: "EMPTY") . "\n";
}
