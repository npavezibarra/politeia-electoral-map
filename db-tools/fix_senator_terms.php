<?php
/**
 * Fix Senator Terms - Set ended_on for expired terms
 */

require_once('../../../../wp-load.php');
global $wpdb;

echo "=== FIXING EXPIRED SENATOR TERMS ===\n\n";

// First, show what will be updated
$preview = $wpdb->get_results("
    SELECT 
        t.id as term_id,
        p.given_names,
        p.paternal_surname,
        t.started_on,
        t.ended_on,
        t.planned_end_on,
        o.code as office_code
    FROM wp_politeia_office_terms t
    JOIN wp_politeia_people p ON p.id = t.person_id
    JOIN wp_politeia_offices o ON o.id = t.office_id
    WHERE t.ended_on IS NULL 
      AND t.planned_end_on IS NOT NULL 
      AND t.planned_end_on < CURDATE()
    ORDER BY t.planned_end_on DESC
");

echo "Terms to be updated: " . count($preview) . "\n\n";

foreach ($preview as $t) {
    echo "   Term ID: {$t->term_id} | {$t->given_names} {$t->paternal_surname} | {$t->office_code}\n";
    echo "      Started: {$t->started_on} | Planned End: {$t->planned_end_on} | Will set ended_on = {$t->planned_end_on}\n";
}

echo "\n--- EXECUTING UPDATE ---\n";

$result = $wpdb->query("
    UPDATE wp_politeia_office_terms 
    SET ended_on = planned_end_on 
    WHERE ended_on IS NULL 
      AND planned_end_on IS NOT NULL 
      AND planned_end_on < CURDATE()
");

echo "\nRows updated: $result\n";

echo "\n=== VERIFICATION ===\n";

$still_open = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM wp_politeia_office_terms 
    WHERE ended_on IS NULL 
      AND planned_end_on IS NOT NULL 
      AND planned_end_on < CURDATE()
");

echo "Remaining expired terms with NULL ended_on: $still_open\n";

if ($still_open == 0) {
    echo "\n✅ SUCCESS! All expired terms have been closed.\n";
} else {
    echo "\n⚠️ WARNING: Some terms were not updated.\n";
}
