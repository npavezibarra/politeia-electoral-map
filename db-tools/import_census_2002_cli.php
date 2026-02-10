<?php
/**
 * Import Script for 2002 Census Data
 *
 * Usage: php import_census_2002_cli.php [dry-run]
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Load WordPress
require_once __DIR__ . '/wp-load.php';

global $wpdb;

$dry_run = isset($argv[1]) && $argv[1] === 'dry-run';
$csv_path = '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/csv-personas-censo-2017/population_counts_2002.csv';

echo "--- Starting Census 2002 Import ---\n";
echo "Dry Run: " . ($dry_run ? "YES" : "NO") . "\n";

// 1. Load Data
echo "Loading Data from {$csv_path}...\n";
if (!file_exists($csv_path)) {
    die("Error: CSV file not found.\n");
}

$population_counts = [];
if (($handle = fopen($csv_path, "r")) !== FALSE) {
    // Header: id;comuna;population
    fgetcsv($handle, 1000, ";");
    while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
        if (count($data) >= 3) {
            $id = trim($data[0]);
            $name = trim($data[1]);
            $pop = intval($data[2]);
            $population_counts[] = [
                'id' => $id,
                'name' => $name,
                'pop' => $pop
            ];
        }
    }
    fclose($handle);
}
echo "Loaded " . count($population_counts) . " records.\n";


// 2. Prepare Matcher
echo "Preparing Jurisdictions...\n";
$jurisdictions_table = $wpdb->prefix . 'politeia_jurisdictions';
$populations_table = $wpdb->prefix . 'politeia_jurisdiction_populations';

$all_jurisdictions = $wpdb->get_results("SELECT id, official_name, common_name, parent_id, external_code FROM $jurisdictions_table", OBJECT);
$normalized_jurs = [];

// Helper for accents
function census_remove_accents($str)
{
    if (function_exists('remove_accents')) {
        return strtoupper(remove_accents($str));
    }
    $unwanted = [
        'Á' => 'A',
        'É' => 'E',
        'Í' => 'I',
        'Ó' => 'O',
        'Ú' => 'U',
        'Ñ' => 'N',
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ñ' => 'n',
        'Ü' => 'U',
        'ü' => 'u'
    ];
    return strtr($str, $unwanted);
}

foreach ($all_jurisdictions as $jur) {
    // Normalize DB names for matching
    $name_opts = [
        $jur->official_name,
        $jur->common_name,
        str_replace('Región ', '', $jur->official_name),
        str_replace('de ', '', $jur->official_name)
    ];

    foreach ($name_opts as $n) {
        if (!$n)
            continue;
        // Standardize: UPPERCASE
        $key = mb_strtoupper(trim($n), 'UTF-8');
        $normalized_jurs[$key] = $jur->id;
        // Standardize: ASCII (No Accents)
        $key_ascii = census_remove_accents($key);
        $normalized_jurs[$key_ascii] = $jur->id;
    }

    // Also index by External Code (if numeric and matches CUT)
    /*
    if (!empty($jur->external_code)) {
        $normalized_jurs['CODE_' . $jur->external_code] = $jur->id;
    }
    */
}


// 3. Match and Import
echo "Starting Database Import...\n";

$imported = 0;
$skipped = 0;

foreach ($population_counts as $record) {
    $raw_name = $record['name'];
    $comuna_id = $record['id'];
    $count = $record['pop'];

    $communa_name_upper = mb_strtoupper($raw_name, 'UTF-8');
    $communa_name_ascii = census_remove_accents($communa_name_upper);

    // Attempt match
    // 1. Exact match (UTF-8)
    $match_id = $normalized_jurs[$communa_name_upper] ?? null;

    // 2. ASCII Match
    if (!$match_id) {
        $match_id = $normalized_jurs[$communa_name_ascii] ?? null;
    }

    if (!$match_id) {
        // Try fuzzy matching or specific fixes
        if ($communa_name_upper === 'AISÉN')
            $match_id = $normalized_jurs['AYSÉN'] ?? null;
        if ($communa_name_upper === 'COIHAIQUE')
            $match_id = $normalized_jurs['COYHAIQUE'] ?? null;
        if ($communa_name_upper === 'CALERA')
            $match_id = $normalized_jurs['LA CALERA'] ?? null;

        // La Reina / La Pintana prefixes
        if (!$match_id) {
            $with_la = "LA " . $communa_name_upper;
            $match_id = $normalized_jurs[$with_la] ?? null;
        }

        // Special Cases for 2002 names?
        // Maybe 'O Higgins' -> 'O\'HIGGINS'
        if (!$match_id && strpos($raw_name, "O'HI") !== false) {
            // Try strict ascii
        }
    }

    if ($match_id) {
        echo "[MATCH] {$raw_name} ($comuna_id) -> JurID {$match_id}: {$count}\n";

        if (!$dry_run) {
            // Insert/Update
            // Check existence
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $populations_table WHERE jurisdiction_id = %d AND year = %d",
                $match_id,
                2002
            ));

            if ($exists) {
                // Update
                $wpdb->update(
                    $populations_table,
                    [
                        'population' => $count,
                        'method' => 'CENSUS',
                        'source' => 'INE Censo 2002',
                        'source_url' => 'https://www.ine.cl',
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $exists]
                );
            } else {
                // Insert
                $wpdb->insert(
                    $populations_table,
                    [
                        'jurisdiction_id' => $match_id,
                        'year' => 2002,
                        'population' => $count,
                        'method' => 'CENSUS',
                        'source' => 'INE Censo 2002',
                        'source_url' => 'https://www.ine.cl',
                        'updated_at' => current_time('mysql')
                    ]
                );
            }
        }
        $imported++;
    } else {
        echo "[SKIP] Could not match {$raw_name} ($comuna_id)\n";
        $skipped++;
    }
}

echo "--- Import Complete ---\n";
echo "Matched & Processed: $imported\n";
echo "Skipped: $skipped\n";
