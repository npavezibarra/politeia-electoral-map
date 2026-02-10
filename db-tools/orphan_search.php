<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "--- ORPHANED CANDIDACIES --- \n";
$orphans = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}politeia_candidacies WHERE election_id NOT IN (SELECT id FROM {$wpdb->prefix}politeia_elections)");
echo "Candidacies with invalid election_id: $orphans\n";

$null_elections = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}politeia_candidacies WHERE election_id IS NULL");
echo "Candidacies with NULL election_id: $null_elections\n";

echo "\n--- DEEP PHOTO CHECK FOR CATALINA (ID 547) --- \n";
$cands = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}politeia_candidacies WHERE person_id = 547");
foreach ($cands as $c) {
    echo "- Candidacy ID: {$c->id} | Election ID: {$c->election_id} | Photo: " . ($c->profile_photo_url ?: "EMPTY") . " | Jurisdiction ID: {$c->jurisdiction_id}\n";

    // Check if election exists for this candidacy
    if ($c->election_id) {
        $elec = $wpdb->get_row("SELECT e.*, o.code FROM {$wpdb->prefix}politeia_elections e JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id WHERE e.id = {$c->election_id}");
        if ($elec) {
            echo "  -> Election: {$elec->name} ({$elec->code})\n";
        } else {
            echo "  -> Election NOT FOUND for ID: {$c->election_id}\n";
        }
    }
}

echo "\n--- SAMPLE ORPHANED CANDIDACY DATA ---\n";
$sample_orphans = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}politeia_candidacies WHERE election_id NOT IN (SELECT id FROM {$wpdb->prefix}politeia_elections) LIMIT 5");
foreach ($sample_orphans as $so) {
    echo "- ID: {$so->id} | Election ID: {$so->election_id} | Person ID: {$so->person_id} | Jur ID: {$so->jurisdiction_id}\n";
}
