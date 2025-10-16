<?php
/**
 * Migration: Add "title" column to wp_politeia_elections
 * Plugin: Politeia Electoral Map
 * Date: 2025-10-16
 *
 * Ensures schema consistency with Colab importer and DBML.
 * Executed automatically on plugin activation or update.
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

global $wpdb;
$table = $wpdb->prefix . 'politeia_elections';

// Check if the "title" column exists.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$column_exists = $wpdb->get_results(
        $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'title' )
);

if ( empty( $column_exists ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
                "\n        ALTER TABLE {$table}\n        ADD COLUMN title VARCHAR(200) NOT NULL\n        COMMENT 'Descriptive election title (e.g. Elección de Alcaldes 2024)'\n        AFTER election_date;\n    "
        );
        error_log( '✅ [Politeia Migration] Added "title" column to wp_politeia_elections' );
} else {
        error_log( 'ℹ️ [Politeia Migration] "title" column already exists — skipped.' );
}
