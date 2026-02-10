<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "GLOBAL ALCALDE CHECK\n";

$alcalde_count = $wpdb->get_var("SELECT COUNT(*) 
    FROM {$wpdb->prefix}politeia_candidacies c
    JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
    WHERE o.code = 'ALCALDE'");

echo "Total ALCALDE Candidacies in DB: $alcalde_count\n";

if ($alcalde_count > 0) {
    $sample = $wpdb->get_results("SELECT DISTINCT j.official_name 
        FROM {$wpdb->prefix}politeia_candidacies c
        JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
        JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
        JOIN {$wpdb->prefix}politeia_jurisdictions j ON j.id = c.jurisdiction_id
        WHERE o.code = 'ALCALDE' LIMIT 10");
    echo "Sample Jurisdictions with ALCALDE data:\n";
    foreach ($sample as $s) {
        echo "- {$s->official_name}\n";
    }
}

// Check office terms
$term_count = $wpdb->get_var("SELECT COUNT(*) 
    FROM {$wpdb->prefix}politeia_office_terms t
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = t.office_id
    WHERE o.code = 'ALCALDE'");
echo "Total ALCALDE Office Terms in DB: $term_count\n";
