<?php
/**
 * Import Photos 2017 - Updated Filename Convention
 * Filename format: {person_id}_{clean_name}_2017_{office}.jpeg
 * 
 * Updates profile_photo_url in wp_politeia_candidacies
 * for 2017 elections (SENADOR, PRESIDENTE, Senatorial Circunscripciones)
 */

require_once('../../../../wp-load.php');
global $wpdb;

$base_url = 'http://politeia.local/wp-content/plugins/politeia-electoral-map/assets/imported_photos_2017';
$base_path = '/Users/nicolasibarra/Local Sites/politeia/app/public/wp-content/plugins/politeia-electoral-map/assets/imported_photos_2017';

$updated = 0;
$skipped = 0;

// 2017 Election IDs: SENADOR (10), PRESIDENTE (16), Senatorial Circunscripciones (21-27)
$election_ids = [10, 16, 21, 22, 23, 24, 25, 26, 27];

echo "Importing photos for 2017 elections (IDs: " . implode(', ', $election_ids) . ")\n";

// Check if output directory exists
if (!is_dir($base_path)) {
    echo "ERROR: Directory does not exist: $base_path\n";
    exit(1);
}

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

        // Parse filename: {person_id}_{clean_name}_2017_{office}.jpeg
        preg_match('/^(\d+)_(.+)_2017_(.+)\.jpe?g$/', $file, $matches);

        if (count($matches) < 4) {
            $skipped++;
            continue;
        }

        $person_id = intval($matches[1]);
        $office = strtoupper($matches[3]);

        // Build URL
        $photo_url = $base_url . '/' . rawurlencode($region) . '/' . rawurlencode($file);

        // Update candidacies for this person_id in 2017 elections
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
        }
    }
}

echo "\nDONE. Updated: $updated records. Skipped: $skipped files.\n";
