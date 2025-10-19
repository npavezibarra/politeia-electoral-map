<?php
/**
 * Migration: Add wp_politeia_party_leanings table
 * Plugin: Politeia Electoral Map
 * Author: Nicolás Pavez
 * Date: 2025-10-19
 * Description: Creates the wp_politeia_party_leanings table to link people with their political party leanings in elections.
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

function politeia_electoral_map_create_table_party_leanings() {
    global $wpdb;
    $table_name      = "{$wpdb->prefix}politeia_party_leanings";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        person_id BIGINT UNSIGNED NOT NULL,
        party_id BIGINT UNSIGNED NOT NULL,
        election_id BIGINT UNSIGNED DEFAULT NULL,
        started_on DATE DEFAULT NULL,
        ended_on DATE DEFAULT NULL,
        type VARCHAR(40) DEFAULT NULL,
        notes VARCHAR(255) DEFAULT NULL,
        source VARCHAR(255) DEFAULT NULL,
        source_url VARCHAR(400) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY person_id (person_id),
        KEY party_id (party_id),
        KEY election_id (election_id),
        CONSTRAINT fk_leanings_person FOREIGN KEY (person_id)
            REFERENCES {$wpdb->prefix}politeia_people(id)
            ON DELETE CASCADE,
        CONSTRAINT fk_leanings_party FOREIGN KEY (party_id)
            REFERENCES {$wpdb->prefix}politeia_political_parties(id)
            ON DELETE CASCADE,
        CONSTRAINT fk_leanings_election FOREIGN KEY (election_id)
            REFERENCES {$wpdb->prefix}politeia_elections(id)
            ON DELETE SET NULL
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    error_log( '✅ [Politeia Electoral Map] Table wp_politeia_party_leanings created or updated successfully.' );
}
add_action( 'politeia_electoral_map_migrate', 'politeia_electoral_map_create_table_party_leanings' );

politeia_electoral_map_create_table_party_leanings();
