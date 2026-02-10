<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "--- CONCEJALES (OTHERS) FOR LAS CONDES --- \n";
// Las Condes is 154. Others elections are 39, 40, 41, 42, 43.
$conc = $wpdb->get_results("
    SELECT c.id, p.given_names, p.paternal_surname, e.name as election_name, c.votes, c.elected
    FROM {$wpdb->prefix}politeia_candidacies c
    JOIN {$wpdb->prefix}politeia_people p ON p.id = c.person_id
    JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
    WHERE c.jurisdiction_id = 154 AND e.office_id = 0
    ORDER BY c.votes DESC
");
foreach ($conc as $cn) {
    echo "- Name: {$cn->given_names} {$cn->paternal_surname} | Election: {$cn->election_name} | Votes: {$cn->votes} | Elected: {$cn->elected}\n";
}
