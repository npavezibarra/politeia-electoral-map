<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

/**
 * Identify Winners for ALCALDE and GOBERNADOR
 * Logic: One winner per jurisdiction (majority)
 */

$offices_to_fix = ['ALCALDE', 'GOBERNADOR', 'PRESIDENTE'];

foreach ($offices_to_fix as $office_code) {
    echo "--- PROCESSING $office_code ---\n";

    // Get all elections for this office
    $elections = $wpdb->get_results($wpdb->prepare("
        SELECT e.id, e.election_date 
        FROM {$wpdb->prefix}politeia_elections e
        JOIN {$wpdb->prefix}politeia_offices o ON o.id = e.office_id
        WHERE o.code = %s", $office_code));

    foreach ($elections as $e) {
        echo "Election ID: {$e->id} ({$e->election_date})\n";

        // Find distinct jurisdictions in this election
        $jurisdictions = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT jurisdiction_id FROM {$wpdb->prefix}politeia_candidacies WHERE election_id = %d",
            $e->id
        ));

        $fixed_count = 0;
        foreach ($jurisdictions as $jur_id) {
            // Find the winner (highest votes)
            $winner = $wpdb->get_row($wpdb->prepare("
                SELECT id, votes 
                FROM {$wpdb->prefix}politeia_candidacies 
                WHERE election_id = %d AND jurisdiction_id = %d
                ORDER BY votes DESC LIMIT 1
            ", $e->id, $jur_id));

            if ($winner && $winner->votes > 0) {
                // Clear any existing elected flag for this election/jurisdiction first to be safe
                $wpdb->update(
                    "{$wpdb->prefix}politeia_candidacies",
                    ['elected' => 0],
                    ['election_id' => $e->id, 'jurisdiction_id' => $jur_id]
                );

                // Set the winner
                $wpdb->update(
                    "{$wpdb->prefix}politeia_candidacies",
                    ['elected' => 1],
                    ['id' => $winner->id]
                );
                $fixed_count++;
            }
        }
        echo "  - Fixed $fixed_count winners for this election.\n";
    }
}

echo "\n✅ Restoration Complete.\n";
