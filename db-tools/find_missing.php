<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "--- PHOTO SEARCH (GLOBALLY) ---\n";
$all_photos = $wpdb->get_results("SELECT profile_photo_url, id FROM {$wpdb->prefix}politeia_candidacies WHERE profile_photo_url IS NOT NULL AND profile_photo_url != '' LIMIT 10");
foreach ($all_photos as $ap) {
    echo "- ID: {$ap->id} | Photo: {$ap->profile_photo_url}\n";
}

echo "\n--- FINDING THE MISSING 41,000 --- \n";
$missing = $wpdb->get_results("
    SELECT e.office_id, COUNT(c.id) as count
    FROM {$wpdb->prefix}politeia_candidacies c
    JOIN {$wpdb->prefix}politeia_elections e ON e.id = c.election_id
    GROUP BY e.office_id
");
foreach ($missing as $m) {
    $title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}politeia_offices WHERE id = %d", $m->office_id)) ?: "UNKNOWN (ID {$m->office_id})";
    echo "- Office ID: {$m->office_id} ({$title}) | Count: {$m->count}\n";
}
