<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * Database schema installer for Politeia Electoral Map.
 *
 * @package Politeia
 */

namespace Politeia\Modules\Database;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles creation and updates of the plugin database tables.
 */
class Installer {

	/**
	 * Install or upgrade database schema using dbDelta.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( self::get_schema_sql( $wpdb ) as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'politeia_electoral_map_db_version', PLEM_DB_VERSION );
	}

	/**
	 * Build schema SQL statements. Uses wp prefix and charset.
	 *
	 * @param wpdb $wpdb WordPress database abstraction object.
	 * @return array
	 */
	public static function get_schema_sql( wpdb $wpdb ): array {
		$collate                  = $wpdb->get_charset_collate();
		$people                   = "{$wpdb->prefix}politeia_people";
		$parties                  = "{$wpdb->prefix}politeia_political_parties";
		$jurisdictions            = "{$wpdb->prefix}politeia_jurisdictions";
		$offices                  = "{$wpdb->prefix}politeia_offices";
		$office_terms             = "{$wpdb->prefix}politeia_office_terms";
		$party_memberships        = "{$wpdb->prefix}politeia_party_memberships";
		$jurisdiction_populations = "{$wpdb->prefix}politeia_jurisdiction_populations";
		$jurisdiction_budgets     = "{$wpdb->prefix}politeia_jurisdiction_budgets";
		$elections                = "{$wpdb->prefix}politeia_elections";
		$candidacies              = "{$wpdb->prefix}politeia_candidacies";

		return array(
			"CREATE TABLE $people (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  given_names VARCHAR(200) NOT NULL,
  paternal_surname VARCHAR(120) NOT NULL,
  maternal_surname VARCHAR(120) NULL,
  birth_date DATE NOT NULL,
  death_date DATE NULL,
  photo_url VARCHAR(400) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_people_name (paternal_surname, maternal_surname, given_names)
) ENGINE=InnoDB $collate;",

			"CREATE TABLE $parties (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  official_name VARCHAR(200) NOT NULL,
  short_name VARCHAR(60) NULL,
  founded_on DATE NULL,
  dissolved_on DATE NULL,
  ideology VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_parties_official_name (official_name),
  KEY idx_parties_short (short_name)
) ENGINE=InnoDB $collate;",

			"CREATE TABLE $jurisdictions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  official_name VARCHAR(200) NOT NULL,
  common_name VARCHAR(200) NULL,
  type VARCHAR(24) NOT NULL,            -- e.g., COUNTRY, REGION, PROVINCE, COMMUNE, etc.
  parent_id BIGINT UNSIGNED NULL,       -- self hierarchy
  external_code VARCHAR(40) NULL,       -- INE/SERVEL
  founded_on DATE NULL,
  dissolved_on DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_juris_parent (parent_id),
  KEY idx_juris_type_name (type, official_name)
) ENGINE=InnoDB $collate;",

			"CREATE TABLE $offices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,          -- Alcalde/a, Concejal/a, Diputado/a, etc.
  requires_scope TINYINT(1) NOT NULL DEFAULT 1,
  allowed_scope VARCHAR(24) NULL,       -- expected jurisdiction type (e.g., COMMUNE)
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_offices_title (title)
) ENGINE=InnoDB $collate;",

			"CREATE TABLE $office_terms (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id BIGINT UNSIGNED NOT NULL,
  office_id BIGINT UNSIGNED NOT NULL,
  jurisdiction_id BIGINT UNSIGNED NULL, -- nullable for nationwide roles
  started_on DATE NOT NULL,
  ended_on DATE NULL,                   -- NULL = current
  planned_end_on DATE NULL,
  status VARCHAR(16) NULL,
  is_acting TINYINT(1) NOT NULL DEFAULT 0,
  source_url VARCHAR(400) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_term_person_office_scope (person_id, office_id, jurisdiction_id, started_on),
  KEY idx_term_scope_office (jurisdiction_id, office_id, started_on),
  KEY idx_term_current (ended_on)
) ENGINE=InnoDB $collate;",

			"CREATE TABLE $party_memberships (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id BIGINT UNSIGNED NOT NULL,
  party_id  BIGINT UNSIGNED NOT NULL,
  started_on DATE NULL,
  ended_on DATE NULL,                   -- NULL = current
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_membership_person (person_id, started_on),
  KEY idx_membership_party (party_id, started_on)
) ENGINE=InnoDB $collate;",

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
  KEY idx_pop_jur (jurisdiction_id)
) ENGINE=InnoDB $collate;",

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

			"CREATE TABLE $elections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  office_id BIGINT UNSIGNED NOT NULL,
  jurisdiction_id BIGINT UNSIGNED NOT NULL,
  election_date DATE NOT NULL,
  name VARCHAR(200) NULL,
  round_number INT NOT NULL DEFAULT 1,
  seats INT NOT NULL DEFAULT 1,
  system VARCHAR(40) NULL,              -- e.g., 'majoritarian', 'dHondt'
  total_registered INT NULL,
  total_votes INT NULL,
  valid_votes INT NULL,
  blank_votes INT NULL,
  null_votes INT NULL,
  source_url VARCHAR(400) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_elec_jur_office_date (jurisdiction_id, office_id, election_date)
) ENGINE=InnoDB $collate;",

			"CREATE TABLE $candidacies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  election_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  party_id BIGINT UNSIGNED NOT NULL,
  alliance VARCHAR(120) NULL,
  list_position INT NULL,
  votes INT NULL,
  vote_share DECIMAL(6,3) NULL,
  elected TINYINT(1) NOT NULL DEFAULT 0,
  rank INT NULL,
  source_url VARCHAR(400) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cand_election_votes (election_id, votes),
  KEY idx_cand_election_elected (election_id, elected)
) ENGINE=InnoDB $collate;",
		);
	}
}
