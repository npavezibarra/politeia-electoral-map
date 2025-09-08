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

		$jurisdictions = $wpdb->prefix . 'politeia_jurisdictions';
		$terms         = $wpdb->prefix . 'politeia_office_terms';
		$people        = $wpdb->prefix . 'politeia_people';
		$offices       = $wpdb->prefix . 'politeia_offices';

        /* phpcs:disable WordPress.DB.PreparedSQL.NotPrepared */
		$query = "SELECT j.official_name AS jurisdiction_name,
                    CONCAT_WS(' ', p.given_names, p.paternal_surname, p.maternal_surname) AS person_name,
                    o.title AS office_title,
                    t.started_on
             FROM %s j
             LEFT JOIN %s t ON t.jurisdiction_id = j.id AND t.ended_on IS NULL
             LEFT JOIN %s p ON p.id = t.person_id
             LEFT JOIN %s o ON o.id = t.office_id
             WHERE j.official_name = %%s OR j.common_name = %%s
             LIMIT 1";

		$sql = $wpdb->prepare(
			sprintf(
				$query,
				$jurisdictions,
				$terms,
				$people,
				$offices
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
				'person_name'       => trim( $row['person_name'] ),
				'office_title'      => $row['office_title'],
				'started_on'        => $row['started_on'],
			),
			200
		);
	}
}

( new Jurisdictions() )->register();
