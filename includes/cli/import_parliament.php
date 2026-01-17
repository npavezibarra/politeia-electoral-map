<?php
/**
 * Import Parliament Members (Deputies and Senators)
 * Usage: wp eval-file includes/cli/import_parliament.php
 */

if (!defined('ABSPATH')) {
    require_once __DIR__ . '/../../../../../wp-load.php';
}

class ParliamentImporter
{

    private $offices = [];
    private $parties = [];
    private $jurisdictions = [];
    private $people_cache = [];

    public function run()
    {
        echo "Starting Import...\n";

        // 1. Ensure Offices Exist
        $this->ensure_offices();

        // 2. Import Deputies (2021) - Period 2022-2026
        $this->import_deputies(
            '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/raw_xlsx/diputados/2021_11_Diputados_Votacion.txt',
            '2022-03-11',
            '2026-03-11'
        );

        // 3. Import Senators (2021) - Period 2022-2030
        $this->import_senators(
            '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/normalized_csv/senadores/2021_11_Senadores_Datos_Eleccion_Votación_por_comuna.csv',
            '2022-03-11',
            '2030-03-11'
        );

        // 4. Import Senators (2017) - Period 2018-2026 (Current active half)
        $this->import_senators(
            '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/normalized_csv/senadores/2017_11_Senatorial_Datos_Eleccion_Votación_por_comuna.csv',
            '2018-03-11',
            '2026-03-11'
        );

        echo "Import Complete.\n";
    }

    private function ensure_offices()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'politeia_offices';

        $offices = [
            'DIPUTADO' => 'Diputado',
            'SENADOR' => 'Senador'
        ];

        foreach ($offices as $code => $title) {
            $exists = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE title = %s", $title));
            if ($exists) {
                $this->offices[$code] = $exists->id;
            } else {
                $wpdb->insert($table, [
                    'code' => $code,
                    'title' => $title,
                    'description' => $title . ' de la República'
                ]);
                $this->offices[$code] = $wpdb->insert_id;
                echo "Created Office: $title\n";
            }
        }
    }

    private function get_or_create_party($name)
    {
        if (isset($this->parties[$name]))
            return $this->parties[$name];

        global $wpdb;
        $table = $wpdb->prefix . 'politeia_political_organizations';

        // Normalize
        $clean_name = trim(mb_strtoupper($name, 'UTF-8'));

        $exists = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE official_name = %s", $clean_name));
        if ($exists) {
            $this->parties[$name] = $exists->id;
            return $exists->id;
        }

        $wpdb->insert($table, [
            'official_name' => $clean_name,
            'type' => 'PARTY'
        ]);
        $id = $wpdb->insert_id;
        $this->parties[$name] = $id;
        return $id;
    }

    private function get_or_create_person($names, $surname1, $surname2)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'politeia_people';

        $names = trim(mb_strtoupper($names, 'UTF-8'));
        $surname1 = trim(mb_strtoupper($surname1, 'UTF-8'));
        $surname2 = trim(mb_strtoupper($surname2, 'UTF-8'));

        $key = "$names|$surname1|$surname2";
        if (isset($this->people_cache[$key]))
            return $this->people_cache[$key];

        $exists = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE given_names = %s AND paternal_surname = %s AND maternal_surname = %s",
            $names,
            $surname1,
            $surname2
        ));

        if ($exists) {
            $this->people_cache[$key] = $exists->id;
            return $exists->id;
        }

        $wpdb->insert($table, [
            'given_names' => $names,
            'paternal_surname' => $surname1,
            'maternal_surname' => $surname2
        ]);
        $id = $wpdb->insert_id;
        $this->people_cache[$key] = $id;
        return $id;
    }

    private function resolve_district($district_str)
    {
        // "DISTRITO 2" -> Extract 2
        if (preg_match('/(\d+)/', $district_str, $m)) {
            $num = $m[1];
            global $wpdb;
            // Try finding "Distrito Electoral N° 2" or close match
            // Use REGEXP to match "Distrito.*N.*1$" or strict
            $like = '%' . $wpdb->esc_like("Distrito Electoral N° $num") . '%';
            $row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}politeia_jurisdictions WHERE official_name LIKE %s AND type='DISTRICT'", $like));

            if ($row)
                return $row->id;

            // Fallback: Try with strict name if like fails? (unlikely)
        }
        return null;
    }

    private function resolve_senate_circ($circ_str, $region_name)
    {
        // "CIRCUNSCRIPCION SENATORIAL 3" -> "Circunscripción Senatorial 3"
        if (preg_match('/(\d+)/', $circ_str, $m)) {
            $num = $m[1];
            $official_name = "Circunscripción Senatorial $num";

            global $wpdb;
            $table = $wpdb->prefix . 'politeia_jurisdictions';

            $row = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE official_name = %s AND type='SENATORIAL_CIRCUMSCRIPTION'", $official_name));

            if ($row)
                return $row->id;

            // Create if missing
            // Need parent region
            $region_id = null;
            // Simple region lookup by name
            $clean_region = trim(mb_strtoupper($region_name, 'UTF-8'));
            $region_like = '%' . $wpdb->esc_like(str_replace('DE ', '', $clean_region)) . '%';
            $r_row = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE (official_name LIKE %s OR common_name LIKE %s) AND type='REGION'", $region_like, $region_like));

            if ($r_row)
                $region_id = $r_row->id;

            $wpdb->insert($table, [
                'official_name' => $official_name,
                'type' => 'SENATORIAL_CIRCUMSCRIPTION',
                'parent_id' => $region_id
            ]);
            echo "Created Circunscripción: $official_name\n";
            return $wpdb->insert_id;
        }
        return null;
    }

    // ----------------------------------------------------------------
    // DEPUTIES IMPORT (Pipe Separated)
    // ----------------------------------------------------------------
    private function import_deputies($file, $start, $end)
    {
        echo "Processing Deputies: " . basename($file) . "\n";
        echo "Full Path: $file\n";

        if (!file_exists($file)) {
            echo "ERROR: File not found!\n";
            return;
        }

        $handle = fopen($file, 'r');
        $header = null;

        $processed_people = [];
        $count = 0;
        $found = 0;

        while (($row = fgetcsv($handle, 0, '|')) !== FALSE) {
            // Convert to UTF-8 if needed (naive)
            $row = array_map(function ($x) {
                return mb_convert_encoding($x, 'UTF-8', 'ISO-8859-1, WINDOWS-1252');
            }, $row);

            if (!$header) {
                $header = $row;
                continue;
            }

            // Pad row if missing columns
            if (count($row) !== count($header))
                continue;

            $data = array_combine($header, $row);
            if (!$data)
                continue;

            $status = strtoupper(trim($data['electo']));

            if ($status !== 'ELECTO')
                continue;

            $names = $data['Nombres'];
            $surname1 = $data['Primer apellido'];
            $surname2 = $data['Segundo apellido'];
            $full_name_key = "$names $surname1 $surname2";

            if (isset($processed_people[$full_name_key]))
                continue;

            echo "Found Elected: $full_name_key\n";
            $found++;

            // 1. Person
            $person_id = $this->get_or_create_person($names, $surname1, $surname2);

            // 2. Term
            $dist_raw = $data['Distrito']; // e.g., "DISTRITO 2"
            $jurisdiction_id = $this->resolve_district($data['Distrito']);

            if (!$jurisdiction_id) {
                echo "  WARNING: Could not resolve district '$dist_raw'\n";
            } else {
                $this->create_term($person_id, $this->offices['DIPUTADO'], $jurisdiction_id, $start, $end);
            }

            // 3. Party
            $party_id = $this->get_or_create_party($data['Partido']);
            $this->create_membership($person_id, $party_id, $start);

            $processed_people[$full_name_key] = true;
            $count++;
        }
        fclose($handle);
        echo "Finished Deputies. Processed: $count (Unique Individuals)\n";
    }

    // ----------------------------------------------------------------
    // SENATORS IMPORT (Comma Separated)
    // ----------------------------------------------------------------
    private function import_senators($file, $start, $end)
    {
        echo "Processing Senators: " . basename($file) . "\n";
        if (!file_exists($file)) {
            echo "ERROR: File not found!\n";
            return;
        }

        $handle = fopen($file, 'r');
        $header = null;

        $processed_people = [];
        $count = 0;

        while (($row = fgetcsv($handle, 0, ',')) !== FALSE) {
            // Convert to UTF-8
            $row = array_map(function ($x) {
                return mb_convert_encoding($x, 'UTF-8', 'ISO-8859-1, WINDOWS-1252');
            }, $row);

            if (!$header) {
                $header = $row;
                continue;
            }

            $data = [];
            foreach ($header as $i => $col) {
                $data[$col] = $row[$i] ?? '';
            }

            $status = strtoupper(trim($data['elected_status']));

            if ($status !== 'SENADOR')
                continue;

            $names = $data['candidate_name'];
            $surname1 = $data['lastname_1'];
            $surname2 = $data['lastname_2'];

            $full_name_key = "$names $surname1 $surname2";
            if (isset($processed_people[$full_name_key]))
                continue;

            echo "Found Elected Senator: $full_name_key\n";

            // 1. Person
            $person_id = $this->get_or_create_person($names, $surname1, $surname2);

            // 2. Term
            $jurisdiction_id = $this->resolve_senate_circ($data['senate_district'], $data['region_name']);

            $this->create_term($person_id, $this->offices['SENADOR'], $jurisdiction_id, $start, $end);

            // 3. Party
            $party_id = $this->get_or_create_party($data['party_name']);
            $this->create_membership($person_id, $party_id, $start);

            $processed_people[$full_name_key] = true;
            $count++;
        }
        fclose($handle);
        echo "Finished Senators. Processed: $count\n";
    }

    private function create_term($person_id, $office_id, $jurisdiction_id, $start, $end)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'politeia_office_terms';

        // Check duplicate
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE person_id = %d AND office_id = %d AND started_on = %s",
            $person_id,
            $office_id,
            $start
        ));

        if (!$exists) {
            $wpdb->insert($table, [
                'person_id' => $person_id,
                'office_id' => $office_id,
                'jurisdiction_id' => $jurisdiction_id,
                'started_on' => $start,
                'planned_end_on' => $end,
                'status' => 'ACTIVE'
            ]);
        }
    }

    private function create_membership($person_id, $org_id, $start)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'politeia_party_memberships';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE person_id = %d AND organization_id = %d AND started_on = %s",
            $person_id,
            $org_id,
            $start
        ));

        if (!$exists) {
            $wpdb->insert($table, [
                'person_id' => $person_id,
                'organization_id' => $org_id,
                'started_on' => $start
            ]);
        }
    }
}

(new ParliamentImporter())->run();
