<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "--- OFFICE CODES IN DB ---\n";
$offices = $wpdb->get_results("SELECT id, code, title FROM {$wpdb->prefix}politeia_offices");
foreach ($offices as $o) {
    echo "- ID: {$o->id} | Code: {$o->code} | Title: {$o->title}\n";
}

echo "\n--- CANDIDACY DISTRIBUTION BY OFFICE ---\n";
$dist = $wpdb->get_results("
    SELECT o.code, COUNT(*) as count 
    FROM {$wpdb->prefix}politeia_candidacies c
    JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
    GROUP BY o.code
");
foreach ($dist as $d) {
    echo "- Office: {$d->code} | Count: {$d->count}\n";
}
