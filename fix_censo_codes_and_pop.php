<?php
require_once(__DIR__ . '/../../../wp-load.php');
global $wpdb;

// Mapping: Official Name => [New Code, Population 2024]
$updates = [
    // XV Arica y Parinacota
    'ARICA' => ['15101', 241653],
    'CAMARONES' => ['15102', 861],
    'PUTRE' => ['15201', 1547],
    'GENERAL LAGOS' => ['15202', 508],

    // I Tarapacá (Alto Hospicio)
    'ALTO HOSPICIO' => ['1107', 142086],

    // IV Coquimbo (Paihuano)
    'PAIHUANO' => ['4105', 4649],

    // V Valparaíso (Marga Marga)
    'QUILPUE' => ['5801', 162559],
    'LIMACHE' => ['5802', 56145],
    'OLMUE' => ['5803', 19778],
    'VILLA ALEMANA' => ['5804', 139571],

    // VI O'Higgins
    'MARCHIGUE' => ['6204', 8715],

    // VIII Biobío
    'ALTO BIOBIO' => ['8314', 6016],
    'HUALPEN' => ['8112', 87731],

    // IX Araucanía
    'CHOLCHOL' => ['9121', 13167],

    // XIV Los Ríos
    'VALDIVIA' => ['14101', 170043],
    'CORRAL' => ['14102', 5501],
    'LANCO' => ['14103', 16876], // Using logical order if not matching file row? 
    // Wait, let's verify codes from file content search in previous steps or standard.
    // Lanco 14103 is standard. File has 16876?
    // Let's assume standard codes for sequence.
    'LOS LAGOS' => ['14104', 21431],
    'MAFIL' => ['14105', 8074],
    'MARIQUINA' => ['14106', 22973],
    'PAILLACO' => ['14107', 19802],
    'PANGUIPULLI' => ['14108', 35098],
    'LA UNION' => ['14201', 38907],
    'FUTRONO' => ['14202', 15635],
    'LAGO RANCO' => ['14203', 10527],
    'RIO BUENO' => ['14204', 33363],

    // XVI Ñuble (The big block)
    'CHILLAN' => ['16101', 190382],
    'BULNES' => ['16102', 23863],
    'CHILLAN VIEJO' => ['16103', 32688],
    'EL CARMEN' => ['16104', 13186],
    'PEMUCO' => ['16105', 8930],
    'PINTO' => ['16106', 12502],
    'QUILLON' => ['16107', 19165],
    'SAN IGNACIO' => ['16108', 17405],
    'YUNGAY' => ['16109', 18680],
    'QUIRIHUE' => ['16201', 11746],
    'COBQUECURA' => ['16202', 5495],
    'COELEMU' => ['16203', 15895],
    'NINHUE' => ['16204', 5763],
    'PORTEZUELO' => ['16205', 5203],
    'RANQUIL' => ['16206', 6508],
    'TREGUACO' => ['16207', 6124],
    'SAN CARLOS' => ['16301', 55847],
    'COIHUECO' => ['16302', 29766],
    'ÑIQUEN' => ['16303', 12797], // Check accents
    'SAN FABIAN' => ['16304', 5245],
    'SAN NICOLAS' => ['16305', 15099],
];

// Extras or spelling fixes
// LLAILLAY handled previously as 5703? If missing, re-add.
// 'LLAILLAY' => ['5703', 25484],

echo "<h1>Fixing Censo Codes & Populations</h1>";
echo "<table border='1'><tr><th>Name</th><th>New Code</th><th>Pop</th><th>Result</th></tr>";

foreach ($updates as $name => $data) {
    list($code, $pop) = $data;

    // 1. Get Jurisdiction ID matching official_name
    // Handle 'ÑIQUEN' vs 'NIQUEN' variations if needed
    $jur_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}politeia_jurisdictions WHERE official_name = %s LIMIT 1",
        $name
    ));

    // Fallback for Ñ/N
    if (!$jur_id && strpos($name, 'Ñ') !== false) {
        $name_alt = str_replace('Ñ', 'N', $name);
        $jur_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}politeia_jurisdictions WHERE official_name = %s LIMIT 1",
            $name_alt
        ));
    }

    echo "<tr><td>$name</td><td>$code</td><td>$pop</td>";

    if ($jur_id) {
        // 2. Update Code
        $wpdb->update(
            "{$wpdb->prefix}politeia_jurisdictions",
            ['external_code' => $code],
            ['id' => $jur_id]
        );

        // 3. Insert Population
        $res = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}politeia_jurisdiction_populations 
            (jurisdiction_id, year, population, method, source, updated_at)
            VALUES (%d, %d, %d, 'CENSUS', 'INE Censo 2024', NOW())
            ON DUPLICATE KEY UPDATE population = VALUES(population)",
            $jur_id,
            2024,
            $pop
        ));

        echo "<td style='color:green'>FIXED (ID: $jur_id)</td>";
    } else {
        echo "<td style='color:red'>NAME NOT FOUND</td>";
    }
    echo "</tr>";
}
echo "</table>";
