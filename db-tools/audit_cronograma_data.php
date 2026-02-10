<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

$offices = ['PRESIDENTE', 'ALCALDE', 'CONCEJAL', 'CORE'];
foreach ($offices as $code) {
    $count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}politeia_office_terms t
        JOIN {$wpdb->prefix}politeia_offices o ON o.id = t.office_id
        WHERE o.code = %s", $code));
    echo "$code: $count terms found\n";

    if ($count > 0) {
        $sample = $wpdb->get_results($wpdb->prepare("
            SELECT t.started_on, p.paternal_surname 
            FROM {$wpdb->prefix}politeia_office_terms t
            JOIN {$wpdb->prefix}politeia_offices o ON o.id = t.office_id
            JOIN {$wpdb->prefix}politeia_people p ON p.id = t.person_id
            WHERE o.code = %s 
            ORDER BY t.started_on ASC LIMIT 5", $code), ARRAY_A);
        print_r($sample);
    }
}
