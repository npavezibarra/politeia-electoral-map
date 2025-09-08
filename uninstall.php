<?php
/**
 * Uninstall routines for Politeia Electoral Map.
 *
 * @package Politeia
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	"{$wpdb->prefix}politeia_office_terms",
	"{$wpdb->prefix}politeia_party_memberships",
	"{$wpdb->prefix}politeia_offices",
	"{$wpdb->prefix}politeia_jurisdictions",
	"{$wpdb->prefix}politeia_political_parties",
	"{$wpdb->prefix}politeia_people",
);

foreach ( $tables as $table ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS `$table`" );
}

delete_option( 'politeia_electoral_map_db_version' );
