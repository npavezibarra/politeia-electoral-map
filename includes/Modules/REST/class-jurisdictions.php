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
		$election_results = $wpdb->prefix . 'politeia_election_results';


		// 1. Resolve Jurisdiction ID with robust matching
		// Try exact match first
		$jur_row = $wpdb->get_row($wpdb->prepare(
			"SELECT id, official_name, common_name, parent_id, type FROM $jurisdictions WHERE official_name = %s OR common_name = %s",
			$name,
			$name
		));

		if (!$jur_row) {
			// Try normalized variations
			// 1. Remove "Región " prefix (case insensitive in logic, but let's be explicit)
			$clean_name = trim(str_ireplace('Región ', '', $name));

			// 2. Try exact match with cleaned name (e.g. "de Atacama" -> "DE ATACAMA")
			$jur_row = $wpdb->get_row($wpdb->prepare(
				"SELECT id, official_name, common_name, parent_id, type FROM $jurisdictions WHERE official_name = %s OR common_name = %s",
				$clean_name,
				$clean_name
			));
		}

		if (!$jur_row && !empty($clean_name)) {
			// 3. Try LIKE match (e.g. "Metropolitana" -> matches "METROPOLITANA DE SANTIAGO")
			// We search for official_name containing the clean name.
			$like_query = '%' . $wpdb->esc_like($clean_name) . '%';
			$jur_row = $wpdb->get_row($wpdb->prepare(
				"SELECT id, official_name, common_name, parent_id, type FROM $jurisdictions WHERE official_name LIKE %s OR common_name LIKE %s LIMIT 1",
				$like_query,
				$like_query
			));
		}

		// Fallback for tricky cases (e.g. "O'Higgins" vs "Del Libertador...")
		if (!$jur_row && !empty($clean_name)) {
			$cleaner_name = trim(str_ireplace('de ', '', $clean_name)); // Remove leading 'de '
			if ($cleaner_name !== $clean_name) {
				$like_query_2 = '%' . $wpdb->esc_like($cleaner_name) . '%';
				$jur_row = $wpdb->get_row($wpdb->prepare(
					"SELECT id, official_name, common_name, parent_id, type FROM $jurisdictions WHERE official_name LIKE %s OR common_name LIKE %s LIMIT 1",
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
		$jur_type = $jur_row->type;

		$target_date = $req->get_param('date');
		if (!$target_date) {
			$target_date = current_time('Y-m-d');
		}

		// 2. Try to find an Office Term active at target_date
		// SKIP THIS for Districts/Circunscripciones as they have multiple officials handled later
		$term_row = null;
		if ($jur_type !== 'DISTRICT' && $jur_type !== 'SENATORIAL_CIRC') {
			// We filter by date overlap: started <= target AND (ended >= target OR (ended IS NULL AND status='ACTIVE' AND target >= started))

			$term_query = "SELECT 
                    CONCAT_WS(' ', p.given_names, p.paternal_surname, p.maternal_surname) AS person_name,
                    o.title AS office_title,
                    t.started_on,
                    t.ended_on as planned_end_on,
                    NULL as party_short_name,
                    (
                        SELECT c.votes 
                        FROM $candidacies c
                        WHERE c.person_id = t.person_id 
                        AND c.jurisdiction_id = t.jurisdiction_id
                        AND c.elected = 1
                        ORDER BY c.id DESC LIMIT 1
                    ) as votes,
                    (
                        SELECT er.total_votes
                        FROM $election_results er
                        INNER JOIN $candidacies c2 ON c2.election_id = er.election_id AND c2.jurisdiction_id = er.jurisdiction_id
                        WHERE c2.person_id = t.person_id 
                        AND c2.jurisdiction_id = t.jurisdiction_id
                        AND c2.elected = 1
                        ORDER BY c2.id DESC LIMIT 1
                    ) as total_votes
                FROM $office_terms t
                INNER JOIN $people p ON p.id = t.person_id
                INNER JOIN $offices o ON o.id = t.office_id
                WHERE t.jurisdiction_id = %d 
                AND t.started_on <= %s 
                AND (t.ended_on >= %s OR t.ended_on IS NULL)
                ORDER BY t.started_on DESC
                LIMIT 1";

			$term_row = $wpdb->get_row($wpdb->prepare($term_query, $jurisdiction_id, $target_date, $target_date), ARRAY_A);
		}

		// Fetch Parent Region Name
		$parent_region_name = null;
		if ($jur_row->parent_id) {
			$parent_region_name = $wpdb->get_var($wpdb->prepare(
				"SELECT official_name FROM $jurisdictions WHERE id = %d",
				$jur_row->parent_id
			));
		}

		if ($term_row) {
			return new \WP_REST_Response(
				array(
					'found' => true,
					'jurisdiction_name' => $jurisdiction_name,
					'common_name' => $common_name,
					'parent_region_name' => $parent_region_name,
					'person_name' => trim($term_row['person_name']),
					'photo_url' => null,
					'office_title' => $term_row['office_title'],
					'party_short_name' => '',
					'started_on' => $term_row['started_on'],
					'planned_end_on' => $term_row['planned_end_on'] ?: date('Y-m-d', strtotime($term_row['started_on'] . ' + 4 years')),
					'votes' => $term_row['votes'],
					'total_votes' => $term_row['total_votes'],
					// Fetch Pop for specific year if possible, else latest
					'population' => $wpdb->get_var($wpdb->prepare("SELECT population FROM " . $wpdb->prefix . "politeia_jurisdiction_populations WHERE jurisdiction_id = %d ORDER BY ABS(year - %d) ASC LIMIT 1", $jurisdiction_id, intval(substr($target_date, 0, 4)))),
					'budget' => null,
				),
				200
			);
		}

		// 3. Fallback: Candidacy Lookup (For Mayors/Alcaldes)
		// Find election closest to target_date (on or before)
		/* phpcs:disable WordPress.DB.PreparedSQL.NotPrepared */
		$cand_query = "SELECT 
			CONCAT_WS(' ', p.given_names, p.paternal_surname, p.maternal_surname) AS person_name,
			o.title AS office_title,
			po.short_name AS party_short_name,
			e.election_date AS started_on,
			DATE_ADD(e.election_date, INTERVAL 4 YEAR) AS planned_end_on,
			c.votes,
			er.total_votes,
			er.valid_votes,
			er.blank_votes,
			er.null_votes,
			c.profile_photo_url, (
				SELECT SUM(sub.votes)
				FROM (
					SELECT MAX(c3.votes) as votes
					FROM $candidacies c3
					INNER JOIN $people p3 ON p3.id = c3.person_id
					WHERE c3.election_id = c.election_id 
					  AND c3.jurisdiction_id = c.jurisdiction_id
					GROUP BY p3.given_names, p3.paternal_surname, p3.maternal_surname
				) as sub
			) as calculated_valid_votes
		FROM $candidacies c
		INNER JOIN $elections e ON e.id = c.election_id
		INNER JOIN $offices o ON o.id = e.office_id
		INNER JOIN $people p ON p.id = c.person_id
		LEFT JOIN $parties po ON po.id = c.party_id
		LEFT JOIN $election_results er ON er.election_id = e.id AND er.jurisdiction_id = c.jurisdiction_id
		WHERE c.jurisdiction_id = %d
		  AND o.code = 'ALCALDE'
		  AND c.elected = 1
		  AND e.election_date <= %s
		ORDER BY e.election_date DESC
		LIMIT 1";

		$cand_row = $wpdb->get_row($wpdb->prepare($cand_query, $jurisdiction_id, $target_date), ARRAY_A);
		/* phpcs:enable */

		if ($cand_row) {
			$total_votes = $cand_row['total_votes'];
			$valid_votes = $cand_row['valid_votes'];
			$blank_votes = floatval($cand_row['blank_votes']);
			$null_votes = floatval($cand_row['null_votes']);

			// Fallback: If 'valid_votes' is missing in 'results' table, use the sum we calculated from 'candidacies'
			if (empty($valid_votes) && !empty($cand_row['calculated_valid_votes'])) {
				$valid_votes = $cand_row['calculated_valid_votes'];
			}

			// Calculate total if missing
			if (empty($total_votes) && !empty($valid_votes)) {
				$total_votes = $valid_votes + $blank_votes + $null_votes;
			}

			// Fetch Population
			$pop_table = $wpdb->prefix . 'politeia_jurisdiction_populations';
			$population = $wpdb->get_var($wpdb->prepare(
				"SELECT population FROM $pop_table WHERE jurisdiction_id = %d ORDER BY year DESC LIMIT 1",
				$jurisdiction_id
			));

			return new \WP_REST_Response(
				array(
					'found' => true,
					'jurisdiction_name' => $jurisdiction_name,
					'common_name' => $common_name,
					'parent_region_name' => $parent_region_name,
					'person_name' => trim($cand_row['person_name']),
					'photo_url' => $cand_row['profile_photo_url'],
					'office_title' => $cand_row['office_title'],
					'party_short_name' => $cand_row['party_short_name'],
					'started_on' => $cand_row['started_on'],
					'planned_end_on' => $cand_row['planned_end_on'],
					'votes' => $cand_row['votes'],
					'total_votes' => $total_votes,
					'population' => $population,
					'budget' => null,
				),
				200
			);
		}

		// 4. Found jurisdiction but no official (e.g. might be a District or Region without Governor/Senate logic yet)

		// --- District Logic: Fetch Deputies --- 
		// If it's a District, we want to return the list of deputies (or at least one + count?)
		// The frontend expects: person_name, office_title, party, etc.
		// Since a District has MULTIPLE deputies, we might need to adjust the structure or just return the first one "and X others"
		// OR we can return a list in a new field 'officials' and let frontend handle it?
		// But for now, to fit the current frontend 'row', let's fetch the first active deputy and list others in a subtitle or similar?
		// Actually, `rm-map.html` has a `concejales` list. We could re-purpose that for Deputies list?

		// Let's check type first.
		$jur_type = $wpdb->get_var($wpdb->prepare("SELECT type FROM $jurisdictions WHERE id = %d", $jurisdiction_id));

		if ($jur_type === 'DISTRICT' || $jur_type === 'SENATORIAL_CIRC') {
			$office_code_target = ($jur_type === 'DISTRICT') ? 'DIPUTADO' : 'SENADOR';

			// Fetch Active Terms for this office
			$terms_query = "SELECT 
                    CONCAT_WS(' ', p.given_names, p.paternal_surname) AS person_name,
                    o.title AS office_title,
                    po.short_name AS party_short_name,
                    t.started_on,
                    t.ended_on,
                    t.planned_end_on
                FROM $office_terms t
                INNER JOIN $people p ON p.id = t.person_id
                INNER JOIN $offices o ON o.id = t.office_id
                -- Fix: Use candidacies to link party, since terms table has no party_id
                LEFT JOIN $candidacies c ON c.person_id = t.person_id AND c.elected = 1
                LEFT JOIN $parties po ON po.id = c.party_id
                WHERE t.jurisdiction_id = %d 
                  AND o.code = %s
                  AND t.started_on <= %s 
                  AND (t.ended_on >= %s OR t.ended_on IS NULL)
                ORDER BY t.started_on DESC, p.paternal_surname ASC";

			$officials = $wpdb->get_results($wpdb->prepare($terms_query, $jurisdiction_id, $office_code_target, $target_date, $target_date), ARRAY_A);

			if (!empty($officials)) {
				$first = $officials[0];

				// Prepare officials array for frontend list
				$officials_list = array_map(function ($o) {
					return [
						'name' => $o['person_name'],
						'title' => $o['office_title'],
						'party' => $o['party_short_name'] ?? '',
						'start' => $o['started_on'],
						'end' => $o['planned_end_on'] // Use planned end
					];
				}, $officials);

				return new \WP_REST_Response(
					array(
						'found' => true,
						'api_version' => 'v2.1', // Bump version to confirm new code
						'jurisdiction_name' => $jurisdiction_name,
						'common_name' => $common_name,
						'parent_region_name' => $parent_region_name,
						'person_name' => str_replace('  ', ' ', trim($first['person_name'])) . (count($officials) > 1 ? ' y ' . (count($officials) - 1) . ' más' : ''),
						'photo_url' => null,
						'office_title' => $first['office_title'] . (count($officials) > 1 ? 's' : ''), // Pluralize
						'party_short_name' => $first['party_short_name'],
						'started_on' => $first['started_on'],
						'planned_end_on' => $first['planned_end_on'] ?: date('Y-m-d', strtotime($first['started_on'] . ' + 4 years')), // Fix column
						'votes' => 0,
						'total_votes' => 0,
						'population' => 0,
						'budget' => null,
						'officials' => $officials_list, // Pass full list
						'debug_message' => "Found " . count($officials) . " officials."
					),
					200
				);
			} else {
				return new \WP_REST_Response(array(
					'found' => false,
					'debug_info' => "Jurisdiction ID: $jurisdiction_id ($jur_type). No active terms found for office $office_code_target on $target_date."
				), 200);
			}
		}

		return new \WP_REST_Response(array('found' => false, 'debug_info' => "Jurisdiction ID: $jurisdiction_id. No Mayor/Alcalde found."), 200);
	}
}

(new Jurisdictions())->register();
