<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

$offices = $wpdb->get_results("SELECT id, code, title FROM {$wpdb->prefix}politeia_offices", ARRAY_A);
echo "OFFICES:\n";
print_r($offices);

$elections_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}politeia_elections");
echo "Total Elections: $elections_count\n";

$terms_sample = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}politeia_office_terms LIMIT 3", ARRAY_A);
echo "TERMS SAMPLE:\n";
print_r($terms_sample);
