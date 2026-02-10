<?php
/**
 * Fix Circunscripción 12 Term Date Anomaly
 */

require_once('../../../../wp-load.php');
global $wpdb;

echo "=== FIXING CIRCUNSCRIPCIÓN 12 TERM ANOMALY ===\n\n";

// Find the problematic term(s) in Circunscripción 12 (jurisdiction_id = 412)
$problematic = $wpdb->get_results("
    SELECT 
        t.id as term_id,
        t.person_id,
        p.given_names,
        p.paternal_surname,
        t.started_on,
        t.ended_on,
        t.planned_end_on,
        c.election_id
    FROM wp_politeia_office_terms t
    JOIN wp_politeia_people p ON p.id = t.person_id
    JOIN wp_politeia_offices o ON o.id = t.office_id
    LEFT JOIN wp_politeia_candidacies c ON c.person_id = t.person_id 
        AND c.jurisdiction_id = t.jurisdiction_id 
        AND c.elected = 1
    LEFT JOIN wp_politeia_elections e ON e.id = c.election_id
    WHERE t.jurisdiction_id = 412
    AND o.code = 'SENADOR'
    AND t.started_on = '2014-03-11'
    AND (e.election_date LIKE '2021%' OR c.election_id IN (28,29,30,31,32,33,34,35,36))
");

echo "Found " . count($problematic) . " problematic term(s):\n\n";

foreach ($problematic as $t) {
    echo "Term ID: {$t->term_id} | {$t->given_names} {$t->paternal_surname}\n";
    echo "   Current: started_on = {$t->started_on}\n";
    echo "   Should be: started_on = 2022-03-11 (like other 2021 senators)\n\n";
}

// Also check terms that have wrong started_on but should be 2022
$all_circ12_terms = $wpdb->get_results("
    SELECT 
        t.id as term_id,
        p.given_names,
        p.paternal_surname,
        t.started_on,
        t.planned_end_on
    FROM wp_politeia_office_terms t
    JOIN wp_politeia_people p ON p.id = t.person_id
    JOIN wp_politeia_offices o ON o.id = t.office_id
    WHERE t.jurisdiction_id = 412
    AND o.code = 'SENADOR'
    ORDER BY t.started_on DESC
");

echo "All Senator terms in Circunscripción 12:\n";
foreach ($all_circ12_terms as $t) {
    $flag = ($t->started_on == '2014-03-11' && $t->planned_end_on == '2030-03-11') ? " *** WRONG ***" : "";
    echo "   Term {$t->term_id}: {$t->given_names} {$t->paternal_surname} | {$t->started_on} - {$t->planned_end_on}{$flag}\n";
}

echo "\n--- EXECUTING FIX ---\n";

// Fix: Update terms where started_on = 2014 but planned_end = 2030 (should be 2022-2030)
$result = $wpdb->query("
    UPDATE wp_politeia_office_terms t
    JOIN wp_politeia_offices o ON o.id = t.office_id
    SET t.started_on = '2022-03-11'
    WHERE t.jurisdiction_id = 412
    AND o.code = 'SENADOR'
    AND t.started_on = '2014-03-11'
    AND t.planned_end_on = '2030-03-11'
");

echo "Rows updated: $result\n";

echo "\n=== VERIFICATION ===\n";
$after = $wpdb->get_results("
    SELECT 
        t.id as term_id,
        p.given_names,
        p.paternal_surname,
        t.started_on,
        t.planned_end_on
    FROM wp_politeia_office_terms t
    JOIN wp_politeia_people p ON p.id = t.person_id
    JOIN wp_politeia_offices o ON o.id = t.office_id
    WHERE t.jurisdiction_id = 412
    AND o.code = 'SENADOR'
    ORDER BY t.started_on DESC
");

foreach ($after as $t) {
    echo "   Term {$t->term_id}: {$t->given_names} {$t->paternal_surname} | {$t->started_on} - {$t->planned_end_on}\n";
}
