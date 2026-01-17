<!DOCTYPE html>
<html>

<head>
    <title>Import All Senators & Candidacies</title>
    <style>
        body {
            font-family: monospace;
            white-space: pre-wrap;
            padding: 20px;
            background: #f0f0f0;
        }
    </style>
</head>

<body>
    <h1>Running Senators Candidates Import...</h1>
    <hr>
    <?php

    $path = __DIR__ . '/../../../wp-load.php';
    if (file_exists($path)) {
        require_once $path;
    } else {
        die("Error: Could not find wp-load.php at $path");
    }

    global $wpdb;

    // Increase time limit
    set_time_limit(600);

    $files = [
        '2017' => [
            'path' => '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/normalized_csv/senadores/2017_11_Senatorial_Datos_Eleccion_Votación_por_comuna.csv',
            'date' => '2017-11-19',
            'term_start' => '2018-03-11',
            'term_end' => '2026-03-11'
        ],
        '2021' => [
            'path' => '/Users/nicolasibarra/Desktop/PoliteiaElectoralMap/ServelGhost/normalized_csv/senadores/2021_11_Senadores_Datos_Eleccion_Votación_por_comuna.csv',
            'date' => '2021-11-21',
            'term_start' => '2022-03-11',
            'term_end' => '2030-03-11'
        ]
    ];

    // Get Office ID
    $office_id = $wpdb->get_var("SELECT id FROM wp_politeia_offices WHERE title = 'SENADOR'");
    if (!$office_id) {
        // Attempt to create office if missing (though it appeared to exist before)
        // Or try exact title match
        $office_id = $wpdb->get_var("SELECT id FROM wp_politeia_offices WHERE title LIKE '%Senad%' LIMIT 1");
        if (!$office_id)
            die("Office SENADOR not found.\n");
    }

    /*
     Data Structure for Aggregation:
     $candidates[year][circ_num][candidate_key] = [
        'name' => ..., 
        'party' => ..., 
        'votes' => 0, 
        'elected' => false,
        'full_name' => ...,
        'last_name' => ...
     ]
    */

    $candidates = [];

    foreach ($files as $year => $info) {
        if (!file_exists($info['path'])) {
            echo "File not found: " . $info['path'] . "\n";
            continue;
        }

        echo "Reading $year CSV...\n";
        $handle = fopen($info['path'], 'r');
        $headers = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $full_name = trim($row[9]) . ' ' . trim($row[10]) . ' ' . trim($row[11]);
            $party_name = trim($row[7]);
            $status = trim($row[13] ?? '');
            $votes = (int) str_replace('.', '', $row[12]); // Remove dots if any
    
            $circ_raw = $row[2];
            if (!preg_match('/SENATORIAL\s+(\d+)/i', $circ_raw, $m))
                continue;
            $circ_num = $m[1];

            $key = $full_name;

            if (!isset($candidates[$year][$circ_num][$key])) {
                $candidates[$year][$circ_num][$key] = [
                    'full_name' => $full_name,
                    'first_name' => trim($row[9]),
                    'last_name' => trim($row[10]) . ' ' . trim($row[11]),
                    'party' => $party_name,
                    'votes' => 0,
                    'elected' => ($status === 'SENADOR'),
                    'file_info' => $info // Keep ref to dates
                ];
            }

            $candidates[$year][$circ_num][$key]['votes'] += $votes;
            // In case they are elected in one row but not marked in others (unlikely but safe)
            if ($status === 'SENADOR') {
                $candidates[$year][$circ_num][$key]['elected'] = true;
            }
        }
        fclose($handle);
    }

    // Processing
    echo "Processing Data...\n";

    foreach ($candidates as $year => $circs) {
        foreach ($circs as $circ_num => $cands) {

            // 1. Resolve Jurisdiction
            $circ_ordinal = $circ_num . 'ª';
            $jur_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM wp_politeia_jurisdictions 
             WHERE type = 'SENATORIAL_CIRC' 
             AND (official_name LIKE %s OR official_name LIKE %s OR external_code = %s)",
                $circ_ordinal . '%',
                'Circunscripción ' . $circ_num . '%',
                $circ_num
            ));

            if (!$jur_id) {
                echo "  [WARN] Jurisdiction Circ $circ_num not found.\n";
                continue;
            }

            // 2. Ensure Election Exists
            // Using current year's file info
            $file_info = reset($cands)['file_info'];
            $election_date = $file_info['date'];

            $election_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM wp_politeia_elections 
             WHERE office_id = %d AND jurisdiction_id = %d AND election_date = %s",
                $office_id,
                $jur_id,
                $election_date
            ));

            if (!$election_id) {
                $wpdb->insert('wp_politeia_elections', [
                    'office_id' => $office_id,
                    'jurisdiction_id' => $jur_id,
                    'election_date' => $election_date,
                    'title' => "Elección Senatorial $year - Circunscripción $circ_num",
                    'name' => "Senatorial $year",
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ]);
                $election_id = $wpdb->insert_id;
                echo "  Created Election: Senatorial $year - Circ $circ_num (ID: $election_id)\n";
            }

            foreach ($cands as $cand) {
                // 3. Resolve Person
                $person_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM wp_politeia_people WHERE full_name = %s",
                    $cand['full_name']
                ));

                if (!$person_id) {
                    // Try fuzzy? No, stick to exact for now.
                    $wpdb->insert('wp_politeia_people', [
                        'full_name' => $cand['full_name'],
                        'given_names' => $cand['first_name'], // Schema: given_names
                        'paternal_surname' => explode(' ', $cand['last_name'])[0], // Approx
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ]);
                    $person_id = $wpdb->insert_id;
                }

                // 4. Resolve Party (Political Organization)
                $party_id = null;
                if ($cand['party']) {
                    $party_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM wp_politeia_political_organizations WHERE official_name = %s OR short_name = %s",
                        $cand['party'],
                        $cand['party']
                    ));
                    if (!$party_id) {
                        $wpdb->insert('wp_politeia_political_organizations', [
                            'official_name' => $cand['party'],
                            'short_name' => substr($cand['party'], 0, 60),
                            'type' => 'PARTY',
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        ]);
                        $party_id = $wpdb->insert_id;
                    }
                }

                // 5. Create/Update Candidacy
                $candidacy_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM wp_politeia_candidacies 
                 WHERE election_id = %d AND person_id = %d",
                    $election_id,
                    $person_id
                ));

                if (!$candidacy_id) {
                    $wpdb->insert('wp_politeia_candidacies', [
                        'election_id' => $election_id,
                        'person_id' => $person_id,
                        'jurisdiction_id' => $jur_id,
                        'party_id' => $party_id,
                        'votes' => $cand['votes'],
                        'elected' => $cand['elected'] ? 1 : 0,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ]);
                } else {
                    // Update votes if changed
                    $wpdb->update(
                        'wp_politeia_candidacies',
                        ['votes' => $cand['votes'], 'elected' => $cand['elected'] ? 1 : 0],
                        ['id' => $candidacy_id]
                    );
                }

                // 6. Create Term if Elected
                if ($cand['elected']) {
                    $term_exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM wp_politeia_office_terms 
                     WHERE person_id = %d AND jurisdiction_id = %d AND office_id = %d AND started_on = %s",
                        $person_id,
                        $jur_id,
                        $office_id,
                        $cand['file_info']['term_start']
                    ));

                    if (!$term_exists) {
                        $wpdb->insert('wp_politeia_office_terms', [
                            'person_id' => $person_id,
                            'jurisdiction_id' => $jur_id,
                            'office_id' => $office_id,
                            'started_on' => $cand['file_info']['term_start'],
                            'planned_end_on' => $cand['file_info']['term_end'],
                            'status' => 'active',
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        ]);
                        echo "    + Elected: " . $cand['full_name'] . "\n";
                    }
                }
            }
            echo "  Processed Circ $circ_num: " . count($cands) . " candidates.\n";
        }
    }

    echo "<hr>Done.";
    ?>
</body>

</html>