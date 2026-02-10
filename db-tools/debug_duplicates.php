<?php
// Load WordPress environment
require_once('../../../../wp-load.php');

global $wpdb;

echo "Checking Las Condes Candidates...\n";

// Get Jurisdiction ID for Las Condes
$jurisdiction = $wpdb->get_row("SELECT id, official_name FROM {$wpdb->prefix}politeia_jurisdictions WHERE official_name = 'Las Condes'");

if (!$jurisdiction) {
    die("Las Condes not found.\n");
}

$jur_id = $jurisdiction->id;
echo "Jurisdiction ID: $jur_id ({$jurisdiction->official_name})\n";

// Get most recent Mayor election for this jurisdiction
$election_id = $wpdb->get_var($wpdb->prepare(
    "SELECT election_id FROM {$wpdb->prefix}politeia_candidacies WHERE jurisdiction_id = %d ORDER BY id DESC LIMIT 1",
    $jur_id
));

if (!$election_id) {
    die("No election found for Las Condes.\n");
}

echo "Election ID: $election_id\n";

// Count total rows in candidacies
$total_rows = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}politeia_candidacies WHERE jurisdiction_id = %d AND election_id = %d",
    $jur_id,
    $election_id
));

// Count unique candidates (by person_id)
$unique_candidates = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT person_id) FROM {$wpdb->prefix}politeia_candidacies WHERE jurisdiction_id = %d AND election_id = %d",
    $jur_id,
    $election_id
));

echo "Total Rows: $total_rows\n";
echo "Unique Candidates: $unique_candidates\n";

if ($total_rows > $unique_candidates) {
    echo "WARNING: DUPLICATE CANDIDACIES DETECTED!\n";
    $ratio = $total_rows / $unique_candidates;
    echo "Duplication Factor: $ratio x\n";
} else {
    echo "No duplicates detected.\n";
}

// Show sum
$sum_votes = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(votes) FROM {$wpdb->prefix}politeia_candidacies WHERE jurisdiction_id = %d AND election_id = %d",
    $jur_id,
    $election_id
));
echo "Sum of Votes in DB: " . number_format($sum_votes) . "\n";
