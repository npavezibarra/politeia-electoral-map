<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "--- ALL USED OFFICE CODES --- \n";
$used_offices = $wpdb->get_results("
    SELECT o.id, o.code, o.title, COUNT(c.id) as candidacy_count
    FROM {$wpdb->prefix}politeia_offices o
    JOIN {$wpdb->prefix}politeia_elections e ON o.id = e.office_id
    JOIN {$wpdb->prefix}politeia_candidacies c ON e.id = c.election_id
    GROUP BY o.id
");
foreach ($used_offices as $uo) {
    echo "- Code: {$uo->code} | Title: {$uo->title} | Count: {$uo->candidacy_count}\n";
}

echo "\n--- PHOTO PATH CHECK ---\n";
// Check if Catalina San Martin (ID 547) has any photo in ANY field or if there's a person field
$person = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}politeia_people WHERE id = 547");
if ($person) {
    echo "Person ID 547: {$person->given_names} {$person->paternal_surname}\n";
}

// Check for any candidacy with a photo specifically in 2024
$elec_2024 = $wpdb->get_results("SELECT c.id, c.profile_photo_url, o.code, j.official_name
    FROM {$wpdb->prefix}politeia_candidacies c
    JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
    JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
    JOIN {$wpdb->prefix}politeia_jurisdictions j ON j.id = c.jurisdiction_id
    WHERE e.election_date = '2024-10-27' AND c.profile_photo_url IS NOT NULL AND c.profile_photo_url != ''
    LIMIT 5");
echo "\nSample Photos from 2024-10-27:\n";
foreach ($elec_2024 as $ep) {
    echo "- Office: {$ep->code} | Jur: {$ep->official_name} | Photo: {$ep->profile_photo_url}\n";
}
