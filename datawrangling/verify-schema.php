<?php
/**
 * Schema Verification Script
 * 
 * Verifies that all v6 schema tables and columns exist in the database.
 * Run with: wp eval-file verify-schema.php
 * 
 * @package Politeia
 */

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__) . '/../../../wp-load.php';
}

global $wpdb;

// Expected tables and their critical columns
$expected_schema = array(
    'politeia_people' => array('id', 'given_names', 'paternal_surname', 'maternal_surname'),
    'politeia_political_organizations' => array('id', 'official_name', 'type', 'color_hex'),
    'politeia_jurisdictions' => array('id', 'official_name', 'type', 'parent_id', 'latitude', 'longitude', 'geometry_json'),
    'politeia_offices' => array('id', 'code', 'title'),
    'politeia_office_terms' => array('id', 'person_id', 'office_id', 'jurisdiction_id'),
    'politeia_party_memberships' => array('id', 'person_id', 'organization_id'),
    'politeia_jurisdiction_populations' => array('id', 'jurisdiction_id', 'year', 'population'),
    'politeia_jurisdiction_budgets' => array('id', 'jurisdiction_id', 'fiscal_year', 'amount_total'),
    'politeia_elections' => array('id', 'office_id', 'election_date', 'title', 'voting_system', 'electoral_system'),
    'politeia_election_coalitions' => array('id', 'election_id', 'name', 'type', 'parent_id'),
    'politeia_election_coalition_members' => array('id', 'coalition_id', 'organization_id'),
    'politeia_election_lista_assignments' => array('id', 'election_id', 'coalition_id', 'lista_code'),
    'politeia_election_results' => array('id', 'election_id', 'jurisdiction_id', 'participation_rate'),
    'politeia_candidacies' => array('id', 'election_id', 'person_id', 'pacto_id', 'subpacto_id', 'party_id', 'sponsoring_party_id', 'affiliation_status', 'jurisdiction_id'),
    'politeia_party_leanings' => array('id', 'person_id', 'organization_id', 'election_id'),
);

$errors = array();
$warnings = array();
$success_count = 0;

echo "\n";
echo "========================================\n";
echo "POLITEIA SCHEMA VERIFICATION\n";
echo "========================================\n\n";

foreach ($expected_schema as $table_name => $columns) {
    $full_table_name = $wpdb->prefix . $table_name;

    // Check if table exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($full_table_name)));

    if (!$table_exists) {
        $errors[] = "❌ Table missing: {$full_table_name}";
        continue;
    }

    // Check columns
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM {$full_table_name}");

    $missing_columns = array();
    foreach ($columns as $column) {
        if (!in_array($column, $existing_columns, true)) {
            $missing_columns[] = $column;
        }
    }

    if (!empty($missing_columns)) {
        $warnings[] = "⚠️  Table {$table_name}: Missing columns: " . implode(', ', $missing_columns);
    } else {
        echo "✅ {$table_name} (" . count($columns) . " columns verified)\n";
        $success_count++;
    }
}

echo "\n";
echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "✅ Tables verified: {$success_count}/" . count($expected_schema) . "\n";

if (!empty($errors)) {
    echo "\n❌ ERRORS:\n";
    foreach ($errors as $error) {
        echo "  {$error}\n";
    }
}

if (!empty($warnings)) {
    echo "\n⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  {$warning}\n";
    }
}

if (empty($errors) && empty($warnings)) {
    echo "\n🎉 All tables and columns verified successfully!\n";
    echo "Schema is ready for ETL pipeline.\n";
}

echo "\n";

// Additional checks
echo "========================================\n";
echo "ADDITIONAL CHECKS\n";
echo "========================================\n";

// Check for old political_parties table
$old_table = $wpdb->prefix . 'politeia_political_parties';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$old_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($old_table)));

if ($old_exists) {
    echo "⚠️  Old table still exists: {$old_table}\n";
    echo "   This should have been renamed to political_organizations\n";
} else {
    echo "✅ Old political_parties table properly renamed\n";
}

// Check record counts
echo "\nRecord counts:\n";
foreach (array_keys($expected_schema) as $table_name) {
    $full_table_name = $wpdb->prefix . $table_name;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($full_table_name)));

    if ($table_exists) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$full_table_name}");
        echo "  {$table_name}: {$count} records\n";
    }
}

echo "\n";
