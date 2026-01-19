<?php
/**
 * CLI Script to match missing Mayor photos with local files.
 * Usage: php includes/cli/match_mayor_photos_cli.php
 */

// Load WordPress environment
require_once dirname(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__))))))) . '/wp-load.php';

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
echo "Found " . count($missing_mayors) . " Mayors missing photos.\n";

// 2. Scan Directory
$base_dir = dirname(dirname(dirname(__FILE__))) . '/assets/politician_profile_photos/2024/';
$files = scandir($base_dir);
$matches_found = 0;

foreach ($missing_mayors as $mayor) {
    $full_name = mb_strtolower($mayor->given_names . ' ' . $mayor->paternal_surname . ' ' . $mayor->maternal_surname);
    $search_terms = [
        mb_strtolower($mayor->given_names . '_' . $mayor->paternal_surname),
        mb_strtolower($mayor->given_names . '_' . $mayor->paternal_surname . '_' . $mayor->maternal_surname),
        mb_strtolower($mayor->paternal_surname . '_' . $mayor->maternal_surname) // Fallback
    ];

    // Normalize (remove accents)
    $normalized_search = [];
    foreach ($search_terms as $term) {
        $normalized_search[] = remove_accents($term);
    }

    $best_match = null;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..')
            continue;

        $normalized_file = remove_accents(mb_strtolower($file));

        foreach ($normalized_search as $term) {
            // Check if matches "firstname_lastname_" at start or contains distinctive parts
            // We favor files ending in '_win.jpeg' or '_alcalde.jpeg'

            // Exact containment check
            if (strpos($normalized_file, $term) !== false) {
                // Heuristic: Prefer "win" files
                if (strpos($normalized_file, 'win') !== false) {
                    $best_match = $file;
                    break 2;
                }
                // If not set yet, store as candidate
                if (!$best_match) {
                    $best_match = $file;
                }
            }
        }
    }

    if ($best_match) {
        $matches_found++;
        $rel_path = 'politician_profile_photos/2024/' . $best_match;
        echo "[MATCH] {$mayor->comuna}: {$mayor->given_names} {$mayor->paternal_surname} -> $best_match\n";

        // Update DB
        $wpdb->update(
            $candidacies_table,
            ['profile_photo_url' => $rel_path],
            ['id' => $mayor->candidacy_id]
        );
    } else {
        echo "[MISS ] {$mayor->comuna}: {$mayor->given_names} {$mayor->paternal_surname} (No match found)\n";
    }
}

echo "\nUpdate Complete. Matched $matches_found out of " . count($missing_mayors) . ".\n";

function remove_accents($string)
{
    $string = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'],
        ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'],
        $string
    );
    return str_replace(' ', '_', $string);
}
