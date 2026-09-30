<?php

namespace WPMailSMTP\Pro;

use WPMailSMTP\Helpers\Helpers;
use WPMailSMTP\Options;
use WPMailSMTP\Pro\Abilities\EmailLogs\GetEmailLogAbility;
use WPMailSMTP\Pro\Abilities\EmailLogs\ListEmailLogsAbility;
use WPMailSMTP\Pro\Abilities\Stats\GetEmailStatsAbility;
use WPMailSMTP\Pro\AdditionalConnections\AdditionalConnections;
use WPMailSMTP\Pro\Admin\Area;
use WPMailSMTP\Pro\Admin\DashboardWidget;
use WPMailSMTP\Pro\Admin\PluginsList;
use WPMailSMTP\Pro\Admin\SetupWizard\Hosted as HostedSetupWizard;
use WPMailSMTP\Pro\Admin\SetupWizard\Local as LocalSetupWizard;
use WPMailSMTP\Pro\Alerts\Alerts;
use WPMailSMTP\Pro\Alerts\Loader as AlertsLoader;
use WPMailSMTP\Pro\BackupConnections\BackupConnections;
use WPMailSMTP\Pro\Deprecated\Pro as DeprecatedMethods;
use WPMailSMTP\Pro\Emails\Logs\Attachments\Attachments;
use WPMailSMTP\Pro\Emails\Logs\EmailsCollection;
use WPMailSMTP\Pro\Emails\Logs\Importers\Importers;
use WPMailSMTP\Pro\Emails\Logs\Logs;
use WPMailSMTP\Pro\Emails\Logs\Reports\Reports;
use WPMailSMTP\Pro\Emails\Logs\Tracking\Tracking;
use WPMailSMTP\Pro\Emails\RateLimiting\RateLimiting;
use WPMailSMTP\Pro\Emails\TestEmail;
use WPMailSMTP\Pro\ProductApi\ProductApi;
use WPMailSMTP\Pro\SetupChecklist\SetupChecklist;
use WPMailSMTP\Pro\SiteHealth;
use WPMailSMTP\Pro\SmartRouting\SmartRouting;
use WPMailSMTP\Pro\UsageTracking\ProductEvents;
use WPMailSMTP\Pro\WPCLI\Options\Registry as WPCLIOptionsRegistry;
use WPMailSMTP\Vendor\ProductApi\ProductApi as ProductApiClient;
use WPMailSMTP\WP;

/**
 * Class Pro handles all Pro plugin code and functionality registration.
 * Initialized inside 'init' WordPress hook.
 *
 * @since 1.5.0
 */
class Pro {

	/**
	 * Backward-compatibility methods relocated from this class.
	 *
	 * @since 4.10.0
	 */
	use DeprecatedMethods;

	/**
	 * Plugin slug.
	 *
	 * @since 1.5.0
	 */
	const SLUG = 'wp-mail-smtp-pro';

	/**
	 * List of files to be included early.
	 * Path from the root of the plugin directory.
	 *
	 * @since 1.5.0
	 */
	const PLUGGABLE_FILES = array(
		'src/Pro/Emails/Control/functions.php',
		'src/Pro/activation.php',
	);

	/**
	 * URL to Pro plugin assets directory.
	 *
	 * @since 1.5.0
	 *
	 * @var string Without trailing slash.
	 */
	public $assets_url = '';

	/**
	 * Pro class constructor.
	 *
	 * @since 1.5.0
	 */
	public function __construct() {

		$this->assets_url = wp_mail_smtp()->assets_url . '/pro';

		$this->init();
	}

	/**
	 * Initialize the main Pro logic.
	 *
	 * @since 1.5.0
	 * @since 3.11.0 Init SiteHealth module only in admin context.
	 */
	public function init() {

		// Load translations just in case.
		load_plugin_textdomain( 'wp-mail-smtp-pro', false, plugin_basename( wp_mail_smtp()->plugin_path ) . '/assets/pro/languages' );

		// Kept ahead of every get_*() call below: get_logs() runs Logs::init(), which
		// calls is_archive() and so reaches the memoized Core::get_admin(). That builds
		// and hooks Lite's Area, and Area::hooks() resolves the Dashboard through the
		// wp_mail_smtp_admin_area_get_dashboard filter, so this Area has to have
		// registered its substitution before then or the Lite Dashboard is cached.
		( new Area() )->hooks();

		add_filter( 'http_request_args', [ $this, 'request_lite_translations' ], 10, 2 );

		// Add the action links to a plugin on Plugins page.
		add_filter( 'plugin_action_links_' . plugin_basename( WPMS_PLUGIN_FILE ), [ $this, 'add_plugin_action_link' ], 15, 1 );

		// Register Action Scheduler tasks.
		add_filter( 'wp_mail_smtp_tasks_get_tasks', [ $this, 'get_tasks' ] );

		// Register DB migrations.
		add_filter( 'wp_mail_smtp_migrations_get_migrations', [ $this, 'get_migrations' ] );

		// Add Pro specific DB tables to the list of custom DB tables.
		add_filter( 'wp_mail_smtp_core_get_custom_db_tables', [ $this, 'add_pro_specific_custom_db_tables' ] );

		// Display custom auth notices based on the error/success codes.
		add_action( 'admin_init', [ $this, 'display_custom_auth_notices' ] );

		// Disable the admin education notice-bar.
		add_filter( 'wp_mail_smtp_admin_education_notice_bar', '__return_false' );

		add_filter( 'plugin_action_links_wp-mail-smtp/wp_mail_smtp.php', [ $this, 'replace_action_links' ] );

		// Alias WPMailArgs class.
		class_alias( 'WPMailSMTP\WPMailArgs', 'WPMailSMTP\Pro\WPMailArgs' );

		ProductApiClient::configure(
			[
				'api_url'        => defined( 'WPMS_PRODUCT_API_BASE_URL' ) ? WPMS_PRODUCT_API_BASE_URL : 'https://wpmailsmtpapi.com/',
				'site_url'       => wp_mail_smtp()->get_license_site_url()->get(),
				'license_key'    => wp_mail_smtp()->get_license_key(),
				'license_valid'  => $this->get_license()->is_valid(),
				'is_pro'         => wp_mail_smtp()->is_pro_allowed(),
				'user_agent'     => Helpers::get_default_user_agent(),
				'environment'    => wp_get_environment_type(),
				'plugin_slug'    => 'wp-mail-smtp',
				'plugin_version' => WPMS_PLUGIN_VER,
			]
		)
			->with_events( [ 'log_events_cap' => wp_mail_smtp()->get_capability_manage_options() ] )
			->boot();

		$this->get_product_events()->init();

		$this->get_multisite()->init();
		$this->get_control();
		$this->get_logs();
		$this->get_providers();
		$this->get_license();
		$this->get_additional_connections();
		$this->get_backup_connections();
		$this->get_importers();
		$this->get_translations();
		$this->get_rate_limiting();
		$this->get_product_api();

		if ( is_admin() ) {
			$this->get_site_health()->init();
		}

		if ( current_user_can( $this->get_logs()->get_manage_capability() ) ) {
			$this->get_logs_export()->init();
		}

		// Initialize alerts.
		( new Alerts() )->hooks();

		// Initialize smart routing.
		( new SmartRouting() )->hooks();

		// Initialize Plugins List.
		( new PluginsList() )->hooks();

		// Initialize test email.
		( new TestEmail() )->hooks();

		( new SetupChecklist() )->hooks();

		// Initialize upgrades.
		( new Upgrade() )->hooks();

		// Initialize WP-CLI options args registry.
		( new WPCLIOptionsRegistry() )->hooks();

		// Both wizard variants are always wired; the Lite launcher decides per
		// request which one renders. The hosted side augments REST routes, so it
		// cannot be gated on is_admin(); the bundled side is admin-only.
		( new HostedSetupWizard() )->hooks();

		if ( is_admin() ) {
			( new LocalSetupWizard() )->hooks();
		}

		// Usage tracking hooks.
		add_filter( 'wp_mail_smtp_usage_tracking_get_data', [ $this, 'usage_tracking_get_data' ] );
		add_filter( 'wp_mail_smtp_admin_pages_misc_tab_show_usage_tracking_setting', '__return_false' );
		add_filter( 'wp_mail_smtp_usage_tracking_is_enabled', '__return_true' );

		// Maybe cancel Pro recurring AS tasks for PHP 8 compatibility in v2.6.
		add_filter( 'wp_mail_smtp_migration_cancel_recurring_tasks', [ $this, 'maybe_cancel_recurring_as_tasks_for_v26' ] );

		// Use the Pro Dashboard Widget.
		add_filter(
			'wp_mail_smtp_core_get_dashboard_widget',
			function () {
				return DashboardWidget::class;
			}
		);

		// Use the Pro Reports.
		add_filter(
			'wp_mail_smtp_core_get_reports',
			function () {
				return Reports::class;
			}
		);

		// Use the Pro DBRepair.
		add_filter(
			'wp_mail_smtp_core_get_db_repair',
			function () {
				return DBRepair::class;
			}
		);

		// Use the Pro ConnectionsManager.
		add_filter(
			'wp_mail_smtp_core_get_connections_manager',
			function () {
				return ConnectionsManager::class;
			}
		);

		// Use the Pro MailCatcher.
		add_filter(
			'wp_mail_smtp_core_generate_mail_catcher',
			function () {
				return version_compare( get_bloginfo( 'version' ), '5.5-alpha', '<' ) ? MailCatcher::class : MailCatcherV6::class;
			}
		);

		// Fix `Options::array_merge_recursive` numeric keys array duplicates.
		add_filter(
			'wp_mail_smtp_options_set',
			function ( $options ) {
				foreach ( array_keys( ( new AlertsLoader() )->get_providers() ) as $alert ) {
					if ( isset( $options["alert_$alert"]['connections'] ) ) {
						$options["alert_$alert"]['connections'] = array_unique(
							$options["alert_$alert"]['connections'],
							SORT_REGULAR
						);
					}
				}

				if ( isset( $options['outlook']['scopes'] ) ) {
					$options['outlook']['scopes'] = array_unique( $options['outlook']['scopes'], SORT_REGULAR );
				}

				return $options;
			}
		);

		wp_mail_smtp()->get_abilities_registrar()->add(
			[
				ListEmailLogsAbility::class,
				GetEmailLogAbility::class,
				GetEmailStatsAbility::class,
			]
		);
	}

	/**
	 * Load the Control functionality.
	 *
	 * @since 1.5.0
	 *
	 * @return Emails\Control\Control
	 */
	public function get_control() {

		static $control;

		if ( ! isset( $control ) ) {
			$control = apply_filters( 'wp_mail_smtp_pro_get_control', new Emails\Control\Control() );

			if ( method_exists( $control, 'init' ) ) {
				$control->init();
			}
		}

		return $control;
	}

	/**
	 * Load the Logs functionality.
	 *
	 * @since 1.5.0
	 *
	 * @return Emails\Logs\Logs
	 */
	public function get_logs() {

		static $logs;

		if ( ! isset( $logs ) ) {
			$logs = apply_filters( 'wp_mail_smtp_pro_get_logs', new Emails\Logs\Logs() );

			if ( method_exists( $logs, 'init' ) ) {
				$logs->init();
			}
		}

		return $logs;
	}

	/**
	 * Load the Logs export functionality.
	 *
	 * @since 2.9.0
	 *
	 * @return Emails\Logs\Export\Export
	 */
	public function get_logs_export() {

		static $logs_export;

		if ( ! isset( $logs_export ) ) {
			$logs_export = apply_filters( 'wp_mail_smtp_pro_get_logs_export', new Emails\Logs\Export\Export() );
		}

		return $logs_export;
	}

	/**
	 * Load the new Providers functionality.
	 *
	 * @since 1.5.0
	 *
	 * @return \WPMailSMTP\Pro\Providers\Providers
	 */
	public function get_providers() {

		static $providers;

		if ( ! isset( $providers ) ) {
			$providers = apply_filters( 'wp_mail_smtp_pro_get_providers', new Providers\Providers() );

			if ( method_exists( $providers, 'init' ) ) {
				$providers->init();
			}
		}

		return $providers;
	}

	/**
	 * Load the new License functionality.
	 *
	 * @since 1.5.0
	 *
	 * @return \WPMailSMTP\Pro\License\License
	 */
	public function get_license() {

		static $license;

		if ( ! isset( $license ) ) {
			$license = apply_filters( 'wp_mail_smtp_pro_get_license', new License\License() );

			if ( method_exists( $license, 'init' ) ) {
				$license->init();
			}
		}

		return $license;
	}

	/**
	 * Load the Site Health functionality.
	 *
	 * @since 1.9.0
	 *
	 * @return SiteHealth
	 */
	public function get_site_health() {

		static $site_health;

		if ( ! isset( $site_health ) ) {
			/**
			 * Filters the Site Health instance.
			 *
			 * @since 1.9.0
			 *
			 * @param SiteHealth $site_health The Site Health instance.
			 */
			$site_health = apply_filters( 'wp_mail_smtp_pro_get_site_health', new SiteHealth() );
		}

		return $site_health;
	}

	/**
	 * Get the Multisite object.
	 *
	 * @since 2.2.0
	 *
	 * @return Multisite
	 */
	public function get_multisite() {

		static $multisite;

		if ( ! isset( $multisite ) ) {
			$multisite = apply_filters( 'wp_mail_smtp_pro_get_multisite', new Multisite() );
		}

		return $multisite;
	}

	/**
	 * Load the Additional Connections functionality.
	 *
	 * @since 3.7.0
	 *
	 * @return AdditionalConnections
	 */
	public function get_additional_connections() {

		static $additional_connections;

		if ( ! isset( $additional_connections ) ) {

			/**
			 * Filter the Additional Connections object.
			 *
			 * @since 3.7.0
			 *
			 * @param AdditionalConnections $additional_connections The Additional Connections object.
			 */
			$additional_connections = apply_filters( 'wp_mail_smtp_pro_get_get_additional_connections', new AdditionalConnections() );

			if ( method_exists( $additional_connections, 'hooks' ) ) {
				$additional_connections->hooks();
			}
		}

		return $additional_connections;
	}

	/**
	 * Load the Backup Connections functionality.
	 *
	 * @since 3.7.0
	 *
	 * @return BackupConnections
	 */
	public function get_backup_connections() {

		static $backup_connections;

		if ( ! isset( $backup_connections ) ) {

			/**
			 * Filter the Backup Connections object.
			 *
			 * @since 3.7.0
			 *
			 * @param BackupConnections $backup_connections The Backup Connections object.
			 */
			$backup_connections = apply_filters( 'wp_mail_smtp_pro_get_get_backup_connections', new BackupConnections() );

			if ( method_exists( $backup_connections, 'hooks' ) ) {
				$backup_connections->hooks();
			}
		}

		return $backup_connections;
	}

	/**
	 * Load the Importers functionality.
	 *
	 * @since 3.8.0
	 *
	 * @return Importers
	 */
	public function get_importers() {

		static $importers;

		if ( ! isset( $importers ) ) {
			/**
			 * Filter the Importers object.
			 *
			 * @since 3.8.0
			 *
			 * @param Importers $importers The Importers object.
			 */
			$importers = apply_filters( 'wp_mail_smtp_pro_get_importers', new Importers() );

			if ( method_exists( $importers, 'init' ) ) {
				$importers->init();
			}
		}

		return $importers;
	}

	/**
	 * Load the Pro Translations functionality.
	 *
	 * @since 3.9.0
	 *
	 * @return Translations
	 */
	public function get_translations() {

		static $translations;

		if ( ! isset( $translations ) ) {
			/**
			 * Filter the Translations object.
			 *
			 * @since 3.9.0
			 *
			 * @param Translations $translations The Translations object.
			 */
			$translations = apply_filters( 'wp_mail_smtp_pro_get_translations', new Translations() );

			if ( method_exists( $translations, 'hooks' ) ) {
				$translations->hooks();
			}
		}

		return $translations;
	}

	/**
	 * Adds WP Mail SMTP (Lite) to the update checklist of installed plugins, to check for new translations.
	 *
	 * @since 1.6.0
	 *
	 * @param array  $args HTTP Request arguments to modify.
	 * @param string $url  The HTTP request URI that is executed.
	 *
	 * @return array The modified Request arguments to use in the update request.
	 */
	public function request_lite_translations( $args, $url ) {

		// Only do something on upgrade requests.
		if ( strpos( $url, 'api.wordpress.org/plugins/update-check' ) === false ) {
			return $args;
		}

		// Bail if plugins list is missing.
		if ( empty( $args['body']['plugins'] ) ) {
			return $args;
		}

		/*
		 * If WP Mail SMTP is already in the list, don't add it again.
		 *
		 * Checking this by name because the install path is not guaranteed.
		 * The capitalized json data defines the array keys, therefore we need to check and define these as such.
		 */
		$plugins = json_decode( $args['body']['plugins'], true );

		// Bail if plugin list can't be decoded or plugins list is missing.
		if ( $plugins === null || ! isset( $plugins['plugins'] ) ) {
			return $args;
		}

		foreach ( $plugins['plugins'] as $slug => $data ) {
			if ( isset( $data['Name'] ) && $data['Name'] === 'WP Mail SMTP' ) {
				return $args;
			}
		}

		// Pro plugin (current plugin) key in $plugins['plugins'].
		$pro_plugin_key = plugin_basename( wp_mail_smtp()->plugin_path ) . '/wp_mail_smtp.php';

		// The pro plugin key has to exist for the code below to work.
		if ( ! isset( $plugins['plugins'][ $pro_plugin_key ] ) ) {
			return $args;
		}

		/*
		 * Add an entry to the list that matches the WordPress.org slug for WP Mail SMTP Lite.
		 *
		 * This entry is based on the currently present data from this plugin, to make sure the version and textdomain
		 * settings are as expected. Take care of the capitalized array key as before.
		 */
		$plugins['plugins']['wp-mail-smtp/wp_mail_smtp.php'] = $plugins['plugins'][ $pro_plugin_key ];
		// Override the name of the plugin.
		$plugins['plugins']['wp-mail-smtp/wp_mail_smtp.php']['Name'] = 'WP Mail SMTP';
		// Override the version of the plugin to prevent increasing the update count.
		$plugins['plugins']['wp-mail-smtp/wp_mail_smtp.php']['Version'] = '9999.0';

		// Overwrite the plugins argument in the body to be sent in the upgrade request.
		$args['body']['plugins'] = wp_json_encode( $plugins );

		return $args;
	}

	/**
	 * Add Pro specific custom DB tables to the list of all plugin's custom DB tables.
	 *
	 * @since 2.1.2
	 *
	 * @param array $tables A list of existing custom tables.
	 *
	 * @return array
	 */
	public function add_pro_specific_custom_db_tables( $tables ) {

		$pro_tables = [];

		if ( $this->get_logs()->is_enabled() ) {
			$pro_tables[] = Logs::get_table_name();
		}

		if ( $this->get_logs()->is_enabled_save_attachments() ) {
			$pro_tables[] = Attachments::get_email_attachments_table_name();
			$pro_tables[] = Attachments::get_attachment_files_table_name();
		}

		if ( $this->get_logs()->is_enabled_tracking() ) {
			$pro_tables[] = Tracking::get_events_table_name();
			$pro_tables[] = Tracking::get_links_table_name();
		}

		return array_merge( $tables, $pro_tables );
	}

	/**
	 * Add plugin action links on Plugins page.
	 *
	 * @since 2.0.0
	 *
	 * @param array $links Existing plugin action links.
	 *
	 * @return array
	 */
	public function add_plugin_action_link( $links ) {

		/*
		 * Add "Settings" links in almost all cases, except if in Multisite setup
		 * and network-wide option is enabled.
		 */
		if ( ! WP::use_global_plugin_settings() ) {
			$custom['wp-mail-smtp-settings'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				esc_url( wp_mail_smtp()->get_admin()->get_admin_page_url() ),
				esc_attr__( 'Go to WP Mail SMTP Settings page', 'wp-mail-smtp-pro' ),
				esc_html__( 'Settings', 'wp-mail-smtp-pro' )
			);
		}

		$custom['wp-mail-smtp-support'] = sprintf(
			'<a href="%1$s" target="_blank" aria-label="%2$s" rel="noopener noreferrer">%3$s</a>',
			// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			esc_url( wp_mail_smtp()->get_utm_url( 'https://wpmailsmtp.com/account/support/', [ 'medium' => 'all-plugins', 'content' => 'Support' ] ) ),
			esc_attr__( 'Go to WPMailSMTP.com support page', 'wp-mail-smtp-pro' ),
			esc_html__( 'Support', 'wp-mail-smtp-pro' )
		);

		$custom['wp-mail-smtp-docs'] = sprintf(
			'<a href="%1$s" target="_blank" aria-label="%2$s" rel="noopener noreferrer">%3$s</a>',
			// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			esc_url( wp_mail_smtp()->get_utm_url( 'https://wpmailsmtp.com/docs/', [ 'medium' => 'all-plugins', 'content' => 'Documentation' ] ) ),
			esc_attr__( 'Go to WPMailSMTP.com documentation page', 'wp-mail-smtp-pro' ),
			esc_html__( 'Docs', 'wp-mail-smtp-pro' )
		);

		return array_merge( $custom, (array) $links );
	}

	/**
	 * Register the pro version Action Scheduler tasks.
	 *
	 * @since 2.1.0
	 * @since 2.1.2 Add EmailLogMigration4 task.
	 * @since 2.2.0 Add EmailLogMigration5 task.
	 * @since 3.8.0 Add EmailLogMigration11 task.
	 *
	 * @param array $tasks Action Scheduler tasks to be registered.
	 *
	 * @return array
	 */
	public function get_tasks( $tasks ) {

		// phpcs:disable WPForms.PHP.BackSlash.UseShortSyntax
		return array_merge(
			$tasks,
			[
				\WPMailSMTP\Pro\Tasks\EmailLogCleanupTask::class,
				\WPMailSMTP\Pro\Tasks\Migrations\EmailLogMigration4::class,
				\WPMailSMTP\Pro\Tasks\Migrations\EmailLogMigration5::class,
				\WPMailSMTP\Pro\Tasks\Migrations\EmailLogMigration11::class,
				\WPMailSMTP\Pro\Tasks\Logs\Sendlayer\VerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\MailerSend\VerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\Mailgun\VerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\Sendinblue\VerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\SMTPcom\VerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\Postmark\VerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\SparkPost\VerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\SMTP2GO\VerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\Mailjet\VerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\ElasticEmail\VerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\Mandrill\VerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\Resend\VerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\ExportCleanupTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\ResendTask::class,
				\WPMailSMTP\Pro\Tasks\Logs\BulkVerifySentStatusTask::class,
				\WPMailSMTP\Pro\Tasks\NotifierTask::class,
				\WPMailSMTP\Pro\Tasks\LicenseCheckTask::class,
			]
		);
		// phpcs:enable WPForms.PHP.BackSlash.UseShortSyntax
	}

	/**
	 * Register DB migrations.
	 *
	 * @since 3.0.0
	 *
	 * @param array $migrations Migrations classes.
	 *
	 * @return array
	 */
	public function get_migrations( $migrations ) {

		// phpcs:disable WPForms.PHP.BackSlash.UseShortSyntax
		return array_merge(
			$migrations,
			[
				Migration::class,
				\WPMailSMTP\Pro\Emails\Logs\Migration::class,
				\WPMailSMTP\Pro\Emails\Logs\Tracking\Migration::class,
				\WPMailSMTP\Pro\Emails\Logs\Attachments\Migration::class,
			]
		);
		// phpcs:enable WPForms.PHP.BackSlash.UseShortSyntax
	}

	/**
	 * Display custom auth notices for pro mailers based on the error/success codes.
	 *
	 * @since 2.3.0
	 */
	public function display_custom_auth_notices() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$error   = isset( $_GET['error'] ) ? sanitize_key( $_GET['error'] ) : '';
		$success = isset( $_GET['success'] ) ? sanitize_key( $_GET['success'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( empty( $error ) && empty( $success ) ) {
			return;
		}

		if ( ! current_user_can( wp_mail_smtp()->get_capability_manage_options() ) ) {
			return;
		}

		switch ( $error ) {
			case 'oauth_invalid_connection':
				WP::add_admin_notice_with_debug(
					esc_html__( 'There was an error while processing the authentication request. The connection was not found. Please try again.', 'wp-mail-smtp-pro' ),
					WP::ADMIN_NOTICE_ERROR
				);
				break;

			case 'microsoft_no_code':
			case 'zoho_no_code':
				WP::add_admin_notice_with_debug(
					esc_html__( 'There was an error while processing the authentication request. The authorization code is missing. Please try again.', 'wp-mail-smtp-pro' ),
					WP::ADMIN_NOTICE_ERROR
				);
				break;

			case 'microsoft_invalid_nonce':
			case 'zoho_invalid_nonce':
				WP::add_admin_notice_with_debug(
					esc_html__( 'There was an error while processing the authentication request. The nonce is invalid. Please try again.', 'wp-mail-smtp-pro' ),
					WP::ADMIN_NOTICE_ERROR
				);
				break;

			case 'microsoft_unsuccessful_oauth':
				WP::add_admin_notice_with_debug(
					esc_html__( 'There was an error while processing the authentication request. Please recheck your Client ID and Client Secret and try again.', 'wp-mail-smtp-pro' ),
					WP::ADMIN_NOTICE_ERROR
				);
				break;

			case 'zoho_no_clients':
				WP::add_admin_notice_with_debug(
					esc_html__( 'There was an error while processing the authentication request. Please make sure that you have Client ID and Client Secret both valid and saved.', 'wp-mail-smtp-pro' ),
					WP::ADMIN_NOTICE_ERROR
				);
				break;

			case 'zoho_access_denied':
				WP::add_admin_notice_with_debug(
				/* translators: %s - error code, returned by Zoho API. */
					sprintf( esc_html__( 'There was an error while processing the authentication request: %s. Please try again.', 'wp-mail-smtp-pro' ), '<code>' . esc_html( $error ) . '</code>' ),
					WP::ADMIN_NOTICE_ERROR
				);
				break;

			case 'zoho_unsuccessful_oauth':
				WP::add_admin_notice_with_debug(
					esc_html__( 'There was an error while processing the authentication request. Please recheck your Region, Client ID and Client Secret and try again.', 'wp-mail-smtp-pro' ),
					WP::ADMIN_NOTICE_ERROR
				);
				break;

			case 'google_one_click_setup_unsuccessful_oauth':
			case 'outlook_one_click_setup_unsuccessful_oauth':
				WP::add_admin_notice_with_debug(
					esc_html__( 'There was an error while processing the authentication request.', 'wp-mail-smtp-pro' ),
					WP::ADMIN_NOTICE_ERROR
				);
				break;
		}

		switch ( $success ) {
			case 'microsoft_site_linked':
				WP::add_admin_notice_with_debug(
					esc_html__( 'You have successfully linked the current site with your Microsoft API project. Now you can start sending emails through Outlook.', 'wp-mail-smtp-pro' ),
					WP::ADMIN_NOTICE_SUCCESS
				);
				break;

			case 'zoho_site_linked':
				WP::add_admin_notice_with_debug(
					esc_html__( 'You have successfully linked the current site with your Zoho Mail API project. Now you can start sending emails through Zoho Mail.', 'wp-mail-smtp-pro' ),
					WP::ADMIN_NOTICE_SUCCESS
				);
				break;

			case 'google_one_click_setup_site_linked':
				WP::add_admin_notice_with_debug(
					esc_html__( 'You have successfully connected your site with your Gmail account. This site will now send emails via your Gmail account.', 'wp-mail-smtp-pro' ),
					WP::ADMIN_NOTICE_SUCCESS
				);
				break;

			case 'outlook_one_click_setup_site_linked':
				WP::add_admin_notice_with_debug(
					esc_html__( 'You have successfully connected your site with your Outlook account. This site will now send emails via your Outlook account.', 'wp-mail-smtp-pro' ),
					WP::ADMIN_NOTICE_SUCCESS
				);
				break;
		}
	}

	/**
	 * Add the Pro usage tracking data.
	 *
	 * @since 2.3.0
	 *
	 * @param array $data The existing usage tracking data.
	 *
	 * @return array
	 */
	public function usage_tracking_get_data( $data ) {

		$options = Options::init();

		$disabled_controls = [];

		// Get the state of each control.
		foreach ( $this->get_control()->get_controls( true ) as $key ) {
			if ( (bool) $options->get( 'control', $key ) ) {
				$disabled_controls[] = $key;
			}
		}

		$data['wp_mail_smtp_pro_enable_log']              = (bool) $options->get( 'logs', 'enabled' );
		$data['wp_mail_smtp_pro_log_email_content']       = (bool) $options->get( 'logs', 'log_email_content' );
		$data['wp_mail_smtp_pro_log_save_attachments']    = (bool) $options->get( 'logs', 'save_attachments' );
		$data['wp_mail_smtp_pro_log_open_email_tracking'] = (bool) $options->get( 'logs', 'open_email_tracking' );
		$data['wp_mail_smtp_pro_log_click_link_tracking'] = (bool) $options->get( 'logs', 'click_link_tracking' );
		$data['wp_mail_smtp_pro_log_retention_period']    = $options->get( 'logs', 'log_retention_period' );
		$data['wp_mail_smtp_pro_log_entry_count']         = $this->get_logs()->is_valid_db() ? ( new EmailsCollection() )->get_count() : 0;
		$data['wp_mail_smtp_pro_disabled_controls']       = $disabled_controls;

		// Alerts usage tracking.
		$alerts_loader = new AlertsLoader();

		$enabled_alerts = array_filter(
			array_keys( $alerts_loader->get_providers() ),
			function ( $provider_slug ) use ( $options ) {
				return $options->get( 'alert_' . $provider_slug, 'enabled' );
			}
		);

		$data['wp_mail_smtp_pro_alerts_enabled'] = count( $enabled_alerts ) > 0;

		foreach ( $enabled_alerts as $provider_slug ) {
			$connections = $options->get( 'alert_' . $provider_slug, 'connections' );

			$data[ 'wp_mail_smtp_pro_alerts_enabled_channel_' . $provider_slug ] = count( $connections );
		}

		$additional_connections    = $this->get_additional_connections()->get_configured_connections();
		$backup_connection_enabled = ! empty( Options::init()->get( 'backup_connection', 'connection_id' ) );
		$smart_routing_enabled     = (bool) Options::init()->get( 'smart_routing', 'enabled' );

		$data['wp_mail_smtp_pro_additional_connections_count'] = count( $additional_connections );
		$data['wp_mail_smtp_pro_backup_connection_enabled']    = $backup_connection_enabled;
		$data['wp_mail_smtp_pro_smart_routing_enabled']        = $smart_routing_enabled;

		// Rate Limiting usage tracking.
		$data['wp_mail_smtp_pro_rate_limiting_enabled'] = (bool) RateLimiting::is_enabled();

		return $data;
	}

	/**
	 * Add any Pro AS tasks that need to be temporary canceled (reset) for PHP 8 compatibility in v2.6 release.
	 *
	 * @since 2.6.0
	 *
	 * @param array $tasks The default tasks that will be canceled.
	 *
	 * @return array
	 */
	public function maybe_cancel_recurring_as_tasks_for_v26( $tasks ) {

		// Get the Logs retention period setting.
		$retention_period = Options::init()->get( 'logs', 'log_retention_period' );

		if ( ! empty( $retention_period ) ) {
			$tasks[] = '\WPMailSMTP\Pro\Tasks\EmailLogCleanupTask';
		}

		return $tasks;
	}

	/**
	 * Load the rate limiting functionality.
	 *
	 * @since 4.0.0
	 *
	 * @return Emails\RateLimiting
	 */
	public function get_rate_limiting() {

		static $rate_limiting;

		if ( ! isset( $rate_limiting ) ) {
			/**
			 * Filter the RateLimiting object.
			 *
			 * @since 4.0.0
			 *
			 * @param RateLimiting $rate_limiting The RateLimiting object.
			 */
			$rate_limiting = apply_filters( 'wp_mail_smtp_pro_get_rate_limiting', new RateLimiting() );

			$rate_limiting->hooks();
		}

		return $rate_limiting;
	}

	/**
	 * Replace the activation link for the Lite version of WP Mail SMTP.
	 * Activating the Lite version of WP Mail SMTP is not allowed when the Pro version is active.
	 *
	 * @since 4.4.0
	 *
	 * @param array $links Plugin row links.
	 *
	 * @return array
	 */
	public function replace_action_links( array $links ): array {

		$links['activate'] = __( 'Inactive &mdash; You are already using WP Mail SMTP Pro', 'wp-mail-smtp-pro' );

		return $links;
	}

	/**
	 * Load the product API functionality.
	 *
	 * @since 4.4.0
	 *
	 * @return ProductApi
	 */
	public function get_product_api() {

		static $product_api;

		if ( ! isset( $product_api ) ) {

			/**
			 * Filter the ProductApi object.
			 *
			 * @since 4.4.0
			 *
			 * @param ProductApi $product_api The ProductApi object.
			 */
			$product_api = apply_filters( 'wp_mail_smtp_pro_product_api', new ProductApi() );

			$product_api->hooks();
		}

		return $product_api;
	}

	/**
	 * Get the ProductEvents object.
	 *
	 * @since 4.10.0
	 *
	 * @return ProductEvents
	 */
	public function get_product_events() {

		static $product_events;

		if ( ! isset( $product_events ) ) {

			/**
			 * Filter the ProductEvents object.
			 *
			 * @since 4.10.0
			 *
			 * @param ProductEvents $product_events The ProductEvents object.
			 */
			$product_events = apply_filters( 'wp_mail_smtp_pro_product_events', new ProductEvents() );
		}

		return $product_events;
	}
}
