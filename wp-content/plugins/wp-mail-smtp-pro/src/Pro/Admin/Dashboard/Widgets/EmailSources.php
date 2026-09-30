<?php

namespace WPMailSMTP\Pro\Admin\Dashboard\Widgets;

use WPMailSMTP\Admin\Dashboard\InlineCards;
use WPMailSMTP\Admin\Dashboard\Widgets\EmailSources as EmailSourcesBase;
use WPMailSMTP\Pro\Admin\Dashboard\LogsDisabled;
use WPMailSMTP\Options;
use WPMailSMTP\Pro\Emails\Logs\EmailsCollection;
use WPMailSMTP\Pro\Emails\Logs\Reports\Report;

// wpms:icon-[fa6-solid--bell-slash] wpms:icon-[fa6-solid--route] are built from an `icon`
// value, so they are named here for Tailwind's scanner, which never sees them.
/**
 * Dashboard "Email Sources" widget (Pro).
 *
 * @since 4.10.0
 */
class EmailSources extends EmailSourcesBase {

	use InlineCards;
	use LogsDisabled;

	/**
	 * How long the by-initiator breakdown is cached for, keyed by date range.
	 *
	 * @since 4.10.0
	 */
	public const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Donut segment colors, by index: five hues from the design's own donut, then a
	 * neutral grey for the folded "Others" row.
	 *
	 * @since 4.10.0
	 */
	public const PALETTE = [ '#3788bd', '#00ba37', '#dba617', '#e79055', '#f86368', '#a7aaad' ];

	/**
	 * The share of a site's email, in percent, a single source must account for before a
	 * card leads with it. Below this the card's own headline undercuts its suggestion.
	 *
	 * @since 4.10.0
	 */
	private const MIN_SOURCE_SHARE_PERCENT = 10;

	/**
	 * Rows kept before the tail is folded into one "Others" row.
	 *
	 * @since 4.10.0
	 */
	public const VISIBLE_ROWS = 5;

	/**
	 * The initiator name `WP::get_initiator_wp_core()` records for emails WordPress
	 * core itself sends, cited by the notifications suggestion card.
	 *
	 * @since 4.10.0
	 */
	public const CORE_INITIATOR_NAME = 'WP Core';

	/**
	 * Whether any email has ever been logged.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	protected function has_logged_emails() {

		return ( new EmailsCollection() )->has_any();
	}

	/**
	 * Render the widget body. The education and connect variants are unchanged from
	 * Lite; only the data variant, reached only on Pro, renders the donut and table.
	 *
	 * @since 4.10.0
	 *
	 * @param string $variant Template variant.
	 * @param array  $data    Aggregated data.
	 *
	 * @return string
	 */
	protected function render_body( string $variant, array $data ): string {

		if ( $variant === 'logs_disabled' ) {
			return $this->render_logs_disabled_overlay( 'email-sources' );
		}

		if ( $variant !== 'data' ) {
			return parent::render_body( $variant, $data );
		}

		$config = $this->get_config( $data['date_range'] ?? null );

		return (string) wp_mail_smtp_render(
			'dashboard/widgets/email-sources',
			[
				'variant'      => $variant,
				'config'       => $config,
				'inline_cards' => $this->render_inline_cards( $this->get_inline_cards( [ 'sources' => $config['all'] ] ) ),
			],
			true
		);
	}

	/**
	 * Resolve the sending-source suggestion cards, already filtered for dismissal.
	 *
	 * @since 4.10.0
	 *
	 * @param array $data Aggregated widget data: sources (get_config()'s `all`).
	 *
	 * @return array
	 */
	protected function get_inline_cards( array $data ): array {

		$sources = $data['sources'] ?? [];

		return $this->prepare_inline_cards(
			[
				$this->get_smart_routing_card( $sources ),
				$this->get_notifications_card( $sources ),
			]
		);
	}

	/**
	 * The "Set Up Smart Routing" card, led by the site's biggest sending source.
	 *
	 * @since 4.10.0
	 *
	 * @param array $sources Source rows, biggest first.
	 *
	 * @return array Empty when the card does not apply.
	 */
	protected function get_smart_routing_card( array $sources = [] ): array {

		if ( $this->is_smart_routing_active() || ! $this->has_leading_source( $sources ) ) {
			return [];
		}

		return [
			'id'    => 'smart_routing',
			'icon'  => 'fa6-solid--route',
			'title' => $this->get_source_share_title( $sources[0] ),
			'text'  => esc_html__( 'Send this source\'s email through a mailer of its own with the Smart Routing feature.', 'wp-mail-smtp-pro' ),
			'cta'   => [
				'label'        => esc_html__( 'Set Up Smart Routing', 'wp-mail-smtp-pro' ),
				'url'          => add_query_arg( 'tab', 'routing', wp_mail_smtp()->get_admin()->get_admin_page_url() ),
				'target_blank' => false,
			],
		];
	}

	/**
	 * Whether the biggest source accounts for enough of the site's email to lead a card.
	 *
	 * @since 4.10.0
	 *
	 * @param array $sources Source rows, biggest first.
	 *
	 * @return bool
	 */
	private function has_leading_source( array $sources ): bool {

		return ( $sources[0]['share'] ?? 0 ) >= self::MIN_SOURCE_SHARE_PERCENT;
	}

	/**
	 * The "Manage Notifications" card, led by WordPress core's own share of the site's
	 * email and linking to the Email Controls tab.
	 *
	 * @since 4.10.0
	 *
	 * @param array $sources Rows from get_config()'s `all`.
	 *
	 * @return array Empty when the card does not apply.
	 */
	private function get_notifications_card( array $sources ): array {

		$index = array_search( self::CORE_INITIATOR_NAME, array_column( $sources, 'name' ), true );

		if ( $index === false ) {
			return [];
		}

		$source = $sources[ $index ];

		if ( ( $source['share'] ?? 0 ) < self::MIN_SOURCE_SHARE_PERCENT ) {
			return [];
		}

		return [
			'id'    => 'email_sources_notifications',
			'icon'  => 'fa6-solid--bell-slash',
			'title' => $this->get_source_share_title( $source ),
			'text'  => esc_html__( 'Want to manage and silence some non-essential WordPress emails?', 'wp-mail-smtp-pro' ),
			'cta'   => [
				'label'        => esc_html__( 'Manage Notifications', 'wp-mail-smtp-pro' ),
				'url'          => add_query_arg( 'tab', 'control', wp_mail_smtp()->get_admin()->get_admin_page_url() ),
				'target_blank' => false,
			],
		];
	}

	/**
	 * The shared card title format, naming a source and its share of total email.
	 *
	 * @since 4.10.0
	 *
	 * @param array $source A get_config() row: name, total, share.
	 *
	 * @return string
	 */
	private function get_source_share_title( array $source ): string {

		return esc_html(
			sprintf(
				/* translators: %1$s - the source name, e.g. "WooCommerce"; %2$s - its share of total email, e.g. "42%". */
				__( '%1$s sends %2$s of your email', 'wp-mail-smtp-pro' ),
				$source['name'],
				round( $source['share'] ) . '%'
			)
		);
	}

	/**
	 * Whether smart routing is switched on and has a rule pointed at a real connection:
	 * rules left behind with the feature off route nothing.
	 *
	 * @since 4.10.0
	 *
	 * @return bool
	 */
	protected function is_smart_routing_active() {

		$options = Options::init();

		if ( empty( $options->get( 'smart_routing', 'enabled' ) ) ) {
			return false;
		}

		$routes = $options->get( 'smart_routing', 'routes' );

		if ( empty( $routes ) ) {
			return false;
		}

		foreach ( $routes as $route ) {
			if ( ! empty( $route['connection_id'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the donut/table config the JS module hydrates from.
	 *
	 * @since 4.10.0
	 *
	 * @param array|null $range Date range to scope to, `[ from, to ]`, or null for all time.
	 *
	 * @return array
	 */
	protected function get_config( ?array $range ): array {

		$stats = $this->get_source_stats( $range );
		$total = array_sum( array_column( $stats, 'total' ) );

		$rows = [];

		foreach ( $stats as $stat ) {
			$rows[] = [
				'name'  => $stat['initiator'],
				'total' => $stat['total'],
				'share' => $total > 0 ? ( $stat['total'] / $total ) * 100 : 0,
			];
		}

		return [
			'all'        => $this->bundle_tail_into_other( $rows ),
			'palette'    => self::PALETTE,
			'donutTotal' => $total,
		];
	}

	/**
	 * Cap the rows to the top `self::VISIBLE_ROWS`, folding the rest into one row
	 * whose total and share are the tail's sums.
	 *
	 * @since 4.10.0
	 *
	 * @param array $rows Rows as [ 'name', 'total', 'share' ], sorted by total descending.
	 *
	 * @return array
	 */
	private function bundle_tail_into_other( array $rows ): array {

		if ( count( $rows ) <= self::VISIBLE_ROWS ) {
			return $rows;
		}

		$kept = array_slice( $rows, 0, self::VISIBLE_ROWS );
		$tail = array_slice( $rows, self::VISIBLE_ROWS );

		$kept[] = [
			'name'  => esc_html__( 'Others', 'wp-mail-smtp-pro' ),
			'total' => array_sum( array_column( $tail, 'total' ) ),
			'share' => array_sum( array_column( $tail, 'share' ) ),
		];

		return $kept;
	}

	/**
	 * The by-initiator totals for the given range, cached for `self::CACHE_TTL`. Fetched
	 * unfolded, since this widget does its own fold.
	 *
	 * @since 4.10.0
	 *
	 * @param array|null $range Date range to scope to, `[ from, to ]`, or null for all time.
	 *
	 * @return array
	 */
	protected function get_source_stats( ?array $range ): array {

		$cache_key = 'wp_mail_smtp_dashboard_email_sources_' . md5( wp_json_encode( $range ) );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$stats = ( new Report( [ 'date' => $range ] ) )->get_stats_by_initiator( -1 );

		set_transient( $cache_key, $stats, self::CACHE_TTL );

		return $stats;
	}
}
