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

                if ( version_compare( $plugin_version, '0.2.6', '<' ) ) {
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

                                $drops = array(
                                        'total_registered',
                                        'total_votes',
                                        'valid_votes',
                                        'blank_votes',
                                        'null_votes',
                                );

                                foreach ( $drops as $drop ) {
                                        if ( isset( $columns[ $drop ] ) ) {
                                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                                $wpdb->query( "ALTER TABLE $table DROP COLUMN $drop" );
                                        }
                                }

                                if ( isset( $columns['system'] ) && ! isset( $columns['voting_system'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table CHANGE COLUMN system voting_system VARCHAR(100) NULL" );
                                }

                                if ( ! isset( $columns['title'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table ADD COLUMN title VARCHAR(255) NULL AFTER election_date" );
                                }

                                // Refresh columns list for subsequent operations.
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

                                // Ensure name column length matches new specification.
                                if ( isset( $columns['name'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table MODIFY COLUMN name VARCHAR(255) NULL" );
                                }

                                if ( isset( $columns['seats'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table MODIFY COLUMN seats INT NULL" );
                                }

                                if ( ! isset( $columns['rounds'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table ADD COLUMN rounds INT NULL AFTER seats" );
                                }

                                if ( ! isset( $columns['voting_system'] ) ) {
                                        // If the column was renamed earlier this condition won't run.
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table ADD COLUMN voting_system VARCHAR(100) NULL AFTER rounds" );
                                }

                                if ( ! isset( $columns['electoral_system'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table ADD COLUMN electoral_system VARCHAR(100) NULL AFTER voting_system" );
                                }

                                if ( isset( $columns['jurisdiction_id'] ) ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table MODIFY COLUMN jurisdiction_id BIGINT UNSIGNED NULL" );
                                }

                                // Ensure unique index exists and legacy index is removed.
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                $indexes = $wpdb->get_results( 'SHOW INDEX FROM ' . $table, ARRAY_A );
                                $has_unique = false;
                                $has_legacy = false;

                                if ( is_array( $indexes ) ) {
                                        foreach ( $indexes as $index ) {
                                                if ( isset( $index['Key_name'] ) ) {
                                                        if ( 'uq_elec_office_scope_date' === $index['Key_name'] ) {
                                                                $has_unique = true;
                                                        }
                                                        if ( 'idx_elec_jur_office_date' === $index['Key_name'] ) {
                                                                $has_legacy = true;
                                                        }
                                                }
                                        }
                                }

                                if ( $has_legacy ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table DROP INDEX idx_elec_jur_office_date" );
                                }

                                if ( ! $has_unique ) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                                        $wpdb->query( "ALTER TABLE $table ADD UNIQUE KEY uq_elec_office_scope_date (office_id, jurisdiction_id, election_date)" );
                                }
                        }

                        $results_table = $wpdb->prefix . 'politeia_election_results';
                        $results_like  = $wpdb->esc_like( $results_table );
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $results_exist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $results_like ) );
                        if ( $results_exist !== $results_table ) {
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
			$wpdb->prefix . 'politeia_jurisdiction_populations',
			$wpdb->prefix . 'politeia_jurisdiction_budgets',
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
