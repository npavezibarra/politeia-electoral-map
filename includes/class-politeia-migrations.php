<?php
/**
 * Loads database migrations for Politeia Electoral Map.
 *
 * @package Politeia
 */

if (!defined('ABSPATH')) {
        exit;
}

require_once __DIR__ . '/migrations/2025-10-15-add-turnout-table.php';
require_once __DIR__ . '/migrations/2026-01-11-sync-with-etl-schema.php';
require_once __DIR__ . '/migrations/2026-01-16-add-districts.php';
