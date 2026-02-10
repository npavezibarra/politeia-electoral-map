<?php
/**
 * Database query helper - access via browser at http://politeia.local/db_query.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/wp-load.php';

global $wpdb;

header('Content-Type: text/plain; charset=utf-8');

echo "=== Politeia Plugin Tables ===\n\n";

$tables = $wpdb->get_results("SHOW TABLES LIKE 'wp_politeia%'", ARRAY_N);
if ($tables) {
    foreach ($tables as $table) {
        $table_name = $table[0];
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
        echo "$table_name: $count rows\n";
    }
} else {
    echo "No wp_politeia tables found.\n";
}

echo "\n=== Sample Table Structure ===\n\n";

// Show structure of first politeia table if exists
if (!empty($tables)) {
    $sample_table = $tables[0][0];
    echo "Structure of $sample_table:\n";
    $columns = $wpdb->get_results("DESCRIBE `$sample_table`", ARRAY_A);
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
}
