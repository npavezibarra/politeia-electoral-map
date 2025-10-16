<?php
/**
 * Migration: Sync full schema for wp_politeia_elections
 * Plugin: Politeia Electoral Map
 * Date: 2025-10-16
 *
 * Ensures all expected columns exist in wp_politeia_elections:
 *  - title
 *  - seats
 *  - rounds
 *  - voting_system
 *  - source_url
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

global $wpdb;
$table = $wpdb->prefix . 'politeia_elections';

if ( ! function_exists( 'politeia_column_exists' ) ) {
        /**
         * Check if a column exists in a table.
         *
         * @param string $table  Table name.
         * @param string $column Column name.
         * @return bool
         */
        function politeia_column_exists( $table, $column ) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $result = $wpdb->get_results(
                        $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', $column )
                );
                return ! empty( $result );
        }
}

// 1. title.
if ( ! politeia_column_exists( $table, 'title' ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
                "\n        ALTER TABLE {$table}\n        ADD COLUMN title VARCHAR(200) NOT NULL\n        COMMENT 'Descriptive election title (e.g. Elección de Alcalde/sa 2024)'\n        AFTER election_date;\n    "
        );
        error_log( '✅ [Politeia Migration] Added "title" column to wp_politeia_elections' );
}

// 2. seats.
if ( ! politeia_column_exists( $table, 'seats' ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
                "\n        ALTER TABLE {$table}\n        ADD COLUMN seats INT DEFAULT 1\n        COMMENT 'Number of seats available in the election'\n        AFTER title;\n    "
        );
        error_log( '✅ [Politeia Migration] Added "seats" column' );
}

// 3. rounds.
if ( ! politeia_column_exists( $table, 'rounds' ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
                "\n        ALTER TABLE {$table}\n        ADD COLUMN rounds INT DEFAULT 1\n        COMMENT 'Number of rounds (1 for single-round elections)'\n        AFTER seats;\n    "
        );
        error_log( '✅ [Politeia Migration] Added "rounds" column' );
}

// 4. voting_system.
if ( ! politeia_column_exists( $table, 'voting_system' ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
                "\n        ALTER TABLE {$table}\n        ADD COLUMN voting_system VARCHAR(80) DEFAULT 'Plurality'\n        COMMENT 'Voting system used (e.g. Mayoral Plurality)'\n        AFTER rounds;\n    "
        );
        error_log( '✅ [Politeia Migration] Added "voting_system" column' );
}

// 5. source_url.
if ( ! politeia_column_exists( $table, 'source_url' ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
                "\n        ALTER TABLE {$table}\n        ADD COLUMN source_url VARCHAR(400)\n        COMMENT 'Source URL of the election data'\n        AFTER voting_system;\n    "
        );
        error_log( '✅ [Politeia Migration] Added "source_url" column' );
}

error_log( '✅ [Politeia Migration] Elections table schema synced successfully.' );
