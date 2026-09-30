<?php

namespace WPMailSMTP\Pro\Admin\Dashboard\Widgets;

use WPMailSMTP\Admin\Area;
use WPMailSMTP\Admin\Dashboard\Widgets\Connections as ConnectionsBase;
use WPMailSMTP\Options;

/**
 * Dashboard "Connections" widget (Pro): adds the backup and additional connection rows.
 *
 * @since 4.10.0
 */
class Connections extends ConnectionsBase {

	/**
	 * Build one additional (or backup) connection's row data.
	 *
	 * @since 4.10.0
	 *
	 * @param string $connection_id Connection id.
	 *
	 * @return array Empty when the connection no longer exists.
	 */
	protected function get_connection_row( $connection_id ) {

		$connection = wp_mail_smtp()->pro->get_additional_connections()->get_connection( $connection_id );

		if ( ! $connection ) {
			return [];
		}

		return [
			'connection_id' => $connection_id,
			'mailer_name'   => $connection->get_title(),
			'mailer_slug'   => $connection->get_mailer_slug(),
			'status'        => $connection->get_mailer()->is_mailer_complete()
				? $this->get_status( $connection_id )
				: ConnectionsBase::STATUS_SETUP_INCOMPLETE,
			'cta_label'     => esc_html__( 'Manage', 'wp-mail-smtp-pro' ),
			'cta_url'       => wp_mail_smtp()->pro->get_additional_connections()->get_connection_admin_page_url( $connection ),
		];
	}

	/**
	 * Ids of every configured additional connection.
	 *
	 * @since 4.10.0
	 *
	 * @return array
	 */
	protected function get_additional_connection_ids() {

		return array_map(
			static function ( $connection ) {

				return $connection->get_id();
			},
			wp_mail_smtp()->pro->get_additional_connections()->get_connections()
		);
	}

	/**
	 * The currently selected backup connection id, empty when none is selected.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	protected function get_backup_connection_id() {

		return (string) Options::init()->get( 'backup_connection', 'connection_id' );
	}

	/**
	 * The backup connection CTA: add one when there are no additional connections yet,
	 * manage the selection once one exists, and none once a backup is already selected.
	 *
	 * @since 4.10.0
	 *
	 * @return array `[ 'action' => 'add_new'|'manage', 'label' => string, 'url' => string ]`, or `[]`.
	 */
	public function get_backup_cta() {

		if ( ! empty( $this->get_backup_connection_id() ) ) {
			return [];
		}

		return $this->get_backup_prompt_cta();
	}

	/**
	 * The Add New or Manage prompt itself, without the has-a-backup check, for the caller
	 * that has already established there is no usable backup.
	 *
	 * @since 4.10.0
	 *
	 * @return array `[ 'action' => 'add_new'|'manage', 'label' => string, 'url' => string ]`.
	 */
	private function get_backup_prompt_cta() {

		if ( empty( $this->get_additional_connection_ids() ) ) {
			return [
				'action' => 'add_new',
				'label'  => esc_html__( 'Add New', 'wp-mail-smtp-pro' ),
				'url'    => $this->get_new_connection_url(),
			];
		}

		return [
			'action' => 'manage',
			'label'  => esc_html__( 'Manage', 'wp-mail-smtp-pro' ),
			'url'    => wp_mail_smtp()->get_admin()->get_admin_page_url( Area::SLUG . '#wp-mail-smtp-setting-row-backup_connection' ),
		];
	}

	/**
	 * The Backup group, unlocked and populated from the real backup connection state.
	 *
	 * @since 4.10.0
	 *
	 * @return array
	 */
	protected function get_backup_group() {

		return [
			'label' => esc_html__( 'Backup', 'wp-mail-smtp-pro' ),
			'rows'  => [ $this->get_backup_group_row( $this->get_backup_connection_id() ) ],
		];
	}

	/**
	 * The Additional Connections group, unlocked and populated from the real
	 * additional connections.
	 *
	 * @since 4.10.0
	 *
	 * @return array
	 */
	protected function get_additional_group() {

		return [
			'label' => esc_html__( 'Additional Connections', 'wp-mail-smtp-pro' ),
			'rows'  => $this->get_additional_group_rows( $this->get_backup_connection_id() ),
		];
	}

	/**
	 * The Backup group's one row: the real backup connection once one is
	 * selected, or the Add New / Manage prompt while none is usable.
	 *
	 * @since 4.10.0
	 *
	 * @param string $backup_id Selected backup connection id, empty when none.
	 *
	 * @return array
	 */
	protected function get_backup_group_row( $backup_id ) {

		if ( ! empty( $backup_id ) ) {
			$row = $this->to_group_row( $this->get_connection_row( $backup_id ) );

			// The stored id can name a connection that has since been deleted, leaving
			// nothing to render. Fall through to the prompt below: there is no backup.
			if ( ! empty( $row ) ) {
				return $row;
			}
		}

		$backup_cta = $this->get_backup_prompt_cta();

		return [
			'title'    => esc_html__( 'No backup added', 'wp-mail-smtp-pro' ),
			'subtitle' => esc_html__( 'Backup helps when primary fails', 'wp-mail-smtp-pro' ),
			'action'   => [
				'label' => $backup_cta['label'],
				'url'   => $backup_cta['url'],
			],
			'locked'   => false,
		];
	}

	/**
	 * The Additional Connections group's rows: every additional connection other than the
	 * backup, closed by the Add New row.
	 *
	 * @since 4.10.0
	 *
	 * @param string $backup_id Selected backup connection id, already rendered
	 *                          above as the Backup group's row.
	 *
	 * @return array
	 */
	protected function get_additional_group_rows( $backup_id ) {

		$rows = [];

		foreach ( $this->get_additional_connection_ids() as $connection_id ) {
			if ( $connection_id === $backup_id ) {
				continue; // Already rendered above as the backup row.
			}

			$row = $this->to_group_row( $this->get_connection_row( $connection_id ) );

			if ( ! empty( $row ) ) {
				$rows[] = $row;
			}
		}

		$rows[] = $this->get_add_new_connection_row();

		return $rows;
	}

	/**
	 * The Additional Connections group's row for adding another connection.
	 *
	 * @since 4.10.0
	 *
	 * @return array
	 */
	protected function get_add_new_connection_row() {

		return [
			'title'    => esc_html__( 'New Connection', 'wp-mail-smtp-pro' ),
			'subtitle' => esc_html__( 'Setup a new mailer connection', 'wp-mail-smtp-pro' ),
			'action'   => [
				'label' => esc_html__( 'Add New', 'wp-mail-smtp-pro' ),
				'url'   => $this->get_new_connection_url(),
			],
			'locked'   => false,
		];
	}

	/**
	 * URL to start adding a new connection.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	protected function get_new_connection_url() {

		return add_query_arg(
			[
				'tab'  => 'connections',
				'mode' => 'new',
			],
			wp_mail_smtp()->get_admin()->get_admin_page_url()
		);
	}
}
