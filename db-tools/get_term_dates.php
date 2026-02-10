<?php
/**
 * Get actual term start dates from database for PERÍODO widget
 */

require_once('../../../../wp-load.php');
global $wpdb;

echo "=== ACTUAL TERM START DATES BY OFFICE TYPE ===\n\n";

// Alcaldes (Comuna)
echo "### ALCALDES (Comuna) ###\n";
$alcalde_terms = $wpdb->get_results("
    SELECT DISTINCT t.started_on, COUNT(*) as count
    FROM wp_politeia_office_terms t
    JOIN wp_politeia_offices o ON o.id = t.office_id
    WHERE o.code = 'ALCALDE'
    GROUP BY t.started_on
    ORDER BY t.started_on
");
foreach ($alcalde_terms as $t) {
    echo "   {$t->started_on} ({$t->count} records)\n";
}

// Gobernadores (Region)
echo "\n### GOBERNADORES (Region) ###\n";
$gob_terms = $wpdb->get_results("
    SELECT DISTINCT t.started_on, COUNT(*) as count
    FROM wp_politeia_office_terms t
    JOIN wp_politeia_offices o ON o.id = t.office_id
    WHERE o.code = 'GOBERNADOR'
    GROUP BY t.started_on
    ORDER BY t.started_on
");
foreach ($gob_terms as $t) {
    echo "   {$t->started_on} ({$t->count} records)\n";
}

// Diputados (District)
echo "\n### DIPUTADOS (District) ###\n";
$dip_terms = $wpdb->get_results("
    SELECT DISTINCT t.started_on, COUNT(*) as count
    FROM wp_politeia_office_terms t
    JOIN wp_politeia_offices o ON o.id = t.office_id
    WHERE o.code = 'DIPUTADO'
    GROUP BY t.started_on
    ORDER BY t.started_on
");
foreach ($dip_terms as $t) {
    echo "   {$t->started_on} ({$t->count} records)\n";
}

// Senadores (Circunscripción)
echo "\n### SENADORES (Circunscripción) ###\n";
$sen_terms = $wpdb->get_results("
    SELECT DISTINCT t.started_on, COUNT(*) as count
    FROM wp_politeia_office_terms t
    JOIN wp_politeia_offices o ON o.id = t.office_id
    WHERE o.code = 'SENADOR'
    GROUP BY t.started_on
    ORDER BY t.started_on
");
foreach ($sen_terms as $t) {
    echo "   {$t->started_on} ({$t->count} records)\n";
}
