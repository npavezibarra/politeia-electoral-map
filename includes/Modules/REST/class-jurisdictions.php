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

		// Table names
		$jurisdictions = $wpdb->prefix . 'politeia_jurisdictions';
		$candidacies = $wpdb->prefix . 'politeia_candidacies';
		$elections = $wpdb->prefix . 'politeia_elections';
		$people = $wpdb->prefix . 'politeia_people';
		$offices = $wpdb->prefix . 'politeia_offices';
		$parties = $wpdb->prefix . 'politeia_political_organizations';
		$office_terms = $wpdb->prefix . 'politeia_office_terms';


		// 1. Resolve Jurisdiction ID with robust matching
		// Try exact match first
		$jur_row = $wpdb->get_row($wpdb->prepare(
			"SELECT id, official_name, common_name, parent_id FROM $jurisdictions WHERE official_name = %s OR common_name = %s",
			$name,
			$name
		));

		if (!$jur_row) {
			// Try normalized variations
			// 1. Remove "Región " prefix (case insensitive in logic, but let's be explicit)
			$clean_name = trim(str_ireplace('Región ', '', $name));

			// 2. Try exact match with cleaned name (e.g. "de Atacama" -> "DE ATACAMA")
			$jur_row = $wpdb->get_row($wpdb->prepare(
				"SELECT id, official_name, common_name, parent_id FROM $jurisdictions WHERE official_name = %s OR common_name = %s",
				$clean_name,
				$clean_name
			));
		}

		if (!$jur_row && !empty($clean_name)) {
			// 3. Try LIKE match (e.g. "Metropolitana" -> matches "METROPOLITANA DE SANTIAGO")
			// We search for official_name containing the clean name.
			$like_query = '%' . $wpdb->esc_like($clean_name) . '%';
			$jur_row = $wpdb->get_row($wpdb->prepare(
				"SELECT id, official_name, common_name, parent_id FROM $jurisdictions WHERE official_name LIKE %s OR common_name LIKE %s LIMIT 1",
				$like_query,
				$like_query
			));
		}

		// Fallback for tricky cases (e.g. "O'Higgins" vs "Del Libertador...")
		// If clean_name has "de ", try removing it too? 
		// "de Atacama" -> "Atacama".
		if (!$jur_row && !empty($clean_name)) {
			$cleaner_name = trim(str_ireplace('de ', '', $clean_name)); // Remove leading 'de '
			if ($cleaner_name !== $clean_name) {
				$like_query_2 = '%' . $wpdb->esc_like($cleaner_name) . '%';
				$jur_row = $wpdb->get_row($wpdb->prepare(
					"SELECT id, official_name, common_name, parent_id FROM $jurisdictions WHERE official_name LIKE %s OR common_name LIKE %s LIMIT 1",
					$like_query_2,
					$like_query_2
				));
			}
		}

		if (!$jur_row) {
			return new \WP_REST_Response(array('found' => false), 200);
		}

		$jurisdiction_id = $jur_row->id;
		$jurisdiction_name = $jur_row->official_name;
		$common_name = $jur_row->common_name;

		// 2. Try to find an ACTIVE Office Term (e.g. Governor)
		// Modified to fetch votes from Candidacies if available
		$term_query = "SELECT 
				CONCAT_WS(' ', p.given_names, p.paternal_surname, p.maternal_surname) AS person_name,
				o.title AS office_title,
				t.started_on,
				NULL as planned_end_on,
				NULL as party_short_name,
				(
                    SELECT c.votes 
                    FROM $candidacies c
                    WHERE c.person_id = t.person_id 
                      AND c.jurisdiction_id = t.jurisdiction_id
                      AND c.elected = 1
                    ORDER BY c.id DESC LIMIT 1
                ) as votes
			FROM $office_terms t
			INNER JOIN $people p ON p.id = t.person_id
			INNER JOIN $offices o ON o.id = t.office_id
			WHERE t.jurisdiction_id = %d AND t.status = 'ACTIVE'
			LIMIT 1";

		$term_row = $wpdb->get_row($wpdb->prepare($term_query, $jurisdiction_id), ARRAY_A);

		// Fetch Parent Region Name
		$parent_region_name = null;
		if ($jur_row->parent_id) {
			$parent_region_name = $wpdb->get_var($wpdb->prepare(
				"SELECT official_name FROM $jurisdictions WHERE id = %d",
				$jur_row->parent_id
			));
		}

		if ($term_row) {
			// Found an active official (e.g. Governor)
			return new \WP_REST_Response(
				array(
					'found' => true,
					'jurisdiction_name' => $jurisdiction_name,
					'common_name' => $common_name,
					'parent_region_name' => $parent_region_name,
					'person_name' => trim($term_row['person_name']),
					'photo_url' => null,
					'office_title' => $term_row['office_title'],
					'party_short_name' => '', // TODO: Fetch from memberships
					'started_on' => $term_row['started_on'],
					'planned_end_on' => date('Y-m-d', strtotime($term_row['started_on'] . ' + 4 years')),
					'votes' => $term_row['votes'], // Now utilizing fetched votes
					'population' => null,
					'budget' => null,
				),
				200
			);
		}

		// 3. Fallback: Candidacy Lookup (For Mayors/Alcaldes existing logic)
		// Note: The previous query was looking for 'ALCALDE' specifically.
		/* phpcs:disable WordPress.DB.PreparedSQL.NotPrepared */
		$cand_query = "SELECT 
			CONCAT_WS(' ', p.given_names, p.paternal_surname, p.maternal_surname) AS person_name,
			o.title AS office_title,
			po.short_name AS party_short_name,
			e.election_date AS started_on,
			DATE_ADD(e.election_date, INTERVAL 4 YEAR) AS planned_end_on,
			c.votes
		FROM $candidacies c
		INNER JOIN $elections e ON e.id = c.election_id
		INNER JOIN $offices o ON o.id = e.office_id
		INNER JOIN $people p ON p.id = c.person_id
		LEFT JOIN $parties po ON po.id = c.party_id
		WHERE c.jurisdiction_id = %d
		  AND o.code = 'ALCALDE'
		  AND c.elected = 1
		ORDER BY e.election_date DESC
		LIMIT 1";

		$cand_row = $wpdb->get_row($wpdb->prepare($cand_query, $jurisdiction_id), ARRAY_A);
		/* phpcs:enable */

		if ($cand_row) {
			return new \WP_REST_Response(
				array(
					'found' => true,
					'jurisdiction_name' => $jurisdiction_name,
					'common_name' => $common_name,
					'parent_region_name' => $parent_region_name,
					'person_name' => trim($cand_row['person_name']),
					'photo_url' => null,
					'office_title' => $cand_row['office_title'],
					'party_short_name' => $cand_row['party_short_name'],
					'started_on' => $cand_row['started_on'],
					'planned_end_on' => $cand_row['planned_end_on'],
					'votes' => $cand_row['votes'],
					'population' => null,
					'budget' => null,
				),
				200
			);
		}

		// 4. Found jurisdiction but no official
		// Return basic info? Or found=false?
		// rm-map.html expects 'person_name' etc. returning found=false is safer to avoid empty card.
		return new \WP_REST_Response(array('found' => false), 200);
	}
}

(new Jurisdictions())->register();
