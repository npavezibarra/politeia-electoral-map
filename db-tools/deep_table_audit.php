<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "--- ELECTION TITLES FOR OFFICE_ID = 0 ---\n";
$elecs = $wpdb->get_results("SELECT id, name, title FROM {$wpdb->prefix}politeia_elections WHERE office_id = 0");
foreach ($elecs as $e) {
    echo "- ID: {$e->id} | Name: {$e->name} | Title: {$e->title}\n";
}

echo "\n--- PEOPLE TABLE SCHEMA CHECK ---\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}politeia_people");
foreach ($columns as $col) {
    echo "- Field: {$col->Field} | Type: {$col->Type}\n";
}

echo "\n--- SEARCHING FOR ANY RECORD WITH A PHOTO URL IN ANY TABLE ---\n";
// Sometimes it's in a different column name
$tables = [$wpdb->prefix . 'politeia_people', $wpdb->prefix . 'politeia_candidacies', $wpdb->prefix . 'politeia_political_organizations'];
foreach ($tables as $t) {
    $cols = $wpdb->get_results("SHOW COLUMNS FROM $t");
    foreach ($cols as $c) {
        if (strpos(strtolower($c->Field), 'photo') !== false || strpos(strtolower($c->Field), 'image') !== false || strpos(strtolower($c->Field), 'url') !== false) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE {$c->Field} IS NOT NULL AND {$c->Field} != ''");
            echo "Table $t | Field {$c->Field} | Count with data: $count\n";
        }
    }
}
