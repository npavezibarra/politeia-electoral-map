<?php
/**
 * Import Photos from Scraped Files (Web Version)
 * 
 * Usage: Access via browser at http://politeia.local/import_photos_web.php
 * 
 * Logic:
 * 1. Scans wp-content/plugins/politeia-electoral-map/assets/imported_photos_2024/
 * 2. Parses filenames: {ID}_{Name}_{Role}.jpg
 * 3. Updates wp_politeia_people SET profile_photo_url = ... WHERE id = {ID}
 */

require_once('../../../../wp-load.php');

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

global $wpdb;
$target_table = $wpdb->prefix . 'politeia_candidacies';

// Target Directory
$plugin_path = WP_PLUGIN_DIR . '/politeia-electoral-map/assets/imported_photos_2024';
$plugin_url_base = content_url() . '/plugins/politeia-electoral-map/assets/imported_photos_2024';

echo "<h1>Importing Photos from ID-based files</h1>";
echo "<p>Scanning directory: $plugin_path</p>";

if (!is_dir($plugin_path)) {
    die("Error: Directory not found. Run the downloader script first.");
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_path));
$count = 0;
$updated = 0;
$errors = 0;

echo "<pre>";

// We prepare the statement once if possible, but IDs vary.
// Let's iterate.

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

        // Construct Public URL
        // We need the relative path from the base dir
        $full_path = $file->getPathname();
        $rel_path = str_replace($plugin_path, '', $full_path);
        // rel_path might be /RM/123_file.jpg

        // Ensure forward slashes
        $rel_url = str_replace('\\', '/', $rel_path);

        $final_url = $plugin_url_base . $rel_url;

        echo "Found ID $person_id: $filename -> $final_url\n";

        // Update DB
        // We update candidacies for this person. 
        // Ideally filter by year 2024, but person_id match is sufficient for 2024 context.
        $result = $wpdb->update(
            $target_table,
            ['profile_photo_url' => $final_url],
            ['person_id' => $person_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            echo "   [ERROR] DB Update Failed: " . $wpdb->last_error . "\n";
            $errors++;
        } elseif ($result === 0) {
            echo "   [INFO] No change (URL already matches?) or ID not found.\n";
            // Check if ID exists?
        } else {
            echo "   [SUCCESS] Updated.\n";
            $updated++;
        }

        $count++;
    }
}

echo "</pre>";
echo "<h2>Done.</h2>";
echo "<p>Total Files Processed: $count</p>";
echo "<p>Updated Records: $updated</p>";
echo "<p>Errors: $errors</p>";
