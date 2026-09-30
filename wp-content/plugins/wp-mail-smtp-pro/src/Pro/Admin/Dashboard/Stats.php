<?php

namespace WPMailSMTP\Pro\Admin\Dashboard;

use DateTime;
use WPMailSMTP\Admin\Dashboard\Stats as StatsBase;
use WPMailSMTP\WP;
use WPMailSMTP\Pro\Emails\Logs\Reports\Report;

/**
 * Dashboard statistics (Pro), sourced from the email log rather than the option counters.
 *
 * @since 4.10.0
 */
class Stats extends StatsBase {

	/**
	 * Default trailing window, in days, for stats not scoped to an explicit range.
	 * Matches the date-range control's own default preset.
	 *
	 * @since 4.10.0
	 */
	public const DEFAULT_RANGE_DAYS = 30;

	/**
	 * Weekly sent/failed rows folded from the email log, memoised per request.
	 *
	 * @since 4.10.0
	 *
	 * @var array|null
	 */
	private $log_weekly_rows;

	/**
	 * The trailing window, in days, the failed-email prompt reports on.
	 *
	 * @since 4.10.0
	 */
	private const RECENT_DAYS = 30;

	/**
	 * That window's sent and failed totals, memoised per request.
	 *
	 * @since 4.10.0
	 *
	 * @var array|null
	 */
	private $recent_totals;

	/**
	 * Per-metric totals by range string, memoised because a delta asks for two ranges
	 * and the cards ask for one of them again.
	 *
	 * @since 4.10.0
	 *
	 * @var array
	 */
	private $range_totals = [];

	/**
	 * The stat cards' totals over the selected date range, defaulting to the range the
	 * control itself opens on.
	 *
	 * @since 4.10.0
	 *
	 * @param array|null $range Date range to scope to, `[ from, to ]`.
	 *
	 * @return array
	 */
	public function get_stat_totals( ?array $range = null ): array {

		return $this->get_range_totals( $range ?? $this->get_preset_range( self::DEFAULT_RANGE_DAYS ) );
	}

	/**
	 * The stat cards' percent changes over the selected date range, each against the
	 * window of the same length immediately before it.
	 *
	 * @since 4.10.0
	 *
	 * @param array|null $range Date range to scope to, `[ from, to ]`.
	 *
	 * @return array
	 */
	public function get_stat_deltas( ?array $range = null ): array {

		$current  = $range ?? $this->get_preset_range( self::DEFAULT_RANGE_DAYS );
		$totals   = $this->get_range_totals( $current );
		$previous = $this->get_range_totals( $this->get_previous_range( $current ) );
		$deltas   = [];

		foreach ( $this->get_stat_metrics() as $metric ) {
			$deltas[ $metric ] = $this->get_range_delta(
				(int) ( $totals[ $metric ] ?? 0 ),
				(int) ( $previous[ $metric ] ?? 0 )
			);
		}

		return $deltas;
	}

	/**
	 * The metrics the stat cards carry, adding the two Pro records.
	 *
	 * @since 4.10.0
	 *
	 * @return array
	 */
	protected function get_stat_metrics(): array {

		return array_merge( parent::get_stat_metrics(), [ 'total', 'opened' ] );
	}

	/**
	 * Every stat-card metric's total over one range, read off the email log.
	 *
	 * @since 4.10.0
	 *
	 * @param array $range Date range, `[ from, to ]`.
	 *
	 * @return array
	 */
	protected function get_range_totals( array $range ): array {

		$key = implode( ' - ', $range );

		if ( isset( $this->range_totals[ $key ] ) ) {
			return $this->range_totals[ $key ];
		}

		$report = new Report( [ 'date' => $range ] );
		$totals = $report->get_stats_totals();

		$this->range_totals[ $key ] = [
			'total'  => $report->get_total_count( $totals ),
			'sent'   => $report->get_sent_count( $totals ),
			'failed' => $report->get_unsent_count( $totals ),
			'opened' => $report->get_open_count( $totals ),
		];

		return $this->range_totals[ $key ];
	}

	/**
	 * Percent change between two totals.
	 *
	 * @since 4.10.0
	 *
	 * @param int $current  The selected range's total.
	 * @param int $previous The preceding window's total.
	 *
	 * @return float|null Null when the preceding window has nothing to compare against.
	 */
	private function get_range_delta( int $current, int $previous ) {

		if ( $previous === 0 ) {
			return null;
		}

		return round( ( $current - $previous ) / $previous * 100, 1 );
	}

	/**
	 * The window of the same length ending the day before `$range` opens.
	 *
	 * @since 4.10.0
	 *
	 * @param array $range Date range, `[ from, to ]`.
	 *
	 * @return array `[ from, to ]`, both `Y-m-d`.
	 */
	private function get_previous_range( array $range ): array {

		// Both bounds are already `Y-m-d`, so the span between them is the same whatever
		// zone reads them; only a range anchored on "now" needs the site's.
		$from = new DateTime( $range[0] );
		$days = (int) $from->diff( new DateTime( $range[1] ) )->days + 1;
		$to   = ( clone $from )->modify( '-1 day' );

		return [
			( clone $to )->modify( '-' . ( $days - 1 ) . ' days' )->format( 'Y-m-d' ),
			$to->format( 'Y-m-d' ),
		];
	}

	/**
	 * Sent and failed totals over the trailing 30 days, the period the failed-email
	 * prompt's copy names. Read on its own window rather than the page's date range, and
	 * memoised because both the threshold and the headline count ask for it.
	 *
	 * @since 4.10.0
	 *
	 * @return array `[ 'sent' => int, 'failed' => int ]`.
	 */
	protected function get_recent_totals() {

		if ( $this->recent_totals !== null ) {
			return $this->recent_totals;
		}

		// Ends today, not yesterday like the range control's presets: a failure an hour
		// ago is the one most worth prompting about.
		$report = new Report(
			[
				'date' => [
					wp_date( 'Y-m-d', strtotime( '-' . ( self::RECENT_DAYS - 1 ) . ' days' ) ),
					wp_date( 'Y-m-d' ),
				],
			]
		);
		$totals = $report->get_stats_totals();
		$failed = (int) ( $totals['unsent'] ?? 0 );

		// Report's own total counts every attempt, failures included.
		$this->recent_totals = [
			'sent'   => (int) ( $totals['total'] ?? 0 ) - $failed,
			'failed' => $failed,
		];

		return $this->recent_totals;
	}

	/**
	 * Chart series for the graph: per-day counts over `$range`, defaulting to the trailing
	 * {@see \WPMailSMTP\Admin\Dashboard\Stats::WEEKS}-week window.
	 *
	 * @since 4.10.0
	 *
	 * @param array|null $range Date range to scope to, `[ from, to ]`.
	 *
	 * @return array Points as `[ 'label' => string, 'tooltip' => string, 'sent' => int,
	 *               'failed' => int, 'confirmed' => int, 'unconfirmed' => int,
	 *               'opened' => int ]`.
	 */
	public function get_series( ?array $range = null ): array {

		$report = new Report( [ 'date' => $range ?? $this->get_preset_range( self::DEFAULT_RANGE_DAYS ) ] );

		// array_values(): get_stats_by_date_chart_data() keys rows by date string, which
		// json_encode() would otherwise serialize as an object, not an array.
		return array_values(
			array_map(
				static function ( $day ) use ( $report ) {

					$timestamp = strtotime( $day['day'] );

					return [
						// The tooltip adds the year, which a range spanning one cannot show
						// on the axis itself.
						'label'       => date_i18n( 'M j', $timestamp ),
						'tooltip'     => date_i18n( 'M j, Y', $timestamp ),
						'sent'        => $report->get_total_count( $day ),
						'failed'      => $report->get_unsent_count( $day ),
						'confirmed'   => $report->get_confirmed_count( $day ),
						'unconfirmed' => $report->get_unconfirmed_count( $day ),
						'opened'      => $report->get_open_count( $day ),
					];
				},
				$report->get_stats_by_date_chart_data()
			)
		);
	}

	/**
	 * Sent counts keyed by ISO week number.
	 *
	 * @since 4.10.0
	 *
	 * @return array
	 */
	protected function get_sent_counters() {

		return array_map(
			static function ( $row ) {

				return (int) $row['sent'];
			},
			$this->get_log_weekly_rows()
		);
	}

	/**
	 * Failed counts keyed by ISO week number.
	 *
	 * @since 4.10.0
	 *
	 * @return array
	 */
	protected function get_failed_counters() {

		return array_map(
			static function ( $row ) {

				return (int) $row['failed'];
			},
			$this->get_log_weekly_rows()
		);
	}

	/**
	 * Weekly sent and failed rows from the email log, folded from the trailing WEEKS
	 * calendar weeks. Memoised per request.
	 *
	 * @since 4.10.0
	 *
	 * @return array Rows keyed by ISO week: [ 'sent', 'failed' ] (int).
	 */
	protected function get_log_weekly_rows() {

		if ( $this->log_weekly_rows !== null ) {
			return $this->log_weekly_rows;
		}

		$report = new Report( [ 'date' => $this->get_weekly_rows_range() ] );

		$rows = [];

		foreach ( $report->get_stats_by_date() as $day ) {
			$week = (int) wp_date( 'W', strtotime( $day['day'] ) );

			if ( ! isset( $rows[ $week ] ) ) {
				$rows[ $week ] = [
					'sent'   => 0,
					'failed' => 0,
				];
			}

			$rows[ $week ]['sent']   += $report->get_total_count( $day );
			$rows[ $week ]['failed'] += $report->get_unsent_count( $day );
		}

		$this->log_weekly_rows = $rows;

		return $this->log_weekly_rows;
	}

	/**
	 * The trailing WEEKS-week window backing the weekly rows, as `Report`'s `date` param
	 * expects. Runs from the Monday opening the oldest week the series shows to today,
	 * whose own calendar week includes today's sends.
	 *
	 * @since 4.10.0
	 *
	 * @return array `[ from, to ]`, both `Y-m-d`.
	 */
	private function get_weekly_rows_range(): array {

		return [
			wp_date( 'Y-m-d', $this->get_week_start( parent::WEEKS - 1 ) ),
			wp_date( 'Y-m-d' ),
		];
	}

	/**
	 * The `[ from, to ]` window a day-count preset resolves to, as `Report`'s `date` param
	 * expects. Anchored on yesterday, matching what the control's own JS resolves it to.
	 *
	 * @since 4.10.0
	 *
	 * @param int $days Preset day count.
	 *
	 * @return array `[ from, to ]`, both `Y-m-d`.
	 */
	public function get_preset_range( int $days ): array {

		$to   = ( new DateTime( 'now', WP::wp_timezone() ) )->modify( '-1 day' );
		$from = ( clone $to )->modify( '-' . ( $days - 1 ) . ' days' );

		return [ $from->format( 'Y-m-d' ), $to->format( 'Y-m-d' ) ];
	}
}
