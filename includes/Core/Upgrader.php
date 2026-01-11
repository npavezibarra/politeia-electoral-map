<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * Runs database schema upgrades when version changes.
 *
 * @package Politeia
 */

namespace Politeia\Core;

use Politeia\Modules\Database\Installer;

if (!defined('ABSPATH')) {
        exit;
}

/**
 * Handles database upgrades.
 */
class Upgrader
{
        /**
         * Ensure required tables exist and upgrades run when needed.
         */
        public static function maybe_upgrade(): void
        {
                global $wpdb;

                $plugin_version = get_option('plem_version', '0.0.0');

                if (version_compare($plugin_version, '0.2.5', '<')) {
                        $table = $wpdb->prefix . 'politeia_elections';
                        $like = $wpdb->esc_like($table);
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
                        if ($exists === $table) {
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                $column = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'event_id'));
                                if (null === $column) {
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                        $wpdb->query("ALTER TABLE $table ADD COLUMN event_id BIGINT UNSIGNED NULL AFTER id");
                                }
                        }
                }

                $stored = get_option('politeia_electoral_map_db_version');

                $required = array(
                        $wpdb->prefix . 'politeia_people',
                        $wpdb->prefix . 'politeia_political_organizations',  // Renamed from political_parties
                        $wpdb->prefix . 'politeia_jurisdictions',
                        $wpdb->prefix . 'politeia_offices',
                        $wpdb->prefix . 'politeia_office_terms',
                        $wpdb->prefix . 'politeia_party_memberships',
                        $wpdb->prefix . 'politeia_jurisdiction_populations',
                        $wpdb->prefix . 'politeia_jurisdiction_budgets',
                        $wpdb->prefix . 'politeia_elections',
                        $wpdb->prefix . 'politeia_election_coalitions',           // NEW
                        $wpdb->prefix . 'politeia_election_coalition_members',    // NEW
                        $wpdb->prefix . 'politeia_election_lista_assignments',    // NEW
                        $wpdb->prefix . 'politeia_election_results',              // NEW
                        $wpdb->prefix . 'politeia_candidacies',
                        $wpdb->prefix . 'politeia_party_leanings',                // NEW
                );

                $missing = false;

                foreach ($required as $table) {
                        $like = $wpdb->esc_like($table);
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
                        if ($found !== $table) {
                                $missing = true;
                                break;
                        }
                }

                if ($missing || PLEM_DB_VERSION !== $stored) {
                        Installer::install();
                }

                if ($plugin_version !== PLEM_VERSION) {
                        update_option('plem_version', PLEM_VERSION);
                }
        }
}
