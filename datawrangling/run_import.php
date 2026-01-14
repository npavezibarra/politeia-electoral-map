<?php
// Bootstrap WordPress
define('WP_USE_THEMES', false);
if (!file_exists(__DIR__ . '/../../../wp-load.php')) {
    die("Error: Could not find wp-load.php in " . __DIR__ . "/../../../wp-load.php\n");
}
require_once __DIR__ . '/../../../wp-load.php';

// Check permission (optional for CLI but good practice)
// Not needed for CLI usually as we are root/user

echo "Starting Governor Import via CLI Runner...\n";

if (class_exists('\Politeia\Modules\ETL\GovernorImporter')) {
    \Politeia\Modules\ETL\GovernorImporter::run();
    echo "\nImport finished.\n";
} else {
    echo "Error: GovernorImporter class not found.\n";
}
