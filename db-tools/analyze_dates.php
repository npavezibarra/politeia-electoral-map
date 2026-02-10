<?php
/**
 * Analyze date columns in Politeia tables
 */

require_once('../../../../wp-load.php');
global $wpdb;

echo "=== DATE COLUMNS IN POLITEIA TABLES ===\n\n";

// 1. Elections table
echo "### wp_politeia_elections ###\n";
$cols = $wpdb->get_results("DESCRIBE wp_politeia_elections");
foreach ($cols as $c) {
    if (strpos(strtolower($c->Field), 'date') !== false || strpos(strtolower($c->Type), 'date') !== false) {
        echo "   {$c->Field} ({$c->Type}) - {$c->Null}\n";
    }
}

// Sample data
echo "\nSample elections:\n";
$samples = $wpdb->get_results("SELECT id, title, election_date FROM wp_politeia_elections ORDER BY election_date DESC LIMIT 5");
foreach ($samples as $s) {
    echo "   ID: {$s->id} | {$s->election_date} | {$s->title}\n";
}

// 2. Candidacies table
echo "\n### wp_politeia_candidacies ###\n";
$cols = $wpdb->get_results("DESCRIBE wp_politeia_candidacies");
foreach ($cols as $c) {
    if (
        strpos(strtolower($c->Field), 'date') !== false ||
        strpos(strtolower($c->Type), 'date') !== false ||
        strpos(strtolower($c->Field), 'start') !== false ||
        strpos(strtolower($c->Field), 'end') !== false
    ) {
        echo "   {$c->Field} ({$c->Type}) - {$c->Null}\n";
    }
}

// 3. Office Terms table
echo "\n### wp_politeia_office_terms ###\n";
$cols = $wpdb->get_results("DESCRIBE wp_politeia_office_terms");
foreach ($cols as $c) {
    if (
        strpos(strtolower($c->Field), 'date') !== false ||
        strpos(strtolower($c->Type), 'date') !== false ||
        strpos(strtolower($c->Field), 'start') !== false ||
        strpos(strtolower($c->Field), 'end') !== false
    ) {
        echo "   {$c->Field} ({$c->Type}) - {$c->Null}\n";
    }
}

// Sample terms
echo "\nSample office_terms:\n";
$samples = $wpdb->get_results("SELECT t.id, p.given_names, p.paternal_surname, t.started_on, t.ended_on, t.planned_end_on 
    FROM wp_politeia_office_terms t 
    JOIN wp_politeia_people p ON p.id = t.person_id 
    ORDER BY t.started_on DESC LIMIT 5");
foreach ($samples as $s) {
    echo "   ID: {$s->id} | {$s->given_names} {$s->paternal_surname} | Started: {$s->started_on} | Ended: {$s->ended_on} | Planned: {$s->planned_end_on}\n";
}

// 4. Compare election_date vs started_on for same election
echo "\n\n=== COMPARISON: Election Date vs Term Start Date ===\n";

$comparison = $wpdb->get_results("
    SELECT 
        e.id as election_id,
        e.title,
        e.election_date,
        t.started_on as term_start,
        DATEDIFF(t.started_on, e.election_date) as days_difference
    FROM wp_politeia_elections e
    JOIN wp_politeia_candidacies c ON c.election_id = e.id AND c.elected = 1
    JOIN wp_politeia_office_terms t ON t.person_id = c.person_id 
        AND t.jurisdiction_id = c.jurisdiction_id
    WHERE e.election_date IS NOT NULL
    GROUP BY e.id
    ORDER BY e.election_date DESC
    LIMIT 20
");

echo "Election ID | Election Date | Term Start | Days Gap | Title\n";
echo str_repeat("-", 80) . "\n";
foreach ($comparison as $c) {
    echo "{$c->election_id} | {$c->election_date} | {$c->term_start} | {$c->days_difference} days | " . substr($c->title, 0, 40) . "\n";
}
