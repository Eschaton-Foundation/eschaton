<?php

namespace WPMailSMTP\Pro\SetupChecklist;

use WPMailSMTP\Options;
use WPMailSMTP\Pro\Alerts\Alerts;
use WPMailSMTP\SetupChecklist\State;

/**
 * Pro side of the Setup Checklist.
 *
 * @since 4.10.0
 */
class SetupChecklist {

	/**
	 * Pro section ID.
	 *
	 * @since 4.10.0
	 *
	 * @var string
	 */
	const SECTION = 'pro_features';

	/**
	 * Register hooks.
	 *
	 * @since 4.10.0
	 */
	public function hooks() {

		add_filter( 'wp_mail_smtp_setup_checklist_config_get_sections', [ $this, 'add_section' ] );
		add_filter( 'wp_mail_smtp_setup_checklist_completion_detector_get_checks', [ $this, 'add_checks' ] );
		add_filter( 'wp_mail_smtp_setup_checklist_completion_detector_get_conditions', [ $this, 'add_conditions' ] );
	}

	/**
	 * Add the Pro group to the checklist sections.
	 *
	 * @since 4.10.0
	 *
	 * @param array $sections Sections keyed by section ID.
	 *
	 * @return array
	 */
	public function add_section( $sections ) {

		$sections[ self::SECTION ] = [
			'id'    => self::SECTION,
			'title' => __( 'Get the Most Out of WP Mail SMTP Pro', 'wp-mail-smtp-pro' ),
			'order' => 20,
			'items' => $this->get_items(),
		];

		return $sections;
	}

	/**
	 * Add the Pro completion callbacks.
	 *
	 * @since 4.10.0
	 *
	 * @param array $checks Callbacks keyed by checklist item ID.
	 *
	 * @return array
	 */
	public function add_checks( $checks ) {

		$checks['email_logging']           = [ $this, 'is_email_logging_enabled' ];
		$checks['backup_mailer']           = [ $this, 'is_backup_connection_configured' ];
		$checks['backup_mailer_sendlayer'] = [ $this, 'is_backup_connection_configured' ];
		$checks['email_alerts']            = [ $this, 'is_alerts_configured' ];
		$checks['wp_notifications']        = [ $this, 'is_wp_notifications_visited' ];

		return $checks;
	}

	/**
	 * Add the Pro applicability callbacks.
	 *
	 * @since 4.10.0
	 *
	 * @param array $conditions Callbacks keyed by checklist item ID.
	 *
	 * @return array
	 */
	public function add_conditions( $conditions ) {

		$conditions['backup_mailer']           = [ $this, 'applies_to_backup_mailer' ];
		$conditions['backup_mailer_sendlayer'] = [ $this, 'applies_to_backup_mailer_sendlayer' ];

		return $conditions;
	}

	/**
	 * Whether the plain backup-mailer item applies.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	public function applies_to_backup_mailer() {

		if ( $this->has_backup_connection() ) {
			return false;
		}

		return $this->get_primary_mailer() === 'sendlayer';
	}

	/**
	 * Whether the SendLayer backup promo applies.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	public function applies_to_backup_mailer_sendlayer() {

		if ( $this->has_backup_connection() ) {
			return false;
		}

		return $this->get_primary_mailer() !== 'sendlayer';
	}

	/**
	 * Whether email logging is switched on.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	public function is_email_logging_enabled() {

		return (bool) Options::init()->get( 'logs', 'enabled' );
	}

	/**
	 * Whether at least one alert channel is configured.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	public function is_alerts_configured() {

		return ( new Alerts() )->is_enabled();
	}

	/**
	 * Whether the WordPress notifications page has been opened.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	public function is_wp_notifications_visited() {

		return ( new State() )->has_visited( 'wp_notifications' );
	}

	/**
	 * Whether a backup connection is configured.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	public function is_backup_connection_configured() {

		return $this->has_backup_connection();
	}

	/**
	 * Whether a backup connection is configured.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	protected function has_backup_connection() {

		return ! empty( Options::init()->get( 'backup_connection', 'connection_id' ) );
	}

	/**
	 * Where the backup-mailer CTA sends a user who picks the connection by hand.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	private function get_backup_connection_url() {

		$settings_url = wp_mail_smtp()->get_admin()->get_admin_page_url();

		if ( wp_mail_smtp()->pro->get_additional_connections()->has_connections() ) {
			// Anchor matches the Backup Connection section id in Settings, General.
			return $settings_url . '#wp-mail-smtp-setting-row-backup_connection';
		}

		return add_query_arg(
			[
				'tab'  => 'connections',
				'mode' => 'new',
			],
			$settings_url
		);
	}

	/**
	 * Mailer slug of the primary connection.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	protected function get_primary_mailer() {

		return (string) wp_mail_smtp()->get_connections_manager()->get_primary_connection()->get_mailer_slug();
	}

	/**
	 * The Pro group's items.
	 *
	 * @since 4.10.0
	 *
	 * @return array
	 */
	private function get_items() {

		return [
			'email_logging'           => [
				'id'          => 'email_logging',
				'section'     => self::SECTION,
				'title'       => __( 'Enable Email Logging', 'wp-mail-smtp-pro' ),
				'description' => __( 'Keep a record of every email your site sends and spot failures at a glance.', 'wp-mail-smtp-pro' ),
				'order'       => 10,
				'cta'         => [
					'label'    => __( 'Enable Email Log', 'wp-mail-smtp-pro' ),
					'url'      => add_query_arg( 'tab', 'logs', wp_mail_smtp()->get_admin()->get_admin_page_url() ),
					'modifier' => 'grey',
				],
			],
			'backup_mailer'           => [
				'id'          => 'backup_mailer',
				'section'     => self::SECTION,
				'title'       => __( 'Set Up a Backup Mailer', 'wp-mail-smtp-pro' ),
				'description' => __( 'Set a backup mailer that automatically takes over if your primary mailer fails.', 'wp-mail-smtp-pro' ),
				'order'       => 20,
				'conditional' => true,
				'cta'         => [
					'label'    => __( 'Set Up Backup Mailer', 'wp-mail-smtp-pro' ),
					'url'      => $this->get_backup_connection_url(),
					'modifier' => 'grey',
				],
			],
			'backup_mailer_sendlayer' => [
				'id'          => 'backup_mailer_sendlayer',
				'section'     => self::SECTION,
				'title'       => __( 'Get a Free Backup Mailer Connection', 'wp-mail-smtp-pro' ),
				'description' => sprintf(
					/* translators: %s: SendLayer, linked to its website. */
					__( 'Set up our recommended mailer, %s as a backup mailer that automatically takes over if your primary mailer fails.', 'wp-mail-smtp-pro' ),
					'<a href="https://sendlayer.com/" target="_blank" rel="noopener noreferrer">SendLayer</a>'
				),
				'order'       => 30,
				'conditional' => true,
				'cta'         => [
					'label'    => __( 'Set Up Backup Mailer', 'wp-mail-smtp-pro' ),
					'modifier' => 'grey',
					'class'    => 'js-wp-mail-smtp-sendlayer-quick-connect-btn',
					'data'     => [
						'mode'        => 'backup_mailer',
						'utm-content' => 'Setup Checklist - Backup Mailer',
					],
				],
			],
			'email_alerts'            => [
				'id'          => 'email_alerts',
				'section'     => self::SECTION,
				'title'       => __( 'Set Up Email Alerts', 'wp-mail-smtp-pro' ),
				'description' => __( 'Get notified via email or Slack the moment an email fails to send, so nothing slips through.', 'wp-mail-smtp-pro' ),
				'order'       => 40,
				'cta'         => [
					'label'    => __( 'Set Up Alerts', 'wp-mail-smtp-pro' ),
					'url'      => add_query_arg( 'tab', 'alerts', wp_mail_smtp()->get_admin()->get_admin_page_url() ),
					'modifier' => 'grey',
				],
			],
			'wp_notifications'        => [
				'id'          => 'wp_notifications',
				'section'     => self::SECTION,
				'title'       => __( 'Manage WordPress Notifications', 'wp-mail-smtp-pro' ),
				'description' => __( 'Choose which emails WordPress sends and silence the ones you don\'t need.', 'wp-mail-smtp-pro' ),
				'order'       => 50,
				'cta'         => [
					'label'    => __( 'Manage Notifications', 'wp-mail-smtp-pro' ),
					'url'      => add_query_arg( 'tab', 'control', wp_mail_smtp()->get_admin()->get_admin_page_url() ),
					'modifier' => 'grey',
				],
			],
		];
	}
}
