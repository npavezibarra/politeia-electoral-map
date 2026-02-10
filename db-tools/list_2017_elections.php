<?php
require_once('../../../../wp-load.php');
global $wpdb;

echo "--- 2017 Elections ---\n";
$elections = $wpdb->get_results("SELECT id, title, election_date, office_id FROM wp_politeia_elections WHERE election_date LIKE '2017%' ORDER BY election_date");
foreach ($elections as $e) {
    $office = $wpdb->get_var("SELECT name FROM wp_politeia_offices WHERE id = " . $e->office_id);
    echo "ID: " . $e->id . " | " . $e->election_date . " | " . $e->title . " | Office: " . $office . "\n";

    // Count candidacies
    $count = $wpdb->get_var("SELECT COUNT(*) FROM wp_politeia_candidacies WHERE election_id = " . $e->id);
    $with_photo = $wpdb->get_var("SELECT COUNT(*) FROM wp_politeia_candidacies WHERE election_id = " . $e->id . " AND profile_photo_url IS NOT NULL AND profile_photo_url != ''");
    echo "   Candidacies: $count | With Photo: $with_photo\n";
}
