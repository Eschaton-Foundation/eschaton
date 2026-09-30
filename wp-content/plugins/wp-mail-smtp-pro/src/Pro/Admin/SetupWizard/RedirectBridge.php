<?php

namespace WPMailSMTP\Pro\Admin\SetupWizard;

use WPMailSMTP\Pro\Providers\Zoho\Auth as ZohoAuth;

/**
 * Pro augmentation of the hosted-wizard redirect bridge.
 *
 * Resolves the OAuth authorization URL for Outlook and Zoho when the bridge fires the
 * shared filter on admin_init, the cookie-bearing request the state nonce has to be
 * minted in.
 *
 * @since 4.10.0
 */
class RedirectBridge {

	/**
	 * Register hooks.
	 *
	 * @since 4.10.0
	 */
	public function hooks() {

		add_filter( 'wp_mail_smtp_admin_setup_wizard_get_oauth_url', [ $this, 'prepare_oauth_url_redirect' ], 10, 2 );
	}

	/**
	 * Resolve the OAuth authorization URL for the Pro oAuth mailers.
	 *
	 * @since 4.10.0
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
}
