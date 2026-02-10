<?php
/**
 * Web Script to match missing Mayor photos with local files.
 * Access via browser: http://[yoursite]/match_mayor_photos_web.php
 */

// Load WordPress environment (Assuming we are in the site root)
require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    die('Access Denied. Admin only.');
}

global $wpdb;
$jurisdictions_table = $wpdb->prefix . 'politeia_jurisdictions';
$people_table = $wpdb->prefix . 'politeia_people';
$candidacies_table = $wpdb->prefix . 'politeia_candidacies';
$elections_table = $wpdb->prefix . 'politeia_elections';
$offices_table = $wpdb->prefix . 'politeia_offices';

// 1. Get List of Mayors Missing Photos
$sql = "
    SELECT 
        c.id as candidacy_id,
        j.official_name as comuna,
        p.given_names,
        p.paternal_surname,
        p.maternal_surname
    FROM $jurisdictions_table j
    JOIN $candidacies_table c ON c.jurisdiction_id = j.id
    JOIN $elections_table e ON c.election_id = e.id
    JOIN $offices_table o ON e.office_id = o.id
    JOIN $people_table p ON c.person_id = p.id
    WHERE j.type = 'COMMUNE'
      AND o.code = 'ALCALDE'
      AND c.elected = 1
      AND YEAR(e.election_date) = 2024
      AND (c.profile_photo_url IS NULL OR c.profile_photo_url = '')
";

$missing_mayors = $wpdb->get_results($sql);
echo "<pre>";
echo "Found " . count($missing_mayors) . " Mayors missing photos.<br>";

// 2. Scan Directory
// Path to photos (Relative to this script in root: wp-content/plugins/...)
$base_dir = ABSPATH . 'wp-content/plugins/politeia-electoral-map/assets/politician_profile_photos/2024/';
$matches_found = 0;

if (!is_dir($base_dir)) {
    echo "Error: Directory not found: $base_dir";
    exit;
}

$files = scandir($base_dir);

foreach ($missing_mayors as $mayor) {
    // Extract just the first name (e.g. "Victor" from "Victor Ramon")
    $first_name = explode(' ', trim($mayor->given_names))[0];

    $search_terms = [
        // Full Name variations
        mb_strtolower($mayor->given_names . '_' . $mayor->paternal_surname . '_' . $mayor->maternal_surname),
        mb_strtolower($mayor->given_names . '_' . $mayor->paternal_surname),

        // Surnames only
        mb_strtolower($mayor->paternal_surname . '_' . $mayor->maternal_surname),

        // First Name (Simple) + Surnames (catch "victor_toro" for "Victor Ramon Toro")
        mb_strtolower($first_name . '_' . $mayor->paternal_surname . '_' . $mayor->maternal_surname),
        mb_strtolower($first_name . '_' . $mayor->paternal_surname)
    ];

    $normalized_search = [];
    foreach ($search_terms as $term) {
        $normalized_search[] = politeia_custom_normalize($term);
    }

    $best_match = null;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..')
            continue;

        $normalized_file = politeia_custom_normalize(mb_strtolower($file));

        foreach ($normalized_search as $term) {
            if (strpos($normalized_file, $term) !== false) {
                if (strpos($normalized_file, 'win') !== false) {
                    $best_match = $file;
                    break 2;
                }
                if (!$best_match) {
                    $best_match = $file;
                }
            }
        }
    }

    if ($best_match) {
        $matches_found++;
        // DB stores relative path usually, depends on your setup.
        // Assuming plugin relative: politician_profile_photos/2024/
        $rel_path = 'politician_profile_photos/2024/' . $best_match;
        echo "[MATCH] {$mayor->comuna}: {$mayor->given_names} {$mayor->paternal_surname} -> $best_match<br>";

        $wpdb->update(
            $candidacies_table,
            ['profile_photo_url' => $rel_path],
            ['id' => $mayor->candidacy_id]
        );
    } else {
        echo "[MISS ] {$mayor->comuna}: {$mayor->given_names} {$mayor->paternal_surname} (No match found)<br>";
    }
}

echo "<br>Update Complete. Matched $matches_found out of " . count($missing_mayors) . ".";
echo "</pre>";

function politeia_custom_normalize($string)
{
    // Use WP built-in remove_accents first
    $string = remove_accents($string);
    // Then replace spaces/special chars
    $string = str_replace([' ', 'ñ', 'Ñ'], ['_', 'n', 'N'], $string);
    return $string;
}
