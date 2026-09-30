<?php

namespace WPMailSMTP\Pro\Deprecated;

use WPMailSMTP\Pro\Admin\DashboardWidget;
use WPMailSMTP\Pro\Emails\Logs\Attachments\Attachments;
use WPMailSMTP\Pro\Emails\Logs\Logs;
use WPMailSMTP\Pro\Emails\Logs\Tracking\Tracking;

/**
 * Deprecated WPMailSMTP\Pro\Pro methods, kept for backward compatibility.
 *
 * @since 4.10.0
 */
trait Pro {

	/**
	 * Get the DashboardWidget object.
	 *
	 * @deprecated 2.9.0
	 *
	 * @since 2.7.0
	 *
	 * @return DashboardWidget
	 */
	public function get_dashboard_widget() {

		_deprecated_function( __METHOD__, '2.9.0' );

		static $dashboard_widget;

		if ( ! isset( $dashboard_widget ) ) {
			/**
			 * Filter the dashboard widget instance.
			 *
			 * @since 2.9.0
			 *
			 * @param DashboardWidget $dashboard_widget Dashboard widget instance.
			 */
			$dashboard_widget = apply_filters( 'wp_mail_smtp_pro_get_dashboard_widget', new DashboardWidget() ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Public hook name, kept for backwards compatibility.
		}

		return $dashboard_widget;
	}

	/**
	 * Get the list of all custom DB tables that should be present in the DB.
	 *
	 * @deprecated 3.0.0
	 *
	 * @since 1.9.0
	 *
	 * @return array List of table names.
	 */
	public function get_custom_db_tables() {

		_deprecated_function( __METHOD__, '3.0.0', '\WPMailSMTP\Core::get_custom_db_tables' );

		return [
			Logs::get_table_name(),
			Attachments::get_email_attachments_table_name(),
			Attachments::get_attachment_files_table_name(),
			Tracking::get_events_table_name(),
			Tracking::get_links_table_name(),
		];
	}

	/**
	 * Filter the HTML of the auto-updates setting for WP Mail SMTP Pro plugin.
	 *
	 * @deprecated 3.0.0
	 *
	 * @since 2.3.0
	 *
	 * @param string $html        The HTML of the plugin's auto-update column content, including
	 *                            toggle auto-update action links and time to next update.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data.
	 *
	 * @return string
	 */
	public function auto_update_setting_html( $html, $plugin_file, $plugin_data ) {

		_deprecated_function( __METHOD__, '3.0.0' );

		if (
			! empty( $plugin_data['Author'] ) &&
			$plugin_data['Author'] === 'WPForms' &&
			$plugin_file === plugin_basename( WPMS_PLUGIN_FILE )
		) {
			$html = esc_html__( 'Auto-updates are not available.', 'wp-mail-smtp-pro' );
		}

		return $html;
	}

	/**
	 * Rollback to default value for automatically update WP Mail SMTP Pro plugin.
	 * Some devs or tools can use `auto_update_plugin` filter and turn on auto-updates for all plugins.
	 *
	 * @deprecated 3.0.0
	 *
	 * @since 2.3.0
	 *
	 * @param mixed  $auto_update    Whether to update.
	 * @param object $filter_payload The update offer.
	 *
	 * @return null|bool
	 */
	public function rollback_auto_update_plugin_default_value( $auto_update, $filter_payload ) {

		_deprecated_function( __METHOD__, '3.0.0' );

		// Check whether auto-updates for plugins are supported and enabled. If not, return early.
		if (
			! function_exists( 'wp_is_auto_update_enabled_for_type' ) ||
			! wp_is_auto_update_enabled_for_type( 'plugin' )
		) {
			return $auto_update;
		}

		if ( empty( $auto_update ) ) {
			return $auto_update;
		}

		if ( ! is_object( $filter_payload ) || empty( $filter_payload->plugin ) ) {
			return $auto_update;
		}

		// Determine if it's a WP Mail SMTP Pro plugin. If so, return null (default value).
		if ( $filter_payload->plugin === plugin_basename( WPMS_PLUGIN_FILE ) ) {
			return null;
		}

		return $auto_update;
	}

	/**
	 * Filter value, which is prepared for `auto_update_plugins` option before it's saved into DB.
	 * We need to exclude WP Mail SMTP Pro.
	 *
	 * @deprecated 3.0.0
	 *
	 * @since 2.3.0
	 *
	 * @param mixed  $plugins     New plugins of the network option.
	 * @param mixed  $old_plugins Old plugins of the network option.
	 * @param string $option      Option name.
	 * @param int    $network_id  ID of the network.
	 *
	 * @return array
	 */
	public function update_auto_update_plugins_option( $plugins, $old_plugins, $option, $network_id ) {

		_deprecated_function( __METHOD__, '3.0.0' );

		// No need to filter out our plugins if none were saved.
		if ( empty( $plugins ) ) {
			return $plugins;
		}

		// Check whether auto-updates for plugins are supported and enabled. If so, exclude WP Mail SMTP Pro plugin.
		if ( function_exists( 'wp_is_auto_update_enabled_for_type' ) && wp_is_auto_update_enabled_for_type( 'plugin' ) ) {
			return array_diff( (array) $plugins, [ plugin_basename( WPMS_PLUGIN_FILE ) ] );
		}

		return $plugins;
	}

	/**
	 * Setup any additional Pro mailer options for the setup wizard.
	 *
	 * @since      2.6.0
	 * @since      3.11.0 Handle WPMS_AMAZONSES_DISPLAY_IDENTITIES constant.
	 * @deprecated {VERSION}
	 *
	 * @param array $data The default mailer options data.
	 *
	 * @return array
	 */
	public function setup_wizard_prepare_mailer_options( $data ) {

		_deprecated_function( __METHOD__, '4.10.0', '\WPMailSMTP\Pro\Admin\SetupWizard\RestApi::prepare_mailer_options' );

		return $data;
	}

	/**
	 * Prepare the oAuth URL redirect for the Pro oAuth mailers.
	 *
	 * @since      2.6.0
	 * @deprecated {VERSION}
	 *
	 * @param array  $data   The default oAuth data.
	 * @param string $mailer The mailer to prepare the redirect URL for.
	 *
	 * @return array
	 */
	public function prepare_oauth_url_redirect( $data, $mailer ) {

		_deprecated_function( __METHOD__, '4.10.0', '\WPMailSMTP\Pro\Admin\SetupWizard\RestApi::prepare_oauth_url_redirect' );

		return $data;
	}

	/**
	 * A filter hook to check if a license key exists.
	 *
	 * @since      2.6.0
	 * @deprecated {VERSION}
	 *
	 * @return bool
	 */
	public function does_license_key_exist() {

		_deprecated_function( __METHOD__, '4.10.0', '\WPMailSMTP\Pro\Admin\SetupWizard\Local::does_license_key_exist' );

		return false;
	}

	/**
	 * AJAX callback for getting the current Amazon SES Identities in a JS friendly format.
	 *
	 * @since      2.6.0
	 * @deprecated {VERSION}
	 */
	public function get_amazon_ses_identities() {

		_deprecated_function( __METHOD__, '4.10.0', '\WPMailSMTP\Pro\Admin\SetupWizard\RestApi::get_amazon_ses_identities' );
	}

	/**
	 * AJAX callback for the Amazon SES identity registration processing.
	 *
	 * @since      2.6.0
	 * @deprecated {VERSION}
	 */
	public function amazon_ses_identity_registration() {

		_deprecated_function( __METHOD__, '4.10.0', '\WPMailSMTP\Pro\Admin\SetupWizard\RestApi::amazon_ses_identity_registration' );
	}

	/**
	 * AJAX callback for verifying the license key.
	 *
	 * @since      2.6.0
	 * @deprecated {VERSION}
	 */
	public function verify_license_key() {

		_deprecated_function( __METHOD__, '4.10.0', '\WPMailSMTP\Pro\Admin\SetupWizard\RestApi::verify_license_key' );
	}
}
