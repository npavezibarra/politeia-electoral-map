<?php
$mysqli = new mysqli("localhost", "root", "root", "local");
if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
    exit();
}
// Get table prefix
$table_prefix = "wp_"; // Assumption based on wp-config.php usually
// Better: query standard WP tables? No, I know the prefix is wp_ from config.php earlier.

$result = $mysqli->query("SELECT id, official_name, type FROM wp_politeia_jurisdictions WHERE type='REGION'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    $result->free();
} else {
    echo "Error or No Regions found: " . $mysqli->error;
}
$mysqli->close();
?>