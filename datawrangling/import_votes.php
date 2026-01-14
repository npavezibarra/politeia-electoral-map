<?php
/**
 * Import Governor Votes into Candidacies
 * Run via: php datawrangling/import_votes.php
 */

// Bootstrap WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

class GovernorVoteImporter
{
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function run()
    {
        echo "Starting Vote Import...\n";

        $office_id = $this->get_governor_office_id();
        if (!$office_id)
            die("Office not found.\n");

        // 1. Ensure Election Exists
        $election_id = $this->ensure_election($office_id, '2024-10-27');

        // 2. Process CSV 1 (Round 1) - Only Winners for now to match current scope
        $this->process_csv('governors_2024.csv', 12, 'ELECTO', $election_id, $office_id);

        // 3. Process CSV 2 (Round 2) - Election Date changed? 
        // Round 2 date usually Nov. Let's create a 2nd election or treat as rounds?
        // Schema has `round_number`. Ideally update round.
        // For simplicity of display, we just want the votes of the WINNER.
        // We can put them in the same "Election" record or separate.
        // Let's us separate election for round 2 if date differs, or same election ID if we treat it as one event.
        // Let's create a second election for Round 2: 24 Nov 2024.
        $election_id_2 = $this->ensure_election($office_id, '2024-11-24', 2);

        $this->process_csv('governors_2024_2v.csv', 13, 'GOBERNADOR REGIONAL', $election_id_2, $office_id);
    }

    private function get_governor_office_id()
    {
        $sql = "SELECT id FROM {$this->wpdb->prefix}politeia_offices WHERE code = 'GOBERNADOR' OR title LIKE '%Gobernador%' LIMIT 1";
        return $this->wpdb->get_var($sql);
    }

    private function ensure_election($office_id, $date, $round = 1)
    {
        $table = $this->wpdb->prefix . 'politeia_elections';
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM $table WHERE office_id = %d AND election_date = %s",
            $office_id,
            $date
        ));

        if ($existing)
            return $existing;

        $this->wpdb->insert($table, [
            'office_id' => $office_id,
            'election_date' => $date,
            'title' => 'Elección de Gobernadores Regionales 2024',
            'round_number' => $round,
            'jurisdiction_id' => 0 // National/Generic? Or null. 
        ]);

        return $this->wpdb->insert_id;
    }

    private function process_csv($filename, $status_col_index, $status_value, $election_id, $office_id)
    {
        $csv_path = dirname(dirname(__FILE__)) . '/assets/csv/' . $filename;
        if (!file_exists($csv_path)) {
            echo "Skipping $filename (not found)\n";
            return;
        }

        $handle = fopen($csv_path, 'r');
        fgetcsv($handle); // Header

        echo "Processing $filename...\n";

        while (($data = fgetcsv($handle)) !== false) {
            $result_status = isset($data[$status_col_index]) ? trim($data[$status_col_index]) : '';
            if ($result_status !== $status_value)
                continue;

            $region_name_raw = $data[1];

            // Indexes (copied from GovernorImporter)
            $idx_name = ($filename === 'governors_2024_2v.csv') ? 9 : 8;
            $idx_last1 = ($filename === 'governors_2024_2v.csv') ? 10 : 9;
            $idx_last2 = ($filename === 'governors_2024_2v.csv') ? 11 : 10;
            $candidate_name = $data[$idx_name];
            $lastname_1 = $data[$idx_last1];
            $lastname_2 = $data[$idx_last2];

            // Votes
            // In File 1: vote_count is index 6? Let's check view_file.
            // view_file 206: "region_id,region_name,...,candidate_name,...,vote_count,..."
            // Let's look at headers again to be safe.
            // File 1 Headers: region_id,region_name,senate_district,district,commune,list_id,pact_name,party_name,candidate_name,lastname_1,lastname_2,vote_count,Resultado
            // Index 11 seems to be vote_count.
            // File 2 Headers: region_id,region_name,senate_district,district,commune,list_id,pact_name,party_name,vote_number,candidate_name,lastname_1,lastname_2,vote_count,elected_status
            // Index 12 seems to be vote_count.

            // BUT WAIT. The CSV is "Votación por comuna". This means MULTIPLE rows per candidate (one per comuna).
            // We need to SUM the votes for the Region? 
            // OR does the CSV have a summary row?
            // The filename says "Votación por comuna".
            // If I import row by row, I'm creating multiple candidacies? NO.
            // I need to SUM them up for the Region-level candidacy.
            // This is tricky. 
            // Actually, is there a Region-Total CSV?
            // User provided "Votación por comuna".

            // Alternative: `politeia_candidacies` usually wants the TOTAL votes for the jurisdiction.
            // If I have comuna-level data, I must Aggregate.

            // Strategy:
            // 1. Maintain a map of Candidate => Total Votes.
            // 2. Iterate whole file, summing votes.
            // 3. Afterwards, insert Candidacies.

            // Let's refrain from inserting inside the loop.
            // First pass: sum votes.
        }
        fclose($handle);

        // RE-PROCESS with Aggregation
        $candidates = []; // key: "Name Last1 Last2" -> {votes: 0, region: "Name", ...}

        $handle = fopen($csv_path, 'r');
        fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== false) {
            $result_status = isset($data[$status_col_index]) ? trim($data[$status_col_index]) : '';
            // Limit to winners?
            // The User wants "votes matching the governor".
            // So yes, we only care about winners.
            if ($result_status !== $status_value)
                continue;

            $idx_name = ($filename === 'governors_2024_2v.csv') ? 9 : 8;
            $idx_last1 = ($filename === 'governors_2024_2v.csv') ? 10 : 9;
            $idx_last2 = ($filename === 'governors_2024_2v.csv') ? 11 : 10;
            $name_key = trim($data[$idx_name] . ' ' . $data[$idx_last1] . ' ' . $data[$idx_last2]);

            // Vote Index
            // File 1: 11? File 2: 12?
            // Let's verifying visually or assuming from previous context.
            // File 1: candidate(8), last1(9), last2(10), votes(11), result(12). Correct.
            // File 2: candidate(9), last1(10), last2(11), votes(12), result(13). Correct.
            $idx_vote = ($filename === 'governors_2024_2v.csv') ? 12 : 11;

            $votes = intval(str_replace('.', '', $data[$idx_vote])); // Remove thousands separator

            if (!isset($candidates[$name_key])) {
                $candidates[$name_key] = [
                    'name' => $data[$idx_name],
                    'last1' => $data[$idx_last1],
                    'last2' => $data[$idx_last2],
                    'region_raw' => $data[1],
                    'votes' => 0
                ];
            }
            $candidates[$name_key]['votes'] += $votes;
        }
        fclose($handle);

        foreach ($candidates as $c) {
            $this->save_candidacy($c, $election_id, $office_id);
        }
    }

    private function save_candidacy($data, $election_id, $office_id)
    {
        // Find Person
        $person_id = $this->get_person_id($data['name'], $data['last1'], $data['last2']);
        if (!$person_id) {
            echo "Person not found: {$data['name']} \n";
            return;
        }

        // Find Region (Jurisdiction)
        $region_name = $data['region_raw'];
        // Use robust lookup similar to API logic
        $jur_id = $this->get_region_id($region_name);

        if (!$jur_id) {
            echo "Region not found: $region_name \n";
            return;
        }

        // Insert/Update Candidacy
        $table = $this->wpdb->prefix . 'politeia_candidacies';

        // Check exist
        $exists_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM $table WHERE election_id = %d AND person_id = %d",
            $election_id,
            $person_id
        ));

        if ($exists_id) {
            $this->wpdb->update(
                $table,
                ['votes' => $data['votes'], 'elected' => 1, 'jurisdiction_id' => $jur_id],
                ['id' => $exists_id]
            );
            echo "Updated {$data['name']} ($region_name): {$data['votes']} votes.\n";
        } else {
            $this->wpdb->insert($table, [
                'election_id' => $election_id,
                'person_id' => $person_id,
                'jurisdiction_id' => $jur_id,
                'votes' => $data['votes'],
                'elected' => 1,
                'affiliation_status' => 'INDEPENDIENTE' // Or parse party..
            ]);
            echo "Inserted {$data['name']} ($region_name): {$data['votes']} votes.\n";
        }
    }

    private function get_person_id($name, $last1, $last2)
    {
        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}politeia_people 
            WHERE given_names = %s AND paternal_surname = %s AND maternal_surname = %s",
            $name,
            $last1,
            $last2
        );
        return $this->wpdb->get_var($sql);
    }

    private function get_region_id($name_raw)
    {
        $table = $this->wpdb->prefix . 'politeia_jurisdictions';

        // 1. Exact
        $id = $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $table WHERE official_name = %s", $name_raw));
        if ($id)
            return $id;

        // 2. Clean
        $clean = trim(str_ireplace(['DE ', 'DEL '], '', $name_raw));
        $id = $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $table WHERE official_name = %s", $clean));
        if ($id)
            return $id;

        // 3. Like
        $like = '%' . $this->wpdb->esc_like($clean) . '%';
        return $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $table WHERE official_name LIKE %s LIMIT 1", $like));
    }
}

$importer = new GovernorVoteImporter();
$importer->run();
