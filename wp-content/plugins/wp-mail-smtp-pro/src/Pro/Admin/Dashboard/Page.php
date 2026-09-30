<?php

namespace WPMailSMTP\Pro\Admin\Dashboard;

use DateTime;
use WPMailSMTP\Admin\Dashboard\Page as PageBase;
use WPMailSMTP\WP;

/**
 * Dashboard page (Pro), adds the date-range selector.
 *
 * @since 4.10.0
 */
class Page extends PageBase {

	/**
	 * Longest range accepted from the request, in days, matching the AJAX endpoint's
	 * own cap so both entry points agree on what is too wide.
	 *
	 * @since 4.10.0
	 */
	private const MAX_RANGE_DAYS = 366;

	/**
	 * Enqueue the Dashboard assets.
	 *
	 * @since 4.10.0
	 */
	public function enqueue_assets(): void {

		wp_enqueue_style(
			'wp-mail-smtp-admin-flatpickr',
			wp_mail_smtp()->assets_url . '/css/vendor/flatpickr.min.css',
			[],
			'4.6.9'
		);

		wp_enqueue_script(
			'wp-mail-smtp-admin-flatpickr',
			wp_mail_smtp()->assets_url . '/js/vendor/flatpickr.min.js',
			[],
			'4.6.9',
			false
		);

		parent::enqueue_assets();
	}

	/**
	 * Get the Dashboard script dependencies, adding flatpickr.
	 *
	 * @since 4.10.0
	 *
	 * @return array
	 */
	protected function get_script_dependencies(): array {

		return array_merge( parent::get_script_dependencies(), [ 'wp-mail-smtp-admin-flatpickr' ] );
	}

	/**
	 * Get the data localized for the Dashboard page script, adding the AJAX nonce and
	 * the date-range-only modules (the stats updater and the range control itself).
	 *
	 * @since 4.10.0
	 *
	 * @return array
	 */
	protected function get_localized_data(): array {

		$data = parent::get_localized_data();
		$min  = WP::asset_min();

		$data['nonce'] = wp_create_nonce( 'wp-mail-smtp-admin' );

		$data['modules'][] = [
			'name' => 'updater',
			'path' => wp_mail_smtp()->pro->assets_url . "/js/smtp-pro-dashboard-updater{$min}.js",
		];

		$data['modules'][] = [
			'name' => 'dateRange',
			'path' => wp_mail_smtp()->pro->assets_url . "/js/smtp-pro-dashboard-date-range{$min}.js",
		];

		$data['i18n'] = array_merge(
			$data['i18n'],
			[
				'reload'          => esc_html__( 'Reload', 'wp-mail-smtp-pro' ),
				'retry'           => esc_html__( 'Retry', 'wp-mail-smtp-pro' ),
				'session_expired' => esc_html__( 'Your session has expired. Please reload the page.', 'wp-mail-smtp-pro' ),
				'network_failure' => esc_html__( 'Something went wrong while loading that date range.', 'wp-mail-smtp-pro' ),
			]
		);

		return $data;
	}

	/**
	 * Get the date-range datepicker rendered HTML.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	protected function get_datepicker_html(): string {

		$active = $this->get_active_date_range_value();
		$bounds = $active === 'custom' ? $this->get_requested_range_bounds() : null;

		return (string) wp_mail_smtp_render(
			'pro/dashboard/date-range',
			[
				'options' => $this->get_date_range_options(),
				'active'  => $active,
				'custom'  => $bounds === null ? '' : implode( ' - ', $bounds ),
			],
			true
		);
	}

	/**
	 * Get the date-range preset options, the same set Email Reports offers.
	 *
	 * @since 4.10.0
	 *
	 * @return array Labels keyed by option value: a day count, or `custom`.
	 */
	protected function get_date_range_options(): array {

		return [
			'7'      => esc_html__( 'Last 7 Days', 'wp-mail-smtp-pro' ),
			'14'     => esc_html__( 'Last 14 Days', 'wp-mail-smtp-pro' ),
			'30'     => esc_html__( 'Last 30 Days', 'wp-mail-smtp-pro' ),
			'custom' => esc_html__( 'Custom Date Range', 'wp-mail-smtp-pro' ),
		];
	}

	/**
	 * The option value matching the range currently in effect, so a reload reflects the
	 * real range instead of always showing the first option.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	protected function get_active_date_range_value(): string {

		// Read-only page state reflecting the control back to itself, not a form
		// submission: nothing here changes as a result.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requested = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';

		if ( $requested === '' ) {
			return (string) Stats::DEFAULT_RANGE_DAYS;
		}

		foreach ( $this->get_date_range_options() as $value => $label ) {
			if ( $value !== 'custom' && $requested === $this->get_preset_range_string( (int) $value ) ) {
				return $value;
			}
		}

		return 'custom';
	}

	/**
	 * Build the `"Y-m-d - Y-m-d"` bounds a day-count preset resolves to today, mirroring
	 * the date-range JS module's own `presetRange()`.
	 *
	 * @since 4.10.0
	 *
	 * @param int $days Preset day count.
	 *
	 * @return string
	 */
	private function get_preset_range_string( int $days ): string {

		return implode( ' - ', $this->get_preset_range( $days ) );
	}

	/**
	 * The `[ from, to ]` bounds a day-count preset resolves to, from the data layer's
	 * own definition, so the label on screen and the data under it cannot drift apart.
	 *
	 * @since 4.10.0
	 *
	 * @param int $days Preset day count.
	 *
	 * @return array `[ from, to ]`, both `Y-m-d`.
	 */
	private function get_preset_range( int $days ): array {

		return ( new Stats() )->get_preset_range( $days );
	}

	/**
	 * The date range the first paint is scoped to: whatever the request carries, or
	 * the default preset.
	 *
	 * @since 4.10.0
	 *
	 * @return array|null `[ from, to ]`, both `Y-m-d`.
	 */
	protected function get_initial_date_range(): ?array {

		return $this->get_requested_range_bounds() ?? $this->get_preset_range( Stats::DEFAULT_RANGE_DAYS );
	}

	/**
	 * The `[ from, to ]` bounds carried by the request, or null when it carries none or
	 * something unusable. Validated exactly as the AJAX re-render validates its own input.
	 *
	 * @since 4.10.0
	 *
	 * @return array|null `[ from, to ]`, both `Y-m-d`.
	 */
	private function get_requested_range_bounds(): ?array {

		// Read-only page state reflecting the request back into the render, not a form
		// submission: nothing here changes as a result.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw   = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
		$parts = array_map( 'trim', explode( ' - ', $raw ) );

		if ( count( $parts ) !== 2 ) {
			return null;
		}

		[ $from, $to ] = $parts;

		if ( ! $this->is_valid_range_date( $from ) || ! $this->is_valid_range_date( $to ) || $from > $to ) {
			return null;
		}

		if ( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS > self::MAX_RANGE_DAYS ) {
			return null;
		}

		return [ $from, $to ];
	}

	/**
	 * Whether a string is a valid `Y-m-d` date. Round-trips the parsed date back to a
	 * string so an overflowing one (`2024-02-30`) does not silently pass as March 1st.
	 *
	 * @since 4.10.0
	 *
	 * @param string $date Date string.
	 *
	 * @return bool
	 */
	private function is_valid_range_date( string $date ): bool {

		$parsed = DateTime::createFromFormat( 'Y-m-d', $date );

		return $parsed !== false && $parsed->format( 'Y-m-d' ) === $date;
	}
}
