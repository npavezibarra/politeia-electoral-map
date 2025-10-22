<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * Runs database schema upgrades when version changes.
 *
 * @package Politeia
 */

namespace Politeia\Core;

use Politeia\Modules\Database\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database upgrades.
 */
class Upgrader {
        /**
         * Ensure required tables exist and upgrades run when needed.
         */
        public static function maybe_upgrade(): void {
                global $wpdb;

                $plugin_version = get_option( 'plem_version', '0.0.0' );

                if ( version_compare( $plugin_version, '0.2.5', '<' ) ) {
                        $table = $wpdb->prefix . 'politeia_elections';
                        $like  = $wpdb->esc_like( $table );
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
                        if ( $exists === $table ) {
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                $column = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'event_id' ) );
                                if ( null === $column ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                        $wpdb->query( "ALTER TABLE $table ADD COLUMN event_id BIGINT UNSIGNED NULL AFTER id" );
                                }
                        }
                }

                if ( version_compare( $plugin_version, '0.2.7', '<' ) ) {
                        $table = $wpdb->prefix . 'politeia_elections';
                        $like  = $wpdb->esc_like( $table );
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
                        if ( $exists === $table ) {
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                $columns_list = $wpdb->get_results( 'SHOW COLUMNS FROM ' . $table, ARRAY_A );
                                $columns      = array();

                                if ( is_array( $columns_list ) ) {
                                        foreach ( $columns_list as $column ) {
                                                if ( isset( $column['Field'] ) ) {
                                                        $columns[ $column['Field'] ] = $column;
                                                }
                                        }
                                }

                                $backup_table = $wpdb->prefix . 'politeia_elections_backup_20251019';
                                $backup_like  = $wpdb->esc_like( $backup_table );
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                $backup_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $backup_like ) );
                                if ( $backup_exists !== $backup_table ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "CREATE TABLE $backup_table AS SELECT * FROM $table" );
                                }

                                if ( isset( $columns['title'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "UPDATE $table SET name = title WHERE title IS NOT NULL AND title != '' AND (name IS NULL OR name = '')" );
                                }

                                if ( ! isset( $columns['name'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table ADD COLUMN name VARCHAR(255) NULL AFTER election_date" );
                                }

                                if ( isset( $columns['title'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table DROP COLUMN title" );
                                        unset( $columns['title'] );
                                }

                                if ( isset( $columns['jurisdiction_id'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table DROP COLUMN jurisdiction_id" );
                                }

                                if ( ! isset( $columns['event_id'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table ADD COLUMN event_id BIGINT UNSIGNED NULL AFTER id" );
                                } else {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table MODIFY COLUMN event_id BIGINT UNSIGNED NULL" );
                                }

                                if ( isset( $columns['voting_system'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table MODIFY COLUMN voting_system VARCHAR(50) NULL" );
                                }

                                if ( isset( $columns['source_url'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table MODIFY COLUMN source_url VARCHAR(500) NULL" );
                                }

                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                $indexes = $wpdb->get_results( 'SHOW INDEX FROM ' . $table, ARRAY_A );
                                $has_unique = false;
                                $has_legacy = false;
                                $has_event  = false;
                                $has_old_ix = false;

                                if ( is_array( $indexes ) ) {
                                        foreach ( $indexes as $index ) {
                                                if ( isset( $index['Key_name'] ) ) {
                                                        if ( 'uq_elec_office_date_round' === $index['Key_name'] ) {
                                                                $has_unique = true;
                                                        }
                                                        if ( 'uq_elec_office_scope_date' === $index['Key_name'] ) {
                                                                $has_legacy = true;
                                                        }
                                                        if ( 'idx_elec_jur_office_date' === $index['Key_name'] ) {
                                                                $has_old_ix = true;
                                                        }
                                                        if ( 'idx_elections_event' === $index['Key_name'] ) {
                                                                $has_event = true;
                                                        }
                                                }
                                        }
                                }

                                if ( $has_legacy ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table DROP INDEX uq_elec_office_scope_date" );
                                }

                                if ( $has_old_ix ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table DROP INDEX idx_elec_jur_office_date" );
                                }

                                if ( ! $has_event ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table ADD KEY idx_elections_event (event_id)" );
                                }

                                if ( ! $has_unique ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table ADD UNIQUE KEY uq_elec_office_date_round (office_id, election_date, round_number)" );
                                }

                                $schema = DB_NAME;
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                $constraints = $wpdb->get_col( $wpdb->prepare( 'SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = %s AND TABLE_NAME = %s', $schema, $table ) );

                                if ( ! is_array( $constraints ) ) {
                                        $constraints = array();
                                }

                                if ( in_array( 'fk_elections_event', $constraints, true ) ) {
                                        // Drop to ensure latest definition is applied.
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table DROP FOREIGN KEY fk_elections_event" );
                                }

                                $events_table = $wpdb->prefix . 'politeia_events';
                                $events_like  = $wpdb->esc_like( $events_table );
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                $events_exist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_like ) );

                                if ( $events_exist !== $events_table ) {
                                        Installer::install();
                                }

                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                $wpdb->query( "ALTER TABLE $table ADD CONSTRAINT fk_elections_event FOREIGN KEY (event_id) REFERENCES $events_table (id) ON DELETE SET NULL" );
                        }

                        $results_table = $wpdb->prefix . 'politeia_election_results';
                        $results_like  = $wpdb->esc_like( $results_table );
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $results_exist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $results_like ) );
                        if ( $results_exist === $results_table ) {
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                $result_columns_list = $wpdb->get_results( 'SHOW COLUMNS FROM ' . $results_table, ARRAY_A );
                                $result_columns      = array();

                                if ( is_array( $result_columns_list ) ) {
                                        foreach ( $result_columns_list as $column ) {
                                                if ( isset( $column['Field'] ) ) {
                                                        $result_columns[ $column['Field'] ] = $column;
                                                }
                                        }
                                }

                                $legacy_columns = array(
                                        'total_registered',
                                        'total_votes',
                                        'valid_votes',
                                        'blank_votes',
                                        'null_votes',
                                        'participation_rate',
                                );

                                foreach ( $legacy_columns as $legacy ) {
                                        if ( isset( $result_columns[ $legacy ] ) ) {
                                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                                $wpdb->query( "ALTER TABLE $results_table DROP COLUMN $legacy" );
                                        }
                                }

                                if ( ! isset( $result_columns['candidate_id'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $results_table ADD COLUMN candidate_id BIGINT UNSIGNED NULL AFTER jurisdiction_id" );
                                }

                                if ( ! isset( $result_columns['party_id'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $results_table ADD COLUMN party_id BIGINT UNSIGNED NULL AFTER candidate_id" );
                                }

                                if ( isset( $result_columns['votes'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $results_table MODIFY COLUMN votes INT NOT NULL DEFAULT 0" );
                                } else {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $results_table ADD COLUMN votes INT NOT NULL DEFAULT 0 AFTER party_id" );
                                }

                                if ( isset( $result_columns['percentage'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $results_table MODIFY COLUMN percentage DECIMAL(5,2) NULL" );
                                } else {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $results_table ADD COLUMN percentage DECIMAL(5,2) NULL AFTER votes" );
                                }

                                if ( isset( $result_columns['elected'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $results_table MODIFY COLUMN elected TINYINT(1) NOT NULL DEFAULT 0" );
                                } else {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $results_table ADD COLUMN elected TINYINT(1) NOT NULL DEFAULT 0 AFTER percentage" );
                                }

                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                $result_indexes = $wpdb->get_results( 'SHOW INDEX FROM ' . $results_table, ARRAY_A );
                                $existing_indexes = array();

                                if ( is_array( $result_indexes ) ) {
                                        foreach ( $result_indexes as $index ) {
                                                if ( isset( $index['Key_name'] ) ) {
                                                        $existing_indexes[ $index['Key_name'] ] = true;
                                                }
                                        }
                                }

                                if ( isset( $existing_indexes['uq_results_per_jurisdiction'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $results_table DROP INDEX uq_results_per_jurisdiction" );
                                }

                                if ( isset( $existing_indexes['idx_results_election_id'] ) && ! isset( $existing_indexes['idx_results_election'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $results_table DROP INDEX idx_results_election_id" );
                                        unset( $existing_indexes['idx_results_election_id'] );
                                }

                                $needed_indexes = array(
                                        'idx_results_election'      => 'ALTER TABLE %s ADD KEY idx_results_election (election_id)',
                                        'idx_results_jurisdiction' => 'ALTER TABLE %s ADD KEY idx_results_jurisdiction (jurisdiction_id)',
                                        'idx_results_candidate'    => 'ALTER TABLE %s ADD KEY idx_results_candidate (candidate_id)',
                                        'idx_results_party'        => 'ALTER TABLE %s ADD KEY idx_results_party (party_id)',
                                );

                                foreach ( $needed_indexes as $name => $statement ) {
                                        if ( ! isset( $existing_indexes[ $name ] ) ) {
                                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                                $wpdb->query( sprintf( $statement, $results_table ) );
                                        }
                                }

                                $schema = DB_NAME;
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                $result_constraints = $wpdb->get_col( $wpdb->prepare( 'SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = %s AND TABLE_NAME = %s', $schema, $results_table ) );

                                if ( ! is_array( $result_constraints ) ) {
                                        $result_constraints = array();
                                }

                                foreach ( $result_constraints as $constraint ) {
                                        if ( in_array( $constraint, array( 'fk_results_election', 'fk_results_jurisdiction', 'fk_results_candidate', 'fk_results_party' ), true ) ) {
                                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                                $wpdb->query( "ALTER TABLE $results_table DROP FOREIGN KEY $constraint" );
                                        }
                                }

                                $jurisdictions_table = $wpdb->prefix . 'politeia_jurisdictions';
                                $people_table        = $wpdb->prefix . 'politeia_people';
                                $parties_table       = $wpdb->prefix . 'politeia_political_parties';

                                $constraints_sql = array(
                                        "ALTER TABLE $results_table ADD CONSTRAINT fk_results_election FOREIGN KEY (election_id) REFERENCES $table (id) ON DELETE CASCADE",
                                        "ALTER TABLE $results_table ADD CONSTRAINT fk_results_jurisdiction FOREIGN KEY (jurisdiction_id) REFERENCES $jurisdictions_table (id) ON DELETE CASCADE",
                                        "ALTER TABLE $results_table ADD CONSTRAINT fk_results_candidate FOREIGN KEY (candidate_id) REFERENCES $people_table (id) ON DELETE SET NULL",
                                        "ALTER TABLE $results_table ADD CONSTRAINT fk_results_party FOREIGN KEY (party_id) REFERENCES $parties_table (id) ON DELETE SET NULL",
                                );

                                foreach ( $constraints_sql as $statement ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( $statement );
                                }
                        } else {
                                Installer::install();
                        }
                }

                $stored = get_option( 'politeia_electoral_map_db_version' );

                $required = array(
                        $wpdb->prefix . 'politeia_people',
                        $wpdb->prefix . 'politeia_political_parties',
                        $wpdb->prefix . 'politeia_jurisdictions',
                        $wpdb->prefix . 'politeia_offices',
                        $wpdb->prefix . 'politeia_office_terms',
                        $wpdb->prefix . 'politeia_party_memberships',
                        $wpdb->prefix . 'politeia_party_leanings',
			$wpdb->prefix . 'politeia_jurisdiction_populations',
                        $wpdb->prefix . 'politeia_jurisdiction_budgets',
                        $wpdb->prefix . 'politeia_events',
                        $wpdb->prefix . 'politeia_elections',
                        $wpdb->prefix . 'politeia_election_results',
			$wpdb->prefix . 'politeia_candidacies',
		);

		$missing = false;

		foreach ( $required as $table ) {
			$like = $wpdb->esc_like( $table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
			if ( $found !== $table ) {
				$missing = true;
				break;
			}
		}

                if ( $missing || PLEM_DB_VERSION !== $stored ) {
                        Installer::install();
                }

                if ( $plugin_version !== PLEM_VERSION ) {
                        update_option( 'plem_version', PLEM_VERSION );
                }
        }
}
