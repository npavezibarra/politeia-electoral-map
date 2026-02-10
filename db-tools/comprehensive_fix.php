<?php
require_once __DIR__ . '/../../../../wp-load.php';
global $wpdb;

echo "--- SUPER-FAST GLOBAL PHOTO FIX --- \n";
$assets_dir = realpath(__DIR__ . '/../assets/');
$photo_dirs = [
    $assets_dir . '/imported_photos_2024/',
    $assets_dir . '/imported_photos_2021/',
    $assets_dir . '/imported_photos_2021_nov/',
    $assets_dir . '/imported_photos_2017/',
];

$wpdb->query("DROP TABLE IF EXISTS tmp_photo_map");
$wpdb->query("CREATE TABLE tmp_photo_map (
    person_id INT,
    norm_name VARCHAR(255),
    photo_path VARCHAR(500),
    KEY (person_id),
    KEY (norm_name)
)");

function normalize_name($s)
{
    if (!$s)
        return '';
    $s = mb_strtolower(trim($s));
    $s = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ñ', ' ', 'ñ'],
        ['a', 'e', 'i', 'o', 'u', 'n', '_', 'n'],
        $s
    );
    $normalized = preg_replace('/[^a-z0-9_]/', '', $s);
    return $normalized;
}

echo "Scanning photo directories...\n";
foreach ($photo_dirs as $base_dir) {
    if (!is_dir($base_dir))
        continue;

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iter as $path => $dir) {
        if (!$dir->isDir()) {
            $filename = $dir->getFilename();
            $rel_path = str_replace($assets_dir . '/', '', $path);

            $pid = null;
            $norm_name = null;

            if (preg_match('/^(\d+)_/', $filename, $matches)) {
                $pid = $matches[1];
            }

            if (preg_match('/^\d+_(.+?)(?:_\d{4})?\.(?:jpe?g|png|jpeg)$/i', $filename, $matches)) {
                $norm_name = $matches[1];
            }

            $wpdb->insert('tmp_photo_map', [
                'person_id' => $pid,
                'norm_name' => $norm_name,
                'photo_path' => $rel_path
            ]);
        }
    }
}

echo "Photos indexed in temporary table.\n";

// 1. UPDATE BY ID
echo "Bulk updating by Person ID...\n";
$wpdb->query("
    UPDATE {$wpdb->prefix}politeia_candidacies c
    JOIN tmp_photo_map tm ON tm.person_id = c.person_id
    SET c.profile_photo_url = tm.photo_path
    WHERE c.elected = 1 AND tm.person_id IS NOT NULL
");

// 2. PRE-NORMALIZE Names in wp_politeia_people
$wpdb->query("DROP TABLE IF EXISTS tmp_people_norm");
$wpdb->query("CREATE TABLE tmp_people_norm (
    person_id INT,
    norm_name VARCHAR(255),
    KEY (person_id),
    KEY (norm_name)
)");

echo "Normalizing and indexing people names...\n";
// This is the only slow part in PHP, but it's only 16k-ish people usually?
$people = $wpdb->get_results("SELECT id, given_names, paternal_surname FROM {$wpdb->prefix}politeia_people");
foreach ($people as $p) {
    $norm = normalize_name($p->given_names . ' ' . $p->paternal_surname);
    if ($norm) {
        $wpdb->insert('tmp_people_norm', [
            'person_id' => $p->id,
            'norm_name' => $norm
        ]);
    }
}

// 3. UPDATE BY NAME
echo "Bulk updating by Name Fallback...\n";
$wpdb->query("
    UPDATE {$wpdb->prefix}politeia_candidacies c
    JOIN tmp_people_norm pn ON pn.person_id = c.person_id
    JOIN tmp_photo_map tm ON tm.norm_name = pn.norm_name
    SET c.profile_photo_url = tm.photo_path
    WHERE c.elected = 1 
      AND (c.profile_photo_url IS NULL OR c.profile_photo_url = '')
");

$wpdb->query("DROP TABLE IF EXISTS tmp_photo_map");
$wpdb->query("DROP TABLE IF EXISTS tmp_people_norm");

echo "✅ Super-Fast Fix Complete.\n";
