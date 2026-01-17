<!DOCTYPE html>
<html>

<head>
    <title>Debug Senator Query</title>
</head>

<body>
    <pre>
<?php
require_once __DIR__ . '/../../../wp-load.php';
global $wpdb;

echo "<h3>1. Check Jurisdiction 'Circunscripción 1'</h3>";
$jurs = $wpdb->get_results("SELECT id, official_name, type, external_code FROM wp_politeia_jurisdictions WHERE official_name LIKE '%Circunscripci%1%' OR external_code = '1'");
foreach ($jurs as $j) {
    echo "ID: {$j->id}, Name: {$j->official_name}, Type: {$j->type}, Code: {$j->external_code}\n";
}

echo "\n<h3>2. Check Person 'Jose Miguel Insulza'</h3>";
$people = $wpdb->get_results("SELECT id, full_name FROM wp_politeia_people WHERE full_name LIKE '%Insulza%'");
foreach ($people as $p) {
    echo "ID: {$p->id}, Name: {$p->full_name}\n";

    echo "  -- Checking Terms for person {$p->id} --\n";
    $terms = $wpdb->get_results("SELECT * FROM wp_politeia_office_terms WHERE person_id = {$p->id}");
    foreach ($terms as $t) {
        echo "    Term ID: {$t->id}, JurID: {$t->jurisdiction_id}, OfficeID: {$t->office_id}, Start: {$t->started_on}, End: {$t->planned_end_on}\n";
    }
}

echo "\n<h3>3. Check All Active Terms for Jurisdiction IDs found in Step 1</h3>";
foreach ($jurs as $j) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM wp_politeia_office_terms WHERE jurisdiction_id = {$j->id} AND CURDATE() BETWEEN started_on AND planned_end_on");
    echo "Jurisdiction {$j->id} ({$j->official_name}) has {$count} active terms.\n";
}
?>
</pre>
</body>

</html>