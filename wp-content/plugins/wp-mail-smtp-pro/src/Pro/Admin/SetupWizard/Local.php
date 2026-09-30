<?php

namespace WPMailSMTP\Pro\Admin\SetupWizard;

use Exception;
use WP_Error;
use WPMailSMTP\Options;
use WPMailSMTP\Pro\Providers\AmazonSES\Auth as AmazonSESAuth;
use WPMailSMTP\Pro\Providers\AmazonSES\IdentitiesTable;
use WPMailSMTP\Pro\Providers\AmazonSES\Options as SESOptions;
use WPMailSMTP\Pro\Providers\Outlook\Auth as OutlookAuth;
use WPMailSMTP\Pro\Providers\Zoho\Auth as ZohoAuth;

/**
 * Pro side of the bundled setup wizard.
 *
 * @since 4.10.0
 */
class Local {

	/**
	 * Hooks.
	 *
	 * @since 4.10.0
	 */
	public function hooks() {

		add_filter( 'wp_mail_smtp_admin_setup_wizard_local_prepare_mailer_options', [ $this, 'prepare_mailer_options' ] );
		add_filter( 'wp_mail_smtp_admin_setup_wizard_local_get_oauth_url', [ $this, 'prepare_oauth_url_redirect' ], 10, 2 );
		add_filter( 'wp_mail_smtp_admin_setup_wizard_local_license_exists', [ $this, 'does_license_key_exist' ] );

		add_action( 'wp_ajax_wp_mail_smtp_vue_get_amazon_ses_identities', [ $this, 'get_amazon_ses_identities' ] );
		add_action( 'wp_ajax_wp_mail_smtp_vue_amazon_ses_identity_registration', [ $this, 'amazon_ses_identity_registration' ] );
		add_action( 'wp_ajax_wp_mail_smtp_vue_verify_license_key', [ $this, 'verify_license_key' ] );
	}

	/**
	 * AJAX callback for getting the current Amazon SES Identities in a JS friendly format.
	 *
	 * @since 2.6.0
	 */
	public function get_amazon_ses_identities() {

		check_ajax_referer( 'wpms-admin-nonce', 'nonce' );

		if ( ! current_user_can( wp_mail_smtp()->get_capability_manage_global_options() ) ) {
			wp_send_json_error();
		}

		$options = Options::init();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ses_settings = isset( $_POST['value'] ) ? wp_slash( json_decode( wp_unslash( $_POST['value'] ), true ) ) : [];

		if ( empty( $ses_settings ) ) {
			wp_send_json_error();
		}

		// Update Amazon SES settings with current settings to retrieve the SES Identities for.
		$options->set( [ 'amazonses' => $ses_settings ], false, false );

		$table = new IdentitiesTable();

		$table->prepare_items();

		$error = $table->get_last_error();

		if ( ! $table->has_items() && $error instanceof WP_Error ) {
			wp_send_json_error( $error->get_error_message() );
		}

		wp_send_json_success(
			[
				'columns' => $table->get_columns_for_js(),
				'data'    => $table->get_items_for_js(),
			]
		);
	}

	/**
	 * AJAX callback for the Amazon SES identity registration processing.
	 *
	 * @since 2.6.0
	 */
	public function amazon_ses_identity_registration() {

		check_ajax_referer( 'wpms-admin-nonce', 'nonce' );

		if ( ! current_user_can( wp_mail_smtp()->get_capability_manage_global_options() ) ) {
			wp_send_json_error();
		}

		$type  = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
		$value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

		if ( $type === 'email' && ! is_email( $value ) ) {
			wp_send_json_error( esc_html__( 'Please provide a valid email address.', 'wp-mail-smtp-pro' ) );
		} elseif ( $type === 'domain' && empty( $value ) ) {
			wp_send_json_error( esc_html__( 'Please provide a domain.', 'wp-mail-smtp-pro' ) );
		}

		$ses = new AmazonSESAuth();

		// Verify domain for easier conditional checking below.
		$domain_dkim_tokens = ( $type === 'domain' ) ? $ses->do_verify_domain_dkim( $value ) : '';

		if ( $type === 'email' && $ses->do_verify_email( $value ) === true ) {
			wp_send_json_success(
				[
					'type'  => $type,
					'value' => esc_html( $value ),
				]
			);
		} elseif ( $type === 'domain' && ! empty( $domain_dkim_tokens ) ) {
			wp_send_json_success(
				[
					'type'                    => $type,
					'value'                   => esc_html( $value ),
					'domain_dkim_dns_records' => SESOptions::prepare_dkim_dns_records(
						$value,
						$domain_dkim_tokens,
						wp_mail_smtp()->get_connections_manager()->get_primary_connection()
					),
				]
			);
		} else {
			$error = $ses->get_last_error();

			wp_send_json_error(
				$error instanceof WP_Error
					? esc_html( $error->get_error_message() )
					: esc_html__( 'Something went wrong. Please try again later.', 'wp-mail-smtp-pro' )
			);
		}
	}

	/**
	 * AJAX callback for verifying the license key.
	 *
	 * @since 2.6.0
	 */
	public function verify_license_key() {

		check_ajax_referer( 'wpms-admin-nonce', 'nonce' );

		if ( ! current_user_can( wp_mail_smtp()->get_capability_manage_global_options() ) ) {
			wp_send_json_error( esc_html__( 'You don\'t have the permission to perform this action.', 'wp-mail-smtp-pro' ) );
		}

		$license_key = ! empty( $_POST['license_key'] ) ? sanitize_key( $_POST['license_key'] ) : '';

		if ( empty( $license_key ) ) {
			wp_send_json_error( esc_html__( 'Please enter your license key.', 'wp-mail-smtp-pro' ) );
		}

		$license_object = wp_mail_smtp()->get_pro()->get_license();

		// Let the License class handle the rest via AJAX.
		if ( method_exists( $license_object, 'verify_key' ) ) {
			$license_object->verify_key( $license_key, true );
		}

		wp_send_json_error( esc_html__( 'License functionality missing!', 'wp-mail-smtp-pro' ) );
	}

	/**
	 * Add the Pro mailer options to the setup wizard mailer options.
	 *
	 * @since 2.6.0
	 * @since 3.11.0 Handle WPMS_AMAZONSES_DISPLAY_IDENTITIES constant.
	 *
	 * @param array $data The default mailer options data.
	 *
	 * @return array
	 */
	public function prepare_mailer_options( $data ) {

		if ( key_exists( 'amazonses', $data ) ) {
			if ( empty( $data['amazonses']['disabled'] ) ) {
				$amazon_regions   = AmazonSESAuth::get_regions_names();
				$prepared_regions = [];

				foreach ( $amazon_regions as $value => $label ) {
					$prepared_regions[] = [
						'label' => $label,
						'value' => $value,
					];
				}

				$data['amazonses']['region_options'] = $prepared_regions;
			}

			$data['amazonses']['display_identities'] = (
				! defined( 'WPMS_AMAZONSES_DISPLAY_IDENTITIES' ) ||
				WPMS_AMAZONSES_DISPLAY_IDENTITIES === true
			);
		}

		if ( key_exists( 'outlook', $data ) && empty( $data['outlook']['disabled'] ) ) {
			$data['outlook']['redirect_uri'] = OutlookAuth::get_plugin_auth_url();
		}

		if ( key_exists( 'zoho', $data ) && empty( $data['zoho']['disabled'] ) ) {
			$data['zoho']['redirect_uri']   = ZohoAuth::get_plugin_auth_url();
			$data['zoho']['domain_options'] = wp_mail_smtp()->get_providers()->get_options( 'zoho' )->get_zoho_domains();
		}

		return $data;
	}

	/**
	 * Prepare the oAuth URL redirect for the Pro oAuth mailers.
	 *
	 * @since 2.6.0
	 *
	 * @param array  $data   The default oAuth data.
	 * @param string $mailer The mailer to prepare the redirect URL for.
	 *
	 * @return array
	 *
	 * @throws Exception If auth classes fail to initialize.
	 */
	public function prepare_oauth_url_redirect( $data, $mailer ) {

		$auth = null;

		switch ( $mailer ) {
			case 'outlook':
				$auth = wp_mail_smtp()->get_providers()->get_auth( 'outlook' );
				break;

			case 'zoho':
				$auth = new ZohoAuth();
				break;
		}

		if ( ! empty( $auth ) && $auth->is_clients_saved() && $auth->is_auth_required() ) {
			$data['oauth_url'] = $auth->get_auth_url();
		}

		return $data;
	}

	/**
	 * A filter hook to check if a license key exists.
	 *
	 * @since 2.6.0
	 *
	 * @return bool
	 */
	public function does_license_key_exist() {

		$license = Options::init()->get( 'license', 'key' );

		return ! empty( $license );
	}
}
