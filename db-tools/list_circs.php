<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;
$rows = $wpdb->get_results("SELECT id, official_name, type FROM wp_politeia_jurisdictions WHERE type IN ('SENATORIAL_CIRC', 'Circunscripción', 'SENATORIAL_CIRCUMSCRIPTION') OR official_name LIKE '%Circunscripción%' ORDER BY official_name");
foreach ($rows as $r) {
    echo "ID: $r->id | Name: $r->official_name | Type: $r->type\n";
}
