<?php

namespace WPMailSMTP\Pro\Admin\Dashboard;

use DateTime;
use WPMailSMTP\Admin\Dashboard\AccessContext;
use WPMailSMTP\Admin\Dashboard\Ajax as AjaxBase;

/**
 * Dashboard AJAX endpoints (Pro).
 *
 * @since 4.10.0
 */
class Ajax extends AjaxBase {

	/**
	 * Maximum span, in days, a custom date range may cover.
	 *
	 * @since 4.10.0
	 */
	private const MAX_RANGE_DAYS = 366;

	/**
	 * Register hooks.
	 *
	 * @since 4.10.0
	 */
	public function hooks(): void {

		parent::hooks();

		add_action( 'wp_ajax_wp_mail_smtp_dashboard_get_stats', [ $this, 'get_stats' ] );
	}

	/**
	 * Re-render the Email Log and Email Sources widgets for a new date range, and
	 * refresh the graph series alongside them.
	 *
	 * @since 4.10.0
	 */
	public function get_stats(): void {

		$access = $this->validate_request();
		$range  = $this->get_requested_date_range();

		if ( $range === null ) {
			$this->send_error( 'range', esc_html__( 'Invalid date range.', 'wp-mail-smtp-pro' ) );
		}

		$data     = $this->get_widget_data( $range );
		$response = [ 'series' => $data['chart_series'] ];

		foreach ( $this->get_range_widgets( $access ) as $key => $widget ) {
			$state = $widget->get_state();

			if ( ! $state->is_visible() ) {
				continue;
			}

			$response[ $key ] = $widget->render( $state->get_variant(), $data );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Parse and validate the posted `"Y-m-d - Y-m-d"` date range.
	 *
	 * @since 4.10.0
	 *
	 * @return array|null `[ from, to ]`, both `Y-m-d`, or null when the posted value is malformed.
	 */
	protected function get_requested_date_range(): ?array {

		// Nonce verified via validate_request() before this runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw   = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
		$parts = array_map( 'trim', explode( ' - ', $raw ) );

		if ( count( $parts ) !== 2 ) {
			return null;
		}

		[ $from, $to ] = $parts;

		if ( ! $this->is_valid_date( $from ) || ! $this->is_valid_date( $to ) || $from > $to ) {
			return null;
		}

		if ( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS > self::MAX_RANGE_DAYS ) {
			return null;
		}

		return [ $from, $to ];
	}

	/**
	 * Whether a string is a valid `Y-m-d` date. Round-trips the parsed date back to a
	 * string so an overflowing one (`2024-02-30`) doesn't silently pass as March 1st.
	 *
	 * @since 4.10.0
	 *
	 * @param string $date Date string.
	 *
	 * @return bool
	 */
	protected function is_valid_date( string $date ): bool {

		$parsed = DateTime::createFromFormat( 'Y-m-d', $date );

		return $parsed instanceof DateTime && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Build the aggregated data the range-scoped widgets render from: only the keys
	 * `get_range_widgets()` and the response read.
	 *
	 * @since 4.10.0
	 *
	 * @param array $range Requested date range, `[ from, to ]`.
	 *
	 * @return array
	 */
	protected function get_widget_data( array $range ): array {

		$stats = new Stats();

		return [
			'stat_totals'  => $stats->get_stat_totals( $range ),
			'stat_deltas'  => $stats->get_stat_deltas( $range ),
			'chart_series' => $stats->get_series( $range ),
			'date_range'   => $range,
		];
	}

	/**
	 * The widgets this endpoint re-renders, keyed by the response field carrying
	 * their markup.
	 *
	 * @since 4.10.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return array
	 */
	protected function get_range_widgets( AccessContext $access ): array {

		return [
			'stat_cards_html'    => new Widgets\StatCards( $access ),
			'email_log_html'     => new Widgets\EmailLog( $access ),
			'email_sources_html' => new Widgets\EmailSources( $access ),
		];
	}
}
