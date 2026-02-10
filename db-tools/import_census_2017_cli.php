<?php
/**
 * Import Script for 2017 Census Data
 *
 * Usage: php import_census_2017_cli.php [dry-run]
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Load WordPress
require_once __DIR__ . '/wp-load.php';

global $wpdb;

$dry_run = isset($argv[1]) && $argv[1] === 'dry-run';
$rar_path = '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/csv-personas-censo-2017/microdato_censo2017-personas.rar';
$labels_path = '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/csv-personas-censo-2017/etiquetas_persona_comuna_16r.csv';

echo "--- Starting Census 2017 Import ---\n";
echo "Dry Run: " . ($dry_run ? "YES" : "NO") . "\n";

// 1. Load Labels
echo "Loading Comuna Labels from {$labels_path}...\n";
if (!file_exists($labels_path)) {
    die("Error: Labels file not found.\n");
}

$labels = [];
if (($handle = fopen($labels_path, "r")) !== FALSE) {
    // Skip header: valor;glosa
    fgets($handle);
    while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
        if (count($data) >= 2) {
            $id = trim($data[0]);
            $name = trim($data[1]);
            // Format name for matching: uppercase
            $labels[$id] = mb_strtoupper($name, 'UTF-8');
        }
    }
    fclose($handle);
}
echo "Loaded " . count($labels) . " labels.\n";

// 2. Stream RAR and Count Populations
echo "Streaming RAR file {$rar_path}...\n";
if (!file_exists($rar_path)) {
    die("Error: RAR file not found.\n");
}

$population_counts = [];
$limit = 0;
// We use 'tar -xOf' to extract to stdout
// The file inside is 'Microdato_Censo2017-Personas.csv'
$cmd = "tar -xOf " . escapeshellarg($rar_path) . " Microdato_Censo2017-Personas.csv";
$handle = popen($cmd, "r");

if (!$handle) {
    die("Error: Could not open RAR stream.\n");
}

$start_time = microtime(true);
$rows = 0;

// Skip CSV Header
fgets($handle);

while (($line = fgets($handle)) !== false) {
    $rows++;
    if ($rows % 1000000 == 0) {
        $elapsed = number_format(microtime(true) - $start_time, 2);
        echo "Processed {$rows} rows... ({$elapsed}s)\r";
    }

    // CSV is semicolon separated
    // We only need the COMUNA column (index 2)
    // REGION;PROVINCIA;COMUNA;...
    // Exploding by ; is faster than str_getcsv for simple extraction
    $parts = explode(';', $line, 4);
    if (isset($parts[2])) {
        $comuna_id = $parts[2];
        if (!isset($population_counts[$comuna_id])) {
            $population_counts[$comuna_id] = 0;
        }
        $population_counts[$comuna_id]++;
    }
}
pclose($handle);
echo "\nFinished processing " . number_format($rows) . " rows.\n";

// 3. Match and Import
echo "Starting Database Import...\n";

$jurisdictions_table = $wpdb->prefix . 'politeia_jurisdictions';
$populations_table = $wpdb->prefix . 'politeia_jurisdiction_populations';

// Pre-fetch all jurisdictions to avoid N+1 queries
$all_jurisdictions = $wpdb->get_results("SELECT id, official_name, common_name, parent_id FROM $jurisdictions_table", OBJECT);
$normalized_jurs = [];
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
        $key_ascii = remove_accents($key);
        $normalized_jurs[$key_ascii] = $jur->id;
    }
}

$imported = 0;
$skipped = 0;

foreach ($population_counts as $comuna_id => $count) {
    $raw_name = $labels[$comuna_id] ?? "Unknown ($comuna_id)";
    $communa_name_upper = mb_strtoupper($raw_name, 'UTF-8');
    $communa_name_ascii = remove_accents($communa_name_upper);

    // Attempt match
    // 1. Exact match (UTF-8)
    $match_id = $normalized_jurs[$communa_name_upper] ?? null;

    // 2. ASCII Match
    if (!$match_id) {
        $match_id = $normalized_jurs[$communa_name_ascii] ?? null;
    }

    if (!$match_id) {
        // Try fuzzy matching or specific fixes
        // e.g. "AYSÉN" vs "AISÉN"
        if ($communa_name_upper === 'AISÉN')
            $match_id = $normalized_jurs['AYSÉN'] ?? null;
        if ($communa_name_upper === 'COIHAIQUE')
            $match_id = $normalized_jurs['COYHAIQUE'] ?? null;
        // La Calera
        if ($communa_name_upper === 'CALERA')
            $match_id = $normalized_jurs['LA CALERA'] ?? null;
        // La Reina / La Pintana prefixes
        if (!$match_id) {
            $with_la = "LA " . $communa_name_upper;
            $match_id = $normalized_jurs[$with_la] ?? null;
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
                2017
            ));

            if ($exists) {
                // Update
                $wpdb->update(
                    $populations_table,
                    [
                        'population' => $count,
                        'method' => 'CENSUS',
                        'source' => 'INE Censo 2017',
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
                        'year' => 2017,
                        'population' => $count,
                        'method' => 'CENSUS',
                        'source' => 'INE Censo 2017',
                        'source_url' => 'https://www.ine.cl',
                        'updated_at' => current_time('mysql')
                    ]
                );
            }
        }
        $imported++;
    } else {
        echo "[SKIP] Could not match {$raw_name} ($comuna_id)\n";
        if ($comuna_id == 3101) {
            echo "DEBUG: Name='$raw_name' Upper='$communa_name_upper' Ascii='$communa_name_ascii'\n";
            // print a few keys to see what we have
            $keys = array_keys($normalized_jurs);
            $sample = array_filter($keys, function ($k) {
                return strpos($k, 'COPIAP') !== false;
            });
            print_r($sample);
        }
        $skipped++;
    }
}

echo "--- Import Complete ---\n";
echo "Matched & Processed: $imported\n";
echo "Skipped: $skipped\n";
