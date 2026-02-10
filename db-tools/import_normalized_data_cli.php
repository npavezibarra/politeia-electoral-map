<?php
/**
 * Import Normalized Election Data (XLSX -> CSV -> DB)
 * Supports dynamic scanning of normalized_csv subdirectories.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

define('WP_USE_THEMES', false);

// Find wp-load.php
$wp_load_path = __DIR__ . '/../../../../wp-load.php';
if (!file_exists($wp_load_path)) {
    die("Error: Could not find wp-load.php at $wp_load_path\n");
}
require_once $wp_load_path;

global $wpdb;

/**
 * Importer Class
 */
class NormalizedDataImporter
{

    private $offices = [];
    private $jurisdictions_cache = [];
    private $people_cache = [];
    private $orgs_cache = [];
    private $elections_cache = [];

    private $date_mapping = [
        '2013_11' => '2013-11-17',
        '2013_12' => '2013-12-15',
        '2017_11' => '2017-11-19',
        '2017_12' => '2017-12-17',
        '2021_05' => '2021-05-16',
        '2021_06' => '2021-06-13',
        '2021_07' => '2021-07-18',
        '2021_11' => '2021-11-21',
        '2021_12' => '2021-12-19',
        '2024_06' => '2024-06-09',
        '2024_10' => '2024-10-27',
        '2024_11' => '2024-11-24',
        '2012_10' => '2012-10-28',
        '2016_10' => '2016-10-23',
    ];

    public function run()
    {
        echo "🚀 Starting Full Dynamic Normalized Data Import...\n";

        // 1. Base Setup
        $this->load_offices();

        $base_dir = '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/normalized_csv';
        $subdirs = [
            'president' => 'PRESIDENTE',
            'senadores' => 'SENADOR',
            'diputados' => 'DIPUTADO',
            'gobernadores' => 'GOBERNADOR',
            'alcaldes' => 'ALCALDE',
            'others' => 'OTHERS'
        ];

        foreach ($subdirs as $dir => $office_code) {
            $path = "$base_dir/$dir";
            if (!is_dir($path))
                continue;

            $files = glob("$path/*.csv");
            foreach ($files as $filepath) {
                // Skip non-data files
                if (strpos(basename($filepath), 'SCHEMA_') !== false)
                    continue;

                $file_meta = [
                    'path' => $filepath,
                    'office' => $office_code,
                ];
                $this->process_file($file_meta);
            }
        }

        echo "\n✅ Full Bulk Import Finished.\n";
    }

    private function process_file($file)
    {
        echo "\n📂 Processing: " . basename($file['path']) . "\n";
        if (!file_exists($file['path'])) {
            echo "  ❌ File not found: {$file['path']}\n";
            return;
        }

        $handle = fopen($file['path'], 'r');
        if (!$handle) {
            echo "  ❌ Could not open file.\n";
            return;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return;
        }

        // Handle duplicated headers in array_combine
        $unique_header = [];
        $counts = [];
        foreach ($header as $h) {
            if (isset($counts[$h])) {
                $counts[$h]++;
                $unique_header[] = $h . '_DUPE_' . $counts[$h];
            } else {
                $counts[$h] = 0;
                $unique_header[] = $h;
            }
        }

        // Peek first row for metadata
        $first_row_data = fgetcsv($handle);
        if (!$first_row_data) {
            fclose($handle);
            return;
        }

        if (count($unique_header) !== count($first_row_data)) {
            echo "  ❌ Header mismatch. Skipping.\n";
            fclose($handle);
            return;
        }

        $peek = array_combine($unique_header, $first_row_data);

        $election_date = $peek['Fecha de Elección'] ?? '';

        // Fallback: detect from filename (YYYY_MM)
        if (empty($election_date)) {
            $filename = basename($file['path']);
            if (preg_match('/^(\d{4}_\d{2})/', $filename, $matches)) {
                $key = $matches[1];
                if (isset($this->date_mapping[$key])) {
                    $election_date = $this->date_mapping[$key];
                } else {
                    $election_date = str_replace('_', '-', $key) . "-01"; // Fallback to 1st of month
                }
            }
        }

        if (empty($election_date)) {
            echo "  ❌ Date not found. Skipping.\n";
            fclose($handle);
            return;
        }

        $year = $peek['election_year'] ?? substr($election_date, 0, 4);

        // Determine suffix (Segunda Vuelta, etc)
        $suffix = '';
        $v_type = strtoupper($peek['Votación Presidencial'] ?? $peek['election_type'] ?? '');
        if (strpos($v_type, 'SEGUNDA') !== false || strpos(basename($file['path']), '_2V_') !== false) {
            $suffix = ' (Segunda Vuelta)';
        } elseif (strpos($v_type, 'UNICA') !== false) {
            $suffix = ' (Votación Única)';
        }

        $file['date'] = $election_date;
        $file['name'] = "Elección de " . ucwords(strtolower($file['office'])) . " $year" . $suffix;

        // Identify Election ID
        $election_id = $this->get_election_id($file);

        $candidacies_count = 0;

        // Process the first row we already read
        $this->process_row($peek, $election_id);
        $candidacies_count++;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($unique_header) !== count($row))
                continue;
            $data = array_combine($unique_header, $row);
            $this->process_row($data, $election_id);

            $candidacies_count++;
            if ($candidacies_count % 2000 === 0) {
                echo "  - Processed $candidacies_count records...\r";
            }
        }

        fclose($handle);
        echo "  ✓ Done. Imported $candidacies_count records for {$file['name']} ($election_date).\n";
    }

    private function process_row($data, $election_id)
    {
        // Map columns
        $names = strtoupper(trim($data['candidate_name'] ?? ''));
        $s1 = strtoupper(trim($data['lastname_1'] ?? ''));
        $s2 = strtoupper(trim($data['lastname_2'] ?? ''));

        if (empty($names) || $names === 'VOTOS EN BLANCO' || $names === 'VOTOS NULOS' || strpos($names, 'TOTAL') !== false) {
            return;
        }

        // Person ID
        $person_id = $this->get_person_id($names, $s1, $s2);

        // Organization ID (Party)
        $party_id = null;
        if (!empty($data['party_name'])) {
            $party_id = $this->get_org_id($data['party_name']);
        }

        // Jurisdiction ID (Commune)
        $commune_name = strtoupper(trim($data['commune'] ?? ''));
        $jurisdiction_id = $this->get_jurisdiction_id($commune_name, 'COMMUNE');

        if (!$jurisdiction_id) {
            return;
        }

        // Vote Count
        $votes = intval($data['vote_count'] ?? 0);

        // Elected STATUS
        $elected = 0;
        $status_val = strtoupper(trim($data['elected_status'] ?? ''));
        if (in_array($status_val, ['ELECTO', 'ELECTA', 'SI', 'PRESIDENTE', 'DIPUTADO', 'SENADOR'])) {
            $elected = 1;
        }

        // Candidate Number
        $candidate_number = intval($data['vote_number'] ?? 0);

        $this->upsert_candidacy([
            'election_id' => $election_id,
            'person_id' => $person_id,
            'party_id' => $party_id,
            'jurisdiction_id' => $jurisdiction_id,
            'votes' => $votes,
            'elected' => $elected,
            'candidate_number' => $candidate_number,
        ]);
    }

    private function get_election_id($file)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'politeia_elections';

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE election_date = %s AND office_id = %d",
            $file['date'],
            $this->offices[$file['office']] ?? 0
        ));

        if ($id) {
            return $id;
        }

        // Create Election
        $name = $file['name'];
        $wpdb->insert($table, [
            'office_id' => $this->offices[$file['office']] ?? 0,
            'election_date' => $file['date'],
            'name' => $name,
            'title' => $name,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    private function get_person_id($names, $s1, $s2)
    {
        $key = "$names|$s1|$s2";
        if (isset($this->people_cache[$key])) {
            return $this->people_cache[$key];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'politeia_people';

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE given_names = %s AND paternal_surname = %s AND maternal_surname = %s",
            $names,
            $s1,
            $s2
        ));

        if ($id) {
            $this->people_cache[$key] = $id;
            return $id;
        }

        $wpdb->insert($table, [
            'given_names' => $names,
            'paternal_surname' => $s1,
            'maternal_surname' => $s2,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        $new_id = $wpdb->insert_id;
        $this->people_cache[$key] = $new_id;
        return $new_id;
    }

    private function get_org_id($name)
    {
        $name = strtoupper(trim($name));
        if (isset($this->orgs_cache[$name])) {
            return $this->orgs_cache[$name];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'politeia_political_organizations';

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE official_name = %s OR short_name = %s",
            $name,
            $name
        ));

        if ($id) {
            $this->orgs_cache[$name] = $id;
            return $id;
        }

        $wpdb->insert($table, [
            'official_name' => $name,
            'type' => 'PARTY',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        $new_id = $wpdb->insert_id;
        $this->orgs_cache[$name] = $new_id;
        return $new_id;
    }

    private function get_jurisdiction_id($name, $type)
    {
        $key = "$name|$type";
        if (isset($this->jurisdictions_cache[$key])) {
            return $this->jurisdictions_cache[$key];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'politeia_jurisdictions';

        // Map some known variations
        $search_name = $name;
        if ($name === 'AISEN' || $name === 'AISÉN')
            $search_name = 'AYSEN';
        if ($name === 'COYHAIQUE' || $name === 'COIHAIQUE')
            $search_name = 'COIHAIQUE';

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE (UPPER(common_name) = %s OR UPPER(official_name) = %s) AND type = %s",
            $search_name,
            $search_name,
            $type
        ));

        if ($id) {
            $this->jurisdictions_cache[$key] = $id;
            return $id;
        }

        return null;
    }

    private function upsert_candidacy($args)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'politeia_candidacies';

        $exists_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE election_id = %d AND person_id = %d AND jurisdiction_id = %d",
            $args['election_id'],
            $args['person_id'],
            $args['jurisdiction_id']
        ));

        if ($exists_id) {
            $wpdb->update($table, [
                'votes' => $args['votes'],
                'elected' => $args['elected'],
                'candidate_number' => $args['candidate_number'],
                'updated_at' => current_time('mysql'),
            ], ['id' => $exists_id]);
        } else {
            $wpdb->insert($table, [
                'election_id' => $args['election_id'],
                'person_id' => $args['person_id'],
                'party_id' => $args['party_id'],
                'jurisdiction_id' => $args['jurisdiction_id'],
                'votes' => $args['votes'],
                'elected' => $args['elected'],
                'candidate_number' => $args['candidate_number'],
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);
        }
    }

    private function load_offices()
    {
        global $wpdb;
        $results = $wpdb->get_results("SELECT id, code FROM {$wpdb->prefix}politeia_offices", ARRAY_A);
        if ($results) {
            foreach ($results as $row) {
                $this->offices[$row['code']] = $row['id'];
            }
        }
        // Fallback for OTHERS
        if (!isset($this->offices['OTHERS'])) {
            $this->offices['OTHERS'] = 0;
        }
    }
}

$importer = new NormalizedDataImporter();
$importer->run();
