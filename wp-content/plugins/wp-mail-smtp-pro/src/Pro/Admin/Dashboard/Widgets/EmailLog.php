<?php

namespace WPMailSMTP\Pro\Admin\Dashboard\Widgets;

use WPMailSMTP\Admin\Area;
use WPMailSMTP\Admin\Dashboard\Widgets\EmailLog as EmailLogBase;
use WPMailSMTP\Pro\Admin\Dashboard\LogsDisabled;
use WPMailSMTP\Pro\Emails\Logs\Email;
use WPMailSMTP\Pro\Emails\Logs\EmailsCollection;

/**
 * Dashboard "Email Log" widget (Pro).
 *
 * @since 4.10.0
 */
class EmailLog extends EmailLogBase {

	use LogsDisabled;

	/**
	 * Whether any email has ever been logged.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	protected function has_logged_emails() {

		return ( new EmailsCollection() )->has_any();
	}

	/**
	 * Render the widget body. The education and connect variants are unchanged from
	 * Lite; only the data variant, reached only on Pro, renders the log rows.
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
			return $this->render_logs_disabled_overlay( 'email-log' );
		}

		if ( $variant !== 'data' ) {
			return parent::render_body( $variant, $data );
		}

		// Register WP built-in Thickbox, reusing the same "View Email" preview modal
		// the Email Log page's own table opens.
		add_thickbox();

		return (string) wp_mail_smtp_render(
			'dashboard/widgets/email-log',
			[
				'variant' => $variant,
				'rows'    => $this->get_log_rows( $data['date_range'] ?? null ),
			],
			true
		);
	}

	/**
	 * The "View All Email Logs" footer, shown once there is data to link to.
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
				'text' => esc_html__( 'Keep track of every email sent from your site.', 'wp-mail-smtp-pro' ),
				'cta'  => [
					'label' => esc_html__( 'View All Email Logs', 'wp-mail-smtp-pro' ),
					'url'   => $this->get_logs_url(),
					'arrow' => false,
				],
			],
			true
		);
	}

	/**
	 * The most recent log rows within the given range, limited to self::ROWS.
	 *
	 * @since 4.10.0
	 *
	 * @param array|null $range Date range to scope to, `[ from, to ]`, or null for all time.
	 *
	 * @return array
	 */
	protected function get_log_rows( ?array $range = null ) {

		$emails = ( new EmailsCollection(
			[
				'per_page' => self::ROWS,
				'orderby'  => 'date_sent',
				'order'    => 'DESC',
				'date'     => $range,
			]
		) )->get();

		$rows = [];

		foreach ( $emails as $email ) {
			$rows[] = [
				'subject'      => $email->get_subject(),
				'subject_attr' => Email::esc_subject_attr( $email->get_subject() ),
				'recipient'    => $this->get_recipient( $email ),
				'mailer'       => $this->get_mailer_name( $email->get_mailer() ),
				'date_sent'    => $this->get_date_sent_display( $email ),
				'status'       => $this->get_status_severity( $email ),
				'status_label' => $email->get_status_name(),
				'edit_url'     => $this->get_email_url( $email, 'edit' ),
				'preview_url'  => ! empty( $email->get_content() ) ? $this->get_email_url( $email, 'preview' ) : '',
			];
		}

		return $rows;
	}

	/**
	 * The row's status as one of three severities the shared template draws a glyph for;
	 * that template cannot name the log's own status constants.
	 *
	 * @since 4.10.0
	 *
	 * @param Email $email Email object.
	 *
	 * @return string One of 'sent', 'waiting' or 'failed'.
	 */
	protected function get_status_severity( Email $email ) {

		$severities = [
			Email::STATUS_SENT      => 'sent',
			Email::STATUS_DELIVERED => 'sent',
			Email::STATUS_WAITING   => 'waiting',
		];

		// Anything else is a send that did not happen: unsent, blocked, or a status a
		// later release adds. Failed is the safe default for an unknown one.
		return $severities[ $email->get_status() ] ?? 'failed';
	}

	/**
	 * The row's "To" recipient(s). `Email::get_people()` returns a string for a
	 * single address or an array for several, so both are handled here.
	 *
	 * @since 4.10.0
	 *
	 * @param Email $email Email object.
	 *
	 * @return string
	 */
	protected function get_recipient( Email $email ) {

		$to = $email->get_people( 'to' );

		if ( is_array( $to ) ) {
			$to = implode( ', ', $to );
		}

		return (string) $to;
	}

	/**
	 * Display name for a mailer slug, cached per slug for the request. Mirrors
	 * `Table::column_mailer()`.
	 *
	 * @since 4.10.0
	 *
	 * @param string $mailer_slug Mailer slug, e.g. 'smtp'.
	 *
	 * @return string
	 */
	protected function get_mailer_name( $mailer_slug ) {

		static $providers = [];

		if ( ! isset( $providers[ $mailer_slug ] ) ) {
			$providers[ $mailer_slug ] = wp_mail_smtp()->get_providers()->get_options( $mailer_slug );
		}

		$provider = $providers[ $mailer_slug ];

		return $provider !== null ? $provider->get_title() : $mailer_slug;
	}

	/**
	 * Format a row's sent date in the site's own date format, falling back to
	 * "N/A" since `get_date_sent()` is declared `@throws \Exception`.
	 *
	 * @since 4.10.0
	 *
	 * @param Email $email Email object.
	 *
	 * @return string
	 */
	protected function get_date_sent_display( Email $email ) {

		$date = null;

		try {
			$date = $email->get_date_sent();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- The empty date fallback below covers this.
		}

		if ( empty( $date ) ) {
			return esc_html__( 'N/A', 'wp-mail-smtp-pro' );
		}

		return date_i18n( get_option( 'date_format' ), $date->getTimestamp() );
	}

	/**
	 * Build one email's "edit" (details page) or "preview" (thickbox modal) link.
	 *
	 * @since 4.10.0
	 *
	 * @param Email  $email Email object.
	 * @param string $mode  Either 'edit' or 'preview'.
	 *
	 * @return string
	 */
	protected function get_email_url( $email, $mode ) {

		if ( $mode === 'preview' ) {
			return add_query_arg(
				[
					'email_id'  => $email->get_id(),
					'mode'      => 'preview',
					'TB_iframe' => true,
					'width'     => 600,
					'height'    => '',
				],
				wp_nonce_url( $this->get_logs_url(), 'wp_mail_smtp_pro_logs_log_preview' )
			);
		}

		return add_query_arg(
			[
				'email_id' => $email->get_id(),
				'mode'     => 'view',
			],
			$this->get_logs_url()
		);
	}

	/**
	 * The full Email Log page URL.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	protected function get_logs_url() {

		return wp_mail_smtp()->get_admin()->get_admin_page_url( Area::SLUG . '-logs' );
	}
}
