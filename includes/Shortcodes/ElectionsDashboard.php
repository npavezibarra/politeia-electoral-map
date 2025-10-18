<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

add_action( 'init', 'politeia_register_elections_dashboard_shortcode' );

/**
 * Registers the [politeia_elections_dashboard] shortcode.
 *
 * @return void
 */
function politeia_register_elections_dashboard_shortcode() {
        add_shortcode( 'politeia_elections_dashboard', 'politeia_render_elections_dashboard' );
}

/**
 * Returns the expected number of jurisdictions for the provided level.
 *
 * @param string $nivel Jurisdiction type.
 *
 * @return int
 */
function politeia_elections_dashboard_expected_jurisdictions( $nivel ) {
        $lookup = array(
                'COMMUNE'   => 346,
                'MUNICIPAL' => 346,
                'REGION'    => 16,
                'PROVINCE'  => 56,
                'DISTRICT'  => 28,
                'NATIONAL'  => 1,
        );

        $normalized = strtoupper( (string) $nivel );

        foreach ( $lookup as $key => $value ) {
                if ( false !== strpos( $normalized, $key ) ) {
                        return (int) $value;
                }
        }

        return 0;
}

/**
 * Renders the dashboard table.
 *
 * @return string
 */
function politeia_render_elections_dashboard() {
        global $wpdb;

        $tables = array(
                'elections'     => $wpdb->prefix . 'politeia_elections',
                'offices'       => $wpdb->prefix . 'politeia_offices',
                'jurisdictions' => $wpdb->prefix . 'politeia_jurisdictions',
                'candidacies'   => $wpdb->prefix . 'politeia_candidacies',
                'turnout'       => $wpdb->prefix . 'politeia_turnout',
                'office_terms'  => $wpdb->prefix . 'politeia_office_terms',
        );

        $query = <<<SQL
SELECT 
        e.id AS election_id,
        e.title AS ciclo_electoral,
        YEAR(e.election_date) AS election_year,
        o.title AS cargo,
        j.type AS nivel,
        COUNT(DISTINCT c.id) AS candidaturas_db,
        SUM(CASE WHEN c.votes IS NOT NULL AND c.votes > 0 THEN 1 ELSE 0 END) AS con_votos,
        SUM(CASE WHEN c.party_id IS NOT NULL THEN 1 ELSE 0 END) AS con_partido,
        COUNT(DISTINCT t.id) AS con_officeterm,
        MAX(CASE WHEN tr.id IS NULL THEN 0 ELSE 1 END) AS con_turnout
FROM {$tables['elections']} e
INNER JOIN {$tables['offices']} o ON o.id = e.office_id
INNER JOIN {$tables['jurisdictions']} j ON j.id = e.jurisdiction_id
LEFT JOIN {$tables['candidacies']} c ON c.election_id = e.id
LEFT JOIN {$tables['turnout']} tr ON tr.election_id = e.id
LEFT JOIN {$tables['office_terms']} t ON t.office_id = e.office_id AND t.jurisdiction_id = e.jurisdiction_id
GROUP BY e.id, e.title, e.election_date, o.title, j.type
ORDER BY e.election_date DESC
SQL;

        $results = $wpdb->get_results( $query );

        if ( empty( $results ) ) {
                return '<p>' . esc_html__( 'No election data found.', 'politeia-electoral-map' ) . '</p>';
        }

        wp_enqueue_style(
                'plem-elections-dashboard',
                PLEM_URL . 'assets/css/dashboard.css',
                array(),
                PLEM_VERSION
        );

        wp_enqueue_script(
                'plem-elections-dashboard',
                PLEM_URL . 'assets/js/dashboard.js',
                array(),
                PLEM_VERSION,
                true
        );

        ob_start();
        ?>
        <div class="politeia-dashboard__wrapper">
                <table class="politeia-dashboard" data-plem-dashboard>
                        <thead>
                                <tr>
                                        <th scope="col" data-type="numeric"><?php esc_html_e( 'Election ID', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Ciclo Electoral', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col" data-type="numeric"><?php esc_html_e( 'Year', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Cargo', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Nivel', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col" data-type="numeric"><?php esc_html_e( 'Expected Jurisdictions', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col" data-type="numeric"><?php esc_html_e( 'Candidaturas', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col" data-type="numeric"><?php esc_html_e( 'With Votes', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col" data-type="numeric"><?php esc_html_e( 'With Party', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col" data-type="numeric"><?php esc_html_e( 'With Turnout', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col" data-type="numeric"><?php esc_html_e( 'With Office Term', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col" data-type="numeric"><?php esc_html_e( '% Complete', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Status', 'politeia-electoral-map' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Observations', 'politeia-electoral-map' ); ?></th>
                                </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $results as $row ) :
                                $expected         = politeia_elections_dashboard_expected_jurisdictions( $row->nivel );
                                $expected         = max( 0, (int) $expected );
                                $candidacies      = (int) $row->candidaturas_db;
                                $turnout_indicator = (int) $row->con_turnout > 0 ? 1 : 0;
                                $percent          = ( $expected > 0 ) ? round( ( $candidacies / $expected ) * 100, 1 ) : 0.0;

                                if ( $percent > 100 ) {
                                        $percent = 100.0;
                                }

                                if ( 100.0 === $percent ) {
                                        $status_text  = __( '✅ Complete', 'politeia-electoral-map' );
                                        $status_class = 'politeia-dashboard__status--complete';
                                } elseif ( $percent >= 80.0 ) {
                                        $status_text  = __( '⚠️ Partial', 'politeia-electoral-map' );
                                        $status_class = 'politeia-dashboard__status--partial';
                                } else {
                                        $status_text  = __( '❌ Incomplete', 'politeia-electoral-map' );
                                        $status_class = 'politeia-dashboard__status--incomplete';
                                }

                                $percent_display  = function_exists( 'number_format_i18n' ) ? number_format_i18n( $percent, 1 ) : number_format( $percent, 1 );
                                $expected_display = function_exists( 'number_format_i18n' ) ? number_format_i18n( $expected ) : number_format( $expected );
                                ?>
                                <tr>
                                        <td data-label="<?php esc_attr_e( 'Election ID', 'politeia-electoral-map' ); ?>"><?php echo esc_html( $row->election_id ); ?></td>
                                        <td data-label="<?php esc_attr_e( 'Ciclo Electoral', 'politeia-electoral-map' ); ?>"><?php echo esc_html( $row->ciclo_electoral ); ?></td>
                                        <td data-label="<?php esc_attr_e( 'Year', 'politeia-electoral-map' ); ?>"><?php echo esc_html( $row->election_year ); ?></td>
                                        <td data-label="<?php esc_attr_e( 'Cargo', 'politeia-electoral-map' ); ?>"><?php echo esc_html( $row->cargo ); ?></td>
                                        <td data-label="<?php esc_attr_e( 'Nivel', 'politeia-electoral-map' ); ?>"><?php echo esc_html( $row->nivel ); ?></td>
                                        <td data-label="<?php esc_attr_e( 'Expected Jurisdictions', 'politeia-electoral-map' ); ?>"><?php echo esc_html( $expected_display ); ?></td>
                                        <td data-label="<?php esc_attr_e( 'Candidaturas', 'politeia-electoral-map' ); ?>"><?php echo esc_html( $candidacies ); ?></td>
                                        <td data-label="<?php esc_attr_e( 'With Votes', 'politeia-electoral-map' ); ?>"><?php echo esc_html( (int) $row->con_votos ); ?></td>
                                        <td data-label="<?php esc_attr_e( 'With Party', 'politeia-electoral-map' ); ?>"><?php echo esc_html( (int) $row->con_partido ); ?></td>
                                        <td data-label="<?php esc_attr_e( 'With Turnout', 'politeia-electoral-map' ); ?>"><?php echo esc_html( $turnout_indicator ); ?></td>
                                        <td data-label="<?php esc_attr_e( 'With Office Term', 'politeia-electoral-map' ); ?>"><?php echo esc_html( (int) $row->con_officeterm ); ?></td>
                                        <td data-label="<?php esc_attr_e( '% Complete', 'politeia-electoral-map' ); ?>"><?php echo esc_html( $percent_display ); ?>%</td>
                                        <td class="politeia-dashboard__status <?php echo esc_attr( $status_class ); ?>" data-label="<?php esc_attr_e( 'Status', 'politeia-electoral-map' ); ?>"><?php echo esc_html( $status_text ); ?></td>
                                        <td data-label="<?php esc_attr_e( 'Observations', 'politeia-electoral-map' ); ?>">&mdash;</td>
                                </tr>
                        <?php endforeach; ?>
                        </tbody>
                </table>
        </div>
        <?php

        return ob_get_clean();
}
