<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * Database schema installer for Politeia Electoral Map.
 * 
 * Updated to match v6 schema from politeia-db-scrapper ETL pipeline.
 *
 * @package Politeia
 */

namespace Politeia\Modules\Database;

use wpdb;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Handles creation and updates of the plugin database tables.
 */
class Installer
{

  /**
   * Install or upgrade database schema using dbDelta.
   */
  public static function install(): void
  {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    foreach (self::get_schema_sql($wpdb) as $sql) {
      dbDelta($sql);
    }

    update_option('politeia_electoral_map_db_version', PLEM_DB_VERSION);
  }

  /**
   * Build schema SQL statements. Uses wp prefix and charset.
   *
   * @param wpdb $wpdb WordPress database abstraction object.
   * @return array
   */
  public static function get_schema_sql(wpdb $wpdb): array
  {
    $collate = $wpdb->get_charset_collate();

    // Table names
    $people = "{$wpdb->prefix}politeia_people";
    $political_organizations = "{$wpdb->prefix}politeia_political_organizations";
    $jurisdictions = "{$wpdb->prefix}politeia_jurisdictions";
    $offices = "{$wpdb->prefix}politeia_offices";
    $office_terms = "{$wpdb->prefix}politeia_office_terms";
    $party_memberships = "{$wpdb->prefix}politeia_party_memberships";
    $jurisdiction_populations = "{$wpdb->prefix}politeia_jurisdiction_populations";
    $jurisdiction_budgets = "{$wpdb->prefix}politeia_jurisdiction_budgets";
    $elections = "{$wpdb->prefix}politeia_elections";
    $election_coalitions = "{$wpdb->prefix}politeia_election_coalitions";
    $coalition_members = "{$wpdb->prefix}politeia_election_coalition_members";
    $lista_assignments = "{$wpdb->prefix}politeia_election_lista_assignments";
    $election_results = "{$wpdb->prefix}politeia_election_results";
    $candidacies = "{$wpdb->prefix}politeia_candidacies";
    $party_leanings = "{$wpdb->prefix}politeia_party_leanings";

    return array(
      // ============================================================
      // PEOPLE
      // ============================================================
      "CREATE TABLE $people (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  given_names VARCHAR(120) NULL,
  paternal_surname VARCHAR(120) NULL,
  maternal_surname VARCHAR(120) NULL,
  birth_date DATE NULL,
  death_date DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_people_paternal (paternal_surname),
  KEY idx_people_given (given_names)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // POLITICAL ORGANIZATIONS (renamed from political_parties)
      // ============================================================
      "CREATE TABLE $political_organizations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  official_name VARCHAR(200) NOT NULL,
  short_name VARCHAR(60) NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'PARTY',
  founded_on DATE NULL,
  dissolved_on DATE NULL,
  ideology VARCHAR(120) NULL,
  color_hex VARCHAR(7) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_orgs_official (official_name),
  KEY idx_orgs_short (short_name)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // JURISDICTIONS
      // ============================================================
      "CREATE TABLE $jurisdictions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  official_name VARCHAR(200) NOT NULL,
  common_name VARCHAR(200) NULL,
  type VARCHAR(24) NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  external_code VARCHAR(20) NULL,
  address VARCHAR(255) NULL,
  latitude DECIMAL(10,8) NULL,
  longitude DECIMAL(11,8) NULL,
  geometry_json TEXT NULL,
  founded_on DATE NULL,
  dissolved_on DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_juris_external (external_code),
  KEY idx_juris_parent (parent_id),
  KEY idx_juris_type (type),
  KEY idx_juris_common (common_name)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // OFFICES
      // ============================================================
      "CREATE TABLE $offices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(30) NULL,
  title VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_offices_code (code),
  KEY idx_offices_title (title)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // OFFICE TERMS
      // ============================================================
      "CREATE TABLE $office_terms (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id BIGINT UNSIGNED NOT NULL,
  office_id BIGINT UNSIGNED NOT NULL,
  jurisdiction_id BIGINT UNSIGNED NULL,
  started_on DATE NOT NULL,
  ended_on DATE NULL,
  planned_end_on DATE NULL,
  status VARCHAR(16) NULL,
  is_acting TINYINT(1) NOT NULL DEFAULT 0,
  source_url VARCHAR(400) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_term_person_office_jur (person_id, office_id, jurisdiction_id, started_on),
  KEY idx_term_jur_office (jurisdiction_id, office_id, started_on),
  KEY idx_term_current (ended_on)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // PARTY MEMBERSHIPS
      // ============================================================
      "CREATE TABLE $party_memberships (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NOT NULL,
  started_on DATE NOT NULL,
  ended_on DATE NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_membership_person (person_id, started_on),
  KEY idx_membership_org (organization_id)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // JURISDICTION POPULATIONS
      // ============================================================
      "CREATE TABLE $jurisdiction_populations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  jurisdiction_id BIGINT UNSIGNED NOT NULL,
  year INT NOT NULL,
  population INT NOT NULL,
  method VARCHAR(16) NULL,
  source VARCHAR(255) NULL,
  source_url VARCHAR(400) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_pop_jur_year (jurisdiction_id, year),
  KEY idx_pop_jur (jurisdiction_id),
  KEY idx_pop_year (year)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // JURISDICTION BUDGETS
      // ============================================================
      "CREATE TABLE $jurisdiction_budgets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  jurisdiction_id BIGINT UNSIGNED NOT NULL,
  fiscal_year INT NOT NULL,
  amount_total DECIMAL(18,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'CLP',
  source VARCHAR(255) NULL,
  source_url VARCHAR(400) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_budget_jur_year (jurisdiction_id, fiscal_year),
  KEY idx_budget_jur (jurisdiction_id)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // ELECTIONS
      // ============================================================
      "CREATE TABLE $elections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NULL,
  office_id BIGINT UNSIGNED NOT NULL,
  jurisdiction_id BIGINT UNSIGNED NULL,
  election_date DATE NOT NULL,
  title VARCHAR(255) NULL,
  name VARCHAR(255) NULL,
  round_number INT NOT NULL DEFAULT 1,
  seats INT NULL,
  rounds INT NULL,
  voting_system VARCHAR(100) NULL,
  electoral_system VARCHAR(100) NULL,
  source_url VARCHAR(400) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_elec_office_jur_date (office_id, jurisdiction_id, election_date)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // ELECTION COALITIONS (PACTO/SUBPACTO)
      // ============================================================
      "CREATE TABLE $election_coalitions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  election_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  type VARCHAR(30) NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_coalition_election_name_type (election_id, name, type),
  KEY idx_coalition_election (election_id),
  KEY idx_coalition_type (type),
  KEY idx_coalition_parent (parent_id)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // ELECTION COALITION MEMBERS
      // ============================================================
      "CREATE TABLE $coalition_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  coalition_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_coalition_org (coalition_id, organization_id),
  KEY idx_coalition_member_org (organization_id)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // ELECTION LISTA ASSIGNMENTS
      // ============================================================
      "CREATE TABLE $lista_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  election_id BIGINT UNSIGNED NOT NULL,
  coalition_id BIGINT UNSIGNED NOT NULL,
  lista_code VARCHAR(10) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_election_coalition (election_id, coalition_id),
  KEY idx_lista_election_code (election_id, lista_code)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // ELECTION RESULTS
      // ============================================================
      "CREATE TABLE $election_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  election_id BIGINT UNSIGNED NOT NULL,
  jurisdiction_id BIGINT UNSIGNED NOT NULL,
  total_registered INT NULL,
  total_votes INT NULL,
  valid_votes INT NULL,
  blank_votes INT NULL,
  null_votes INT NULL,
  participation_rate DECIMAL(6,3) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_election_jurisdiction (election_id, jurisdiction_id),
  KEY idx_results_election (election_id)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // CANDIDACIES
      // ============================================================
      "CREATE TABLE $candidacies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  election_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  pacto_id BIGINT UNSIGNED NULL,
  subpacto_id BIGINT UNSIGNED NULL,
  party_id BIGINT UNSIGNED NULL,
  sponsoring_party_id BIGINT UNSIGNED NULL,
  affiliation_status VARCHAR(30) NULL,
  jurisdiction_id BIGINT UNSIGNED NULL,
  candidate_number INT NULL,
  list_position INT NULL,
  votes INT NULL,
  vote_share DECIMAL(6,3) NULL,
  elected TINYINT(1) NOT NULL DEFAULT 0,
  result_rank INT NULL,
  source_url VARCHAR(400) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_cand_election_person_jur (election_id, person_id, jurisdiction_id),
  KEY idx_cand_election_votes (election_id, votes),
  KEY idx_cand_pacto (pacto_id),
  KEY idx_cand_subpacto (subpacto_id),
  KEY idx_cand_party (party_id),
  KEY idx_cand_sponsoring (sponsoring_party_id),
  KEY idx_cand_affiliation (affiliation_status)
) ENGINE=InnoDB $collate;",

      // ============================================================
      // PARTY LEANINGS
      // ============================================================
      "CREATE TABLE $party_leanings (
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
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_person_org_election (person_id, organization_id, election_id),
  KEY idx_leaning_org (organization_id)
) ENGINE=InnoDB $collate;",
    );
  }
}
