<?php

namespace WPMailSMTP\Pro\Admin\Dashboard;

use WPMailSMTP\Admin\Dashboard\AccessContext;
use WPMailSMTP\Admin\Dashboard\AccessResolver;
use WPMailSMTP\Admin\Dashboard\Ajax as AjaxBase;
use WPMailSMTP\Admin\Dashboard\Dashboard as DashboardBase;
use WPMailSMTP\Admin\Dashboard\Page as PageBase;
use WPMailSMTP\Admin\Dashboard\Stats as StatsBase;
use WPMailSMTP\Admin\Dashboard\WidgetPipeline;
use WPMailSMTP\Admin\Dashboard\Widgets\Connections as ConnectionsBase;
use WPMailSMTP\Admin\Dashboard\Widgets\EmailLog as EmailLogBase;
use WPMailSMTP\Admin\Dashboard\Widgets\EmailsOverview as EmailsOverviewBase;
use WPMailSMTP\Admin\Dashboard\Widgets\EmailSources as EmailSourcesBase;
use WPMailSMTP\Admin\Dashboard\Widgets\StatCards as StatCardsBase;
use WPMailSMTP\Admin\Dashboard\Widgets\Version as VersionBase;

/**
 * Dashboard orchestrator (Pro).
 *
 * @since 4.10.0
 */
class Dashboard extends DashboardBase {

	/**
	 * Build the object graph and register hooks, then swap in the Pro widgets.
	 *
	 * @since 4.10.0
	 */
	public function hooks() {

		parent::hooks();

		add_filter( 'wp_mail_smtp_admin_dashboard_widget_pipeline_get_widgets', [ $this, 'swap_widgets' ], 10, 2 );
	}

	/**
	 * Get the Dashboard statistics instance, sourced from the email log.
	 *
	 * @since 4.10.0
	 *
	 * @return StatsBase
	 */
	protected function get_stats() {

		return new Stats();
	}

	/**
	 * Get the Dashboard page controller, with the date-range control.
	 *
	 * @since 4.10.0
	 *
	 * @param AccessResolver $access_resolver Access resolver.
	 * @param WidgetPipeline $pipeline        Widget pipeline.
	 * @param StatsBase      $stats           Dashboard statistics.
	 *
	 * @return PageBase
	 */
	protected function get_page( AccessResolver $access_resolver, WidgetPipeline $pipeline, StatsBase $stats ) {

		return new Page( $access_resolver, $pipeline, $stats );
	}

	/**
	 * Get the Dashboard AJAX endpoints, with the date-range refresh endpoint.
	 *
	 * @since 4.10.0
	 *
	 * @param AccessResolver $access_resolver Access resolver.
	 *
	 * @return AjaxBase
	 */
	protected function get_ajax( AccessResolver $access_resolver ) {

		return new Ajax( $access_resolver );
	}

	/**
	 * Replace the Lite widgets that have a Pro subclass with it, matched by class so an
	 * already-filtered list still works.
	 *
	 * @since 4.10.0
	 *
	 * @param array         $widgets Widget instances.
	 * @param AccessContext $access  Access context.
	 *
	 * @return array
	 */
	public function swap_widgets( array $widgets, AccessContext $access ): array {

		$map = [
			ConnectionsBase::class    => Widgets\Connections::class,
			EmailLogBase::class       => Widgets\EmailLog::class,
			EmailsOverviewBase::class => Widgets\EmailsOverview::class,
			EmailSourcesBase::class   => Widgets\EmailSources::class,
			StatCardsBase::class      => Widgets\StatCards::class,
			VersionBase::class        => Widgets\Version::class,
		];

		foreach ( $widgets as $key => $widget ) {
			$class = get_class( $widget );

			if ( isset( $map[ $class ] ) ) {
				$widgets[ $key ] = new $map[ $class ]( $access );
			}
		}

		return $widgets;
	}
}
