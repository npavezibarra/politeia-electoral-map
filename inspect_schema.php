<!DOCTYPE html>
<html><body><pre>
<?php
require_once __DIR__ . '/../../../wp-load.php';
global $wpdb;

$tables = $wpdb->get_results("SHOW TABLES LIKE '" . $wpdb->prefix . "politeia_%'");
foreach ($tables as $t) {
    $vals = array_values((array)$t);
    $table_name = $vals[0];
    echo "TABLE: $table_name\n";
    
    $cols = $wpdb->get_results("DESCRIBE $table_name");
    foreach ($cols as $col) {
        echo "  - " . $col->Field . " (" . $col->Type . ")\n";
    }
    echo "\n";
}
?>
</pre></body></html>