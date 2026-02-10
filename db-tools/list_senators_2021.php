<?php
/**
 * List 2021 Senatorial Elections and Elected Senators
 */

require_once('../../../../wp-load.php');
global $wpdb;

echo "=== CIRCUNSCRIPCIONES WITH ELECTIONS IN 2021 ===\n\n";

// Get all 2021 Senatorial elections
$elections = $wpdb->get_results("
    SELECT e.id, e.title, e.election_date, j.common_name as circ_name
    FROM wp_politeia_elections e
    LEFT JOIN wp_politeia_jurisdictions j ON e.jurisdiction_id = j.id
    WHERE e.election_date LIKE '2021-11%'
    AND (e.title LIKE '%Senatorial%' OR e.title LIKE '%SENADOR%')
    ORDER BY e.id
");

echo "Found " . count($elections) . " senatorial elections in 2021:\n\n";

foreach ($elections as $e) {
    echo "ID: {$e->id} | {$e->title}\n";
    echo "   Circunscripción: {$e->circ_name}\n";

    // Get elected senators for this election
    $senators = $wpdb->get_results($wpdb->prepare("
        SELECT 
            p.given_names,
            p.paternal_surname,
            p.maternal_surname,
            c.votes,
            c.elected
        FROM wp_politeia_candidacies c
        JOIN wp_politeia_people p ON c.person_id = p.id
        WHERE c.election_id = %d
        AND c.elected = 1
        ORDER BY c.votes DESC
    ", $e->id));

    if ($senators) {
        echo "   ELECTED SENATORS:\n";
        foreach ($senators as $s) {
            $full_name = trim($s->given_names . ' ' . $s->paternal_surname . ' ' . ($s->maternal_surname ?? ''));
            echo "      - $full_name (Votes: $s->votes)\n";
        }
    } else {
        echo "   NO ELECTED SENATORS FOUND\n";
    }
    echo "\n";
}

echo "\n=== SUMMARY: ELECTED SENATORS BY CIRCUNSCRIPCIÓN ===\n\n";

// Get all senators elected in 2021 grouped by circunscripción
$all_senators = $wpdb->get_results("
    SELECT 
        e.title as election_title,
        j.common_name as circ_name,
        p.given_names,
        p.paternal_surname,
        p.maternal_surname,
        c.votes
    FROM wp_politeia_candidacies c
    JOIN wp_politeia_people p ON c.person_id = p.id
    JOIN wp_politeia_elections e ON c.election_id = e.id
    LEFT JOIN wp_politeia_jurisdictions j ON e.jurisdiction_id = j.id
    WHERE e.election_date LIKE '2021-11%'
    AND (e.title LIKE '%Senatorial%' OR e.title LIKE '%SENADOR%')
    AND c.elected = 1
    ORDER BY e.id, c.votes DESC
");

$current_circ = '';
foreach ($all_senators as $s) {
    if ($s->circ_name !== $current_circ) {
        $current_circ = $s->circ_name;
        echo "\n### $current_circ ###\n";
    }
    $full_name = trim($s->given_names . ' ' . $s->paternal_surname . ' ' . ($s->maternal_surname ?? ''));
    echo "   $full_name\n";
}
