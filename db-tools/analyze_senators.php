<?php
/**
 * Detailed analysis of senator candidacies per circunscripción
 * Check for duplicates and term date issues
 */

require_once('../../../../wp-load.php');
global $wpdb;

$circs = [
    '3ª Circunscripción' => 28,
    '5ª Circunscripción' => 29,
    '7ª Circunscripción' => 34,
    '8ª Circunscripción' => 30,
    '10ª Circunscripción' => 31,
    '12ª Circunscripción' => 35,
    '13ª Circunscripción' => 32,
    '15ª Circunscripción' => 33,
    '16ª Circunscripción' => 36
];

echo "=== DETAILED SENATOR ANALYSIS PER CIRCUNSCRIPCIÓN ===\n\n";

foreach ($circs as $name => $election_id_2021) {
    echo "### $name ###\n";

    // Get ALL candidacies for senators in this circunscripción's jurisdiction
    // First find the jurisdiction_id from the 2021 election
    $jurisdiction_id = $wpdb->get_var($wpdb->prepare("
        SELECT jurisdiction_id FROM wp_politeia_elections WHERE id = %d
    ", $election_id_2021));

    echo "Jurisdiction ID: $jurisdiction_id\n";

    // Get all candidacies linked to this jurisdiction across ALL elections
    $all_candidacies = $wpdb->get_results($wpdb->prepare("
        SELECT 
            c.id as candidacy_id,
            c.person_id,
            p.given_names,
            p.paternal_surname,
            c.elected,
            c.votes,
            e.id as election_id,
            e.title as election_title,
            e.election_date,
            c.jurisdiction_id
        FROM wp_politeia_candidacies c
        JOIN wp_politeia_people p ON c.person_id = p.id
        JOIN wp_politeia_elections e ON c.election_id = e.id
        WHERE c.jurisdiction_id = %d
        AND c.elected = 1
        ORDER BY e.election_date DESC, c.votes DESC
    ", $jurisdiction_id));

    echo "Total elected candidacies for this jurisdiction: " . count($all_candidacies) . "\n";

    $seen_persons = [];
    foreach ($all_candidacies as $c) {
        $name_full = $c->given_names . ' ' . $c->paternal_surname;
        $duplicate = in_array($c->person_id, $seen_persons) ? " *** DUPLICATE PERSON_ID ***" : "";
        $seen_persons[] = $c->person_id;

        echo "   [{$c->election_date}] {$name_full} (Election: {$c->election_title}, person_id: {$c->person_id}){$duplicate}\n";
    }
    echo "\n";
}

echo "\n=== CHECKING FOR DUPLICATE PERSON_IDS IN SAME ELECTION ===\n";

$duplicates = $wpdb->get_results("
    SELECT 
        c.person_id,
        c.election_id,
        COUNT(*) as count,
        p.given_names,
        p.paternal_surname,
        e.title
    FROM wp_politeia_candidacies c
    JOIN wp_politeia_people p ON c.person_id = p.id
    JOIN wp_politeia_elections e ON c.election_id = e.id
    WHERE e.title LIKE '%Senatorial%' OR e.title LIKE '%SENADOR%'
    GROUP BY c.person_id, c.election_id
    HAVING COUNT(*) > 1
");

if ($duplicates) {
    echo "Found " . count($duplicates) . " duplicates:\n";
    foreach ($duplicates as $d) {
        echo "   Person: {$d->given_names} {$d->paternal_surname} (ID: {$d->person_id}) appears {$d->count} times in '{$d->title}'\n";
    }
} else {
    echo "No duplicate person_id in same election found.\n";
}
