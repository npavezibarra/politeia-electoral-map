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
			$wpdb->prefix . 'politeia_candidacies',
		);

		$missing = false;
               foreach ( $required as $table ) {
                       $like = $wpdb->esc_like( $table );
                       // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                       if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ) !== $table ) {
                               $missing = true;
                               break;
                       }
               }

		if ( $missing || PLEM_DB_VERSION !== $stored ) {
			Installer::install();
		}
	}
}
