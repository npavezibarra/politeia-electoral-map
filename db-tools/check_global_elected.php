<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

$total_cands = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}politeia_candidacies");
$elected_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}politeia_candidacies WHERE elected = 1");

echo "Total Candidacies: $total_cands\n";
echo "Total Elected (flag=1): $elected_count\n";

if ($elected_count > 0) {
    echo "Sample Elected Offices:\n";
    $sample = $wpdb->get_results("SELECT o.code, COUNT(*) as count 
        FROM {$wpdb->prefix}politeia_candidacies c
        JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
        JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
        WHERE c.elected = 1 GROUP BY o.code");
    foreach ($sample as $s) {
        echo "- {$s->code}: {$s->count}\n";
    }
}
