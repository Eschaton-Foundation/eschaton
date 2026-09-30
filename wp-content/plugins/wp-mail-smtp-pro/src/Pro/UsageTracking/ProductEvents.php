<?php

namespace WPMailSMTP\Pro\UsageTracking;

use Throwable;
use WP_CLI;
use WPMailSMTP\PartnerPlugins\Plugins\WPVibe;
use WPMailSMTP\Pro\Alerts\Loader as AlertsLoader;
use WPMailSMTP\TestEmail\TestEmail;
use WPMailSMTP\Vendor\ProductApi\Events\Event;
use WPMailSMTP\Vendor\ProductApi\Events\EventsManager;
use WPMailSMTP\Vendor\ProductApi\ProductApi;
use WPMailSMTP\WP;

/**
 * Plugin events reported to the Product API.
 *
 * @since 4.10.0
 */
class ProductEvents {

	/**
	 * Option holding the last-fired timestamp per throttle key.
	 *
	 * @since 4.10.0
	 */
	private const THROTTLE_OPTION = 'wp_mail_smtp_events_last_fired';

	/**
	 * Initialize event tracking.
	 *
	 * @since 4.10.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 4.10.0
	 */
	protected function hooks() {

		add_action( 'wp_mail_smtp_test_email_send_after', [ $this, 'track_test_email_sent' ] );
		add_action( 'wp_mail_smtp_options_set_after', [ $this, 'track_feature_changes' ], 10, 2 );
		add_action( 'wp_mail_smtp_admin_area_process_actions_process_post_before', [ $this, 'track_settings_saved' ], 10, 2 );
		add_action( 'wp_mail_smtp_partners_installer_installed', [ $this, 'track_partner_plugin_installed' ] );
		add_action( 'wp_mail_smtp_partners_installer_activated', [ $this, 'track_partner_plugin_activated' ] );
		add_action( 'load-wp-mail-smtp_page_wp-mail-smtp-logs', [ $this, 'track_log_viewed' ] );
		add_action( 'load-wp-mail-smtp_page_wp-mail-smtp-tools', [ $this, 'track_ai_mcp_tab_viewed' ] );
		add_action( 'wp_mail_smtp_admin_setup_wizard_launcher_started', [ $this, 'track_wizard_started' ], 10, 2 );
	}

	/**
	 * Report a test email result.
	 *
	 * @since 4.10.0
	 *
	 * @param TestEmail $test_email The test email that was just sent.
	 */
	public function track_test_email_sent( $test_email ) {

		if ( ! $test_email instanceof TestEmail ) {
			return;
		}

		$results = [
			TestEmail::SUCCESS             => 'success',
			TestEmail::FAILED              => 'failed',
			TestEmail::FAILED_DOMAIN_CHECK => 'failed_domain_check',
		];

		$contexts = [
			TestEmail::CONTEXT_ADMIN_TEST   => 'admin_test',
			TestEmail::CONTEXT_SETUP_WIZARD => 'setup_wizard',
		];

		$result     = $test_email->get_result();
		$context    = $test_email->get_context();
		$connection = $test_email->get_connection();

		$this->track_event(
			'test_email_sent',
			[
				'result'  => $results[ $result ] ?? 'failed',
				'context' => $this->is_cli() ? 'wp_cli' : ( $contexts[ $context ] ?? 'admin_test' ),
				'html'    => $test_email->is_html(),
				'mailer'  => $connection !== null ? $connection->get_mailer_slug() : '',
			]
		);
	}

	/**
	 * Report every feature an options write turned on or off.
	 *
	 * @since 4.10.0
	 *
	 * @param array $options     Options as they were saved.
	 * @param array $old_options Options as they were before the write.
	 */
	public function track_feature_changes( $options, $old_options ) {

		$this->track_toggle( 'log_setting_changed', $options, $old_options, 'logs', 'enabled' );
		$this->track_toggle( 'smart_routing_setting_changed', $options, $old_options, 'smart_routing', 'enabled' );
		$this->track_backup_connection( $options, $old_options );
		$this->track_alert_channels( $options, $old_options );
	}

	/**
	 * Report a setting this write switched on or off.
	 *
	 * @since 4.10.0
	 *
	 * @param string $name        Event name.
	 * @param array  $options     Options as they were saved.
	 * @param array  $old_options Options as they were before the write.
	 * @param string $group       Option group holding the setting.
	 * @param string $key         Key within that group.
	 */
	private function track_toggle( $name, $options, $old_options, $group, $key ) {

		$was_on = ! empty( $old_options[ $group ][ $key ] );
		$is_on  = ! empty( $options[ $group ][ $key ] );

		if ( $was_on === $is_on ) {
			return;
		}

		$this->track_event( $name, [ 'enabled' => $is_on ] );
	}

	/**
	 * Report the backup connection this write selected.
	 *
	 * @since 4.10.0
	 *
	 * @param array $options     Options as they were saved.
	 * @param array $old_options Options as they were before the write.
	 */
	private function track_backup_connection( $options, $old_options ) {

		$old_id = $old_options['backup_connection']['connection_id'] ?? '';
		$new_id = $options['backup_connection']['connection_id'] ?? '';

		if ( $old_id === $new_id ) {
			return;
		}

		$this->track_event( 'backup_connection_changed', [ 'mailer' => $this->get_connection_mailer( $new_id ) ] );
	}

	/**
	 * The mailer a connection sends through.
	 *
	 * @since 4.10.0
	 *
	 * @param string $connection_id Connection ID, empty when none is selected.
	 *
	 * @return string Mailer slug, 'none' for no selection, 'unknown' when the
	 *                connection no longer resolves.
	 */
	private function get_connection_mailer( $connection_id ) {

		if ( empty( $connection_id ) ) {
			return 'none';
		}

		$connection = wp_mail_smtp()->get_connections_manager()->get_connection( $connection_id, false );

		return $connection ? sanitize_key( $connection->get_mailer_slug() ) : 'unknown';
	}

	/**
	 * Report each alert channel this write connected or disconnected.
	 *
	 * @since 4.10.0
	 *
	 * @param array $options     Options as they were saved.
	 * @param array $old_options Options as they were before the write.
	 */
	private function track_alert_channels( $options, $old_options ) {

		$old_channels = $this->get_enabled_alert_channels( $old_options );
		$new_channels = $this->get_enabled_alert_channels( $options );

		foreach ( array_diff( $new_channels, $old_channels ) as $channel ) {
			$this->track_event( 'alert_channel_added', [ 'channel' => sanitize_key( $channel ) ] );
		}

		foreach ( array_diff( $old_channels, $new_channels ) as $channel ) {
			$this->track_event( 'alert_channel_removed', [ 'channel' => sanitize_key( $channel ) ] );
		}
	}

	/**
	 * Slugs of the alert channels enabled in a given options array.
	 *
	 * @since 4.10.0
	 *
	 * @param array $options Options array to read.
	 *
	 * @return string[]
	 */
	private function get_enabled_alert_channels( $options ) {

		return array_values(
			array_filter(
				array_keys( ( new AlertsLoader() )->get_providers() ),
				function ( $provider_slug ) use ( $options ) {
					return ! empty( $options[ 'alert_' . $provider_slug ]['enabled'] );
				}
			)
		);
	}

	/**
	 * Report that somebody opened their email logs.
	 *
	 * @since 4.10.0
	 */
	public function track_log_viewed() {

		$this->track_event_once_daily( 'log_viewed' );
	}

	/**
	 * Report a resend.
	 *
	 * @since 4.10.0
	 *
	 * @param bool $bulk Whether this was a bulk resend.
	 */
	public function track_email_resent( $bulk ) {

		$this->track_event( 'email_resent', [ 'bulk' => (bool) $bulk ] );
	}

	/**
	 * Report an export.
	 *
	 * @since 4.10.0
	 *
	 * @param string $type Export type slug.
	 */
	public function track_log_exported( $type ) {

		$type = sanitize_key( $type );

		// Literal list, not Export::get_export_types(): that source is filterable and
		// drops xlsx/eml when ZipArchive is unavailable, which would misreport the type.
		$type = in_array( $type, [ 'csv', 'xlsx', 'eml' ], true ) ? $type : 'csv';

		$this->track_event( 'log_exported', [ 'type' => $type ] );
	}

	/**
	 * Report that a settings tab was saved.
	 *
	 * @since 4.10.0
	 *
	 * @param array  $post      Posted data.
	 * @param string $page_slug Slug of the tab being saved.
	 */
	public function track_settings_saved( $post, $page_slug ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $post is part of the hook signature.

		$tab = sanitize_key( $page_slug );

		if ( $tab === '' ) {
			return;
		}

		$this->track_event_once_daily( 'settings_saved', [ 'tab' => $tab ], 'settings_saved:' . $tab );
	}

	/**
	 * Report a partner plugin install started from one of our screens.
	 *
	 * @since 4.10.0
	 *
	 * @param string $slug Partner plugin slug.
	 */
	public function track_partner_plugin_installed( $slug ) {

		$this->track_event(
			'partner_plugin_installed',
			[
				'plugin' => sanitize_key( $slug ),
				'source' => $this->get_install_source(),
			]
		);
	}

	/**
	 * Report a partner plugin activation started from one of our screens.
	 *
	 * @since 4.10.0
	 *
	 * @param string $slug Partner plugin slug.
	 */
	public function track_partner_plugin_activated( $slug ) {

		$this->track_event(
			'partner_plugin_activated',
			[
				'plugin' => sanitize_key( $slug ),
				'source' => $this->get_install_source(),
			]
		);
	}

	/**
	 * Which screen started an install or activate request.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	private function get_install_source() {

		$allowed = [
			'about_tab',
			'test_email_banner',
			'test_email_pro_tip',
			'ai_mcp',
			'recommendations',
			'activelayer_wc',
			'code_snippets',
			'setup_checklist',
		];

		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The AJAX handlers that reach this have already verified the nonce.

		if ( in_array( $source, $allowed, true ) ) {
			return $source;
		}

		// The wizard posts a JSON body to a REST route, which $_POST never sees,
		// and it is the only REST caller of the installer. Derived after the
		// allowlist so neither this nor 'unknown' can be posted.
		if ( WP::is_doing_rest_request() ) {
			return 'setup_wizard';
		}

		return 'unknown';
	}

	/**
	 * Report a view of the AI MCP tab.
	 *
	 * @since 4.10.0
	 */
	public function track_ai_mcp_tab_viewed() {

		$admin = wp_mail_smtp()->get_admin();
		$pages = $admin->get_parent_pages();

		// The tab resolver reads the request without checking which page it is on.
		if (
			! $admin->is_admin_page( 'tools' ) ||
			! isset( $pages['tools'] ) ||
			$pages['tools']->get_current_tab() !== 'ai-mcp'
		) {
			return;
		}

		$this->track_event_once_daily(
			'ai_mcp_tab_viewed',
			[ 'wpvibe_state' => ( new WPVibe() )->get_install_state() ]
		);
	}

	/**
	 * Report the Setup Wizard run that started, and which wizard the user got.
	 *
	 * @since 4.10.0
	 *
	 * @param string $variant Wizard variant the run started in.
	 * @param string $reason  Why it is the bundled wizard, empty for a hosted run.
	 */
	public function track_wizard_started( $variant, $reason = '' ) {

		$this->track_event(
			'wizard_started',
			[
				'variant' => sanitize_key( $variant ),
				// Dots survive as underscores: sanitize_key() would strip them and
				// run the segments of an error code together.
				'reason'  => sanitize_key( str_replace( '.', '_', $reason ) ),
			]
		);
	}

	/**
	 * Whether event tracking is enabled on this site.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	protected function is_enabled() {

		/**
		 * Whether product events tracking is enabled.
		 *
		 * @since 4.10.0
		 *
		 * @param bool $enabled Whether product events tracking is enabled.
		 */
		return (bool) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- The sniff wants a
			// wp_mail_smtp_pro_usage_tracking_product_events prefix; this name deliberately pairs
			// with the Lite wp_mail_smtp_usage_tracking_is_enabled filter it extends.
			'wp_mail_smtp_usage_tracking_is_product_events_enabled',
			wp_mail_smtp()->get_usage_tracking()->is_enabled()
		);
	}

	/**
	 * Whether an event may be reported from this request.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	protected function is_allowed() {

		if ( wp_doing_cron() ) {
			return false;
		}

		// Our own commands configure the plugin, so they report like any other
		// surface. Everything else running under WP-CLI stays silent.
		if ( $this->is_cli() ) {
			return $this->is_our_cli_command();
		}

		$is_admin_context = is_admin() || WP::is_doing_rest_request();

		if ( ! $is_admin_context ) {
			return false;
		}

		return is_user_logged_in();
	}

	/**
	 * Report an event.
	 *
	 * @since 4.10.0
	 *
	 * @param string $name       Event name.
	 * @param array  $properties Event properties. Enums, booleans, counts and slugs only.
	 */
	protected function track_event( $name, $properties = [] ) {

		if ( ! $this->is_enabled() || ! $this->is_allowed() ) {
			return;
		}

		$this->dispatch( $name, $properties );
	}

	/**
	 * Report an event at most once per day for this site.
	 *
	 * @since 4.10.0
	 *
	 * @param string $name         Event name.
	 * @param array  $properties   Event properties.
	 * @param string $throttle_key Throttle key, when one event needs several
	 *                             independent guards. Defaults to the event name.
	 */
	protected function track_event_once_daily( $name, $properties = [], $throttle_key = '' ) {

		if ( ! $this->is_enabled() || ! $this->is_allowed() ) {
			return;
		}

		$key = $throttle_key !== '' ? $throttle_key : $name;

		if ( ! $this->passes_day_guard( $key ) ) {
			return;
		}

		$this->dispatch( $name, $properties );
	}

	/**
	 * Whether this key has not fired in the last day, stamping it when it has not.
	 *
	 * @since 4.10.0
	 *
	 * @param string $key Throttle key.
	 *
	 * @return bool
	 */
	private function passes_day_guard( $key ) {

		$stamps = get_option( self::THROTTLE_OPTION, [] );

		if ( ! is_array( $stamps ) ) {
			$stamps = [];
		}

		$previous = isset( $stamps[ $key ] ) ? (int) $stamps[ $key ] : 0;

		if ( $previous > 0 && ( time() - $previous ) < DAY_IN_SECONDS ) {
			return false;
		}

		$stamps[ $key ] = time();

		// Stamped before dispatch: a client that cannot send would otherwise be
		// retried on every request for the rest of the day.
		update_option( self::THROTTLE_OPTION, $stamps, false );

		return true;
	}

	/**
	 * Hand the event to the client's request-scoped queue.
	 *
	 * @since 4.10.0
	 *
	 * @param string $name       Event name.
	 * @param array  $properties Event properties.
	 */
	protected function dispatch( $name, $properties ) {

		try {
			ProductApi::get( EventsManager::class )
				->get_tracker()
				->track( $this->make_event( $name, $properties ) );
		} catch ( Throwable $e ) {
			// Throwable, not Exception: an unconfigured client throws an Error.
			return;
		}
	}

	/**
	 * Build the event to dispatch.
	 *
	 * @since 4.10.0
	 *
	 * @param string $name       Event name.
	 * @param array  $properties Event properties.
	 *
	 * @return Event
	 */
	protected function make_event( $name, $properties ) {

		$event = new Event( $name, $properties );

		// Without a system context the client attributes the event to the
		// admin_email user, who was not there.
		return $this->is_cli() ? $event->context( 'system' ) : $event;
	}

	/**
	 * Whether this request is running under WP-CLI.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	protected function is_cli() {

		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Whether WP-CLI is running one of our own commands.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	protected function is_our_cli_command() {

		if ( ! method_exists( WP_CLI::class, 'get_runner' ) ) {
			return false;
		}

		$arguments = WP_CLI::get_runner()->arguments;

		return ! empty( $arguments ) && $arguments[0] === 'wp-mail-smtp';
	}
}
