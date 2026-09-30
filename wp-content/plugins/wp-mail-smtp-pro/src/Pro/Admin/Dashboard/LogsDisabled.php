<?php

namespace WPMailSMTP\Pro\Admin\Dashboard;

use WPMailSMTP\Admin\Dashboard\WidgetState;

/**
 * The enable-logging prompt a log-backed widget shows in place of its content while
 * email logging is off. Pro writes no weekly counters, so with the log disabled these
 * widgets have no data source at all and their usual empty-state CTAs, which ask for a
 * test email, would never fill them in.
 *
 * @since 4.10.0
 */
trait LogsDisabled {

	/**
	 * Get the widget state, taking over from the host's own empty-state reasoning while
	 * email logging is off.
	 *
	 * @since 4.10.0
	 *
	 * @return WidgetState
	 */
	public function get_state(): WidgetState {

		if ( ! $this->is_email_log_enabled() ) {
			return new WidgetState( true, 'logs_disabled' );
		}

		return parent::get_state();
	}

	/**
	 * Whether email logging is recording the data the host widget reports on.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	protected function is_email_log_enabled() {

		return wp_mail_smtp()->get_pro()->get_logs()->is_enabled();
	}

	/**
	 * Render the prompt over the host widget's own teaser.
	 *
	 * @since 4.10.0
	 *
	 * @param string $teaser Teaser template name under `dashboard/teasers/`.
	 *
	 * @return string
	 */
	protected function render_logs_disabled_overlay( string $teaser ): string {

		return (string) wp_mail_smtp_render(
			'dashboard/widget-overlay',
			[
				'title'  => esc_html__( 'Email Logging Is Disabled', 'wp-mail-smtp-pro' ),
				'text'   => esc_html__( 'Enable email logging to keep track of every email your site sends and see the details right here in your dashboard.', 'wp-mail-smtp-pro' ),
				'cta'    => [
					'label' => esc_html__( 'Enable Email Logging', 'wp-mail-smtp-pro' ),
					'url'   => wp_mail_smtp()->get_pro()->get_logs()->get_settings_url(),
				],
				'teaser' => (string) wp_mail_smtp_render( 'dashboard/teasers/' . $teaser, [], true ),
			],
			true
		);
	}
}
