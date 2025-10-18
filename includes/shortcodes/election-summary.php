<?php
/**
 * Shortcode to render an aggregated election summary table.
 *
 * @package PoliteiaElectoralMap\Shortcodes
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'politeia_render_election_summary' ) ) {
        /**
         * Render the election summary table.
         *
         * @return string
         */
        function politeia_render_election_summary() {
                global $wpdb;

                if ( defined( 'PLEM_URL' ) ) {
                        wp_enqueue_style(
                                'politeia-election-summary',
                                trailingslashit( PLEM_URL ) . 'assets/css/election-summary.css',
                                array(),
                                defined( 'PLEM_VERSION' ) ? PLEM_VERSION : false
                        );
                }

                $tables = array(
                        'elections'          => $wpdb->prefix . 'politeia_elections',
                        'offices'            => $wpdb->prefix . 'politeia_offices',
                        'candidacies'        => $wpdb->prefix . 'politeia_candidacies',
                        'people'             => $wpdb->prefix . 'politeia_people',
                        'party_memberships'  => $wpdb->prefix . 'politeia_party_memberships',
                        'turnout'            => $wpdb->prefix . 'politeia_turnout',
                        'jurisdictions'      => $wpdb->prefix . 'politeia_jurisdictions',
                );

                $query = sprintf(
                        "SELECT\n  e.title AS election_title,\n  CASE\n    WHEN o.allowed_scope = 'COMMUNE' THEN 346\n    WHEN o.allowed_scope = 'REGION'  THEN 16\n    WHEN o.allowed_scope = 'NATIONAL' THEN 1\n    ELSE COUNT(DISTINCT e.jurisdiction_id)\n  END AS expected_jurisdictions,\n  SUM(CASE WHEN c.elected = 1 AND c.person_id IS NOT NULL THEN 1 ELSE 0 END) AS name_in_db,\n  SUM(CASE WHEN c.elected = 1 AND pm.id IS NOT NULL THEN 1 ELSE 0 END) AS pp_membership,\n  COUNT(DISTINCT CASE WHEN e.jurisdiction_id IS NOT NULL AND j.id IS NOT NULL THEN e.jurisdiction_id END) AS jurisdictions_linked,\n  SUM(t.valid_votes) AS valid_votes,\n  SUM(t.null_votes) AS null_votes,\n  SUM(t.blank_votes) AS blank_votes,\n  SUM(t.registered_voters) AS registered_voters,\n  ROUND(\n    100 * SUM(CASE WHEN c.elected = 1 AND c.person_id IS NOT NULL THEN 1 ELSE 0 END) /\n    NULLIF(\n      CASE\n        WHEN o.allowed_scope = 'COMMUNE' THEN 346\n        WHEN o.allowed_scope = 'REGION'  THEN 16\n        WHEN o.allowed_scope = 'NATIONAL' THEN 1\n        ELSE COUNT(DISTINCT e.jurisdiction_id)\n      END,\n      0\n    ), 1\n  ) AS pct_complete\nFROM %s e\nJOIN %s o ON o.id = e.office_id\nLEFT JOIN %s c ON c.election_id = e.id\nLEFT JOIN %s p ON p.id = c.person_id\nLEFT JOIN %s pm ON pm.person_id = p.id AND pm.ended_on IS NULL\nLEFT JOIN %s t ON t.election_id = e.id\nLEFT JOIN %s j ON j.id = e.jurisdiction_id\nWHERE e.title IS NOT NULL\nGROUP BY e.title, o.allowed_scope\nORDER BY MAX(e.election_date) DESC, e.title ASC",
                        $tables['elections'],
                        $tables['offices'],
                        $tables['candidacies'],
                        $tables['people'],
                        $tables['party_memberships'],
                        $tables['turnout'],
                        $tables['jurisdictions']
                );

                $results = $wpdb->get_results( $query );

                if ( empty( $results ) ) {
                        return '<p class="politeia-summary__empty">' . esc_html__( 'No election data available.', 'politeia-electoral-map' ) . '</p>';
                }

                ob_start();
                ?>
                <table class="politeia-summary">
                        <thead>
                                <tr>
                                        <th><?php esc_html_e( 'Election', 'politeia-electoral-map' ); ?></th>
                                        <th><?php esc_html_e( 'Expected Jurisdictions', 'politeia-electoral-map' ); ?></th>
                                        <th><?php esc_html_e( 'Name in DB', 'politeia-electoral-map' ); ?></th>
                                        <th><?php esc_html_e( 'PP Membership', 'politeia-electoral-map' ); ?></th>
                                        <th><?php esc_html_e( 'Jurisdiction Linked', 'politeia-electoral-map' ); ?></th>
                                        <th><?php esc_html_e( 'Registered Voters', 'politeia-electoral-map' ); ?></th>
                                        <th><?php esc_html_e( 'Valid Votes', 'politeia-electoral-map' ); ?></th>
                                        <th><?php esc_html_e( 'Null Votes', 'politeia-electoral-map' ); ?></th>
                                        <th><?php esc_html_e( 'Blank Votes', 'politeia-electoral-map' ); ?></th>
                                        <th><?php esc_html_e( '% Complete', 'politeia-electoral-map' ); ?></th>
                                        <th><?php esc_html_e( 'Status', 'politeia-electoral-map' ); ?></th>
                                        <th><?php esc_html_e( 'Observations', 'politeia-electoral-map' ); ?></th>
                                </tr>
                        </thead>
                        <tbody>
                                <?php foreach ( $results as $row ) :
                                        $pct_complete = is_null( $row->pct_complete ) ? 0.0 : (float) $row->pct_complete;
                                        if ( $pct_complete >= 100 ) {
                                                $status_text  = __( 'Complete', 'politeia-electoral-map' );
                                                $status_class = 'status--complete';
                                                $status_icon  = '✅';
                                        } elseif ( $pct_complete >= 80 ) {
                                                $status_text  = __( 'Partial', 'politeia-electoral-map' );
                                                $status_class = 'status--partial';
                                                $status_icon  = '⚠️';
                                        } else {
                                                $status_text  = __( 'Incomplete', 'politeia-electoral-map' );
                                                $status_class = 'status--incomplete';
                                                $status_icon  = '❌';
                                        }

                                        $expected_jurisdictions = (int) $row->expected_jurisdictions;
                                        $name_in_db              = (int) $row->name_in_db;
                                        $pp_membership           = (int) $row->pp_membership;
                                        $jurisdictions_linked    = (int) $row->jurisdictions_linked;
                                        $registered_voters       = is_null( $row->registered_voters ) ? 0 : (int) $row->registered_voters;
                                        $valid_votes             = is_null( $row->valid_votes ) ? 0 : (int) $row->valid_votes;
                                        $null_votes              = is_null( $row->null_votes ) ? 0 : (int) $row->null_votes;
                                        $blank_votes             = is_null( $row->blank_votes ) ? 0 : (int) $row->blank_votes;
                                ?>
                                <tr>
                                        <td class="politeia-summary__cell-title"><?php echo esc_html( $row->election_title ); ?></td>
                                        <td><?php echo esc_html( $expected_jurisdictions ); ?></td>
                                        <td><?php echo esc_html( $name_in_db ); ?></td>
                                        <td><?php echo esc_html( $pp_membership ); ?></td>
                                        <td><?php echo esc_html( $jurisdictions_linked ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( $registered_voters ) ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( $valid_votes ) ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( $null_votes ) ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( $blank_votes ) ); ?></td>
                                        <td><?php echo esc_html( number_format_i18n( $pct_complete, 1 ) ); ?>%</td>
                                        <td class="politeia-summary__status <?php echo esc_attr( $status_class ); ?>">
                                                <span aria-hidden="true"><?php echo esc_html( $status_icon ); ?></span>
                                                <span class="screen-reader-text"><?php echo esc_html( $status_text ); ?></span>
                                        </td>
                                        <td>—</td>
                                </tr>
                                <?php endforeach; ?>
                        </tbody>
                </table>
                <?php
                return trim( ob_get_clean() );
        }

        add_shortcode( 'politeia_election_summary', 'politeia_render_election_summary' );
}
