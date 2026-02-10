<?php
if (php_sapi_name() !== 'cli' && !defined('ABSPATH')) {
    require_once __DIR__ . '/../../../../wp-load.php';
}

// Ensure high limits
set_time_limit(0);
ini_set('memory_limit', '1024M');

class SenatorHistoricalImporter
{

    // Hardcoded Mapping of Region Name Keywords -> Current Jurisdiction ID
    // Based on user provided list:
    // 401: Arica, 402: Tarapaca, 403: Antofagasta, 404: Atacama, 405: Coquimbo
    // 406: Valparaíso, 407: Metropolitana, 408: O'Higgins, 409: Maule
    // 410: Biobío, 411: Araucanía, 412: Los Ríos, 413: Los Lagos
    // 414: Aisén, 415: Magallanes, 416: Ñuble
    private $region_map = [
        'ARICA' => 401,
        'TARAPACA' => 402,
        'ANTOFAGASTA' => 403,
        'ATACAMA' => 404,
        'COQUIMBO' => 405,
        'VALPARAISO' => 406,
        'METROPOLITANA' => 407,
        'O\'HIGGINS' => 408,
        'MAULE' => 409,
        'BIOBIO' => 410,
        'ARAUCANIA' => 411,
        'RIOS' => 412,
        'LOS LAGOS' => 413,
        'AISEN' => 414,
        'AYSEN' => 414, // Add alternative spelling
        'MAGALLANES' => 415,
        'ÑUBLE' => 416
    ];

    private $office_id;
    private $people_cache = [];
    private $parties_cache = [];

    public function __construct()
    {
        global $wpdb;
        // Get Senator Office ID
        $this->office_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}politeia_offices WHERE code='SENADOR'");
        if (!$this->office_id)
            die("Error: Office SENADOR not found.\n");
        echo "Office SENADOR ID: $this->office_id\n";
    }

    public function run()
    {
        // 1. Import 2013 (Term 2014-2022)
        $this->process_file(
            '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/normalized_csv/senadores/2013_11_Senatorial_DatosEleccion_Votación_por_comuna.csv',
            '2014-03-11',
            '2022-03-11'
        );

        // 2. Import 2017 (Term 2018-2026)
        $this->process_file(
            '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/normalized_csv/senadores/2017_11_Senatorial_Datos_Eleccion_Votación_por_comuna.csv',
            '2018-03-11',
            '2026-03-11'
        );
    }

    private function get_jurisdiction_id($region_name)
    {
        $upper = strtoupper($region_name);
        foreach ($this->region_map as $key => $id) {
            // Need robust matching.
            // "DE ANTOFAGASTA" contains "ANTOFAGASTA"
            // "DEL BIOBIO" contains "BIOBIO"
            // "METROPOLITANA DE SANTIAGO" contains "METROPOLITANA"
            if (strpos($upper, $key) !== false) {
                return $id;
            }
        }
        return null;
    }

    private function process_file($path, $start_date, $end_date)
    {
        echo "Processing $path...\n";
        if (!file_exists($path)) {
            echo "File not found!\n";
            return;
        }

        $handle = fopen($path, 'r');
        // 2013 CSV usually comma, 2017 might be comma or semicolon? checking head...
        // 2013 head output showed comma separated.
        // Let's assume comma for now, but inspect first line or try-catch.

        $headers = fgetcsv($handle, 0, ','); // Try comma first

        // Map headers to lower case for easier access
        $h_map = [];
        foreach ($headers as $i => $h) {
            $h_map[trim($h)] = $i;
        }

        $count = 0;
        $seen_candidates = [];

        while (($row = fgetcsv($handle, 0, ',')) !== FALSE) {
            // Convert encoding ??
            // 2013 output looked fine in terminal (UTF8-ish).
            // But we can apply utf8_encode if needed.
            // $row = array_map('utf8_encode', $row); 

            if (count($row) < count($headers))
                continue;

            // Access via map
            // 2013 Schema: elected_status, candidate_name, lastname_1, lastname_2, region_name, party_name
            // 2017 Schema: might differ. We will check existence.

            // Normalize header access
            $data = [];
            foreach ($h_map as $name => $idx) {
                $data[$name] = $row[$idx] ?? '';
            }

            // Check if ELECTED
            $status = strtoupper(trim($data['elected_status'] ?? $data['electo'] ?? '')); // 2013: elected_status, 2017: electo?
            if ($status !== 'SENADOR' && $status !== 'ELECTO')
                continue;

            // If 2017 uses 'electo' column and value 'ELECTO', that's fine.
            // If 2013 uses 'elected_status' and value 'SENADOR', that's fine.
            // Wait, 2013 head showed `elected_status` empty for non-elected? No, output showed `,` at end.
            // Ah, looking at grep output: `...,1450,` -> elected_status was empty.
            // Maybe I need to find rows where `elected_status` IS populated?
            // The file likely contains ALL candidates.
            // Let me re-read the grep output of 2013:
            // ...,1450, 
            // The last column was empty.
            // I need to know what the "Elected" value is. 'SENADOR'? 'ELECTO'?
            // I will assume if it's NOT EMPTY, it's elected? Or specific string.

            if (empty($status))
                continue;

            // Get Name
            $name = $data['candidate_name'] ?? $data['Nombres'] ?? '';
            $l1 = $data['lastname_1'] ?? $data['Primer apellido'] ?? '';
            $l2 = $data['lastname_2'] ?? $data['Segundo apellido'] ?? '';

            $fullname = "$name $l1 $l2";

            // De-duplicate per region (CSV has row per commune)
            $region_name = $data['region_name'] ?? $data['Region'] ?? '';
            $uniq = "$fullname|$region_name";
            if (isset($seen_candidates[$uniq]))
                continue;
            $seen_candidates[$uniq] = true;

            // Resolve Jurisdiction
            $jur_id = $this->get_jurisdiction_id($region_name);
            if (!$jur_id) {
                echo "Warn: Could not map region '$region_name'\n";
                continue;
            }

            // Get Person ID
            $pid = $this->get_person_id($name, $l1, $l2);

            // Insert Term
            $this->add_term($pid, $jur_id, $start_date, $end_date);

            // Add Party
            $party_name = $data['party_name'] ?? $data['Partido'] ?? '';
            if ($party_name) {
                $this->add_membership($pid, $party_name, $start_date);
            }

            $count++;
            echo "Imported: $fullname for Region $region_name ($start_date - $end_date)\n";
        }
        fclose($handle);
        echo "Finished $path. Total: $count\n\n";
    }

    private function get_person_id($n, $l1, $l2)
    {
        global $wpdb;
        $n = trim($n);
        $l1 = trim($l1);
        $l2 = trim($l2);
        // Simple match
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}politeia_people WHERE given_names=%s AND paternal_surname=%s AND maternal_surname=%s",
            $n,
            $l1,
            $l2
        ));
        if ($id)
            return $id;

        $wpdb->insert("{$wpdb->prefix}politeia_people", [
            'given_names' => $n,
            'paternal_surname' => $l1,
            'maternal_surname' => $l2
        ]);
        return $wpdb->insert_id;
    }

    private function add_term($pid, $jid, $start, $end)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}politeia_office_terms";
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE person_id=%d AND office_id=%d AND jurisdiction_id=%d AND started_on=%s",
            $pid,
            $this->office_id,
            $jid,
            $start
        ));

        if (!$exists) {
            $wpdb->insert($table, [
                'person_id' => $pid,
                'office_id' => $this->office_id,
                'jurisdiction_id' => $jid,
                'started_on' => $start,
                'planned_end_on' => $end,
                'status' => 'ACTIVE' // or 'CONCLUDED' if past? Schema says 'ACTIVE' usually implies valid record. Logic uses dates.
            ]);
        }
    }

    private function add_membership($pid, $pname, $start)
    {
        global $wpdb;
        // Find party
        $pname = strtoupper(trim($pname));
        $oid = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}politeia_political_organizations WHERE official_name=%s", $pname));
        if (!$oid) {
            $wpdb->insert("{$wpdb->prefix}politeia_political_organizations", ['official_name' => $pname, 'type' => 'PARTY']);
            $oid = $wpdb->insert_id;
        }

        $table = "{$wpdb->prefix}politeia_party_memberships";
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE person_id=%d AND organization_id=%d AND started_on=%s", $pid, $oid, $start));
        if (!$exists) {
            $wpdb->insert($table, ['person_id' => $pid, 'organization_id' => $oid, 'started_on' => $start]);
        }
    }
}

$importer = new SenatorHistoricalImporter();
$importer->run();
?>