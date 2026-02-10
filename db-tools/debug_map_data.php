<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

$jur_id = 154; // Las Condes as per screenshot
$target_date = date('Y-m-d');

echo "DIAGNOSTIC FOR JURISDICTION ID: $jur_id on date $target_date\n";

// Check Jurisdiction
$jur = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}politeia_jurisdictions WHERE id = $jur_id");
echo "Jurisdiction: " . ($jur ? $jur->official_name : "NOT FOUND") . " (Type: " . ($jur ? $jur->type : "N/A") . ")\n";

// Check Terms
$terms = $wpdb->get_results("SELECT t.*, p.paternal_surname, o.code as office_code 
    FROM {$wpdb->prefix}politeia_office_terms t 
    JOIN {$wpdb->prefix}politeia_people p ON p.id = t.person_id
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = t.office_id
    WHERE t.jurisdiction_id = $jur_id");
echo "Terms found: " . count($terms) . "\n";
foreach ($terms as $t) {
    echo "- Office: {$t->office_code} | Person: {$t->paternal_surname} | Start: {$t->started_on} | End: {$t->ended_on}\n";
}

// Check Candidacies
$cands = $wpdb->get_results("SELECT c.*, p.paternal_surname, o.code as office_code, e.election_date
    FROM {$wpdb->prefix}politeia_candidacies c 
    JOIN {$wpdb->prefix}politeia_people p ON p.id = c.person_id
    JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
    WHERE c.jurisdiction_id = $jur_id AND c.elected = 1");
echo "Elected Candidacies found: " . count($cands) . "\n";
foreach ($cands as $c) {
    echo "- Office: {$c->office_code} | Person: {$c->paternal_surname} | Election Date: {$c->election_date}\n";
}

// Check if ALCALDE exists in offices
$office_alcalde = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}politeia_offices WHERE code = 'ALCALDE'");
echo "Office 'ALCALDE' ID: " . ($office_alcalde ?: "NOT FOUND") . "\n";
