<?php
/**
 * REST Controller for Concejales
 *
 * @package Politeia\Modules\REST
 */

namespace Politeia\Modules\REST;

/**
 * Concejales REST Controller
 */
class Concejales extends Controller
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
        return 'concejales';
    }

    /**
     * Handle GET request for concejales information.
     *
     * @param \WP_REST_Request $req Request.
     * @return WP_REST_Response
     */
    public function handle_index($req)
    {
        global $wpdb;
        $name = $req->get_param('name');
        if (!$name) {
            return new \WP_REST_Response(array('found' => false), 200);
        }

        $jurisdictions = $wpdb->prefix . 'politeia_jurisdictions';
        $candidacies = $wpdb->prefix . 'politeia_candidacies';
        $elections = $wpdb->prefix . 'politeia_elections';
        $people = $wpdb->prefix . 'politeia_people';
        $offices = $wpdb->prefix . 'politeia_offices';
        $parties = $wpdb->prefix . 'politeia_political_organizations';

        $target_date = $req->get_param('date');
        if (!$target_date) {
            $target_date = current_time('Y-m-d');
        }

        /* phpcs:disable WordPress.DB.PreparedSQL.NotPrepared */
        $query = "SELECT 
			CONCAT_WS(' ', p.given_names, p.paternal_surname, p.maternal_surname) AS person_name,
			po.short_name AS party_short_name,
			c.votes
		FROM %s c
		INNER JOIN %s j ON j.id = c.jurisdiction_id
		INNER JOIN %s e ON e.id = c.election_id
		INNER JOIN %s o ON o.id = e.office_id
		INNER JOIN %s p ON p.id = c.person_id
		LEFT JOIN %s po ON po.id = c.party_id
		WHERE (j.official_name = %%s OR j.common_name = %%s)
		  AND o.code = 'CONCEJAL'
		  AND c.elected = 1
		  AND e.election_date = (
		      SELECT MAX(e2.election_date)
		      FROM %s e2
		      INNER JOIN %s o2 ON o2.id = e2.office_id
		      WHERE o2.code = 'CONCEJAL'
		        AND e2.election_date <= %%s
		  )
		ORDER BY c.votes DESC
		LIMIT 15";

        $sql = $wpdb->prepare(
            sprintf(
                $query,
                $candidacies,
                $jurisdictions,
                $elections,
                $offices,
                $people,
                $parties,
                $elections,
                $offices
            ),
            $name,
            $name,
            $target_date
        );
        /* phpcs:enable */

        $rows = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!$rows) {
            return new \WP_REST_Response(array('found' => false, 'concejales' => array()), 200);
        }

        $concejales = array();
        foreach ($rows as $row) {
            $concejales[] = array(
                'name' => trim($row['person_name']),
                'party' => $row['party_short_name'],
                'votes' => $row['votes'],
            );
        }

        return new \WP_REST_Response(
            array(
                'found' => true,
                'concejales' => $concejales,
            ),
            200
        );
    }
}

(new Concejales())->register();
