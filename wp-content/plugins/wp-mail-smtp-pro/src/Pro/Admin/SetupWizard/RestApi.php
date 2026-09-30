<?php

namespace WPMailSMTP\Pro\Admin\SetupWizard;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use WPMailSMTP\Admin\SetupWizard\RestApiResponse;
use WPMailSMTP\Options;
use WPMailSMTP\Pro\Providers\AmazonSES\Auth as AmazonSESAuth;
use WPMailSMTP\Pro\Providers\AmazonSES\Options as SESOptions;
use WPMailSMTP\Pro\Providers\Gmail\Auth as GmailAuth;
use WPMailSMTP\Pro\Providers\Outlook\Auth as OutlookAuth;
use WPMailSMTP\Pro\Providers\Zoho\Auth as ZohoAuth;

/**
 * Pro setup wizard REST routes.
 *
 * Registers the Pro-only wizard REST routes through the
 * `wp_mail_smtp_admin_setup_wizard_rest_api_routes` filter, and augments the Lite wizard data
 * (mailer options, oAuth URLs, connected account, license state) via filters.
 *
 * @since 4.10.0
 */
class RestApi {

	/**
	 * Shares the response()/error() envelope with the Lite REST controller.
	 *
	 * @since 4.10.0
	 */
	use RestApiResponse;

	/**
	 * Hooks.
	 *
	 * @since 4.10.0
	 */
	public function hooks() {

		add_filter( 'wp_mail_smtp_admin_setup_wizard_rest_api_routes', [ $this, 'register_routes' ] );
		add_filter( 'wp_mail_smtp_admin_setup_wizard_prepare_mailer_options', [ $this, 'prepare_mailer_options' ] );
		add_filter( 'wp_mail_smtp_admin_setup_wizard_rest_api_connected_data', [ $this, 'prepare_connected_data' ], 10, 2 );
	}

	/**
	 * Register the Pro wizard REST routes.
	 *
	 * @since 4.10.0
	 *
	 * @param array $routes Route map: path => [ method, callback ].
	 *
	 * @return array
	 */
	public function register_routes( $routes ) {

		$routes['/get-amazon-ses-identities'] = [
			WP_REST_Server::CREATABLE,
			[ $this, 'get_amazon_ses_identities' ],
		];

		$routes['/amazon-ses-identity-registration'] = [
			WP_REST_Server::CREATABLE,
			[ $this, 'amazon_ses_identity_registration' ],
		];

		$routes['/verify-license-key'] = [
			WP_REST_Server::CREATABLE,
			[ $this, 'verify_license_key' ],
		];

		$routes['/refresh-license-key'] = [
			WP_REST_Server::CREATABLE,
			[ $this, 'refresh_license_key' ],
		];

		$routes['/remove-license-key'] = [
			WP_REST_Server::CREATABLE,
			[ $this, 'remove_license_key' ],
		];

		$routes['/remove-gmail-one-click-setup-oauth-connection'] = [
			WP_REST_Server::CREATABLE,
			[ $this, 'remove_gmail_one_click_setup_oauth_connection' ],
		];

		$routes['/remove-outlook-one-click-setup-oauth-connection'] = [
			WP_REST_Server::CREATABLE,
			[ $this, 'remove_outlook_one_click_setup_oauth_connection' ],
		];

		return $routes;
	}

	/**
	 * REST endpoint for getting the current Amazon SES Identities in a JS friendly format.
	 *
	 * @since 4.10.0
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function get_amazon_ses_identities( $request ) {

		$options = Options::init();

		// Stored slashed, matching what Options::get() strips back off on read.
		$ses_settings = wp_slash( (array) $request->get_param( 'value' ) );

		if ( empty( $ses_settings ) ) {
			return $this->error( esc_html__( 'Please provide the Amazon SES settings to retrieve identities for.', 'wp-mail-smtp-pro' ) );
		}

		// Update Amazon SES settings with current settings to retrieve the SES Identities for.
		$options->set( [ 'amazonses' => $ses_settings ], false, false );

		$auth       = new AmazonSESAuth();
		$identities = $auth->get_identities();
		$error      = $auth->get_last_error();

		if ( empty( $identities ) && $error instanceof WP_Error ) {
			return $this->error( $error->get_error_message() );
		}

		return $this->response(
			[
				'data' => array_map(
					static function ( $identity ) {

						return $identity->get_all();
					},
					$identities
				),
			]
		);
	}

	/**
	 * REST endpoint for the Amazon SES identity registration processing.
	 *
	 * @since 4.10.0
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function amazon_ses_identity_registration( $request ) {

		$type  = $request->get_param( 'type' ) !== null ? sanitize_key( $request->get_param( 'type' ) ) : '';
		$value = $request->get_param( 'value' ) !== null ? sanitize_text_field( $request->get_param( 'value' ) ) : '';

		if ( $type === 'email' && ! is_email( $value ) ) {
			return $this->error( esc_html__( 'Please provide a valid email address.', 'wp-mail-smtp-pro' ) );
		} elseif ( $type === 'domain' && empty( $value ) ) {
			return $this->error( esc_html__( 'Please provide a domain.', 'wp-mail-smtp-pro' ) );
		}

		$ses = new AmazonSESAuth();

		// Verify domain for easier conditional checking below.
		$domain_dkim_tokens = ( $type === 'domain' ) ? $ses->do_verify_domain_dkim( $value ) : '';

		if ( $type === 'email' && $ses->do_verify_email( $value ) === true ) {
			return $this->response(
				[
					'type'  => $type,
					'value' => esc_html( $value ),
				]
			);
		} elseif ( $type === 'domain' && ! empty( $domain_dkim_tokens ) ) {
			return $this->response(
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

			return $this->error(
				$error instanceof WP_Error
					? esc_html( $error->get_error_message() )
					: esc_html__( 'Something went wrong. Please try again later.', 'wp-mail-smtp-pro' )
			);
		}
	}

	/**
	 * REST endpoint for verifying the license key.
	 *
	 * @since 4.10.0
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function verify_license_key( $request ) {

		$license_key = ! empty( $request->get_param( 'license_key' ) ) ? sanitize_key( $request->get_param( 'license_key' ) ) : '';

		if ( empty( $license_key ) ) {
			return $this->error( esc_html__( 'Please enter your license key.', 'wp-mail-smtp-pro' ) );
		}

		$license = wp_mail_smtp()->get_pro()->get_license();

		// Verify synchronously (ajax = false); the License class records the
		// outcome in its public $errors / $success arrays rather than echoing.
		$verified = $license->verify_key( $license_key, false );

		if ( ! $verified ) {
			return $this->error(
				! empty( $license->errors )
					? implode( ' ', array_map( 'esc_html', $license->errors ) )
					: $license->get_remote_error_message()
			);
		}

		return $this->response(
			[
				'message' => ! empty( $license->success )
					? implode( ' ', array_map( 'esc_html', $license->success ) )
					: $license->get_key_verified_message(),
			]
		);
	}

	/**
	 * REST endpoint for re-validating the license key already stored on the site.
	 *
	 * @since 4.10.0
	 */
	public function refresh_license_key() {

		$key = wp_mail_smtp()->get_license_key();

		if ( empty( $key ) ) {
			return $this->error( esc_html__( 'There is no license key on this site to verify.', 'wp-mail-smtp-pro' ) );
		}

		$license = wp_mail_smtp()->get_pro()->get_license();

		// Forced, so a refresh records its outcome, and synchronous, so the License class fills
		// its message arrays instead of answering the request itself.
		$status = $license->validate_key( $key, true, false, true );

		if ( $status === 'valid' ) {
			return $this->response(
				[
					'message' => ! empty( $license->success )
						? implode( ' ', array_map( 'esc_html', $license->success ) )
						: $license->get_key_refreshed_message(),
				]
			);
		}

		// Only a failure to reach the server leaves a message behind. A key the server rejected
		// is described by the state it has just been refreshed to, which the wizard has copy for.
		return $this->error(
			! empty( $license->errors )
				? implode( ' ', array_map( 'esc_html', $license->errors ) )
				: '',
			200,
			$license->get_state()
		);
	}

	/**
	 * REST endpoint for removing the license key from the site.
	 *
	 * @since 4.10.0
	 */
	public function remove_license_key() {

		if ( empty( wp_mail_smtp()->get_license_key() ) ) {
			return $this->error( esc_html__( 'There is no license key on this site to remove.', 'wp-mail-smtp-pro' ) );
		}

		$license = wp_mail_smtp()->get_pro()->get_license();

		$license->deactivate_key();

		return $this->response(
			[
				'message' => ! empty( $license->success )
					? implode( ' ', array_map( 'esc_html', $license->success ) )
					: $license->get_key_removed_message(),
			]
		);
	}

	/**
	 * REST endpoint for removing the Gmail One-Click Setup connection.
	 *
	 * @since 4.10.0
	 */
	public function remove_gmail_one_click_setup_oauth_connection() {

		$auth = new GmailAuth();

		$auth->get_client()->remove_connection();

		$options = Options::init();
		$old_opt = $options->get_all_raw();

		unset( $old_opt['gmail']['one_click_setup_credentials'] );
		unset( $old_opt['gmail']['one_click_setup_user_details'] );
		unset( $old_opt['gmail']['one_click_setup_status'] );

		$options->set( $old_opt );

		return $this->response();
	}

	/**
	 * REST endpoint for removing the Outlook One-Click Setup connection.
	 *
	 * @since 4.10.0
	 */
	public function remove_outlook_one_click_setup_oauth_connection() {

		$options = Options::init();
		$old_opt = $options->get_all_raw();

		unset( $old_opt['outlook']['one_click_setup_credentials'] );
		unset( $old_opt['outlook']['one_click_setup_user_details'] );

		$options->set( $old_opt );

		return $this->response();
	}

	/**
	 * Add the connected email for the Pro oAuth mailers to the setup wizard connected data.
	 *
	 * @since 4.10.0
	 *
	 * @param array  $data   Connected data.
	 * @param string $mailer The mailer to add connected data for.
	 *
	 * @return array
	 */
	public function prepare_connected_data( $data, $mailer ) {

		switch ( $mailer ) {
			case 'outlook':
				// get_auth() returns the One-Click Setup auth when it is enabled.
				$auth = wp_mail_smtp()->get_providers()->get_auth( 'outlook' );

				if ( ! $auth->is_clients_saved() || $auth->is_auth_required() ) {
					break;
				}

				$user_info = $auth->get_user_info();

				if ( ! empty( $user_info['email'] ) ) {
					$data['connected_email'] = $user_info['email'];
				}
				break;

			case 'zoho':
				$user_info = Options::init()->get( 'zoho', 'user_details' );

				if ( ! empty( $user_info['email'] ) ) {
					$data['connected_email'] = $user_info['email'];
				}
				break;
		}

		return $data;
	}

	/**
	 * Add the Pro mailer options to the setup wizard mailer options.
	 *
	 * @since 4.10.0
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

}
