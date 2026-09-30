<?php

namespace WPMailSMTP\Pro\License;

use WPMailSMTP\Options;
use WPMailSMTP\Providers\OptionsAbstract;
use WPMailSMTP\Pro\Providers\Providers;
use WPMailSMTP\WP;

/**
 * Blocks the Pro features that need an active license from being configured.
 *
 * @since 4.10.0
 */
class Gate {

	/**
	 * The license handler.
	 *
	 * @since 4.10.0
	 *
	 * @var License
	 */
	private $license;

	/**
	 * The current screen's gates, memoized.
	 *
	 * @since 4.10.0
	 *
	 * @var array[]|null
	 */
	private $gates = null;

	/**
	 * Constructor.
	 *
	 * @since 4.10.0
	 *
	 * @param License $license The license handler.
	 */
	public function __construct( $license ) {

		$this->license = $license;
	}

	/**
	 * Register hooks.
	 *
	 * @since 4.10.0
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( 'wp_mail_smtp_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_mail_smtp_admin_pages_after_section_heading', [ $this, 'render_section_key_field' ] );
		add_action( 'wp_mail_smtp_admin_connection_settings_display_mailer_options_before', [ $this, 'render_mailer_key_field' ] );
		add_action( 'wp_mail_smtp_admin_pages_before_content', [ $this, 'render_page_key_card' ] );
		add_action( 'wp_mail_smtp_admin_pages_before_content', [ $this, 'render_modal_source' ] );
	}

	/**
	 * Enqueue the gate script on a screen that has something gated on it.
	 *
	 * @since 4.10.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		$gates = $this->get_gates();

		if ( empty( $gates ) ) {
			return;
		}

		wp_enqueue_script(
			'wp-mail-smtp-license-gate',
			wp_mail_smtp()->pro->assets_url . '/js/smtp-pro-license-gate' . WP::asset_min() . '.js',
			[ 'jquery', 'wp-mail-smtp-admin-jconfirm' ],
			WPMS_PLUGIN_VER,
			// In the head, so a whole page gate stamps the root before the body is parsed.
			false
		);

		wp_localize_script(
			'wp-mail-smtp-license-gate',
			'wp_mail_smtp_license_gate',
			[
				'gates'       => $gates,
				'badge_label' => $this->license->get_action_label(),
			]
		);
	}

	/**
	 * Render the License Key field under a gated surface's section heading.
	 *
	 * @since 4.10.0
	 *
	 * @return void
	 */
	public function render_section_key_field() {

		foreach ( $this->get_gates( [ 'page' ] ) as $gate ) {
			$this->render_key_field( $gate );
		}
	}

	/**
	 * Render the License Key row above a gated mailer's own settings.
	 *
	 * @since 4.10.0
	 *
	 * @param OptionsAbstract $provider The mailer's options object.
	 *
	 * @return void
	 */
	public function render_mailer_key_field( $provider ) {

		foreach ( $this->get_gates( [ 'mailer' ] ) as $gate ) {
			if ( $gate['slug'] !== $provider->get_slug() ) {
				continue;
			}

			// TODO: give every state its own doc, once the remaining ones are written.
			$docs_url = $this->license->is_expired()
				? $this->license->get_renewal_docs_url( $gate['utm'] )
				: $this->license->get_activation_docs_url( $gate['utm'] );
			?>
			<div class="wp-mail-smtp-setting-row wp-mail-smtp-clear wpms-license-gate__exempt">
				<p class="wpms:m-0 wpms:text-[var(--wpms-text-primary,#2c3338)] wpms:text-sm wpms:leading-[20px] wpms-reset">
					<?php
					// Built from escaped strings.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $this->get_mailer_state_report();
					?>
					<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="wpms:text-[var(--wpms-text-link,#056aab)] wpms:no-underline wpms-reset"><?php esc_html_e( 'Learn more', 'wp-mail-smtp-pro' ); ?></a>
				</p>
			</div>

			<div class="wp-mail-smtp-setting-row wp-mail-smtp-clear wpms-license-gate__exempt">
				<div class="wp-mail-smtp-setting-label">
					<label for="wpms-license-key-field-input<?php echo esc_attr( $gate['id_suffix'] ); ?>">
						<?php esc_html_e( 'License Key', 'wp-mail-smtp-pro' ); ?>
					</label>
				</div>
				<div class="wp-mail-smtp-setting-field">
					<?php $this->license->display_license_key_field( Options::init(), $gate['id_suffix'], 'mailer-settings' ); ?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Render the License Key card over a gated surface's content.
	 *
	 * @since 4.10.0
	 *
	 * @return void
	 */
	public function render_page_key_card() {

		foreach ( $this->get_gates( [ 'page_card' ] ) as $gate ) {
			$this->render_key_card( $gate );
		}
	}

	/**
	 * Render the source the gate script clones to build its License Key dialog.
	 *
	 * A `template`, so the field's ids stay out of the document and cannot collide.
	 *
	 * @since 4.10.0
	 *
	 * @return void
	 */
	public function render_modal_source() {

		if ( empty( $this->get_gates() ) || ! empty( $this->get_gates( [ 'page_card' ] ) ) ) {
			return;
		}
		?>
		<template class="js-wp-mail-smtp-license-gate-modal">
			<div class="wpms-license-key-card wpms-license-key-card--padded">
				<i data-icon="fa6-solid--lock" aria-hidden="true" class="wpms-license-key-card__icon wpms:icon-[fa6-solid--lock]"></i>
				<p class="wpms-license-key-card__title"><?php echo esc_html( $this->license->get_state_title() ); ?></p>
				<p class="wpms-license-key-card__body">
					<?php
					// The gated feature is only known once a gate is clicked, so the script
					// substitutes its name here on open.
					echo esc_html( $this->get_card_body( '%name%' ) );
					?>
					<a href="<?php echo esc_url( $this->license->get_activation_docs_url( 'license-gate-modal' ) ); ?>" target="_blank" rel="noopener noreferrer" class="wpms-license-key-card__link"><?php esc_html_e( 'Need Help?', 'wp-mail-smtp-pro' ); ?></a>
				</p>

				<?php $this->license->display_license_key_field( Options::init(), '-modal', 'card' ); ?>
			</div>
		</template>
		<?php
	}

	/**
	 * The gates on the current admin screen, optionally narrowed to the given types.
	 *
	 * @since 4.10.0
	 *
	 * @param string[] $types The gate types to return. All of them when empty.
	 *
	 * @return array[] {
	 *     Empty while the license is valid, or on a screen with nothing gated on it.
	 *
	 *     @type string $type      One of `page`, `page_card`, `row` or `mailer`.
	 *     @type string $selector  The gated container.
	 *     @type string $feature   The gated feature, named in the copy.
	 *     @type string $utm       The utm_content value identifying the gate. Absent on a `row`.
	 *     @type string $id_suffix Appended to the gate's License Key field ids. Absent on a `row`.
	 *     @type string $slug      The gated mailer's slug. On a `mailer` gate only.
	 *     @type string[] $remove  Selectors the gate strips from the page rather than covers.
	 *                             Optional.
	 * }
	 */
	private function get_gates( $types = [] ) {

		if ( ! isset( $this->gates ) ) {
			$this->gates = $this->prepare_gates();
		}

		if ( empty( $types ) ) {
			return $this->gates;
		}

		return array_filter(
			$this->gates,
			function ( $gate ) use ( $types ) {
				return in_array( $gate['type'], $types, true );
			}
		);
	}

	/**
	 * Work out what is gated on the current admin screen.
	 *
	 * @since 4.10.0
	 *
	 * @return array[]
	 */
	private function prepare_gates() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		if ( $this->license->is_valid() ) {
			return [];
		}

		$admin       = wp_mail_smtp()->get_admin();
		$current_tab = $admin->get_current_tab();

		// Settings tabs that hold nothing but the gated feature.
		$gated_tabs = [
			'logs'    => [
				'feature' => esc_html__( 'Email Log', 'wp-mail-smtp-pro' ),
				'utm'     => 'email-logs-settings',
			],
			'alerts'  => [
				'feature' => esc_html__( 'Alerts', 'wp-mail-smtp-pro' ),
				'utm'     => 'alerts-settings',
			],
			'routing' => [
				'feature' => esc_html__( 'Smart Routing', 'wp-mail-smtp-pro' ),
				'utm'     => 'smart-routing-settings',
				'remove'  => [ '.wp-mail-smtp-smart-routing-route-add' ],
			],
			'control' => [
				'feature' => esc_html__( 'Email Controls', 'wp-mail-smtp-pro' ),
				'utm'     => 'email-controls-settings',
			],
		];

		if ( isset( $gated_tabs[ $current_tab ] ) ) {
			$gated_tab = $gated_tabs[ $current_tab ];
			$gate      = $this->prepare_page_gate( 'page', $gated_tab['feature'], $gated_tab['utm'] );

			if ( isset( $gated_tab['remove'] ) ) {
				$gate['remove'] = $gated_tab['remove'];
			}

			return [ $gate ];
		}

		$parent_pages = $admin->get_parent_pages();

		if (
			$admin->is_admin_page( 'tools' ) &&
			isset( $parent_pages['tools'] ) &&
			$parent_pages['tools']->get_current_tab() === 'export'
		) {
			return [ $this->prepare_page_gate( 'page', esc_html__( 'Export Email Logs', 'wp-mail-smtp-pro' ), 'export-settings' ) ];
		}

		if ( $current_tab === 'connections' ) {
			return $this->prepare_connections_gates();
		}

		$logs = wp_mail_smtp()->get_pro()->get_logs();

		// Both gate the table they render, which is only there while logging is on.
		if ( $logs->is_enabled() && $logs->is_valid_db() ) {
			if ( $logs->is_archive() ) {
				return [ $this->prepare_page_gate( 'page_card', esc_html__( 'Email Log', 'wp-mail-smtp-pro' ), 'email-logs-archive', '-email-logs' ) ];
			}

			if ( $admin->is_admin_page( 'reports' ) ) {
				return [ $this->prepare_page_gate( 'page_card', esc_html__( 'Email Reports', 'wp-mail-smtp-pro' ), 'email-reports', '-email-reports' ) ];
			}
		}

		// The General tab's form also holds the License Key field and the mail settings, which
		// have to stay editable, so its gates are containers inside the form.
		if ( $current_tab === 'settings' ) {
			$gates = $this->prepare_mailer_gates();

			$gates[] = [
				'type'     => 'row',
				'selector' => '#wp-mail-smtp-setting-row-backup_connection',
				'feature'  => esc_html__( 'Backup Connection', 'wp-mail-smtp-pro' ),
			];

			return $gates;
		}

		return [];
	}

	/**
	 * A gate covering the whole of a page's own form.
	 *
	 * @since 4.10.0
	 *
	 * @param string $type      Either `page` or `page_card`.
	 * @param string $feature   The gated feature, named in the copy.
	 * @param string $utm       The utm_content value identifying the gate.
	 * @param string $id_suffix Appended to the gate's License Key field ids.
	 * @param string $selector  The gated container, for a page whose content is not a form.
	 *
	 * @return array
	 */
	private function prepare_page_gate( $type, $feature, $utm, $id_suffix = '', $selector = '' ) {

		return [
			'type'      => $type,
			// This default is repeated in `assets/pro/css/smtp-pro-settings.scss`, which paints
			// the region before the script runs, so the two must not drift.
			'selector'  => empty( $selector ) ? '.wp-mail-smtp-page-content form' : $selector,
			'feature'   => $feature,
			'utm'       => $utm,
			'id_suffix' => $id_suffix,
		];
	}

	/**
	 * The gates on the Additional Connections tab.
	 *
	 * @since 4.10.0
	 *
	 * @return array[]
	 */
	private function prepare_connections_gates() {

		$feature = esc_html__( 'Additional Connections', 'wp-mail-smtp-pro' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mode = isset( $_GET['mode'] ) ? sanitize_key( $_GET['mode'] ) : '';

		if ( in_array( $mode, [ 'new', 'edit' ], true ) ) {
			return [ $this->prepare_page_gate( 'page', $feature, 'additional-connections' ) ];
		}

		$gate = $this->prepare_page_gate( 'page', $feature, 'additional-connections', '', '.wp-mail-smtp-additional-connections-list' );

		$gate['remove'] = [ '.js-wp-mail-smtp-additional-connections-add' ];

		return [ $gate ];
	}

	/**
	 * A gate for each mailer that needs an active license before it can be configured.
	 *
	 * Built from the mailers Pro injects, so a Lite mailer Pro merely extends is left alone.
	 *
	 * @since 4.10.0
	 *
	 * @return array[]
	 */
	private function prepare_mailer_gates() {

		$gates = [];

		foreach ( array_keys( Providers::PRO_MAILERS ) as $slug ) {
			$gates[] = [
				'type'      => 'mailer',
				'slug'      => $slug,
				'selector'  => '.wp-mail-smtp-mailer-option-' . $slug,
				'feature'   => wp_mail_smtp()->get_providers()->get_options( $slug )->get_title(),
				'utm'       => 'mailer-' . $slug,
				'id_suffix' => '-' . $slug,
			];
		}

		return $gates;
	}

	/**
	 * Render the License Key row shown under a gated page's section heading.
	 *
	 * @since 4.10.0
	 *
	 * @param array $gate The gate being rendered for.
	 *
	 * @return void
	 */
	private function render_key_field( $gate ) {

		// TODO: give every state its own doc, once the remaining ones are written.
		$docs_url = $this->license->is_expired()
			? $this->license->get_renewal_docs_url( $gate['utm'] )
			: $this->license->get_activation_docs_url( $gate['utm'] );
		?>
		<div class="wp-mail-smtp-setting-row wp-mail-smtp-clear wpms-license-gate__key-row wpms-license-gate__exempt">
			<div class="wp-mail-smtp-setting-field">
				<p class="wpms:m-0 wpms:mb-[var(--wpms-spacing-20,20px)] wpms:text-[var(--wpms-text-primary,#2c3338)] wpms:text-sm wpms:leading-[20px] wpms-reset">
					<?php
					// Built from escaped strings.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $this->get_feature_state_report();
					?>
					<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="wpms:text-[var(--wpms-text-link,#056aab)] wpms:underline wpms-reset"><?php esc_html_e( 'Learn more', 'wp-mail-smtp-pro' ); ?></a>
				</p>

				<?php $this->license->display_license_key_field( Options::init(), $gate['id_suffix'], 'feature-settings' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * The license's standing, and what it has to become for this feature to unlock.
	 *
	 * @since 4.10.0
	 *
	 * @return string Escaped markup, echoed as-is.
	 */
	private function get_feature_state_report() {

		$state = $this->license->get_state();

		$asks = [
			'no_key'        => esc_html__( 'Please enter and activate your license key to activate this feature.', 'wp-mail-smtp-pro' ),
			'expired'       => esc_html__( 'Please renew and activate your license key to activate this feature.', 'wp-mail-smtp-pro' ),
			'limit_reached' => esc_html__( 'Please update the list of your sites or upgrade your license to activate this feature.', 'wp-mail-smtp-pro' ),
			'disabled'      => esc_html__( 'Please use a different license key to activate this feature.', 'wp-mail-smtp-pro' ),
			'invalid'       => esc_html__( 'Please use a different license key to activate this feature.', 'wp-mail-smtp-pro' ),
		];

		$ask = isset( $asks[ $state ] ) ? $asks[ $state ] : '';

		// A site with no key yet has nothing to state: the ask already says what is missing.
		if ( $state === 'no_key' ) {
			return $ask;
		}

		return sprintf( '<b>%1$s</b> %2$s', $this->license->get_state_title( $state ), $ask );
	}

	/**
	 * The license's standing, and what it has to become for this mailer to be editable.
	 *
	 * @since 4.10.0
	 *
	 * @return string Escaped markup, echoed as-is.
	 */
	private function get_mailer_state_report() {

		$state = $this->license->get_state();

		$asks = [
			'no_key'        => esc_html__( 'Please enter and activate your license key to make edits to this mailer.', 'wp-mail-smtp-pro' ),
			'expired'       => esc_html__( 'Please renew and activate your license key to make edits to this mailer.', 'wp-mail-smtp-pro' ),
			'limit_reached' => esc_html__( 'Please update the list of your sites or upgrade your license to make edits to this mailer.', 'wp-mail-smtp-pro' ),
			'disabled'      => esc_html__( 'Please use a different license key to make edits to this mailer.', 'wp-mail-smtp-pro' ),
			'invalid'       => esc_html__( 'Please use a different license key to make edits to this mailer.', 'wp-mail-smtp-pro' ),
		];

		$ask = isset( $asks[ $state ] ) ? $asks[ $state ] : '';

		// A site with no key yet has nothing to state: the ask already says what is missing.
		if ( $state === 'no_key' ) {
			return $ask;
		}

		return sprintf( '<b>%1$s</b> %2$s', $this->license->get_state_title( $state ), $ask );
	}

	/**
	 * What a gated Pro feature costs while the license cannot unlock it.
	 *
	 * @since 4.10.0
	 *
	 * @param string $feature_name The gated feature, named in the copy. The modal passes its own
	 *                             placeholder here and substitutes the name on open.
	 *
	 * @return string
	 */
	private function get_card_body( $feature_name ) {

		if ( $this->license->get_state() === 'no_key' ) {
			return sprintf( /* translators: %s - the Pro feature the license unlocks. */
				esc_html__( 'Paste your license key below to verify your purchase and get access to %s and all WP Mail SMTP Pro features with automatic updates.', 'wp-mail-smtp-pro' ),
				$feature_name
			);
		}

		return sprintf( /* translators: %s - the Pro feature the license unlocks. */
			esc_html__( 'An active license is needed to access %s, plugin updates (including security improvements), and our world class support!', 'wp-mail-smtp-pro' ),
			$feature_name
		);
	}

	/**
	 * Render the License Key card that sits over a gated Pro feature's content.
	 *
	 * Placed before the gated region, so it sits clear of the veil without an exemption.
	 *
	 * @since 4.10.0
	 *
	 * @param array $gate The gate being rendered for.
	 *
	 * @return void
	 */
	private function render_key_card( $gate ) {
		?>
		<div class="wpms-license-gate__key-card-wrap">
			<div class="wpms-license-gate__key-card wpms-license-key-card">
				<i data-icon="fa6-solid--lock" aria-hidden="true" class="wpms-license-key-card__icon wpms:icon-[fa6-solid--lock]"></i>
				<p class="wpms-license-key-card__title"><?php echo esc_html( $this->license->get_state_title() ); ?></p>
				<p class="wpms-license-key-card__body">
					<?php echo esc_html( $this->get_card_body( $gate['feature'] ) ); ?>
					<a href="<?php echo esc_url( $this->license->get_activation_docs_url( $gate['utm'] ) ); ?>" target="_blank" rel="noopener noreferrer" class="wpms-license-key-card__link"><?php esc_html_e( 'Need Help?', 'wp-mail-smtp-pro' ); ?></a>
				</p>

				<?php $this->license->display_license_key_field( Options::init(), $gate['id_suffix'], 'card' ); ?>
			</div>
		</div>
		<?php
	}
}
