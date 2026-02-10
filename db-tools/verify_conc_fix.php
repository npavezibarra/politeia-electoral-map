<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "--- CONCEJAL VERIFICATION FOR LAS CONDES (ID 154) --- \n";
$jur_id = 154;

$elecs = $wpdb->get_results("
    SELECT e.id, e.name, o.code 
    FROM {$wpdb->prefix}politeia_elections e 
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id 
    WHERE e.id IN (39, 43)
");
foreach ($elecs as $e) {
    echo "- Election ID: {$e->id} | Name: {$e->name} | Office: {$e->code}\n";
    $elected_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}politeia_candidacies WHERE election_id = %d AND jurisdiction_id = %d AND elected = 1", $e->id, $jur_id));
    echo "  -> Elected Winners in Las Condes: $elected_count\n";

    if ($elected_count > 0) {
        $winners = $wpdb->get_results($wpdb->prepare("
            SELECT p.given_names, p.paternal_surname 
            FROM {$wpdb->prefix}politeia_candidacies c 
            JOIN {$wpdb->prefix}politeia_people p ON p.id = c.person_id 
            WHERE c.election_id = %d AND c.jurisdiction_id = %d AND c.elected = 1
        ", $e->id, $jur_id));
        foreach ($winners as $w) {
            echo "     * {$w->given_names} {$w->paternal_surname}\n";
        }
    }
}
