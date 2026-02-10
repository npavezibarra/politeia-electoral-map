<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "JURISDICTION SEARCH FOR 'CONDES'\n";

$jurs = $wpdb->get_results("SELECT id, official_name, type FROM {$wpdb->prefix}politeia_jurisdictions WHERE official_name LIKE '%CONDES%' OR common_name LIKE '%CONDES%'");
foreach ($jurs as $j) {
    echo "- ID: {$j->id} | Name: {$j->official_name} | Type: {$j->type}\n";

    // Check alcaldes for each match
    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) 
        FROM {$wpdb->prefix}politeia_candidacies c
        JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
        JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
        WHERE c.jurisdiction_id = %d AND o.code = 'ALCALDE'", $j->id));
    echo "  -> ALCALDE Candidacies: $count\n";
}
