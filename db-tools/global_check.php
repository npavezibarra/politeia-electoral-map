<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "--- GLOBAL OFFICE & ELECTION CHECK ---\n";

// 1. Check Offices
$offices = $wpdb->get_results("SELECT id, code, title FROM {$wpdb->prefix}politeia_offices");
foreach ($offices as $o) {
    echo "- Office ID: {$o->id} | Code: {$o->code} | Title: {$o->title}\n";
}

// 2. Check for ANY photo URLs
$photos = $wpdb->get_results("SELECT profile_photo_url, person_id, election_id FROM {$wpdb->prefix}politeia_candidacies WHERE profile_photo_url IS NOT NULL AND profile_photo_url != '' LIMIT 10");
echo "\nSample Photo URLs in DB:\n";
foreach ($photos as $p) {
    echo "- Person ID: {$p->person_id} | Election ID: {$p->election_id} | URL: {$p->profile_photo_url}\n";
}

// 3. Check for Concejales in ANY jurisdiction
$total_concejales = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}politeia_candidacies c JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id WHERE o.code = 'CONCEJAL'");
echo "\nTotal CONCEJAL Candidacies in DB: $total_concejales\n";

// 4. Check Las Condes Election IDs
$las_condes_elections = $wpdb->get_results("SELECT DISTINCT e.id, e.election_date, o.code 
    FROM {$wpdb->prefix}politeia_elections e 
    JOIN {$wpdb->prefix}politeia_candidacies c ON c.election_id = e.id 
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
    WHERE c.jurisdiction_id = 154");
echo "\nElections found for Las Condes (ID 154):\n";
foreach ($las_condes_elections as $le) {
    echo "- ID: {$le->id} | Date: {$le->election_date} | Office: {$le->code}\n";
}
