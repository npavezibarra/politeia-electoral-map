<?php
/**
 * REST Controller for Jurisdictions
 *
 * @package Politeia\Modules\REST
 */

namespace Politeia\Modules\REST;

/**
 * Jurisdictions REST Controller
 */
class Jurisdictions extends Controller
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
		return 'jurisdictions';
	}

	/**
	 * Handle GET request for jurisdiction information.
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

		/* phpcs:disable WordPress.DB.PreparedSQL.NotPrepared */
		$query = "SELECT 
			j.official_name AS jurisdiction_name,
			j.common_name AS common_name,
			CONCAT_WS(' ', p.given_names, p.paternal_surname, p.maternal_surname) AS person_name,
			o.title AS office_title,
			po.short_name AS party_short_name,
			e.election_date AS started_on,
			DATE_ADD(e.election_date, INTERVAL 4 YEAR) AS planned_end_on,
			c.votes,
			NULL AS population,
			NULL AS budget
		FROM %s c
		INNER JOIN %s j ON j.id = c.jurisdiction_id
		INNER JOIN %s e ON e.id = c.election_id
		INNER JOIN %s o ON o.id = e.office_id
		INNER JOIN %s p ON p.id = c.person_id
		LEFT JOIN %s po ON po.id = c.party_id
		WHERE (j.official_name = %%s OR j.common_name = %%s)
		  AND o.code = 'ALCALDE'
		  AND c.elected = 1
		ORDER BY e.election_date DESC
		LIMIT 1";

		$sql = $wpdb->prepare(
			sprintf(
				$query,
				$candidacies,
				$jurisdictions,
				$elections,
				$offices,
				$people,
				$parties
			),
			$name,
			$name
		);
		/* phpcs:enable */

		$row = $wpdb->get_row($sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if (!$row) {
			return new \WP_REST_Response(array('found' => false), 200);
		}

		return new \WP_REST_Response(
			array(
				'found' => true,
				'jurisdiction_name' => $row['jurisdiction_name'],
				'common_name' => $row['common_name'],
				'person_name' => trim($row['person_name']),
				'photo_url' => null,
				'office_title' => $row['office_title'],
				'party_short_name' => $row['party_short_name'],
				'started_on' => $row['started_on'],
				'planned_end_on' => $row['planned_end_on'],
				'votes' => $row['votes'],
				'population' => $row['population'],
				'budget' => $row['budget'],
			),
			200
		);
	}
}

(new Jurisdictions())->register();
