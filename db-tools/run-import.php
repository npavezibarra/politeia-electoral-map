<!DOCTYPE html>
<html>

<head>
    <title>Politeia Import</title>
    <style>
        body {
            font-family: monospace;
            white-space: pre-wrap;
            padding: 20px;
            background: #f0f0f0;
        }
    </style>
</head>

<body>
    <h1>Running Parliament Import...</h1>
    <hr>
    <?php
    // Define ABSPATH if not present, loading wp-load.php relative to plugin dir
// Plugin dir: .../wp-content/plugins/politeia-electoral-map/
// wp-load.php is in public/ (3 levels up: plugins -> wp-content -> public)
    
    if (!defined('ABSPATH')) {
        $path = __DIR__ . '/../../../../wp-load.php';
        if (file_exists($path)) {
            require_once $path;
        } else {
            die("Error: Could not find wp-load.php at $path");
        }
    }

    // Check admin permissions
    if (!current_user_can('manage_options')) {
        // Optional: die("Access Denied. You must be logged in as admin.");
        // For local dev, we might skip this strict check or rely on local auth state
    }

    // Increase Limits
    set_time_limit(300);

    class WebParliamentImporter
    {

        private $offices = [];
        private $parties = [];
        private $people_cache = [];
        private $districts_cache = [];

        private function out($msg)
        {
            echo htmlspecialchars($msg) . "\n";
            flush();
        }

        public function run()
        {
            $this->out("Starting Import Process...");

            // 1. Setup Offices
            $this->ensure_offices();

            // 2. Import Deputies
            $deputy_file = '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/raw_xlsx/diputados/2021_11_Diputados_Votacion.txt';
            $this->import_deputies($deputy_file, '2022-03-11', '2026-03-11');

            // 3. Import Senators (2022-2030)
            $senator_file_2021 = '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/normalized_csv/senadores/2021_11_Senadores_Datos_Eleccion_Votación_por_comuna.csv';
            $this->import_senators($senator_file_2021, '2022-03-11', '2030-03-11');

            // 4. Import Senators (2018-2026)
            $senator_file_2017 = '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/normalized_csv/senadores/2017_11_Senatorial_Datos_Eleccion_Votación_por_comuna.csv';
            $this->import_senators($senator_file_2017, '2018-03-11', '2026-03-11');

            // 5. Import Deputies (2014-2018) - 2013 Election
            $deputy_file_2013 = '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/normalized_csv/diputados/2013_11_Diputados_DatosEleccion_Votación_por_comuna.csv';
            $this->import_deputies_2013($deputy_file_2013, '2014-03-11', '2018-03-11');

            // 6. Import Senators (2014-2022) - 2013 Election
            $senator_file_2013 = '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/normalized_csv/senadores/2013_11_Senatorial_DatosEleccion_Votación_por_comuna.csv';
            $this->import_senators($senator_file_2013, '2014-03-11', '2022-03-11');

            $this->out("DONE.");
        }

        private function ensure_offices()
        {
            global $wpdb;
            $table = $wpdb->prefix . 'politeia_offices';
            $offices = ['DIPUTADO' => 'Diputado', 'SENADOR' => 'Senador'];
            foreach ($offices as $code => $title) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE title = %s", $title));
                if ($row) {
                    $this->offices[$code] = $row->id;
                } else {
                    $wpdb->insert($table, ['code' => $code, 'title' => $title, 'description' => $title . ' de la República']);
                    $this->offices[$code] = $wpdb->insert_id;
                    $this->out("Created Office: $title");
                }
            }
        }

        private function get_district_id($num)
        {
            if (isset($this->districts_cache[$num]))
                return $this->districts_cache[$num];
            global $wpdb;
            $like = '%' . $wpdb->esc_like("Distrito Electoral N° $num") . '%';
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}politeia_jurisdictions WHERE official_name LIKE %s AND type='DISTRICT'", $like));
            if ($id) {
                $this->districts_cache[$num] = $id;
                return $id;
            }
            return null;
        }

        private function get_person_id($names, $s1, $s2)
        {
            global $wpdb;
            $names = strtoupper(trim($names));
            $s1 = strtoupper(trim($s1));
            $s2 = strtoupper(trim($s2));
            $key = "$names|$s1|$s2";

            if (isset($this->people_cache[$key]))
                return $this->people_cache[$key];

            $table = $wpdb->prefix . 'politeia_people';
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE given_names = %s AND paternal_surname = %s AND maternal_surname = %s", $names, $s1, $s2));

            if ($id) {
                $this->people_cache[$key] = $id;
                return $id;
            }

            $wpdb->insert($table, ['given_names' => $names, 'paternal_surname' => $s1, 'maternal_surname' => $s2]);
            $new_id = $wpdb->insert_id;
            $this->people_cache[$key] = $new_id;
            return $new_id;
        }

        private function get_party_id($name)
        {
            global $wpdb;
            $name = strtoupper(trim($name));
            if (isset($this->parties[$name]))
                return $this->parties[$name];

            $table = $wpdb->prefix . 'politeia_political_organizations';
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE official_name = %s", $name));

            if ($id) {
                $this->parties[$name] = $id;
                return $id;
            }

            $wpdb->insert($table, ['official_name' => $name, 'type' => 'PARTY']);
            $new_id = $wpdb->insert_id;
            $this->parties[$name] = $new_id;
            return $new_id;
        }

        private function resolve_circ_senatorial($txt, $region_name)
        {
            if (preg_match('/(\d+)/', $txt, $m)) {
                $num = $m[1];
                $official = "Circunscripción Senatorial $num";
                global $wpdb;
                $table = $wpdb->prefix . 'politeia_jurisdictions';
                $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE official_name = %s AND type='SENATORIAL_CIRCUMSCRIPTION'", $official));
                if ($id)
                    return $id;

                $wpdb->insert($table, ['official_name' => $official, 'type' => 'SENATORIAL_CIRCUMSCRIPTION']);
                $this->out("Created new jurisdiction: $official");
                return $wpdb->insert_id;
            }
            return null;
        }

        private function import_deputies($path, $start, $end)
        {
            $this->out("Importing Deputies from file...");
            if (!file_exists($path)) {
                $this->out("ERROR: File Not Found: $path");
                return;
            }

            $handle = fopen($path, 'r');
            $header = fgetcsv($handle, 0, '|');

            $count = 0;
            $seen = [];

            while (($row = fgetcsv($handle, 0, '|')) !== FALSE) {
                $row = array_map('utf8_encode', $row);
                if (count($row) != count($header))
                    continue;
                $data = array_combine($header, $row);

                if (strtoupper(trim($data['electo'] ?? '')) !== 'ELECTO')
                    continue;

                $name = $data['Nombres'];
                $s1 = $data['Primer apellido'];
                $s2 = $data['Segundo apellido'];
                $key = "$name $s1 $s2";

                if (isset($seen[$key]))
                    continue;
                $seen[$key] = true;

                // Person
                $pid = $this->get_person_id($name, $s1, $s2);

                // District
                $d_id = null;
                if (preg_match('/(\d+)/', $data['Distrito'], $m))
                    $d_id = $this->get_district_id($m[1]);

                if ($d_id) {
                    $this->add_term($pid, $this->offices['DIPUTADO'], $d_id, $start, $end);
                    $count++;
                } else {
                    $this->out("Warn: District not found for $name ($data[Distrito])");
                }

                // Party
                $party_id = $this->get_party_id($data['Partido']);
                $this->add_membership($pid, $party_id, $start);
            }
            fclose($handle);
            $this->out("Imported $count Deputies.");
        }

        private function import_deputies_2013($path, $start, $end)
        {
            $this->out("Importing Deputies (2013) from file...");
            if (!file_exists($path)) {
                $this->out("ERROR: File Not Found: $path");
                return;
            }

            $handle = fopen($path, 'r');
            $header = fgetcsv($handle, 0, ','); // Comma
    
            $count = 0;
            $seen = [];

            while (($row = fgetcsv($handle, 0, ',')) !== FALSE) {
                // $row = array_map('utf8_encode', $row); // 2013 CSV likely UTF8 or standard
                $row = array_map(function ($x) {
                    return mb_convert_encoding($x, 'UTF-8', 'ISO-8859-1'); }, $row);

                if (count($row) < count($header))
                    continue;
                $data = [];
                foreach ($header as $i => $h)
                    $data[$h] = $row[$i] ?? '';

                if (strtoupper(trim($data['elected_status'])) !== 'DIPUTADO')
                    continue;

                $name = $data['candidate_name'];
                $s1 = $data['lastname_1'];
                $s2 = $data['lastname_2'];
                $key = "$name $s1 $s2";

                if (isset($seen[$key]))
                    continue;
                $seen[$key] = true;

                $pid = $this->get_person_id($name, $s1, $s2);

                // District "DISTRITO 2"
                $d_id = null;
                if (preg_match('/(\d+)/', $data['district'], $m))
                    $d_id = $this->get_district_id($m[1]);

                if ($d_id) {
                    $this->add_term($pid, $this->offices['DIPUTADO'], $d_id, $start, $end);
                    $count++;
                }

                $party_id = $this->get_party_id($data['party_name']);
                $this->add_membership($pid, $party_id, $start);
            }
            fclose($handle);
            $this->out("Imported $count Deputies (2013).");
        }

        private function import_senators($path, $start, $end)
        {
            $this->out("Importing Senators from file...");
            if (!file_exists($path)) {
                $this->out("ERROR: File Not Found: $path");
                return;
            }

            $handle = fopen($path, 'r');
            $header = fgetcsv($handle, 0, ',');

            $count = 0;
            $seen = [];

            while (($row = fgetcsv($handle, 0, ',')) !== FALSE) {
                $row = array_map(function ($x) {
                    return mb_convert_encoding($x, 'UTF-8', 'ISO-8859-1');
                }, $row);

                if (count($row) < count($header))
                    continue;

                $data = [];
                foreach ($header as $i => $h)
                    $data[$h] = $row[$i] ?? '';

                if (strtoupper(trim($data['elected_status'])) !== 'SENADOR')
                    continue;

                $name = $data['candidate_name'];
                $s1 = $data['lastname_1'];
                $s2 = $data['lastname_2'];
                $key = "$name $s1 $s2";

                if (isset($seen[$key]))
                    continue;
                $seen[$key] = true;

                $pid = $this->get_person_id($name, $s1, $s2);
                $circ_id = $this->resolve_circ_senatorial($data['senate_district'], $data['region_name']);

                $this->add_term($pid, $this->offices['SENADOR'], $circ_id, $start, $end);

                $party_id = $this->get_party_id($data['party_name']);
                $this->add_membership($pid, $party_id, $start);

                $count++;
            }
            fclose($handle);
            $this->out("Imported $count Senators.");
        }

        private function add_term($pid, $oid, $jid, $start, $end)
        {
            global $wpdb;
            $table = $wpdb->prefix . 'politeia_office_terms';
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE person_id=%d AND office_id=%d AND started_on=%s", $pid, $oid, $start));
            if (!$exists) {
                $wpdb->insert($table, [
                    'person_id' => $pid,
                    'office_id' => $oid,
                    'jurisdiction_id' => $jid,
                    'started_on' => $start,
                    'planned_end_on' => $end,
                    'is_acting' => 0,
                    'status' => 'ACTIVE'
                ]);
            }
        }

        private function add_membership($pid, $orgid, $start)
        {
            global $wpdb;
            $table = $wpdb->prefix . 'politeia_party_memberships';
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE person_id=%d AND organization_id=%d AND started_on=%s", $pid, $orgid, $start));
            if (!$exists) {
                $wpdb->insert($table, ['person_id' => $pid, 'organization_id' => $orgid, 'started_on' => $start]);
            }
        }
    }

    (new WebParliamentImporter())->run();
    ?>
</body>

</html>