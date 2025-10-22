<?php
/**
 * Loads database migrations for Politeia Electoral Map.
 *
 * @package Politeia
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

require_once __DIR__ . '/migrations/2025-10-15-add-turnout-table.php';
require_once __DIR__ . '/migrations/2025_10_19_add_wp_politeia_party_leanings.php';
