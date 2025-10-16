<?php
/**
 * Migration: Add wp_politeia_turnout table
 * Date: 2025-10-15
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$table_elections = $wpdb->prefix . 'politeia_elections';
$table_turnout   = $wpdb->prefix . 'politeia_turnout';

$sql = "CREATE TABLE IF NOT EXISTS {$table_turnout} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  election_id bigint(20) unsigned NOT NULL,
  valid_votes int DEFAULT NULL,
  blank_votes int DEFAULT NULL,
  null_votes int DEFAULT NULL,
  registered_voters int DEFAULT NULL,
  actual_voters int DEFAULT NULL,
  turnout_percent decimal(6,3) DEFAULT NULL,
  source_url varchar(400) DEFAULT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY election_id_unique (election_id),
  CONSTRAINT fk_turnout_election
    FOREIGN KEY (election_id)
    REFERENCES {$table_elections}(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) {$charset_collate};";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql );
