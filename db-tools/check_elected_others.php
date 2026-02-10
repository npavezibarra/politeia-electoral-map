<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "--- ELECTED STATUS IN OTHERS --- \n";
$elected_others = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}politeia_candidacies WHERE election_id IN (39, 40, 41, 42, 43) AND elected = 1");
echo "Total Elected in 'Others': $elected_others\n";

if ($elected_others > 0) {
    echo "Sample Elected in 'Others':\n";
    $sample = $wpdb->get_results("SELECT p.paternal_surname, e.name FROM {$wpdb->prefix}politeia_candidacies c JOIN {$wpdb->prefix}politeia_people p ON p.id = c.person_id JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id WHERE c.elected = 1 AND e.id IN (39, 40, 41, 42, 43) LIMIT 5");
    foreach ($sample as $s) {
        echo "- {$s->paternal_surname} | {$s->name}\n";
    }
}
