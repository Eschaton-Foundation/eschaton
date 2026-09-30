<?php

namespace WPMailSMTP\Pro\Admin\Dashboard\Widgets;

use WPMailSMTP\Admin\Area;
use WPMailSMTP\Admin\Dashboard\WidgetState;
use WPMailSMTP\Admin\Dashboard\Widgets\Version as VersionBase;
use WPMailSMTP\Pro\License\License;

/**
 * The version strip (Pro): offers the license the action it needs whenever one cannot
 * be used, in place of the What's New link.
 *
 * @since 4.10.0
 */
class Version extends VersionBase {

	/**
	 * Get the widget state.
	 *
	 * @since 4.10.0
	 *
	 * @return WidgetState
	 */
	public function get_state(): WidgetState {

		if ( $this->get_license()->get_state() !== 'valid' ) {
			return new WidgetState( true, 'license' );
		}

		if ( $this->has_update() ) {
			return new WidgetState( true, 'update' );
		}

		return parent::get_state();
	}

	/**
	 * Get the card's extra state classes.
	 *
	 * @since 4.10.0
	 *
	 * @param string $variant Template variant.
	 * @param array  $data    Aggregated data.
	 *
	 * @return array
	 */
	protected function get_extra_classes( string $variant, array $data ): array {

		if ( $variant !== 'license' ) {
			return parent::get_extra_classes( $variant, $data );
		}

		return [ 'wpms-dashboard-widget-version--' . $this->get_license_tone() ];
	}

	/**
	 * The license CTA: what the license needs doing, pointing at the key field that
	 * does it, and what the state means for the site meanwhile.
	 *
	 * @since 4.10.0
	 *
	 * @return array
	 */
	protected function get_license_cta(): array {

		return [
			'label'  => $this->get_license()->get_action_label(),
			'url'    => wp_mail_smtp()->get_admin()->get_admin_page_url( Area::SLUG . '#wp-mail-smtp-setting-row-license_key' ),
			'notice' => $this->get_license_notice(),
			'tone'   => $this->get_license_tone(),
		];
	}

	/**
	 * What the license state costs the site, in its own copy or the notices' report.
	 *
	 * @since 4.10.0
	 *
	 * @return string Escaped markup, echoed as-is.
	 */
	private function get_license_notice(): string {

		$license = $this->get_license();
		$state   = $license->get_state();

		if ( $state === 'no_key' ) {
			return esc_html__( 'An active license is needed to unlock Pro features like email logging, reports, and alerts. It also provides access to plugin updates, security improvements, and our world-class support.', 'wp-mail-smtp-pro' );
		}

		if ( $state === 'expired' ) {
			return esc_html__( 'Your license has expired. Renew to continue receiving updates and new features.', 'wp-mail-smtp-pro' );
		}

		return $license->get_state_report( $state, 'dashboard-version' );
	}

	/**
	 * How severely the license state reads.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	private function get_license_tone(): string {

		return $this->get_license()->get_state() === 'no_key' ? 'warning' : 'error';
	}

	/**
	 * Where the update variant's CTA goes: core's own updater, which installs the update.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	protected function get_update_url(): string {

		$basename = plugin_basename( WPMS_PLUGIN_FILE );
		$path     = 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $basename );

		// Plugin files belong to the network; this page only renders under a site's admin.
		return wp_nonce_url(
			is_multisite() ? network_admin_url( $path ) : self_admin_url( $path ),
			'upgrade-plugin_' . $basename
		);
	}

	/**
	 * Whether a newer version is waiting, for someone who can install it.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	private function has_update(): bool {

		if ( ! current_user_can( 'update_plugins' ) ) {
			return false;
		}

		$updates = get_site_transient( 'update_plugins' );

		return isset( $updates->response[ plugin_basename( WPMS_PLUGIN_FILE ) ] );
	}

	/**
	 * The license handler.
	 *
	 * @since 4.10.0
	 *
	 * @return License
	 */
	private function get_license() {

		return wp_mail_smtp()->get_pro()->get_license();
	}
}
