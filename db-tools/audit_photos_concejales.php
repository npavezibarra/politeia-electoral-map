<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

$jur_id = 154; // Las Condes

echo "--- AUDIT FOR LAS CONDES (ID $jur_id) ---\n";

// 1. Check Alcalde Photo
$alcalde = $wpdb->get_row($wpdb->prepare("
    SELECT c.id, c.profile_photo_url, p.paternal_surname, e.election_date
    FROM {$wpdb->prefix}politeia_candidacies c
    JOIN {$wpdb->prefix}politeia_people p ON p.id = c.person_id
    JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
    WHERE c.jurisdiction_id = %d AND o.code = 'ALCALDE' AND c.elected = 1
    ORDER BY e.election_date DESC LIMIT 1
", $jur_id));

if ($alcalde) {
    echo "Current Alcalde: {$alcalde->paternal_surname} ({$alcalde->election_date})\n";
    echo "Photo URL in DB: " . ($alcalde->profile_photo_url ?: "EMPTY") . "\n";
} else {
    echo "No elected Alcalde found.\n";
}

// 2. Check Concejales
$concejales = $wpdb->get_results($wpdb->prepare("
    SELECT c.id, c.elected, p.given_names, p.paternal_surname, e.election_date
    FROM {$wpdb->prefix}politeia_candidacies c
    JOIN {$wpdb->prefix}politeia_people p ON p.id = c.person_id
    JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
    WHERE c.jurisdiction_id = %d AND o.code = 'CONCEJAL'
    ORDER BY e.election_date DESC LIMIT 20
", $jur_id));

echo "\nRecent Concejales in DB:\n";
foreach ($concejales as $c) {
    echo "- Name: {$c->given_names} {$c->paternal_surname} | Elected: {$c->elected} | Date: {$c->election_date}\n";
}
