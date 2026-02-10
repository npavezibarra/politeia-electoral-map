<?php
/**
 * Import Photos 2024 - Updated Filename Convention
 * Filename format: {person_id}_{clean_name}_2024_{office}.jpeg
 * 
 * This script updates profile_photo_url in wp_politeia_candidacies
 * for the 2024 election only (election_id = 3 for ALCALDE 2024).
 */

require_once('../../../../wp-load.php');
global $wpdb;

$base_url = 'http://politeia.local/wp-content/plugins/politeia-electoral-map/assets/imported_photos_2024';
$base_path = '/Users/nicolasibarra/Local Sites/politeia/app/public/wp-content/plugins/politeia-electoral-map/assets/imported_photos_2024';

$updated = 0;
$skipped = 0;

// Get 2024 Election IDs (Alcaldes, Gobernadores, Cores, Concejales)
// Looking at election_date = 2024-10-01
$election_ids = $wpdb->get_col("SELECT id FROM wp_politeia_elections WHERE election_date LIKE '2024%'");

if (empty($election_ids)) {
    echo "No 2024 elections found!\n";
    exit;
}

echo "Found 2024 Election IDs: " . implode(', ', $election_ids) . "\n";

// Iterate through regions
$regions = scandir($base_path);

foreach ($regions as $region) {
    if ($region === '.' || $region === '..')
        continue;

    $region_path = $base_path . '/' . $region;
    if (!is_dir($region_path))
        continue;

    echo "Processing Region: $region\n";

    $files = scandir($region_path);

    foreach ($files as $file) {
        if (strpos($file, '.jpeg') === false && strpos($file, '.jpg') === false)
            continue;

        // Parse filename: {person_id}_{clean_name}_2024_{office}.jpeg
        // Example: 547_catalina_san_martin_cavada_2024_alcalde.jpeg
        preg_match('/^(\d+)_(.+)_2024_(.+)\.jpe?g$/', $file, $matches);

        if (count($matches) < 4) {
            // Try old format without year (for backward compat)
            preg_match('/^(\d+)_(.+)_([a-z]+)\.jpe?g$/', $file, $matches);
            if (count($matches) < 4) {
                $skipped++;
                continue;
            }
        }

        $person_id = intval($matches[1]);
        // $name_slug = $matches[2]; // Not needed for matching
        $office = strtoupper($matches[3]);

        // Build URL
        $photo_url = $base_url . '/' . rawurlencode($region) . '/' . rawurlencode($file);

        // Update candidacies for this person_id in 2024 elections
        $placeholders = implode(',', array_fill(0, count($election_ids), '%d'));
        $query_args = array_merge([$photo_url, $person_id], $election_ids);

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE wp_politeia_candidacies 
             SET profile_photo_url = %s 
             WHERE person_id = %d 
             AND election_id IN ($placeholders)",
            $query_args
        ));

        if ($result > 0) {
            $updated += $result;
            echo "   Updated $result records for person_id $person_id\n";
        }
    }
}

echo "\nDONE. Updated: $updated records. Skipped: $skipped files.\n";
