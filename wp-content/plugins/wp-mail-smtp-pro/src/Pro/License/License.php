<?php

namespace WPMailSMTP\Pro\License;

use WPMailSMTP\Admin\DebugEvents\DebugEvents;
use WPMailSMTP\Helpers\Helpers;
use WPMailSMTP\Options;
use WPMailSMTP\Pro\Pro;
use WPMailSMTP\WP;

/**
 * License key fun.
 *
 * @since 1.5.0
 */
class License {

	/**
	 * Interval time, in days, to remote fetch the latest version.
	 *
	 * @since 3.8.0
	 *
	 * @var int
	 */
	const REMOTE_FETCH_LATEST_VERSION_INTERVAL_IN_DAYS = 7;

	/**
	 * Cache key for remote latest version.
	 *
	 * @since 3.8.0
	 *
	 * @var string
	 */
	const CACHE_REMOTE_LATEST_VERSION_KEY = 'wp_mail_smtp_latest_remote_version';

	/**
	 * Holds any license error messages.
	 *
	 * @since 1.5.0
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * Holds any license success messages.
	 *
	 * @since 1.5.0
	 *
	 * @var array
	 */
	public $success = array();

	/**
	 * Remote URL for getting license information.
	 *
	 * @since 1.5.0
	 *
	 * @var string
	 */
	public $remote_url = 'https://wpmailsmtpapi.com/license/v1';

	/**
	 * Remote URL for getting the latest version information.
	 *
	 * @since 3.8.0
	 *
	 * @var string
	 */
	private $latest_version_remote_url = 'https://wpmailsmtpapi.com/feeds/v1/core-plugin-info';

	/**
	 * Primary class constructor.
	 *
	 * @since 1.5.0
	 */
	public function __construct() {}

	/**
	 * Class initialization.
	 *
	 * @since 4.0.0
	 */
	public function init() {

		$this->register_updater();
		$this->hooks();

		( new BundledLicense() )->hooks();
		( new Gate( $this ) )->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 4.0.0
	 */
	protected function hooks() {

		// Register licensing ajax action (with custom tasks).
		add_action( 'wp_ajax_wp_mail_smtp_pro_license_ajax', array( $this, 'process_ajax' ) );

		// Filter admin area options save process.
		add_filter( 'wp_mail_smtp_options_set', array( $this, 'filter_options_set' ) );

		// Redefine licensing field content.
		add_filter( 'wp_mail_smtp_admin_get_pages', function ( $pages ) {

			remove_action( 'wp_mail_smtp_admin_pages_settings_license_key', array(
				\WPMailSMTP\Admin\Pages\SettingsTab::class,
				'display_license_key_field_content',
			) );

			add_action( 'wp_mail_smtp_admin_pages_settings_license_key', [ $this, 'render_settings_license_key_field' ] );

			return $pages;
		} );

		add_action( 'wp_mail_smtp_admin_pages_settings_tab_before_license_key', [ $this, 'render_license_banner' ] );
		add_filter( 'wp_mail_smtp_admin_setup_wizard_hosted_license_state', [ $this, 'get_state' ] );

		add_action( 'admin_init', [ $this, 'maybe_adopt_moved_site_url' ] );

		// Admin notices.
		add_action( 'admin_notices', [ $this, 'notices' ] );

		if ( WP::use_global_plugin_settings() ) {
			add_action( 'network_admin_notices', [ $this, 'notices' ] );
		}
	}

	/**
	 * Load plugin updater.
	 *
	 * @since 1.5.0
	 */
	protected function register_updater() {

		// Only in admin area or WP CLI.
		if ( ! is_admin() && ! Helpers::is_wp_cli() ) {
			return;
		}

		$key = wp_mail_smtp()->get_license_key();

		// Only if we have the key.
		if ( empty( $key ) ) {
			return;
		}

		// Initialize the updater.
		new Updater(
			array(
				'plugin_name' => 'WP Mail SMTP Pro',
				'plugin_slug' => Pro::SLUG,
				'plugin_path' => Pro::SLUG . '/wp_mail_smtp.php',
				'plugin_url'  => trailingslashit( wp_mail_smtp()->plugin_url ),
				'version'     => WPMS_PLUGIN_VER,
				'key'         => $key,
			)
		);
	}

	/**
	 * Process AJAX requests fired by a pro version of a plugin and related to a license management.
	 *
	 * @since 1.5.0
	 */
	public function process_ajax() {

		$generic_error = esc_html__( 'Something went wrong. Please try again later.', 'wp-mail-smtp-pro' );

		// Verify nonce.
		if ( ! check_ajax_referer( 'wp-mail-smtp-admin', 'nonce', false ) ) {
			wp_send_json_error( $generic_error );
		}

		if ( ! current_user_can( wp_mail_smtp()->get_capability_manage_global_options() ) ) {
			wp_send_json_error( $generic_error );
		}

		$task = isset( $_POST['task'] ) ? sanitize_key( $_POST['task'] ) : '';

		switch ( $task ) {
			case 'license_verify':
				$license = isset( $_POST['license'] ) ? sanitize_key( $_POST['license'] ) : '';

				if ( empty( $license ) ) {
					wp_send_json_error( esc_html__( 'Please enter your license key.', 'wp-mail-smtp-pro' ) );
				}

				$this->verify_key( $license, true );
				break;

			case 'license_deactivate':
				$this->deactivate_key( true );
				break;

			case 'license_refresh':
				$this->validate_key( wp_mail_smtp()->get_license_key(), true, true );
				break;
		}

		// Process unknown tasks or other edge cases.
		wp_send_json_error( $generic_error );
	}

	/**
	 * The "force a refresh" line shown under a license key that needs re-checking.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	private function get_refresh_message() {

		return sprintf(
			wp_kses( /* translators: %1$s - opening link tag; %2$s - closing link tag. */
				__( 'If your license has been renewed or upgraded, then please force a %1$sstatus refresh%2$s.', 'wp-mail-smtp-pro' ),
				// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				[ 'a' => [ 'href' => [], 'class' => [] ] ]
			),
			'<a href="#" class="js-wp-mail-smtp-license-key-refresh wpms-license-key-field__link">',
			'</a>'
		);
	}

	/**
	 * The prompt for someone who has no license yet.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	public function get_signup_prompt() {

		return sprintf(
			wp_kses( /* translators: %s - pricing page link. */
				__( 'Don\'t have a license key? %s', 'wp-mail-smtp-pro' ),
				// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				[ 'a' => [ 'href' => [], 'target' => [], 'rel' => [], 'class' => [] ] ]
			),
			sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__link">%s</a>',
				esc_url( wp_mail_smtp()->get_upgrade_link( [ 'content' => 'License Sign Up Today' ] ) ),
				esc_html__( 'Sign up today!', 'wp-mail-smtp-pro' )
			)
		);
	}

	/**
	 * The prompt for someone holding a key the server will not accept.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	public function get_another_key_prompt() {

		return sprintf(
			wp_kses( /* translators: %s - pricing page link. */
				__( 'Don\'t have another license key? %s', 'wp-mail-smtp-pro' ),
				// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				[ 'a' => [ 'href' => [], 'target' => [], 'rel' => [], 'class' => [] ] ]
			),
			sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__link">%s</a>',
				esc_url( wp_mail_smtp()->get_upgrade_link( [ 'content' => 'License Sign Up Today' ] ) ),
				esc_html__( 'Sign up today!', 'wp-mail-smtp-pro' )
			)
		);
	}

	/**
	 * The prompt for someone whose license expired and who has not renewed yet.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	public function get_renewal_prompt() {

		return sprintf(
			wp_kses( /* translators: %s - license renewal link. */
				__( 'Don\'t have a renewed license key yet? %s.', 'wp-mail-smtp-pro' ),
				// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				[ 'a' => [ 'href' => [], 'target' => [], 'rel' => [], 'class' => [] ] ]
			),
			sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__link">%s</a>',
				esc_url( $this->get_renewal_link( [ 'content' => 'License Key Renew Your Plan' ] ) ),
				esc_html__( 'Renew Your Plan', 'wp-mail-smtp-pro' )
			)
		);
	}

	/**
	 * Where a license key is found, with an optional follow-up sentence.
	 *
	 * @since 4.10.0
	 *
	 * @param string $follow_up Sentence appended after it, already escaped.
	 *
	 * @return string
	 */
	private function get_account_dashboard_message( $follow_up = '' ) {

		$message = sprintf(
			wp_kses( /* translators: %s - WP Mail SMTP account dashboard link. */
				__( 'Your license key can be found in your %s.', 'wp-mail-smtp-pro' ),
				// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				[ 'a' => [ 'href' => [], 'target' => [], 'rel' => [], 'class' => [] ] ]
			),
			sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__account-link">%s</a>',
				esc_url( wp_mail_smtp()->get_utm_url( 'https://wpmailsmtp.com/account/', [ 'content' => 'License Key Account Dashboard Link' ] ) ),
				esc_html__( 'WP Mail SMTP Account Dashboard', 'wp-mail-smtp-pro' )
			)
		);

		return empty( $follow_up ) ? $message : $message . ' ' . $follow_up;
	}

	/**
	 * The license's standing, said in one sentence.
	 *
	 * @since 4.10.0
	 *
	 * @param string $state The state to speak for. The license's own when empty.
	 *
	 * @return string Empty while the license is valid.
	 */
	public function get_state_title( $state = '' ) {

		if ( empty( $state ) ) {
			$state = $this->get_state();
		}

		$titles = [
			'no_key'        => esc_html__( 'Enter and activate your license key', 'wp-mail-smtp-pro' ),
			'expired'       => esc_html__( 'Your license has expired.', 'wp-mail-smtp-pro' ),
			'limit_reached' => esc_html__( 'Your license has no site activations left.', 'wp-mail-smtp-pro' ),
			'disabled'      => esc_html__( 'Your license has been disabled.', 'wp-mail-smtp-pro' ),
			'invalid'       => esc_html__( 'Your license is invalid.', 'wp-mail-smtp-pro' ),
		];

		return isset( $titles[ $state ] ) ? $titles[ $state ] : '';
	}

	/**
	 * The two ways out of a license with no site activations left.
	 *
	 * @since 4.10.0
	 *
	 * @param array|string $utm Array of UTM params, or if string provided - utm_content URL parameter.
	 *
	 * @return string Escaped markup, echoed as-is.
	 */
	public function get_limit_reached_message( $utm ) {

		return sprintf(
			wp_kses( /* translators: %1$s - WPMailSMTP.com account area URL; %2$s - WP Mail SMTP upgrade page URL. */
				__( 'You can update the list of your sites or upgrade the license in the <a href="%1$s" target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__link">Account area</a>. Or you can <a href="%2$s" target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__link">purchase a new license key</a>.', 'wp-mail-smtp-pro' ),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
						'class'  => [],
					],
				]
			),
			esc_url( wp_mail_smtp()->get_utm_url( 'https://wpmailsmtp.com/account/licenses/', $utm ) ),
			esc_url( wp_mail_smtp()->get_upgrade_link( $utm ) )
		);
	}

	/**
	 * Why a key the server no longer recognises is invalid.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	private function get_invalid_key_reason() {

		return esc_html__( 'The license key no longer exists or the user associated with it has been deleted.', 'wp-mail-smtp-pro' );
	}

	/**
	 * What is asked of someone holding a key the server will not accept.
	 *
	 * @since 4.10.0
	 *
	 * @param string $utm The utm_content URL parameter.
	 *
	 * @return string
	 */
	private function get_replace_key_message( $utm ) {

		return sprintf(
			wp_kses( /* translators: %s - WPMailSMTP.com account licenses URL. */
				__( '<a href="%s" target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__link">Please use a different license key</a> to use WP Mail SMTP Pro features and enable automatic updates.', 'wp-mail-smtp-pro' ),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
						'class'  => [],
					],
				]
			),
			esc_url( wp_mail_smtp()->get_utm_url( 'https://wpmailsmtp.com/account/licenses/', $utm ) )
		);
	}

	/**
	 * What an unusable license is told about itself, with the way out linked.
	 *
	 * @since 4.10.0
	 *
	 * @param string $state      One of `expired`, `limit_reached`, `disabled` or `invalid`.
	 * @param string $utm_medium Identifies the surface doing the reporting.
	 *
	 * @return string Escaped markup, echoed as-is.
	 */
	public function get_state_report( $state, $utm_medium ) {

		$utm = [
			'medium'  => $utm_medium,
			'content' => 'license ' . str_replace( '_', ' ', $state ),
		];

		if ( $state === 'expired' ) {
			$body = sprintf(
				wp_kses( /* translators: %s - WPMailSMTP.com license renewal URL. */
					__( '<a href="%s" target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__link">Please renew and activate your license key</a> to use WP Mail SMTP Pro features and enable automatic updates.', 'wp-mail-smtp-pro' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
							'class'  => [],
						],
					]
				),
				esc_url( $this->get_renewal_link( $utm ) )
			);
		} elseif ( $state === 'limit_reached' ) {
			$body = $this->get_limit_reached_message( $utm );
		} else {
			$body = $this->get_replace_key_message( $utm );

			if ( $state === 'invalid' ) {
				$body = esc_html( $this->get_invalid_key_reason() ) . ' ' . $body;
			}
		}

		return sprintf( '<b>%1$s</b> %2$s', $this->get_state_title( $state ), $body );
	}

	/**
	 * The report that a license key was accepted by the server.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	public function get_key_verified_message() {

		return esc_html__( 'Congratulations! This site is now receiving automatic updates.', 'wp-mail-smtp-pro' );
	}

	/**
	 * The report that a license key was re-checked against the server.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	public function get_key_refreshed_message() {

		return esc_html__( 'Your license key has been refreshed successfully.', 'wp-mail-smtp-pro' );
	}

	/**
	 * The message reporting that a key was released from this site.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	public function get_key_removed_message() {

		return esc_html__( 'Your license key has been removed from this site.', 'wp-mail-smtp-pro' );
	}

	/**
	 * The message shown when the licensing server could not be reached.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	public function get_remote_error_message() {

		return esc_html__( 'There was an error connecting to the remote server. Please try again later.', 'wp-mail-smtp-pro' );
	}

	/**
	 * Redefine admin area Settings page License Key field content.
	 *
	 * @since      1.5.0
	 * @since      3.11.0 Removed name attribute of license key input element.
	 * @deprecated {VERSION}
	 *
	 * @param Options $options   The plugin options.
	 * @param bool    $echo      Whether to print the field or return it.
	 * @param string  $id_suffix Appended to every element id.
	 *
	 * @return string The field, when $echo is false.
	 */
	public function display_settings_license_key_field_content( $options, $echo = true, $id_suffix = '' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.echoFound

		_deprecated_function( __METHOD__, '4.10.0', __CLASS__ . '::display_license_key_field()' );

		if ( $echo ) {
			$this->display_license_key_field( $options, $id_suffix );

			return '';
		}

		ob_start();

		$this->display_license_key_field( $options, $id_suffix );

		return ob_get_clean();
	}

	/**
	 * Render the License Key field on the General tab, under the license banner.
	 *
	 * @since 4.10.0
	 *
	 * @param Options $options The plugin options.
	 *
	 * @return void
	 */
	public function render_settings_license_key_field( $options ) {

		$this->display_license_key_field( $options );
	}

	/**
	 * The lines a license key card shows under the field.
	 *
	 * A card has room for two, so each one carries more than its settings-row counterpart.
	 *
	 * @since 4.10.0
	 *
	 * @param Options $options The plugin options.
	 *
	 * @return string[] Escaped markup, echoed as-is.
	 */
	private function get_card_field_messages( $options ) {

		$license = $options->get_group( 'license' );
		$link    = 'target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__link"';
		$refresh = '<a href="#" class="js-wp-mail-smtp-license-key-refresh wpms-license-key-field__link">';
		$account = sprintf(
			'<a href="%s" %s>%s</a>',
			esc_url( wp_mail_smtp()->get_utm_url( 'https://wpmailsmtp.com/account/', [ 'content' => 'License Key Account Dashboard Link' ] ) ),
			$link,
			esc_html__( 'WP Mail SMTP Account Dashboard', 'wp-mail-smtp-pro' )
		);

		$refreshed = sprintf(
			wp_kses( /* translators: %1$s - opening link tag; %2$s - closing link tag. */
				__( 'Already renewed or upgraded? %1$sForce a status refresh%2$s.', 'wp-mail-smtp-pro' ),
				// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				[ 'a' => [ 'href' => [], 'class' => [] ] ]
			),
			$refresh,
			'</a>'
		);

		$found_in = sprintf(
			wp_kses( /* translators: %s - WP Mail SMTP account dashboard link. */
				__( 'You can find one in your %s.', 'wp-mail-smtp-pro' ),
				// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				[ 'a' => [ 'href' => [], 'target' => [], 'rel' => [], 'class' => [] ] ]
			),
			$account
		);

		if ( empty( wp_mail_smtp()->get_license_key() ) ) {
			return [
				$this->get_account_dashboard_message(),
				$this->get_signup_prompt(),
			];
		}

		if ( ! empty( $license['is_expired'] ) ) {
			return [
				$this->get_account_dashboard_message() . ' ' . $refreshed,
				$this->get_renewal_prompt(),
			];
		}

		if ( ! empty( $license['is_limit_reached'] ) ) {
			$utm = [
				'medium'  => 'license key card',
				'content' => 'license site limit reached',
			];

			return [
				sprintf(
					wp_kses( /* translators: %1$s - WPMailSMTP.com account area URL; %2$s - WP Mail SMTP upgrade page URL. */
						__( 'Update your site list or upgrade your license in the <a href="%1$s" target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__link">Account area</a>, or <a href="%2$s" target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__link">purchase a new license key</a>.', 'wp-mail-smtp-pro' ),
						[
							'a' => [
								'href'   => [],
								'target' => [],
								'rel'    => [],
								'class'  => [],
							],
						]
					),
					esc_url( wp_mail_smtp()->get_utm_url( 'https://wpmailsmtp.com/account/licenses/', $utm ) ),
					esc_url( wp_mail_smtp()->get_upgrade_link( $utm ) )
				),
				$refreshed,
			];
		}

		if ( ! empty( $license['is_disabled'] ) || ! empty( $license['is_invalid'] ) ) {
			$replace = esc_html__( 'Please use a different license key.', 'wp-mail-smtp-pro' ) . ' ' . $found_in;

			if ( ! empty( $license['is_invalid'] ) ) {
				$replace = esc_html__( 'The license key or its user no longer exists.', 'wp-mail-smtp-pro' ) . ' ' . $replace;
			}

			return [
				$replace,
				$this->get_another_key_prompt(),
			];
		}

		return [ $refreshed ];
	}

	/**
	 * Render the License Key field.
	 *
	 * @since 4.10.0
	 *
	 * @param Options $options   The plugin options.
	 * @param string  $id_suffix Appended to every element id, so a page rendering the field more
	 *                           than once keeps its ids unique. Behaviour binds to the classes.
	 * @param string  $surface   The surface the field renders on, one of `general-settings`,
	 *                           `mailer-settings`, `feature-settings` or `card`. Each states
	 *                           the license's standing to a different degree above the field,
	 *                           so the field says only what is left to say.
	 */
	public function display_license_key_field( $options, $id_suffix = '', $surface = 'general-settings' ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$key              = wp_mail_smtp()->get_license_key();
		$type             = wp_mail_smtp()->get_license_type();
		$license          = $options->get_group( 'license' );
		$is_expired       = isset( $license['is_expired'] ) && $license['is_expired'] === true;
		$is_disabled      = isset( $license['is_disabled'] ) && $license['is_disabled'] === true;
		$is_invalid       = isset( $license['is_invalid'] ) && $license['is_invalid'] === true;
		$is_limit_reached = isset( $license['is_limit_reached'] ) && $license['is_limit_reached'] === true;
		$is_valid         = ! empty( $key ) && ! $is_expired && ! $is_disabled && ! $is_invalid && ! $is_limit_reached;

		$input_class = '';

		if ( $is_valid ) {
			$input_class = 'wpms-license-key-field__input--valid';
		} elseif ( ! empty( $key ) ) {
			$input_class = 'wpms-license-key-field__input--invalid';
		}

		ob_start();
		?>
		<div class="wpms-license-key-field">
			<div class="wpms-license-key-field__row">
				<input type="password" id="wpms-license-key-field-input<?php echo esc_attr( $id_suffix ); ?>"
					value="<?php echo esc_attr( $key ); ?>"
					placeholder="<?php esc_attr_e( 'Paste your license key here', 'wp-mail-smtp-pro' ); ?>"
					class="wpms-license-key-field__input js-wp-mail-smtp-license-key<?php echo ! empty( $input_class ) ? ' ' . esc_attr( $input_class ) : ''; ?>"
					<?php echo ( $options->is_const_defined( 'license', 'key' ) || $is_valid ) ? 'disabled' : ''; ?>
				/>

				<?php if ( ! $is_valid ) : ?>
					<button type="button" id="wpms-license-key-field-verify<?php echo esc_attr( $id_suffix ); ?>" class="js-wp-mail-smtp-license-key-verify wp-mail-smtp-btn wp-mail-smtp-btn-md <?php echo empty( $key ) ? 'wp-mail-smtp-btn-orange' : 'wp-mail-smtp-btn-red'; ?>">
						<?php echo empty( $key ) ? esc_html__( 'Verify Key', 'wp-mail-smtp-pro' ) : esc_html__( 'Verify', 'wp-mail-smtp-pro' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( ! empty( $key ) ) : ?>
					<button type="button" id="wpms-license-key-field-deactivate<?php echo esc_attr( $id_suffix ); ?>" class="js-wp-mail-smtp-license-key-deactivate wp-mail-smtp-btn wp-mail-smtp-btn-md wp-mail-smtp-btn-grey">
						<?php esc_html_e( 'Remove Key', 'wp-mail-smtp-pro' ); ?>
					</button>
				<?php endif; ?>

			</div>

			<div role="alert" id="wpms-license-key-field-error<?php echo esc_attr( $id_suffix ); ?>" class="js-wp-mail-smtp-license-key-error wpms-license-key-field__error"></div>

			<?php
			$type_message  = '';
			$desc_messages = [];

			if ( $surface === 'card' ) {
				$desc_messages = $this->get_card_field_messages( $options );
			} elseif ( empty( $key ) ) {
				$desc_messages[] = $this->get_account_dashboard_message( $this->get_signup_prompt() );
			} elseif ( $is_valid ) {
				$type_message = sprintf( /* translators: $s - license type. */
					esc_html__( 'Your license level is %s.', 'wp-mail-smtp-pro' ),
					// The licensing server reports the level in lower case.
					'<strong>' . esc_html( ucfirst( $type ) ) . '</strong>'
				);

				$desc_messages[] = $this->get_refresh_message();
			} elseif ( $is_expired ) {
				$desc_messages[] = $this->get_refresh_message();

				$desc_messages[] = $this->get_account_dashboard_message( $this->get_renewal_prompt() );
			} elseif ( $is_disabled || $is_invalid ) {
				$replace_message = esc_html__( 'Please use a different license key to use WP Mail SMTP Pro features and enable automatic updates.', 'wp-mail-smtp-pro' );

				$desc_messages[] = $is_invalid
					? esc_html__( 'The license key no longer exists or the user associated with it has been deleted.', 'wp-mail-smtp-pro' ) . ' ' . $replace_message
					: $replace_message;

				$desc_messages[] = $this->get_account_dashboard_message( $this->get_another_key_prompt() );
			} elseif ( $is_limit_reached ) {
				$desc_messages[] = $this->get_refresh_message();

				$utm = [
					'medium'  => 'license key field',
					'content' => 'license site limit reached',
				];

				$desc_messages[] = sprintf(
					wp_kses( /* translators: %1$s - WPMailSMTP.com account area URL; %2$s - WP Mail SMTP upgrade page URL. */
						__( 'You can update the list of your sites or upgrade the license in the <a href="%1$s" target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__link">Account area</a>. Or you can <a href="%2$s" target="_blank" rel="noopener noreferrer" class="wpms-license-key-field__link">purchase a new license key</a>.', 'wp-mail-smtp-pro' ),
						[
							'a' => [
								'href'   => [],
								'target' => [],
								'rel'    => [],
								'class'  => [],
							],
						]
					),
					esc_url( wp_mail_smtp()->get_utm_url( 'https://wpmailsmtp.com/account/licenses/', $utm ) ),
					esc_url( wp_mail_smtp()->get_upgrade_link( $utm ) )
				);
			}

			// Every surface states an unusable license above the field, in its own copy.
			if ( ! empty( $type_message ) && $is_valid && $surface === 'general-settings' ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				printf( '<p class="wpms-license-key-field__status">%s</p>', $type_message );
			}

			foreach ( $desc_messages as $desc_message ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				printf( '<p class="wpms-license-key-field__desc">%s</p>', $desc_message );
			}

			?>
		</div>
		<?php

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo ob_get_clean();
	}

	/**
	 * Sanitize admin area options.
	 *
	 * @since 1.5.0
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function filter_options_set( $options ) {

		if ( isset( $options['license'] ) ) {
			$options['license']['key']  = array_key_exists( 'key', $options['license'] ) ? sanitize_key( (string) $options['license']['key'] ) : '';
			$options['license']['type'] = array_key_exists( 'type', $options['license'] ) ? sanitize_key( (string) $options['license']['type'] ) : '';

			if ( array_key_exists( 'is_expired', $options['license'] ) ) {
				$options['license']['is_expired'] = (bool) $options['license']['is_expired'];
			}
			if ( array_key_exists( 'is_disabled', $options['license'] ) ) {
				$options['license']['is_disabled'] = (bool) $options['license']['is_disabled'];
			}
			if ( array_key_exists( 'is_invalid', $options['license'] ) ) {
				$options['license']['is_invalid'] = (bool) $options['license']['is_invalid'];
			}
			if ( array_key_exists( 'is_limit_reached', $options['license'] ) ) {
				$options['license']['is_limit_reached'] = (bool) $options['license']['is_limit_reached'];
			}

			if ( array_key_exists( 'site_url', $options['license'] ) ) {
				$options['license']['site_url'] = esc_url_raw( (string) $options['license']['site_url'] );
			}

			if ( array_key_exists( 'site_url_pinned_at', $options['license'] ) ) {
				$options['license']['site_url_pinned_at'] = absint( $options['license']['site_url_pinned_at'] );
			}
		} else {
			// Lite values by default.
			$options['license'] = [
				'key'              => '',
				'type'             => 'lite',
				'is_expired'       => false,
				'is_disabled'      => false,
				'is_invalid'       => false,
				'is_limit_reached' => false,
			];
		}

		return $options;
	}

	/**
	 * Verify a license key entered by the user.
	 *
	 * @since 1.5.0
	 *
	 * @param string $key
	 * @param bool   $ajax
	 *
	 * @return bool
	 */
	public function verify_key( $key = '', $ajax = false ) {

		if ( empty( $key ) ) {
			return false;
		}

		$options = Options::init();
		$all_opt = $options->get_all();

		// An explicit action derives the URL again instead of reusing the pin.
		$site_url = wp_mail_smtp()->get_license_site_url()->derive();

		// Perform a request to verify the key.
		$verify = $this->perform_remote_request(
			'verify-key',
			[
				'tgm-updater-key'     => $key,
				'tgm-updater-referer' => $site_url,
			]
		);

		// If it returns false, send back a generic error message and return.
		if ( ! $verify ) {
			$msg = $this->get_remote_error_message();

			if ( $ajax ) {
				wp_send_json_error( $msg );
			} else {
				$this->errors[] = $msg;

				return false;
			}
		}

		// If an error is returned, set the error and return.
		if ( ! empty( $verify->error ) ) {
			if ( $ajax ) {
				// The licensing server's message carries its own renewal link, and the field renders it as markup.
				wp_send_json_error(
					wp_kses(
						$verify->error,
						[
							'a' => [
								'href'   => [],
								'title'  => [],
								'target' => [],
								'rel'    => [],
							],
							'b' => [],
						]
					)
				);
			} else {
				$this->errors[] = $verify->error;

				return false;
			}
		}

		$success = isset( $verify->success ) ? $verify->success : $this->get_key_verified_message();

		$this->success[] = $success;

		$license_type = isset( $verify->type ) ? $verify->type : $all_opt['license']['type'];

		// Otherwise, our request has been done successfully. Update the option and set the success message.
		$data = [
			'license' => [
				'key'              => $key,
				'type'             => $license_type,
				'is_expired'       => false,
				'is_disabled'      => false,
				'is_invalid'       => false,
				'is_limit_reached' => false,
			],
		];

		$data['license'] = array_merge( $data['license'], $this->get_site_url_pin( $site_url ) );

		DebugEvents::add_debug( 'License activated on ' . $site_url );

		$options->set( $data, false, false );

		wp_clean_plugins_cache( true );

		if ( $ajax ) {
			wp_send_json_success(
				[
					'type'          => $license_type,
					'message'       => $success,
					// Emptied rather than dropped: the field is re-rendered by a reload now,
					// but the key shipped in this response and something may still read it.
					'settings_html' => '',
				]
			);
		}

		return true;
	}

	/**
	 * Maybe validate a license key entered by the user.
	 *
	 * @since 1.5.0
	 */
	public function maybe_validate_key() {

		$options = Options::init();
		$all_opt = $options->get_all();

		if ( empty( $all_opt['license']['key'] ) ) {
			return;
		}

		if ( empty( $all_opt['license']['updates'] ) ) {
			$data = [
				'license' => [
					'updates' => strtotime( '+24 hours' ),
				],
			];

			$options->set( $data, false, false );

			// Perform a request to validate the key.
			$this->validate_key( $all_opt['license']['key'] );
		} else {
			$current_timestamp = time();
			if ( $current_timestamp < $all_opt['license']['updates'] ) {
				return;
			} else {
				$data = [
					'license' => [
						'updates' => strtotime( '+24 hours' ),
					],
				];

				$options->set( $data, false, false );
				$this->validate_key( $all_opt['license']['key'] );
			}
		}
	}

	/**
	 * Validate a license key entered by the user.
	 *
	 * @since 1.5.0
	 *
	 * @param string $key           License key.
	 * @param bool   $forced        Force to set contextual messages (false by default).
	 * @param bool   $ajax          AJAX.
	 * @param bool   $return_status Option to return the license status.
	 * @param array  $extra_params  Extra query parameters for the request.
	 *
	 * @return string|bool
	 */
	public function validate_key( $key = '', $forced = false, $ajax = false, $return_status = false, $extra_params = [] ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$body     = array_merge( [ 'tgm-updater-key' => $key ], $extra_params );
		$site_url = '';

		// An explicit refresh derives the URL again instead of reusing the pin.
		if ( $forced ) {
			$site_url                    = wp_mail_smtp()->get_license_site_url()->derive();
			$body['tgm-updater-referer'] = $site_url;
		}

		$validate = $this->perform_remote_request( 'validate-key', $body );
		$options  = Options::init();
		$all_opt  = $options->get_all();

		// If there was a basic API error in validation - do nothing.
		if ( ! $validate ) {
			// If forced, set contextual success message.
			if ( $forced ) {
				$msg = $this->get_remote_error_message();

				if ( $ajax ) {
					wp_send_json_error( $msg );
				} else {
					$this->errors[] = $msg;
				}
			}

			return false;
		}

		$data = [
			'license' => [
				'is_expired'       => false,
				'is_disabled'      => false,
				'is_invalid'       => false,
				'is_limit_reached' => false,
			],
		];

		// If a key or author error is returned, the license no longer exists or the user has been deleted, so reset license.
		if ( isset( $validate->key ) || isset( $validate->author ) ) {
			$data['license']['is_invalid'] = true;

			$options->set( $data, false, false );

			if ( $ajax ) {
				wp_send_json_error( $this->get_state_report( 'invalid', 'license-alert-modal' ) );
			}

			return $return_status ? 'invalid' : false;
		}

		// If the license has expired, set the transient and expired flag and return.
		if ( isset( $validate->expired ) ) {
			$data['license']['is_expired'] = true;

			$options->set( $data, false, false );

			if ( $ajax ) {
				wp_send_json_error( $this->get_state_report( 'expired', 'license-alert-modal' ) );
			}

			return $return_status ? 'expired' : false;
		}

		// If the license is disabled, set the transient and disabled flag and return.
		if ( isset( $validate->disabled ) ) {
			$data['license']['is_disabled'] = true;

			$options->set( $data, false, false );

			if ( $ajax ) {
				wp_send_json_error( $this->get_state_report( 'disabled', 'license-alert-modal' ) );
			}

			return $return_status ? 'disabled' : false;
		}

		// If the license site activations limit reached, set limit reached flag and return.
		if ( isset( $validate->limit_reached ) ) {
			$data['license']['is_limit_reached'] = true;

			$options->set( $data, false, false );

			if ( $ajax ) {
				wp_send_json_error( $this->get_state_report( 'limit_reached', 'license-alert-modal' ) );
			}

			return $return_status ? 'limit_reached' : false;
		}

		$license_type = isset( $validate->type ) ? $validate->type : $all_opt['license']['type'];

		// Otherwise, our check has returned successfully. Set the transient and update our license type and flags.
		$data['license']['type'] = $license_type;

		// Only an action the admin took pins the URL: a background check has no request context
		// of its own, so the URL it computed is not one to hold the site to.
		if ( $forced ) {
			$data['license'] = array_merge( $data['license'], $this->get_site_url_pin( $site_url ) );

			DebugEvents::add_debug( 'License refreshed on ' . $site_url );
		}

		$options->set( $data, false, false );

		// If forced, set contextual success message.
		if ( $forced ) {
			$msg             = $this->get_key_refreshed_message();
			$this->success[] = $msg;

			if ( $ajax ) {
				wp_send_json_success(
					[
						'type'          => $license_type,
						'message'       => $msg,
						'settings_html' => '',
					]
				);
			}
		}

		return $return_status ? 'valid' : true;
	}

	/**
	 * Deactivate a license key entered by the user.
	 *
	 * The key leaves this site whether the licensing server confirms it or not, so a key the
	 * server refuses to deactivate can still be removed.
	 *
	 * @since 1.5.0
	 * @since 4.10.0 The key is removed even when the remote request fails.
	 *
	 * @param bool $ajax Whether the caller is an AJAX request.
	 */
	public function deactivate_key( $ajax = false ) {

		$options = Options::init();
		$all_opt = $options->get_all();

		if ( empty( $all_opt['license']['key'] ) ) {
			return;
		}

		$deactivate = $this->perform_remote_request( 'deactivate-key', [ 'tgm-updater-key' => $all_opt['license']['key'] ] );

		// The license group is replaced rather than merged, so the pinned site URL goes with the
		// key: re-entering the key would otherwise resend a URL the server has already refused.
		$raw_settings            = $options->get_all_raw();
		$raw_settings['license'] = [
			'key'  => '',
			'type' => 'lite',
		];

		$options->set( $raw_settings );

		$is_released = true;

		if ( empty( $deactivate ) || ! empty( $deactivate->error ) ) {
			$is_released = false;
			$message     = esc_html__( 'The license key has been removed from this site. The licensing server could not be reached to release it, so this site may still count towards your license activations.', 'wp-mail-smtp-pro' );
		} elseif ( isset( $deactivate->success ) ) {
			$message = $deactivate->success;
		} else {
			$message = $this->get_key_removed_message();
		}

		$this->success[] = $message;

		if ( $ajax ) {
			wp_send_json_success(
				[
					'message'       => $message,
					'settings_html' => '',
					'is_released'   => $is_released,
				]
			);
		}
	}

	/**
	 * Reduce a site URL to the part the licensing server matches on: the host and the path.
	 *
	 * @since 4.10.0
	 *
	 * @param string $url Site URL.
	 *
	 * @return string
	 */
	private function normalize_site_url( $url ) {

		// Whitespace is not a different site, and a trailing newline is worse than useless
		// here: wp_parse_url() reports it as part of the host.
		$parts = wp_parse_url( trim( (string) $url ) );

		// An unparsable URL compares equal to nothing, so no move follows from one.
		if ( empty( $parts['host'] ) ) {
			return '';
		}

		// The server lowercases the whole URL before matching, so a path that differs only in
		// case is the same activation to it and must not read as a move here.
		$host = preg_replace( '/^www\./', '', strtolower( $parts['host'] ) );
		$path = isset( $parts['path'] ) ? strtolower( untrailingslashit( $parts['path'] ) ) : '';

		return $host . $path;
	}

	/**
	 * License option values that pin a site URL for every later request.
	 *
	 * @since 4.10.0
	 *
	 * @param string $url Site URL to pin.
	 *
	 * @return array
	 */
	private function get_site_url_pin( $url ) {

		return [
			'site_url'           => $url,
			'site_url_pinned_at' => time(),
		];
	}

	/**
	 * Adopt a site URL that has genuinely moved, on a deliberate admin page view.
	 *
	 * @since 4.10.0
	 */
	public function maybe_adopt_moved_site_url() {

		if ( ! $this->is_site_url_adoption_context() ) {
			return;
		}

		$key = wp_mail_smtp()->get_license_key();

		if ( empty( $key ) ) {
			return;
		}

		$options = Options::init();

		// One move a day. Every URL the server holds no activation for costs a seat, so a site
		// reachable on several hostnames must not hand it a new one per page view.
		if ( time() - (int) $options->get( 'license', 'site_url_pinned_at' ) < DAY_IN_SECONDS ) {
			return;
		}

		$moved = $this->get_moved_site_url();

		if ( empty( $moved ) ) {
			return;
		}

		$options->set( [ 'license' => $this->get_site_url_pin( $moved ) ], false, false );

		DebugEvents::add_debug( 'License site URL changed to ' . $moved );

		// Whatever the server answers for the moved URL is this site's license state now.
		$this->validate_key( $key, false, false, false, [ 'site_url_changed' => 1 ] );
	}

	/**
	 * The site URL this install has moved to, or an empty string when it has not moved.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	private function get_moved_site_url() {

		$pinned = $this->normalize_site_url( Options::init()->get( 'license', 'site_url' ) );

		// Nothing to compare against until an explicit action has pinned a URL, and no reason
		// to read the row on an install that has not.
		if ( empty( $pinned ) ) {
			return '';
		}

		$derived    = wp_mail_smtp()->get_license_site_url()->derive();
		$normalized = $this->normalize_site_url( $derived );

		if ( empty( $normalized ) || $normalized === $pinned ) {
			return '';
		}

		// The normalised form answers the comparison and nothing else. What gets pinned keeps
		// its scheme and port, because the Product API clients are handed it as a real URL.
		return $derived;
	}

	/**
	 * Whether this request is a deliberate admin page view by someone who manages the license.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	private function is_site_url_adoption_context() {

		// admin-ajax.php and the REST API also run with `is_admin()` true, and both are
		// contexts a multilingual plugin rewrites the site URL in.
		if (
			! is_admin() ||
			wp_doing_ajax() ||
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			Helpers::is_wp_cli()
		) {
			return false;
		}

		return current_user_can( wp_mail_smtp()->get_capability_manage_global_options() );
	}

	/**
	 * Output any notices generated by the class.
	 *
	 * @since 1.5.0
	 * @since 3.8.0 Add `manage_options` capability check.
	 *
	 * @param bool $below_h2
	 */
	public function notices( $below_h2 = false ) {

		// Only users with sufficient capability can see the notices.
		if ( ! current_user_can( wp_mail_smtp()->get_capability_manage_global_options() ) ) {
			return;
		}

		// Grab the option and output any nag dealing with license keys.
		$options  = Options::init();
		$all_opt  = $options->get_all();
		$below_h2 = $below_h2 ? 'below-h2' : '';

		// If there is no license key, output nag about ensuring key is set for automatic updates.
		if ( empty( $all_opt['license']['key'] ) ) :
			?>
			<div class="notice notice-error <?php echo esc_attr( $below_h2 ); ?> wp-mail-smtp-license-notice">
				<p>
					<?php
					printf(
						wp_kses( /* translators: %s - plugin settings page URL. */
							__( 'Please <a href="%s" class="wpms-license-key-field__link">enter and activate</a> your license key to use WP Mail SMTP Pro features and enable automatic updates.', 'wp-mail-smtp-pro' ),
							array(
								'a' => array(
									'href' => array(),
								),
							)
						),
						esc_url( add_query_arg( array( 'page' => 'wp-mail-smtp' ), WP::admin_url( 'admin.php' ) ) )
					);
					?>
				</p>
			</div>
			<?php
		endif;

		// A license the server will not accept gets a nag naming the state and the way out.
		foreach ( [ 'expired', 'disabled', 'invalid', 'limit_reached' ] as $state ) {
			if ( empty( $all_opt['license'][ 'is_' . $state ] ) ) {
				continue;
			}
			?>
			<div class="notice notice-error <?php echo esc_attr( $below_h2 ); ?> wp-mail-smtp-license-notice">
				<p>
					<?php
					// Built from escaped strings.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $this->get_state_report( $state, 'license-admin-notice' );
					?>
				</p>
			</div>
			<?php
		}

		// If there are any license errors, output them now.
		if ( ! empty( $this->errors ) ) :
			?>
			<div class="notice notice-error <?php echo esc_attr( $below_h2 ); ?> wp-mail-smtp-license-notice">
				<p><?php echo implode( '<br>', array_map( 'esc_html', $this->errors ) ); ?></p>
			</div>
			<?php
		endif;

		// If there are any success messages, output them now.
		if ( ! empty( $this->success ) ) :
			?>
			<div class="notice notice-success <?php echo esc_attr( $below_h2 ); ?> wp-mail-smtp-license-notice">
				<p><?php echo implode( '<br>', array_map( 'esc_html', $this->success ) ); ?></p>
			</div>
			<?php
		endif;
	}

	/**
	 * Send a request to the remote URL via wp_remote_get() and return a json decoded response.
	 *
	 * @since 1.5.0
	 * @since 2.7.0 Switch from POST to GET request.
	 *
	 * @param string $action        The name of the request action var.
	 * @param array  $body          The GET query attributes.
	 * @param array  $headers       The headers to send to the remote URL.
	 * @param string $return_format The format for returning content from the remote URL.
	 *
	 * @return string|bool Json decoded response on success, false on failure.
	 */
	public function perform_remote_request( $action, $body = [], $headers = [], $return_format = 'json' ) {

		// Request query parameters.
		$query_params = wp_parse_args(
			$body,
			[
				'tgm-updater-action'      => $action,
				'tgm-updater-key'         => $body['tgm-updater-key'],
				'tgm-updater-wp-version'  => get_bloginfo( 'version' ),
				'tgm-updater-php-version' => phpversion(),
				// The pinned URL, unless the caller is an explicit action that derives a new one.
				'tgm-updater-referer'     => wp_mail_smtp()->get_license_site_url()->get(),
			]
		);

		if ( $this->is_validate_key_request( (string) $action ) === true ) {
			$query_params['wpforms_refresh_key'] = 1;
		}

		$args = [
			'headers'    => $headers,
			'user-agent' => Helpers::get_default_user_agent(),
			'timeout'    => 30,
		];

		if ( defined( 'WPMS_UPDATER_API' ) ) {
			$this->remote_url = WPMS_UPDATER_API;
		}

		$remote_url = $this->remote_url . '/' . $action;

		// Perform the query and retrieve the response.
		$response      = wp_remote_get( add_query_arg( $query_params, $remote_url ), $args );
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Bail out early if there are any errors.
		if ( 200 != $response_code || is_wp_error( $response_body ) ) {
			return false;
		}

		// Return the json decoded content.
		return json_decode( $response_body );
	}

	/**
	 * The status of the license.
	 *
	 * @since 1.9.0
	 *
	 * @return array The results array with 'valid' (bool) and 'message' (string) attributes.
	 */
	public function get_status() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$license_key = wp_mail_smtp()->get_license_key();

		$result = [
			'valid' => false,
		];

		if ( empty( $license_key ) ) {
			$result['message'] = sprintf(
				wp_kses( /* translators: %s - plugin settings page URL. */
					__( 'Please <a href="%s" class="wpms-license-key-field__link">enter and activate</a> your license key to use WP Mail SMTP Pro features and enable automatic updates.', 'wp-mail-smtp-pro' ),
					[
						'a' => [
							'href'  => [],
							'class' => [],
						],
					]
				),
				esc_url( wp_mail_smtp()->get_admin()->get_admin_page_url() )
			);

			return $result;
		}

		$license_status = $this->validate_key( $license_key, false, false, true );

		if ( $license_status === false ) {
			$result['message'] = $this->get_remote_error_message();

			return $result;
		}

		if ( in_array( $license_status, [ 'expired', 'disabled', 'invalid', 'limit_reached' ], true ) ) {
			$result['message'] = $this->get_state_report( $license_status, 'site-health' );

			return $result;
		}

		return [
			'valid'   => true,
			'message' => esc_html__( 'Your WP Mail SMTP Pro license is active and valid.', 'wp-mail-smtp-pro' ),
		];
	}

	/**
	 * Check whether the license is valid.
	 *
	 * @since 3.5.0
	 *
	 * @param bool $remote Perform remote request or use DB license data.
	 *
	 * @return bool
	 */
	public function is_valid( $remote = false ) {

		if ( $remote ) {
			return $this->get_status()['valid'];
		}

		$saved_license = Options::init()->get_group( 'license' );

		return ! empty( $saved_license['key'] ) &&
			empty( $saved_license['is_expired'] ) &&
			empty( $saved_license['is_disabled'] ) &&
			empty( $saved_license['is_invalid'] ) &&
			empty( $saved_license['is_limit_reached'] );
	}

	/**
	 * Check whether the license is expired.
	 *
	 * @since 3.11.0
	 *
	 * @return bool
	 */
	public function is_expired() {

		$saved_license = Options::init()->get_group( 'license' );

		return ! empty( $saved_license['is_expired'] );
	}

	/**
	 * The UTM tagged URL of the documentation covering how to activate a license key.
	 *
	 * @since 4.10.0
	 *
	 * @param string $utm_content The utm_content value identifying the calling surface.
	 *
	 * @return string
	 */
	public function get_activation_docs_url( $utm_content ) {

		return wp_mail_smtp()->get_utm_url(
			'https://wpmailsmtp.com/docs/how-to-verify-your-wp-mail-smtp-license/',
			[ 'content' => $utm_content ]
		);
	}

	/**
	 * The documentation URL for renewing a license.
	 *
	 * @since 4.10.0
	 *
	 * @param string $utm_content The utm_content URL parameter.
	 *
	 * @return string
	 */
	public function get_renewal_docs_url( $utm_content ) {

		return wp_mail_smtp()->get_utm_url(
			'https://wpmailsmtp.com/docs/how-to-renew-your-wp-mail-smtp-license/',
			[ 'content' => $utm_content ]
		);
	}

	/**
	 * Render the license banner shown above the License Key field.
	 *
	 * Renders nothing while the license is valid, so callers need no condition of their own.
	 *
	 * @since 4.10.0
	 *
	 * @return void
	 */
	public function render_license_banner() {

		if ( $this->is_valid() ) {
			return;
		}

		$state     = $this->get_state();
		$is_no_key = $state === 'no_key';

		// The illustration carries the tone's own wash, so it ships once per tone.
		if ( $is_no_key ) {
			$state_classes = 'wpms:bg-[color:var(--wpms-color-utility-yellow-0,#fcf9e8)] wpms:outline-[color:var(--wpms-color-utility-yellow-30,#f2d675)]';
			$title         = esc_html__( "You're almost all set up with WP Mail SMTP Pro!", 'wp-mail-smtp-pro' );
			$tone          = 'warning';
		} else {
			$state_classes = 'wpms:bg-[color:var(--wpms-color-utility-red-0,#fcf0f1)] wpms:outline-[color:var(--wpms-color-utility-red-30,#f86368)]';
			$title         = $this->get_state_title( $state );
			$tone          = 'error';
		}

		// TODO: give every state its own doc, once the remaining ones are written.
		$docs_url = $state === 'expired'
			? $this->get_renewal_docs_url( 'general-license-banner' )
			: $this->get_activation_docs_url( 'general-license-banner' );
		?>
		<div class="wpms:w-full wpms:max-w-[699px] <?php echo esc_attr( $state_classes ); ?> wpms:rounded-sm wpms:shadow-[0px_2px_4px_0px_rgba(0,0,0,0.07)] wpms:outline wpms:outline-1 wpms:outline-offset-[-1px] wpms:flex wpms:items-start wpms:gap-[var(--wpms-spacing-5,5px)] wpms:overflow-hidden wpms-reset">
			<div class="wpms:flex-1 wpms:p-[var(--wpms-spacing-20,20px)] wpms:flex wpms:flex-col wpms:items-start wpms:gap-[var(--wpms-spacing-10,10px)] wpms-reset">
				<div class="wpms:self-stretch wpms:text-[var(--wpms-text-primary,#2c3338)] wpms:text-sm wpms:font-medium wpms:leading-[22px] wpms-reset"><?php echo esc_html( $title ); ?></div>
				<div class="wpms:self-stretch wpms:text-[var(--wpms-text-secondary,#50575e)] wpms:text-sm wpms:font-normal wpms:leading-[22px] wpms-reset">
					<?php if ( $is_no_key ) : ?>
						<?php esc_html_e( 'Enter your license key to unlock Pro features and automatic updates.', 'wp-mail-smtp-pro' ); ?>
						<br/>
						<?php esc_html_e( 'Your license key is in your WP Mail SMTP Account Dashboard.', 'wp-mail-smtp-pro' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'An active license is needed to access Pro features, plugin updates (including security improvements), and our world class support!', 'wp-mail-smtp-pro' ); ?>
					<?php endif; ?>
					<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="wpms:text-[var(--wpms-text-link,#056aab)] wpms:underline wpms-reset"><?php esc_html_e( 'Learn more', 'wp-mail-smtp-pro' ); ?></a>
				</div>
			</div>
			<img src="<?php echo esc_url( wp_mail_smtp()->pro->assets_url . '/images/license-banner-illustration-' . $tone . '.svg' ); ?>" alt="" width="136" height="116" class="wpms:shrink-0 wpms:self-end wpms-reset"/>
		</div>
		<?php
	}

	/**
	 * Check whether the license has run out of site activations.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	public function is_limit_reached() {

		$saved_license = Options::init()->get_group( 'license' );

		return ! empty( $saved_license['is_limit_reached'] );
	}

	/**
	 * The license's standing, as one of the states the remote validator reports.
	 *
	 * @since 4.10.0
	 *
	 * @return string One of `valid`, `no_key`, `expired`, `limit_reached`, `disabled` or
	 *                `invalid`.
	 */
	public function get_state() {

		if ( $this->is_valid() ) {
			return 'valid';
		}

		if ( empty( wp_mail_smtp()->get_license_key() ) ) {
			return 'no_key';
		}

		if ( $this->is_expired() ) {
			return 'expired';
		}

		if ( $this->is_limit_reached() ) {
			return 'limit_reached';
		}

		return $this->is_disabled() ? 'disabled' : 'invalid';
	}

	/**
	 * What the license needs doing, as a button label.
	 *
	 * @since 4.10.0
	 *
	 * @return string Empty while the license is valid.
	 */
	public function get_action_label() {

		$labels = [
			'expired'       => esc_html__( 'Renew License', 'wp-mail-smtp-pro' ),
			'limit_reached' => esc_html__( 'Manage License', 'wp-mail-smtp-pro' ),
		];

		$state = $this->get_state();

		if ( $state === 'valid' ) {
			return '';
		}

		return isset( $labels[ $state ] ) ? $labels[ $state ] : esc_html__( 'Activate License', 'wp-mail-smtp-pro' );
	}

	/**
	 * Check whether the license key has been disabled.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	public function is_disabled() {

		$saved_license = Options::init()->get_group( 'license' );

		return ! empty( $saved_license['is_disabled'] );
	}

	/**
	 * Check whether the license key no longer exists.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	public function is_invalid() {

		$saved_license = Options::init()->get_group( 'license' );

		return ! empty( $saved_license['is_invalid'] );
	}

	/**
	 * Renewal link used within the various admin pages.
	 *
	 * @since 3.8.0
	 *
	 * @param array|string $utm Array of UTM params, or if string provided - utm_content URL parameter.
	 *
	 * @return string
	 */
	public function get_renewal_link( $utm ) {

		$license_key = wp_mail_smtp()->get_license_key();

		if ( ! empty( $license_key ) && strlen( $license_key ) === 32 ) {
			return wp_mail_smtp()->get_utm_url(
				add_query_arg(
					'edd_license_key',
					$license_key,
					'https://wpmailsmtp.com/checkout/'
				),
				$utm
			);
		}

		return wp_mail_smtp()->get_utm_url( 'https://wpmailsmtp.com/account/licenses/', $utm );
	}

	/**
	 * Fetch the remote latest version.
	 *
	 * @since 3.8.0
	 *
	 * @param bool $force_remote Whether or not to force remote fetch. Optional. Default `false`.
	 *
	 * @return string
	 */
	public function fetch_latest_plugin_version( $force_remote = false ) {

		if ( $force_remote ) {
			return $this->remote_fetch_and_cache_latest_plugin_version();
		}

		$cache = get_transient( self::CACHE_REMOTE_LATEST_VERSION_KEY );

		if ( $cache === false ) {
			return $this->remote_fetch_and_cache_latest_plugin_version();
		}

		return $cache['version'];
	}

	/**
	 * Fetch the latest version from our remote source.
	 *
	 * @since 3.8.0
	 *
	 * @return string Returns empty string '' if unable to fetch the latest version.
	 *                Otherwise, returns the latest version.
	 */
	private function remote_fetch_and_cache_latest_plugin_version() {

		// Perform the query and retrieve the response.
		$response      = wp_remote_get(
			$this->latest_version_remote_url,
			[
				'user-agent' => Helpers::get_default_user_agent(),
			]
		);
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Bail out early if there are any errors.
		if ( $response_code !== 200 || is_wp_error( $response_body ) ) {
			$this->cache_remote_latest_version( '' );

			return '';
		}

		// Decode the response.
		$json_response = json_decode( $response_body );

		if ( empty( $json_response ) || empty( $json_response[0]->version ) ) {
			$this->cache_remote_latest_version( '' );

			return '';
		}

		$this->cache_remote_latest_version( $json_response[0]->version );

		return $json_response[0]->version;
	}

	/**
	 * Cache the remote latest version.
	 *
	 * @since 3.8.0
	 *
	 * @param string $latest_version Latest version to cache.
	 *
	 * @return void
	 */
	private function cache_remote_latest_version( $latest_version ) {

		set_transient(
			self::CACHE_REMOTE_LATEST_VERSION_KEY,
			[
				'version'      => $latest_version,
				'last_checked' => time(),
			],
			DAY_IN_SECONDS * $this->get_remote_latest_version_interval()
		);
	}

	/**
	 * Get the interval time, in days, to remote fetch the latest version.
	 *
	 * @since 3.8.0
	 *
	 * @return int
	 */
	private function get_remote_latest_version_interval() {

		return absint(
			/**
			 * Filters the interval time, in days, to remote fetch the latest version.
			 *
			 * @since 3.8.0
			 *
			 * @param int $interval Interval time in days.
			 */
			apply_filters( 'wp_mail_smtp_pro_license_get_remote_latest_version_interval', self::REMOTE_FETCH_LATEST_VERSION_INTERVAL_IN_DAYS )
		);
	}

	/**
	 * Check if this is an ajax request to validate the key.
	 *
	 * @since 4.1.0
	 *
	 * @param string $action Action.
	 *
	 * @return bool
	 */
	private function is_validate_key_request( string $action ): bool {

		$allowed_tasks = [ 'license_verify', 'license_refresh' ];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if (
			! isset( $_REQUEST['action'] ) ||
			$_REQUEST['action'] !== 'wp_mail_smtp_pro_license_ajax' ||
			! isset( $_REQUEST['task'] ) ||
			! in_array( $_REQUEST['task'], $allowed_tasks, true )
		) {
			return false;
		}

		$is_verify_key_request = (
			$_REQUEST['task'] === 'license_verify' &&
			$action === 'verify-key'
		);

		$is_refresh_key_request = (
			$_REQUEST['task'] === 'license_refresh' &&
			$action === 'validate-key'
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $is_verify_key_request || $is_refresh_key_request;
	}
}
