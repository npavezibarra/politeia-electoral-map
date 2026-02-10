<?php
/**
 * Import Photos 2021 Nov - Updated Filename Convention
 * Filename format: {person_id}_{clean_name}_2021_{office}.jpeg
 * 
 * Updates profile_photo_url in wp_politeia_candidacies
 * for 2021 November elections (Senadores, Diputados, Cores)
 */

require_once('../../../../wp-load.php');
global $wpdb;

$base_url = 'http://politeia.local/wp-content/plugins/politeia-electoral-map/assets/imported_photos_2021_nov';
$base_path = '/Users/nicolasibarra/Local Sites/politeia/app/public/wp-content/plugins/politeia-electoral-map/assets/imported_photos_2021_nov';

$updated = 0;
$skipped = 0;

// 2021 November Election IDs: SENADOR 2021 (11), OTHERS 2021 (17), Senatorial by Circ (28-36)
$election_ids = [11, 17, 28, 29, 30, 31, 32, 33, 34, 35, 36];

echo "Importing photos for 2021 November elections (IDs: " . implode(', ', $election_ids) . ")\n";

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

        // Parse filename: {person_id}_{clean_name}_2021_{office}.jpeg
        preg_match('/^(\d+)_(.+)_2021_(.+)\.jpe?g$/', $file, $matches);

        if (count($matches) < 4) {
            $skipped++;
            continue;
        }

        $person_id = intval($matches[1]);
        // $name_slug = $matches[2]; // Not needed for matching
        $office = strtoupper($matches[3]);

        // Build URL
        $photo_url = $base_url . '/' . rawurlencode($region) . '/' . rawurlencode($file);

        // Update candidacies for this person_id in 2021 November elections
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
            if ($updated % 50 == 0) {
                echo "   Updated $updated records so far...\n";
            }
        }
    }
}

echo "\nDONE. Updated: $updated records. Skipped: $skipped files.\n";
