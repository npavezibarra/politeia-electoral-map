<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

$jur_id = 154; // Las Condes

echo "ELECTED FLAG CHECK FOR ALCALDES IN ID $jur_id\n";

$alcaldes = $wpdb->get_results($wpdb->prepare("SELECT c.id, c.elected, e.election_date, p.paternal_surname, o.code
    FROM {$wpdb->prefix}politeia_candidacies c
    JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
    JOIN {$wpdb->prefix}politeia_people p ON p.id = c.person_id
    WHERE c.jurisdiction_id = %d AND o.code = 'ALCALDE'
    ORDER BY e.election_date DESC", $jur_id));

foreach ($alcaldes as $a) {
    echo "- ID: {$a->id} | Elected: {$a->elected} | Date: {$a->election_date} | Person: {$a->paternal_surname}\n";
}
