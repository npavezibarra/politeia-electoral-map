#!/bin/bash
# MySQL Query Wrapper for Local by Flywheel
# Usage: ./mysql-query.sh "SELECT * FROM wp_politeia_elections LIMIT 5"

SITE_ROOT="/Users/nicolasibarra/Local Sites/politeia/app/public"

if [ -z "$1" ]; then
    echo "Usage: $0 'SQL QUERY'"
    echo "Example: $0 'SHOW TABLES LIKE \"wp_politeia%\"'"
    exit 1
fi

QUERY="$1"

# Create temporary PHP script
cat > /tmp/mysql_exec.php << 'EOPHP'
<?php
require_once '/Users/nicolasibarra/Local Sites/politeia/app/public/wp-load.php';
global $wpdb;

$query = $argv[1] ?? '';
if (empty($query)) {
    die("No query provided\n");
}

// Execute query
$results = $wpdb->get_results($query, ARRAY_A);

if ($wpdb->last_error) {
    echo "ERROR: " . $wpdb->last_error . "\n";
    exit(1);
}

// Check if it's a non-SELECT query
if ($results === null) {
    echo "Query executed successfully. Rows affected: " . $wpdb->rows_affected . "\n";
    exit(0);
}

// Display results
if (empty($results)) {
    echo "Empty set\n";
    exit(0);
}

// Print header
$headers = array_keys($results[0]);
echo implode("\t", $headers) . "\n";
echo str_repeat("-", 80) . "\n";

// Print rows
foreach ($results as $row) {
    echo implode("\t", array_map(function($v) { 
        return $v === null ? 'NULL' : $v; 
    }, $row)) . "\n";
}

echo "\n" . count($results) . " rows in set\n";
EOPHP

# Use PHP binary from Local
PHP_BIN="/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin/bin/php"

if [ ! -x "$PHP_BIN" ]; then
    echo "Error: PHP binary not found at $PHP_BIN"
    exit 1
fi

# Execute
$PHP_BIN /tmp/mysql_exec.php "$QUERY"
