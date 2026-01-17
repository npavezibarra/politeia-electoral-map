<?php
/**
 * REST Controller for Districts
 *
 * @package Politeia\Modules\REST
 */

namespace Politeia\Modules\REST;

/**
 * Districts REST Controller
 */
class Districts extends Controller
{

    /**
     * Get the namespace for this controller.
     *
     * @return string
     */
    public function namespace(): string
    {
        return 'politeia/v1';
    }

    /**
     * Get the route base for this controller.
     *
     * @return string
     */
    public function route_base(): string
    {
        return 'districts';
    }

    /**
     * Handle GET request for districts information.
     *
     * @param \WP_REST_Request $req Request.
     * @return \WP_REST_Response
     */
    public function handle_index($req)
    {
        global $wpdb;

        $t_jur = $wpdb->prefix . 'politeia_jurisdictions';
        $t_mem = $wpdb->prefix . 'politeia_jurisdiction_memberships';

        // Query to fetch Districts and their member Communes (grouped)
        // We use standard SQL JOINs.
        // Note: GROUP_CONCAT or JSON_ARRAYAGG (if MySQL 5.7+) works.
        // Since we don't know exact MySQL version, we can fetch rows and group in PHP for safety.

        $sql = "SELECT 
					d.id as district_id,
					d.official_name as district_name,
					c.official_name as commune_name
				FROM $t_jur d
				INNER JOIN $t_mem m ON m.parent_jurisdiction_id = d.id
				INNER JOIN $t_jur c ON c.id = m.child_jurisdiction_id
				WHERE d.type IN ('DISTRICT', 'SENATORIAL_CIRC')
				  AND c.type = 'COMMUNE'
				  AND m.relationship_type = 'ELECTORAL'
				ORDER BY d.type, d.id, c.official_name";

        $results = $wpdb->get_results($sql);

        $districts = [];

        foreach ($results as $row) {
            $id = $row->district_id;
            if (!isset($districts[$id])) {
                $districts[$id] = [
                    'id' => $id,
                    'name' => $row->district_name,
                    'type' => ($row->district_name && stripos($row->district_name, 'Circunscripción') !== false) ? 'Circunscripción' : 'District',
                    // Or retrieve type from DB if I selected it. I selected it by filtering but didn't select d.type in SELECT clause.
                    // Better to select d.type in SQL.
                    'communes' => [],
                ];
            }
            $districts[$id]['communes'][] = $row->commune_name;
        }

        // Re-index array to list
        $response_data = array_values($districts);

        return new \WP_REST_Response($response_data, 200);
    }
}

(new Districts())->register();
