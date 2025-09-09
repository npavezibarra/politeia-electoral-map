<?php
/**
 * REST controller for commune information.
 *
 * @package PoliteiaElectoralMap
 */

namespace Politeia\Modules\REST;

use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides data about jurisdictions and their current authorities.
 */
class Jurisdictions extends Controller {
	/**
	 * REST namespace.
	 *
	 * @return string
	 */
	public function namespace(): string {
		return 'politeia/v1';
	}

	/**
	 * Route base.
	 *
	 * @return string
	 */
	public function route_base(): string {
		return 'jurisdictions';
	}

	/**
	 * Handle GET requests for jurisdiction info.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public function handle_index( $req ) {
		global $wpdb;
		$name = $req->get_param( 'name' );
		if ( ! $name ) {
			return new WP_REST_Response( array( 'found' => false ), 200 );
		}

		$jurisdictions     = $wpdb->prefix . 'politeia_jurisdictions';
		$terms             = $wpdb->prefix . 'politeia_office_terms';
		$people            = $wpdb->prefix . 'politeia_people';
		$offices           = $wpdb->prefix . 'politeia_offices';
		$party_memberships = $wpdb->prefix . 'politeia_party_memberships';
		$parties           = $wpdb->prefix . 'politeia_political_parties';
		$budgets           = $wpdb->prefix . 'politeia_jurisdiction_budgets';
		$populations       = $wpdb->prefix . 'politeia_jurisdiction_populations';
		$elections         = $wpdb->prefix . 'politeia_elections';
		$candidacies       = $wpdb->prefix . 'politeia_candidacies';

        /* phpcs:disable WordPress.DB.PreparedSQL.NotPrepared */
				$query = "SELECT j.official_name AS jurisdiction_name,
                    j.common_name AS common_name,
                    CONCAT_WS(' ', p.given_names, p.paternal_surname, p.maternal_surname) AS person_name,
                    p.photo_url,
                    o.title AS office_title,
                    pa.short_name AS party_short_name,
                    t.started_on,
                    t.planned_end_on,
                    (SELECT c.votes FROM %s c INNER JOIN %s e ON e.id = c.election_id WHERE c.person_id = t.person_id AND e.jurisdiction_id = j.id AND e.office_id = t.office_id ORDER BY e.election_date DESC LIMIT 1) AS votes,
                    (SELECT b.amount_total FROM %s b WHERE b.jurisdiction_id = j.id ORDER BY b.fiscal_year DESC LIMIT 1) AS budget,
                    (SELECT pop.population FROM %s pop WHERE pop.jurisdiction_id = j.id ORDER BY pop.year DESC LIMIT 1) AS population
             FROM %s j
             LEFT JOIN %s t ON t.jurisdiction_id = j.id AND t.ended_on IS NULL
             LEFT JOIN %s p ON p.id = t.person_id
             LEFT JOIN %s o ON o.id = t.office_id
             LEFT JOIN %s pm ON pm.person_id = p.id AND pm.ended_on IS NULL
             LEFT JOIN %s pa ON pa.id = pm.party_id
             WHERE j.official_name = %%s OR j.common_name = %%s
             LIMIT 1";

				$sql = $wpdb->prepare(
					sprintf(
						$query,
						$candidacies,
						$elections,
						$budgets,
						$populations,
						$jurisdictions,
						$terms,
						$people,
						$offices,
						$party_memberships,
						$parties
					),
					$name,
					$name
				);
        /* phpcs:enable */

		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $row ) {
			return new WP_REST_Response( array( 'found' => false ), 200 );
		}

				return new WP_REST_Response(
					array(
						'found'             => true,
						'jurisdiction_name' => $row['jurisdiction_name'],
						'common_name'       => $row['common_name'],
						'person_name'       => trim( $row['person_name'] ),
						'photo_url'         => $row['photo_url'],
						'office_title'      => $row['office_title'],
						'party_short_name'  => $row['party_short_name'],
						'started_on'        => $row['started_on'],
						'planned_end_on'    => $row['planned_end_on'],
						'votes'             => $row['votes'],
						'population'        => $row['population'],
						'budget'            => $row['budget'],
					),
					200
				);
	}
}

( new Jurisdictions() )->register();
