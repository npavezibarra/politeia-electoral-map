<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

$jur_id = 154; // Las Condes

echo "DETAILED ALCALDE CHECK FOR ID $jur_id\n";

// Check all candidacies for this jurisdiction regardless of office to see what's there
$all_cands = $wpdb->get_results("SELECT c.id, o.code as office_code, c.elected, e.election_date, p.paternal_surname
    FROM {$wpdb->prefix}politeia_candidacies c
    JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
    JOIN {$wpdb->prefix}politeia_people p ON p.id = c.person_id
    WHERE c.jurisdiction_id = $jur_id
    ORDER BY e.election_date DESC LIMIT 50");

foreach ($all_cands as $c) {
    echo "- ID: {$c->id} | Office: {$c->office_code} | Elected: {$c->elected} | Date: {$c->election_date} | Person: {$c->paternal_surname}\n";
}

// Check for any term for Las Condes
$all_terms = $wpdb->get_results("SELECT t.id, o.code as office_code, p.paternal_surname, t.started_on
    FROM {$wpdb->prefix}politeia_office_terms t
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = t.office_id
    JOIN {$wpdb->prefix}politeia_people p ON p.id = t.person_id
    WHERE t.jurisdiction_id = $jur_id
    ORDER BY t.started_on DESC");

foreach ($all_terms as $t) {
    echo "- Term ID: {$t->id} | Office: {$t->office_code} | Start: {$t->started_on} | Person: {$t->paternal_surname}\n";
}
