<?php

namespace WPMailSMTP\Pro\Admin\Dashboard\Widgets;

use WPMailSMTP\Admin\Dashboard\Widgets\StatCards as StatCardsBase;

// wpms:icon-[fa6-solid--envelope] wpms:icon-[fa7-solid--ban] wpms:icon-[fa6-solid--circle-check]
// wpms:icon-[fa7-regular--eye] are built from an `icon` value, so they are named here
// for Tailwind's scanner.
/**
 * Dashboard stat cards row (Pro): the metrics the Email Reports page leads with, on the
 * same terms.
 *
 * @since 4.10.0
 */
class StatCards extends StatCardsBase {

	/**
	 * The stat cards, in display order.
	 *
	 * @since 4.10.0
	 *
	 * @param array $data Aggregated data.
	 *
	 * @return array
	 */
	public function get_cards( array $data ) {

		// Sent counts confirmed and unconfirmed sends together, which is the split a
		// mailer with delivery verification produces and what one without it reports
		// wholesale.
		$cards = [
			$this->get_card( $data, 'total', esc_html__( 'Total Emails', 'wp-mail-smtp-pro' ), 'fa6-solid--envelope', 'emails' ),
			$this->get_card( $data, 'sent', esc_html__( 'Sent', 'wp-mail-smtp-pro' ), 'fa6-solid--circle-check', 'sent' ),
			$this->get_card( $data, 'failed', esc_html__( 'Failed', 'wp-mail-smtp-pro' ), 'fa7-solid--ban', 'failed' ),
		];

		if ( $this->is_open_tracking_enabled() ) {
			$cards[] = $this->get_card( $data, 'opened', esc_html__( 'Opened', 'wp-mail-smtp-pro' ), 'fa7-regular--eye', 'opened' );
		}

		return $cards;
	}

	/**
	 * One unlocked card over a metric's own value and delta for the selected range.
	 *
	 * @since 4.10.0
	 *
	 * @param array  $data   Aggregated data.
	 * @param string $metric Metric key in `stat_totals` and `stat_deltas`.
	 * @param string $label  Card label.
	 * @param string $icon   Iconify identifier (set--name) for the card's icon.
	 * @param string $tone   Icon tint.
	 *
	 * @return array
	 */
	private function get_card( array $data, string $metric, string $label, string $icon, string $tone ): array {

		[ $value, $delta ] = $this->get_metric( $data, $metric );

		return $this->build_card( $metric, $label, $value, $delta, false, $icon, $tone );
	}

	/**
	 * Whether open tracking is recording the figure the Opened card would show.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	protected function is_open_tracking_enabled() {

		return wp_mail_smtp()->get_pro()->get_logs()->is_enabled_open_email_tracking();
	}
}
