<?php
require_once(__DIR__ . '/../../../../wp-load.php');
global $wpdb;

$missing = $wpdb->get_results("
    SELECT j.official_name, j.external_code 
    FROM {$wpdb->prefix}politeia_jurisdictions j
    LEFT JOIN {$wpdb->prefix}politeia_jurisdiction_populations p ON p.jurisdiction_id = j.id
    WHERE j.type IN ('COMUNA', 'COMMUNE') 
    AND p.population IS NULL
");

echo "<h3>Missing Populations (" . count($missing) . ")</h3>";
foreach ($missing as $row) {
    echo "{$row->official_name} (Code: {$row->external_code})<br>";
}
