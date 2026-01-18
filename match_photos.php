<?php
if (!defined('ABSPATH')) {
    require_once __DIR__ . '/../../../wp-load.php';
}

global $wpdb;
if (!$wpdb) {
    die("WPDB not available.\n");
}

class PhotoMatcher
{
    private $base_dir;
    private $dry_run;
    private $db;

    private $office_map = [
        'senador' => 'SENADOR',
        'diputado' => 'DIPUTADO',
        'alcalde' => 'ALCALDE',
        'gobernador' => 'GOBERNADOR',
        'concejal' => 'CONCEJAL',
        'core' => 'CORE'
    ];

    public function __construct($base_dir, $db, $dry_run = true)
    {
        $this->base_dir = $base_dir;
        $this->db = $db;
        $this->dry_run = $dry_run;
    }

    public function run()
    {
        $years = ['2017', '2021', '2024'];

        foreach ($years as $year) {
            $dir = $this->base_dir . '/' . $year;
            if (!is_dir($dir)) {
                echo "Directory not found: $dir\n";
                continue;
            }

            echo "Processing Year: $year\n";
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..')
                    continue;
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'jpeg')
                    continue;

                $this->process_file($year, $file);
            }
        }
    }

    private function process_file($year, $filename)
    {
        $name_part = pathinfo($filename, PATHINFO_FILENAME);

        // Remove _win
        $is_winner = false;
        if (substr($name_part, -4) === '_win') {
            $is_winner = true;
            $name_part = substr($name_part, 0, -4);
        }

        // Extract office
        $office_key = '';
        foreach (array_keys($this->office_map) as $k) {
            if (substr($name_part, -strlen($k)) === $k) {
                $office_key = $k;
                break;
            }
        }

        if (!$office_key) {
            echo "Skipping (unknown office): $filename\n";
            return;
        }

        $clean_name_str = substr($name_part, 0, -(strlen($office_key) + 1));
        $parts = explode('_', $clean_name_str);

        $given = '';
        $paternal = '';
        $maternal = '';

        if (count($parts) >= 3) {
            $maternal = array_pop($parts);
            $paternal = array_pop($parts);
            $given = implode(' ', $parts);
        } elseif (count($parts) == 2) {
            $paternal = array_pop($parts);
            $given = implode(' ', $parts);
            // Maternal empty
        } else {
            echo "Skipping invalid name format: $clean_name_str\n";
            return;
        }

        $person_id = $this->find_person($given, $paternal, $maternal);

        if (!$person_id) {
            // Try fuzzy if generic match failed
            echo "No match for person: $given $paternal $maternal ($filename)\n";
            return;
        }

        $office_code = $this->office_map[$office_key];
        $candidacy_id = $this->find_candidacy($person_id, $year, $office_code);

        if ($candidacy_id) {
            $rel_path = 'politician_profile_photos/' . $year . '/' . $filename;
            if ($this->dry_run) {
                echo "[DRY RUN] Would update Candidacy ID $candidacy_id with photo: $rel_path\n";
            } else {
                $this->update_candidacy($candidacy_id, $rel_path);
                echo "Updated Candidacy ID $candidacy_id\n";
            }
        } else {
            echo "No candidacy found for Person ID $person_id in $year as $office_code\n";
        }
    }

    private function find_person($given, $paternal, $maternal)
    {
        // Case 1: All 3 parts
        if ($maternal) {
            $sql = $this->db->prepare("SELECT id FROM {$this->db->prefix}politeia_people WHERE given_names LIKE %s AND paternal_surname LIKE %s AND maternal_surname LIKE %s", $given . '%', $paternal, $maternal);
            $id = $this->db->get_var($sql);
            if ($id)
                return $id;
        } else {
            // Case 2: Only Given + Paternal (ignore maternal in DB or Partial)
            // We look for any person with these names. Risk of duplicates but low.
            $sql = $this->db->prepare("SELECT id FROM {$this->db->prefix}politeia_people WHERE given_names LIKE %s AND paternal_surname LIKE %s", $given . '%', $paternal);
            $ids = $this->db->get_col($sql);
            if (count($ids) == 1) {
                return $ids[0];
            }
            if (count($ids) > 1) {
                echo "Ambiguous match for $given $paternal (Found " . count($ids) . ")\n";
                // Start filtering? return first? return false?
                // For now return first as per user requirement to just work where possible
                return $ids[0];
            }
        }

        return null;
    }

    private function find_candidacy($person_id, $year, $office_code)
    {
        $sql = "
            SELECT c.id 
            FROM {$this->db->prefix}politeia_candidacies c
            JOIN {$this->db->prefix}politeia_elections e ON c.election_id = e.id
            JOIN {$this->db->prefix}politeia_offices o ON e.office_id = o.id
            WHERE c.person_id = %d
            AND YEAR(e.election_date) = %d
            AND o.code = %s
            LIMIT 1
        ";

        return $this->db->get_var($this->db->prepare($sql, $person_id, $year, $office_code));
    }

    private function update_candidacy($cid, $path)
    {
        $this->db->update(
            "{$this->db->prefix}politeia_candidacies",
            ['profile_photo_url' => $path],
            ['id' => $cid]
        );
    }
}

// Execution Block
if (defined('WP_CLI') && WP_CLI) {
    // WP CLI context
    $base_dir = __DIR__ . '/assets/politician_profile_photos';
    $matcher = new PhotoMatcher($base_dir, $wpdb, false); // False = Real run
    $matcher->run();
} elseif (php_sapi_name() === 'cli' || isset($_GET['run_match'])) {
    // Standalone CLI or Web Trigger
    $base_dir = __DIR__ . '/assets/politician_profile_photos';
    // $dry_run = !isset($argv[1]) || $argv[1] !== '--force';
    $dry_run = false; // Force real run for now
    $matcher = new PhotoMatcher($base_dir, $wpdb, $dry_run);
    $matcher->run();
}
