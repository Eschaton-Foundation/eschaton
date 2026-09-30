<?php

namespace WPMailSMTP\Pro\Admin\Dashboard\Widgets;

use WPMailSMTP\Admin\Area;
use WPMailSMTP\Admin\Dashboard\Widgets\EmailsOverview as EmailsOverviewBase;
use WPMailSMTP\Pro\Admin\Dashboard\LogsDisabled;
use WPMailSMTP\Pro\Alerts\Alerts;
use WPMailSMTP\Pro\Emails\Logs\Email;
use WPMailSMTP\Pro\Emails\Logs\EmailsCollection;

/**
 * Emails Overview widget (Pro).
 *
 * @since 4.10.0
 */
class EmailsOverview extends EmailsOverviewBase {

	use LogsDisabled;

	/**
	 * Whether the site has ever sent an email, read from the email log rather than the
	 * Lite weekly counters, which Pro never writes to. The chart draws whatever the
	 * selected range holds, zeros included; the prompt is for a site with no email at all.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	protected function has_data() {

		return ( new EmailsCollection(
			[ 'status' => [ Email::STATUS_UNSENT, Email::STATUS_SENT, Email::STATUS_DELIVERED ] ]
		) )->has_any();
	}

	/**
	 * Pro tracks confirmation and opens, so its chart carries four series instead of
	 * Lite's two.
	 *
	 * @since 4.10.0
	 *
	 * @return array
	 */
	public function get_series_meta(): array {

		return [
			[
				'id'    => 'confirmed',
				'label' => esc_html__( 'Confirmed', 'wp-mail-smtp-pro' ),
				'color' => '#056aab',
				'fill'  => true,
			],
			[
				'id'    => 'unconfirmed',
				'label' => esc_html__( 'Unconfirmed', 'wp-mail-smtp-pro' ),
				'color' => '#8c8f94',
				'fill'  => false,
			],
			[
				'id'    => 'failed',
				'label' => esc_html__( 'Failed', 'wp-mail-smtp-pro' ),
				'color' => '#d63638',
				'fill'  => false,
			],
			[
				'id'    => 'opened',
				'label' => esc_html__( 'Opened', 'wp-mail-smtp-pro' ),
				'color' => '#dba617',
				'fill'  => false,
			],
		];
	}

	/**
	 * Render the widget body, standing in the enable-logging prompt for the chart while
	 * email logging is off.
	 *
	 * @since 4.10.0
	 *
	 * @param string $variant Template variant.
	 * @param array  $data    Aggregated data.
	 *
	 * @return string
	 */
	protected function render_body( string $variant, array $data ): string {

		if ( $variant === 'logs_disabled' ) {
			return $this->render_logs_disabled_overlay( 'chart' );
		}

		return parent::render_body( $variant, $data );
	}

	/**
	 * The reports footer, shown once there is data to link to.
	 *
	 * @since 4.10.0
	 *
	 * @param string $variant Template variant.
	 * @param array  $data    Aggregated data.
	 *
	 * @return string
	 */
	protected function render_footer( string $variant, array $data ): string {

		if ( $variant !== 'data' ) {
			return '';
		}

		return (string) wp_mail_smtp_render(
			'card-footer',
			[
				'text' => esc_html__( 'See email performance with detailed stats on the Email Reports page', 'wp-mail-smtp-pro' ),
				'cta'  => [
					'label' => esc_html__( 'View all Reports', 'wp-mail-smtp-pro' ),
					'url'   => wp_mail_smtp()->get_admin()->get_admin_page_url( Area::SLUG . '-reports' ),
					'arrow' => false,
				],
			],
			true
		);
	}

	/**
	 * Whether at least one alert provider is enabled, which is what the
	 * failed-emails card would be asking the user to set up.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	protected function is_alerts_configured() {

		return ( new Alerts() )->is_enabled();
	}
}
