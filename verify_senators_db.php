<?php
require_once __DIR__ . '/../../../wp-load.php';
global $wpdb;

echo "Verifying Active Senators (Current Date: " . current_time('Y-m-d') . ")\n";

$circs = [2, 7, 9, 12]; // Sample mix of 2017 and 2021 elections

foreach ($circs as $c) {
    echo "\nCircunscripción $c:\n";
    $jur_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM wp_politeia_jurisdictions WHERE type='SENATORIAL_CIRC' AND official_name LIKE %s", $c . 'ª%'));

    if (!$jur_id) {
        echo "  [Error] Jurisdiction not found.\n";
        continue;
    }

    $terms = $wpdb->get_results($wpdb->prepare(
        "SELECT p.full_name, t.started_on, t.planned_end_on, o.official_name as party
         FROM wp_politeia_office_terms t
         JOIN wp_politeia_people p ON t.person_id = p.id
         LEFT JOIN wp_politeia_political_organizations o ON t.party_id = o.id
         WHERE t.jurisdiction_id = %d 
         AND t.office_id = (SELECT id FROM wp_politeia_offices WHERE title = 'SENADOR')
         AND CURDATE() BETWEEN t.started_on AND t.planned_end_on",
        $jur_id
    ));

    if (count($terms) === 0) {
        echo "  No active senators found.\n";
    }

    foreach ($terms as $t) {
        echo "  - {$t->full_name} ({$t->party}) [{$t->started_on} to {$t->planned_end_on}]\n";
    }
}
