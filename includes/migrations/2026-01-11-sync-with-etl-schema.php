<?php
/**
 * Migration: Sync schema with ETL v6 (politeia-db-scrapper)
 * Date: 2026-01-11
 * 
 * This migration updates the plugin schema to match the comprehensive v6 schema
 * developed for the ETL pipeline. Changes include:
 * 
 * 1. Rename political_parties → political_organizations
 * 2. Add 6 new tables (coalitions, coalition_members, lista_assignments, etc.)
 * 3. Update existing tables with new fields
 * 4. Add proper indexes and constraints
 */

if (!defined('ABSPATH'))
    exit;

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();

// Table names
$political_organizations = $wpdb->prefix . 'politeia_political_organizations';
$political_parties = $wpdb->prefix . 'politeia_political_parties';
$election_coalitions = $wpdb->prefix . 'politeia_election_coalitions';
$coalition_members = $wpdb->prefix . 'politeia_election_coalition_members';
$lista_assignments = $wpdb->prefix . 'politeia_election_lista_assignments';
$election_results = $wpdb->prefix . 'politeia_election_results';
$party_leanings = $wpdb->prefix . 'politeia_party_leanings';
$jurisdictions = $wpdb->prefix . 'politeia_jurisdictions';
$offices = $wpdb->prefix . 'politeia_offices';
$elections = $wpdb->prefix . 'politeia_elections';
$candidacies = $wpdb->prefix . 'politeia_candidacies';

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// ============================================================
// STEP 1: Rename political_parties to political_organizations
// ============================================================

// Check if old table exists and new one doesn't
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$old_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($political_parties)));
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$new_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($political_organizations)));

if ($old_exists && !$new_exists) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("RENAME TABLE {$political_parties} TO {$political_organizations}");
}

// ============================================================
// STEP 2: Update political_organizations table
// ============================================================

// Check if table exists before trying to alter it
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($political_organizations)));

if ($table_exists) {
    // Add type column if it doesn't exist
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $type_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW COLUMNS FROM {$political_organizations} LIKE %s",
        'type'
    ));

    if (!$type_exists) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query("ALTER TABLE {$political_organizations} ADD COLUMN type VARCHAR(20) DEFAULT 'PARTY' AFTER short_name");
    }

    // Add color_hex column if it doesn't exist
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $color_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW COLUMNS FROM {$political_organizations} LIKE %s",
        'color_hex'
    ));

    if (!$color_exists) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query("ALTER TABLE {$political_organizations} ADD COLUMN color_hex VARCHAR(7) NULL AFTER ideology");
    }
}

// ============================================================
// STEP 3: Create election_coalitions table
// ============================================================

$sql_coalitions = "CREATE TABLE IF NOT EXISTS {$election_coalitions} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  election_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  type VARCHAR(30) NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_coalition_election_name_type (election_id, name, type),
  KEY idx_coalition_election (election_id),
  KEY idx_coalition_type (type),
  KEY idx_coalition_parent (parent_id)
) {$charset_collate};";

dbDelta($sql_coalitions);

// ============================================================
// STEP 4: Create election_coalition_members table
// ============================================================

$sql_coalition_members = "CREATE TABLE IF NOT EXISTS {$coalition_members} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  coalition_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_coalition_org (coalition_id, organization_id),
  KEY idx_coalition_member_org (organization_id)
) {$charset_collate};";

dbDelta($sql_coalition_members);

// ============================================================
// STEP 5: Create election_lista_assignments table
// ============================================================

$sql_lista = "CREATE TABLE IF NOT EXISTS {$lista_assignments} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  election_id BIGINT UNSIGNED NOT NULL,
  coalition_id BIGINT UNSIGNED NOT NULL,
  lista_code VARCHAR(10) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_election_coalition (election_id, coalition_id),
  KEY idx_lista_election_code (election_id, lista_code)
) {$charset_collate};";

dbDelta($sql_lista);

// ============================================================
// STEP 6: Create election_results table
// ============================================================

$sql_results = "CREATE TABLE IF NOT EXISTS {$election_results} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  election_id BIGINT UNSIGNED NOT NULL,
  jurisdiction_id BIGINT UNSIGNED NOT NULL,
  total_registered INT NULL,
  total_votes INT NULL,
  valid_votes INT NULL,
  blank_votes INT NULL,
  null_votes INT NULL,
  participation_rate DECIMAL(6,3) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_election_jurisdiction (election_id, jurisdiction_id),
  KEY idx_results_election (election_id)
) {$charset_collate};";

dbDelta($sql_results);

// ============================================================
// STEP 7: Create party_leanings table
// ============================================================

$sql_leanings = "CREATE TABLE IF NOT EXISTS {$party_leanings} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NOT NULL,
  election_id BIGINT UNSIGNED NULL,
  started_on DATE NULL,
  ended_on DATE NULL,
  type VARCHAR(40) NULL,
  notes VARCHAR(255) NULL,
  source VARCHAR(255) NULL,
  source_url VARCHAR(400) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_person_org_election (person_id, organization_id, election_id),
  KEY idx_leaning_org (organization_id)
) {$charset_collate};";

dbDelta($sql_leanings);

// ============================================================
// STEP 8: Update jurisdictions table - add geospatial fields
// ============================================================

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$address_exists = $wpdb->get_var($wpdb->prepare(
    "SHOW COLUMNS FROM {$jurisdictions} LIKE %s",
    'address'
));

if (!$address_exists) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query(
        "ALTER TABLE {$jurisdictions} 
        ADD COLUMN address VARCHAR(255) NULL AFTER external_code,
        ADD COLUMN latitude DECIMAL(10,8) NULL AFTER address,
        ADD COLUMN longitude DECIMAL(11,8) NULL AFTER latitude,
        ADD COLUMN geometry_json TEXT NULL AFTER longitude"
    );
}

// ============================================================
// STEP 9: Update offices table - add code field
// ============================================================

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$code_exists = $wpdb->get_var($wpdb->prepare(
    "SHOW COLUMNS FROM {$offices} LIKE %s",
    'code'
));

if (!$code_exists) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("ALTER TABLE {$offices} ADD COLUMN code VARCHAR(30) NULL AFTER id");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("ALTER TABLE {$offices} ADD UNIQUE KEY ux_offices_code (code)");
}

// ============================================================
// STEP 10: Update elections table - add new fields
// ============================================================

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$title_exists = $wpdb->get_var($wpdb->prepare(
    "SHOW COLUMNS FROM {$elections} LIKE %s",
    'title'
));

if (!$title_exists) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query(
        "ALTER TABLE {$elections} 
        ADD COLUMN title VARCHAR(255) NULL AFTER election_date,
        ADD COLUMN rounds INT NULL AFTER seats,
        ADD COLUMN voting_system VARCHAR(100) NULL AFTER rounds,
        ADD COLUMN electoral_system VARCHAR(100) NULL AFTER voting_system"
    );
}

// ============================================================
// STEP 11: Update candidacies table - add coalition fields
// ============================================================

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$pacto_exists = $wpdb->get_var($wpdb->prepare(
    "SHOW COLUMNS FROM {$candidacies} LIKE %s",
    'pacto_id'
));

if (!$pacto_exists) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query(
        "ALTER TABLE {$candidacies} 
        ADD COLUMN pacto_id BIGINT UNSIGNED NULL AFTER person_id,
        ADD COLUMN subpacto_id BIGINT UNSIGNED NULL AFTER pacto_id,
        ADD COLUMN sponsoring_party_id BIGINT UNSIGNED NULL AFTER party_id,
        ADD COLUMN affiliation_status VARCHAR(30) NULL AFTER sponsoring_party_id,
        ADD COLUMN jurisdiction_id BIGINT UNSIGNED NULL AFTER affiliation_status,
        ADD COLUMN candidate_number INT NULL AFTER jurisdiction_id,
        ADD COLUMN result_rank INT NULL AFTER elected"
    );

    // Add indexes
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query(
        "ALTER TABLE {$candidacies} 
        ADD KEY idx_cand_pacto (pacto_id),
        ADD KEY idx_cand_subpacto (subpacto_id),
        ADD KEY idx_cand_sponsoring (sponsoring_party_id),
        ADD KEY idx_cand_affiliation (affiliation_status),
        ADD KEY idx_cand_jurisdiction (jurisdiction_id)"
    );
}
