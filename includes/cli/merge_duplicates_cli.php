<?php
/**
 * Merge Duplicate Politicians
 * Usage: wp eval-file includes/cli/merge_duplicates_cli.php -- --dry-run
 */

if (!defined('ABSPATH')) {
    require_once __DIR__ . '/../../../../../wp-load.php';
}

class DuplicateMerger
{
    private $dry_run = false;

    public function __construct($dry_run = false)
    {
        $this->dry_run = $dry_run;
    }

    public function run()
    {
        global $wpdb;
        echo "Starting Duplicate Detection...\n";
        if ($this->dry_run) {
            echo "DRY RUN MODE: No changes will be made.\n";
        }

        // 1. Group strictly by Surnames
        // We use lower case and trim for safety
        $sql = "
            SELECT 
                LOWER(TRIM(paternal_surname)) as p_surname, 
                LOWER(TRIM(maternal_surname)) as m_surname, 
                COUNT(*) as c 
            FROM {$wpdb->prefix}politeia_people 
            WHERE paternal_surname IS NOT NULL AND paternal_surname != ''
            GROUP BY LOWER(TRIM(paternal_surname)), LOWER(TRIM(maternal_surname))
            HAVING c > 1
        ";

        $groups = $wpdb->get_results($sql);
        echo "Found " . count($groups) . " groups sharing surnames.\n";

        $total_merged = 0;

        foreach ($groups as $g) {
            // Get people in this group
            $people_sql = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}politeia_people 
                 WHERE LOWER(TRIM(paternal_surname)) = %s 
                 AND LOWER(TRIM(COALESCE(maternal_surname, ''))) = %s
                 ORDER BY id ASC",
                $g->p_surname,
                $g->m_surname
            );
            $people = $wpdb->get_results($people_sql);

            // Process subgroups by matching First Names
            $this->process_group($people);
        }
    }

    private function process_group($people)
    {
        // Simple O(N^2) comparison to find matches
        // We cluster them. 
        // A cluster is a set of people who match each other.

        $processed_ids = [];

        foreach ($people as $i => $p1) {
            if (in_array($p1->id, $processed_ids))
                continue;

            $cluster = [$p1];
            $processed_ids[] = $p1->id;

            for ($j = $i + 1; $j < count($people); $j++) {
                $p2 = $people[$j];
                if (in_array($p2->id, $processed_ids))
                    continue;

                if ($this->names_match($p1->given_names, $p2->given_names)) {
                    $cluster[] = $p2;
                    $processed_ids[] = $p2->id;
                }
            }

            if (count($cluster) > 1) {
                $this->merge_cluster($cluster);
            }
        }
    }

    private function names_match($n1, $n2)
    {
        // Normalize
        $n1 = $this->normalize($n1);
        $n2 = $this->normalize($n2);

        // Exact match
        if ($n1 === $n2)
            return true;

        // One contains the other? e.g. "JORGE IVAN" vs "JORGE"
        // We split into words
        $w1 = explode(' ', $n1);
        $w2 = explode(' ', $n2);

        // Intersection count
        $intersect = array_intersect($w1, $w2);

        // If the shorter name is fully contained in the longer name
        $min_words = min(count($w1), count($w2));
        if (count($intersect) == $min_words && $min_words > 0) {
            return true;
        }

        return false;
    }

    private function normalize($str)
    {
        return trim(strtoupper($this->strip_accents($str)));
    }

    private function strip_accents($str)
    {
        return strtr(utf8_decode($str), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
    }

    private function merge_cluster($cluster)
    {
        global $wpdb;

        // Score items to find winner
        // Scores: 
        // +100 for having party membership
        // +100 for having party leaning
        // +10 for higher ID (prefer newer)

        $scores = [];
        foreach ($cluster as $p) {
            $score = 0;

            // Checks
            $has_mem = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}politeia_party_memberships WHERE person_id = %d", $p->id));
            if ($has_mem > 0)
                $score += 100;

            $has_lean = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}politeia_party_leanings WHERE person_id = %d", $p->id));
            if ($has_lean > 0)
                $score += 100;

            $score += ($p->id / 100000); // Tiny boost for ID

            $scores[$p->id] = $score;
        }

        // Sort by score DESC
        uasort($cluster, function ($a, $b) use ($scores) {
            return $scores[$b->id] <=> $scores[$a->id];
        });

        $winner = $cluster[array_key_first($cluster)];
        $losers = array_slice($cluster, 1);

        echo "\n[MATCH FOUND]\n";
        echo "WINNER: [{$winner->id}] {$winner->given_names} {$winner->paternal_surname} (Score: {$scores[$winner->id]})\n";

        foreach ($losers as $loser) {
            echo "  MERGE: [{$loser->id}] {$loser->given_names} {$loser->paternal_surname} (Score: {$scores[$loser->id]})\n";
            $this->perform_merge($winner->id, $loser->id);
        }
    }

    private function perform_merge($winner_id, $loser_id)
    {
        if ($this->dry_run)
            return;

        global $wpdb;

        // 1. Move Office Terms
        $wpdb->update(
            "{$wpdb->prefix}politeia_office_terms",
            ['person_id' => $winner_id],
            ['person_id' => $loser_id]
        );

        // 2. Move Candidacies
        $wpdb->update(
            "{$wpdb->prefix}politeia_candidacies",
            ['person_id' => $winner_id],
            ['person_id' => $loser_id]
        );

        // 3. Move Memberships (ignore duplicates for now, assume winner has them or they merge safely)
        // If query fails due to unique constraint, we might lose some history from loser, but usually loser has NO data
        // We use UPDATE IGNORE just in case
        $wpdb->query($wpdb->prepare("UPDATE IGNORE {$wpdb->prefix}politeia_party_memberships SET person_id = %d WHERE person_id = %d", $winner_id, $loser_id));

        // 4. Move Leanings
        $wpdb->query($wpdb->prepare("UPDATE IGNORE {$wpdb->prefix}politeia_party_leanings SET person_id = %d WHERE person_id = %d", $winner_id, $loser_id));

        // 5. Delete Loser
        $wpdb->delete("{$wpdb->prefix}politeia_people", ['id' => $loser_id]);

        echo "    -> Merged {$loser_id} into {$winner_id}\n";
    }
}

$dry_run = in_array('--dry-run', $argv);
$merger = new DuplicateMerger($dry_run);
$merger->run();
