<?php
/**
 * Import Scraped Photos to DB (2021 Version)
 * Scans 'assets/imported_photos_2021' and updates 'wp_politeia_candidacies'.
 */

require_once('../../../../wp-load.php');

// if (!current_user_can('manage_options')) {
//    wp_die('Access denied');
// }

global $wpdb;
$target_table = $wpdb->prefix . 'politeia_candidacies';

// Target Directory
$plugin_path = WP_PLUGIN_DIR . '/politeia-electoral-map/assets/imported_photos_2021';
$plugin_url_base = content_url() . '/plugins/politeia-electoral-map/assets/imported_photos_2021';

echo "<h1>Importing Photos from ID-based files (2021)</h1>";
echo "<p>Scanning directory: $plugin_path</p>";

if (!is_dir($plugin_path)) {
    die("Error: Directory not found. Run the downloader script first.");
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_path));
$count = 0;
$updated = 0;
$errors = 0;

echo "<pre>";

foreach ($iterator as $file) {
    if ($file->isDir())
        continue;

    $filename = $file->getFilename();
    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'jpeg' && pathinfo($filename, PATHINFO_EXTENSION) !== 'jpg') {
        continue;
    }

    // Parse ID from filename
    // Format: 1234_name_slug_role.jpeg
    if (preg_match('/^(\d+)_/', $filename, $matches)) {
        $person_id = intval($matches[1]);

        $full_path = $file->getPathname();
        $rel_path = str_replace($plugin_path, '', $full_path);
        $rel_url = str_replace('\\', '/', $rel_path);

        $final_url = $plugin_url_base . $rel_url;

        echo "Found ID $person_id: $filename -> $final_url\n";

        // Update DB (Candidacies)
        // Note: ID is person_id. We update ALL candidacies for this person? 
        // Or specific to 2021?
        // Photos are usually universal profile photos.
        // But the table is 'candidacies'.
        // If we update 'candidacies', we should target the 2021 one.
        // But finding the exact candidacy ID is hard without extra query.
        // However, we can use WHERE person_id = X AND (election_id IN (SELECT id FROM ... WHERE year=2021))?
        // Simplest: Update ALL candidacies for this person where year=2021.

        // We can do a join update or subquery.
        // $wpdb->query("UPDATE $target_table c JOIN ... SET c.profile_photo_url = ...")

        // Let's stick to simple update for person_id, knowing it might update multiple rows if multiple candidacies exist for same person (unlikely in same year).
        // But wait, if they ran in 2024 too, we don't want to overwrite 2024 photo with 2021 photo?
        // Actually, usually latest photo is best.
        // But 2024 photo should be preserved if it exists.

        // The files are separated by year folder.
        // If I update rows, I should probably target 2021 elections only using subquery logic or just blind update?
        // Given "Database-First" matches specific ID from the 2021 Export.
        // The ID in filename IS person_id.
        // The export was filtered by `YEAR(election_date) = 2021`.
        // So this person IS a candidate in 2021.

        // Query to update only 2021 candidacies:
        // UPDATE candidacies c JOIN elections e ON c.election_id = e.id 
        // SET c.profile_photo_url = %s 
        // WHERE c.person_id = %d AND YEAR(e.election_date) = 2021

        $sql = $wpdb->prepare(
            "UPDATE $target_table c 
             JOIN {$wpdb->prefix}politeia_elections e ON c.election_id = e.id 
             SET c.profile_photo_url = %s 
             WHERE c.person_id = %d AND YEAR(e.election_date) = 2021",
            $final_url,
            $person_id
        );

        $res = $wpdb->query($sql);

        if ($res === false) {
            echo "   [ERROR] DB Update Failed: " . $wpdb->last_error . "\n";
            $errors++;
        } else {
            if ($res > 0) {
                echo "   [OK] Updated $res rows.\n";
                $updated += $res;
            } else {
                echo "   [SKIP] No rows updated (maybe unmatched year?).\n";
            }
        }
        $count++;
    }
}

echo "</pre>";
echo "<h2>Done.</h2>";
echo "<p>Total Files Processed: $count</p>";
echo "<p>Updated Records: $updated</p>";
echo "<p>Errors: $errors</p>";
