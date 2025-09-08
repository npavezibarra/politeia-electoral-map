<?php
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
     * Check stored DB version and upgrade if needed.
     */
    public static function maybe_upgrade(): void {
        $stored = get_option( 'politeia_electoral_map_db_version' );
        if ( PLEM_DB_VERSION !== $stored ) {
            Installer::install();
        }
    }
}
