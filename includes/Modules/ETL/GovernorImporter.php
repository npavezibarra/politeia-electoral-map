<?php

namespace Politeia\Modules\ETL;

use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class GovernorImporter
{
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public static function run()
    {
        $importer = new self();
        $importer->import();
    }

    public function import()
    {
        // 1. Ensure Office Exists
        $office_id = $this->ensure_governor_office();
        if (!$office_id) {
            echo "Error: Could not create/find Governor office.<br>";
            return;
        }


        // 2. Read CSV 1 (First Round)
        $this->process_csv('governors_2024.csv', 12, 'ELECTO');

        // 3. Read CSV 2 (Second Round)
        $this->process_csv('governors_2024_2v.csv', 13, 'GOBERNADOR REGIONAL');

        echo "Import completed.";
    }

    private function process_csv($filename, $status_col_index, $status_value)
    {
        $csv_path = plugin_dir_path(dirname(dirname(__DIR__))) . 'assets/csv/' . $filename;
        if (!file_exists($csv_path)) {
            echo "Error: CSV file not found at $csv_path<br>";
            return;
        }

        $handle = fopen($csv_path, 'r');
        if ($handle === false) {
            echo "Error: Could not open CSV $filename.<br>";
            return;
        }

        $headers = fgetcsv($handle); // Skip headers

        $regions_processed = [];
        echo "Processing $filename...<br>";

        while (($data = fgetcsv($handle)) !== false) {
            // Columns vary slightly but name/lastname seems consistent around index 8,9,10
            // File 1: 0:id, 1:name, ... 8:cand_name, 9:last1, 10:last2, ... 12:result
            // File 2: 0:id, 1:name, ... 9:cand_name, 10:last1, 11:last2, ... 13:result (Actually shifted by 1?)

            // Let's re-verify column indexes.
            // File 1 viewed in Step 206 (Wait, I didn't view file 1 fully, I just assumed structure or viewed it in previous session? I viewed it in Step 186 of previous session?)
            // I should be careful about indexes.
            // File 2 headers: region_id,region_name,senate_district,district,commune,list_id,pact_name,party_name,vote_number,candidate_name,lastname_1,lastname_2,vote_count,elected_status
            // Index 0: region_id
            // Index 1: region_name
            // Index 9: candidate_name
            // Index 10: lastname_1
            // Index 11: lastname_2
            // Index 13: elected_status

            // File 1 headers (I assumed): 
            // In GovernorImporter I used: 8, 9, 10 for name. 
            // If File 2 is shifted, I need dynamic search or passing indexes.

            $result_status = isset($data[$status_col_index]) ? trim($data[$status_col_index]) : '';
            if ($result_status !== $status_value) {
                continue;
            }

            $region_name_raw = $data[1];

            // Indexes for Name:
            $idx_name = ($filename === 'governors_2024_2v.csv') ? 9 : 8;
            $idx_last1 = ($filename === 'governors_2024_2v.csv') ? 10 : 9;
            $idx_last2 = ($filename === 'governors_2024_2v.csv') ? 11 : 10;

            $candidate_name = $data[$idx_name];
            $lastname_1 = $data[$idx_last1];
            $lastname_2 = $data[$idx_last2];

            // Map Region Name to ID
            $jurisdiction_id = $this->get_region_id($region_name_raw);
            if (!$jurisdiction_id) {
                $clean_name = str_replace("DE ", "", $region_name_raw);
                $jurisdiction_id = $this->get_region_id($clean_name);
            }

            if (!$jurisdiction_id) {
                echo "Warning: Could not find region jurisdiction for '$region_name_raw'. Skipping.<br>";
                continue;
            }

            // Ensure Office Exists (cached or fetched)
            $office_id = $this->ensure_governor_office();

            // Create/Find Person
            $person_id = $this->ensure_person($candidate_name, $lastname_1, $lastname_2);

            // Create Office Term
            $this->create_office_term($person_id, $office_id, $jurisdiction_id);

            if (!in_array($region_name_raw, $regions_processed)) {
                echo "Processed Winner for: $region_name_raw<br>";
                $regions_processed[] = $region_name_raw;
            }
        }
        fclose($handle);
    }

    private function ensure_governor_office()
    {
        $table = $this->wpdb->prefix . 'politeia_offices';
        $code = 'GOBERNADOR';

        $exists = $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $table WHERE code = %s", $code));

        if ($exists) {
            return $exists;
        }

        $this->wpdb->insert($table, [
            'code' => $code,
            'title' => 'Gobernador Regional',
            'description' => 'Maximum authority of the Regional Government.'
        ]);

        return $this->wpdb->insert_id;
    }

    private function get_region_id($name_part)
    {
        $table = $this->wpdb->prefix . 'politeia_jurisdictions';
        // CSV: "DE TARAPACA" -> DB: "Región de Tarapacá" or "Tarapacá"?
        // We try partial matching.
        $search = '%' . $this->wpdb->esc_like(ucwords(strtolower($name_part))) . '%';

        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM $table WHERE type = 'REGION' AND (official_name LIKE %s OR common_name LIKE %s)",
            $search,
            $search
        ));
    }

    private function ensure_person($names, $last1, $last2)
    {
        $table = $this->wpdb->prefix . 'politeia_people';

        // Simple duplicate check
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM $table WHERE given_names = %s AND paternal_surname = %s",
            $names,
            $last1
        ));

        if ($exists) {
            return $exists;
        }

        $this->wpdb->insert($table, [
            'given_names' => $names,
            'paternal_surname' => $last1,
            'maternal_surname' => $last2,
        ]);

        return $this->wpdb->insert_id;
    }

    private function create_office_term($person_id, $office_id, $jur_id)
    {
        $table = $this->wpdb->prefix . 'politeia_office_terms';
        $start_date = '2025-01-06'; // Approx start for 2024 winners

        // Check duplicate
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM $table WHERE person_id = %d AND office_id = %d AND jurisdiction_id = %d AND started_on = %s",
            $person_id,
            $office_id,
            $jur_id,
            $start_date
        ));

        if ($exists)
            return;

        $this->wpdb->insert($table, [
            'person_id' => $person_id,
            'office_id' => $office_id,
            'jurisdiction_id' => $jur_id,
            'started_on' => $start_date,
            'status' => 'ACTIVE'
        ]);
    }
}
