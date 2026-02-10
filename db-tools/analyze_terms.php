<?php
/**
 * Analyze office_terms for senators - check for overlapping terms
 */

require_once('../../../../wp-load.php');
global $wpdb;

echo "=== SENATOR OFFICE TERMS ANALYSIS ===\n\n";

$circ_ids = [403, 405, 407, 408, 410, 412, 413, 415, 416];

foreach ($circ_ids as $jur_id) {
    $jur_name = $wpdb->get_var("SELECT common_name FROM wp_politeia_jurisdictions WHERE id = $jur_id");

    echo "### Jurisdiction ID $jur_id: $jur_name ###\n";

    $terms = $wpdb->get_results("
        SELECT 
            t.id as term_id,
            t.person_id,
            p.given_names,
            p.paternal_surname,
            t.started_on,
            t.ended_on,
            t.planned_end_on,
            o.code as office_code
        FROM wp_politeia_office_terms t
        JOIN wp_politeia_people p ON p.id = t.person_id
        JOIN wp_politeia_offices o ON o.id = t.office_id
        WHERE t.jurisdiction_id = $jur_id
        AND o.code = 'SENADOR'
        ORDER BY t.started_on DESC
    ");

    if (empty($terms)) {
        echo "   No terms found.\n";
    } else {
        foreach ($terms as $t) {
            $name = $t->given_names . ' ' . $t->paternal_surname;
            $ended = $t->ended_on ?? 'NULL';
            $planned = $t->planned_end_on ?? 'NULL';
            echo "   Term ID: {$t->term_id} | {$name}\n";
            echo "      Started: {$t->started_on} | Ended: $ended | Planned: $planned\n";

            // Check if this term would be active today
            $today = date('Y-m-d');
            $is_active = ($t->started_on <= $today && ($t->ended_on === null || $t->ended_on >= $today));
            echo "      Active today? " . ($is_active ? "YES" : "NO") . "\n";
        }
    }
    echo "\n";
}

echo "\n=== CHECKING FOR MULTIPLE ACTIVE TERMS FOR SAME PERSON ===\n";

$duplicates = $wpdb->get_results("
    SELECT 
        t.person_id,
        p.given_names,
        p.paternal_surname,
        COUNT(*) as term_count,
        GROUP_CONCAT(t.started_on ORDER BY t.started_on) as starts,
        GROUP_CONCAT(IFNULL(t.ended_on, 'NULL') ORDER BY t.started_on) as ends
    FROM wp_politeia_office_terms t
    JOIN wp_politeia_people p ON p.id = t.person_id
    JOIN wp_politeia_offices o ON o.id = t.office_id
    WHERE o.code = 'SENADOR'
    GROUP BY t.person_id
    HAVING COUNT(*) > 1
");

if ($duplicates) {
    echo "Found " . count($duplicates) . " persons with multiple SENATOR terms:\n";
    foreach ($duplicates as $d) {
        echo "   {$d->given_names} {$d->paternal_surname} (ID: {$d->person_id})\n";
        echo "      Terms: {$d->term_count} | Starts: {$d->starts} | Ends: {$d->ends}\n";
    }
} else {
    echo "No duplicate person terms found.\n";
}
