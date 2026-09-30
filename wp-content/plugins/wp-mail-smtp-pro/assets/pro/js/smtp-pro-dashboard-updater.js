/* global wp_mail_smtp_dashboard, ajaxurl */

/**
 * WP Mail SMTP Dashboard: stats data module.
 *
 * Owns the dashboard stats fetch lifecycle: fetches the cached stats for a
 * chosen date range and announces the fresh payload via the
 * `wpMailSmtpDashboardDataReceived` event, and listeners patch their own UI. The
 * datepicker module only asks this module to fetch.
 *
 * Deliberately has no `init()`: it binds no load-time events and renders DOM
 * only on demand (the inline failure notice), so there is nothing to wire on
 * load. It is a pure service reached through `app.updater`
 * (the orchestrator instantiates every module before calling any `init()`, and
 * skips the call when the method is absent).
 *
 * @since 4.10.0
 *
 * @param {object} document Document object.
 * @param {object} window   Window object.
 * @param {jQuery} $        jQuery object.
 * @param {object} app      Shared dashboard page app (el, vars, classNames).
 *
 * @returns {object} Public module API.
 */
export default function( document, window, $, app ) { // eslint-disable-line max-lines-per-function
	const { el } = app;

	/**
	 * Query-arg that forces a server-side cache recompute.
	 *
	 * @since 4.10.0
	 *
	 * @type {string}
	 */
	const FORCE_CHECK_PARAM = 'force-check';

	/**
	 * The stats request currently in flight, kept so a superseded one can be aborted.
	 *
	 * @since 4.10.0
	 *
	 * @type {object|null}
	 */
	let currentRequest = null;

	/**
	 * Monotonic token identifying the newest stats request. Responses carrying an older
	 * token are ignored, so a slower earlier range cannot overwrite newer state.
	 *
	 * @since 4.10.0
	 *
	 * @type {number}
	 */
	let currentGeneration = 0;

	/**
	 * The last requested range, kept so the inline-error Retry button can replay it.
	 *
	 * @since 4.10.0
	 *
	 * @type {string}
	 */
	let lastDate = '';

	/**
	 * Class of the inline error notice rendered above the stat cards.
	 *
	 * @since 4.10.0
	 *
	 * @type {string}
	 */
	const INLINE_ERROR_CLASS = 'wpms-dashboard-inline-error';

	/**
	 * Selector for the loader beside the date-range control.
	 *
	 * @since 4.10.0
	 *
	 * @type {string}
	 */
	const SPINNER_SELECTOR = '.wpms-dashboard-date-range__spinner';

	const updater = {

		/**
		 * Request the cached stats for a date range and broadcast the response.
		 *
		 * @since 4.10.0
		 *
		 * @param {string} date Posted "YYYY-MM-DD - YYYY-MM-DD" range string.
		 */
		fetchStats( date ) {
			lastDate = date;

			updater.setLoadingState( true );

			const data = {
				action: 'wp_mail_smtp_dashboard_get_stats',
				nonce: wp_mail_smtp_dashboard.nonce,
				date,
			};

			// Carry the forced-recompute flag through, so a force-checked page view keeps
			// bypassing the server-side cache on every range switch.
			if ( updater.isForceCheck() ) {
				data[ FORCE_CHECK_PARAM ] = 1;
			}

			// Claim the newest generation before aborting, so the aborted request's own
			// handlers already see a stale token and skip their work.
			const generation = ++currentGeneration;

			currentRequest?.abort();

			currentRequest = $.post( ajaxurl, data )
				.done( ( response ) => {
					if ( generation === currentGeneration ) {
						updater.applyStats( response, date );
					}
				} )
				.fail( ( jqXHR, textStatus ) => {

					// An abort is this module superseding itself, not a transport error.
					if ( generation === currentGeneration && textStatus !== 'abort' ) {
						window.console?.error?.( 'WP Mail SMTP Dashboard: unable to load stats.', textStatus );

						updater.handleNetworkFailure( jqXHR );
					}
				} )
				.always( () => {

					// Leave the spinner up while a newer request is still running.
					if ( generation !== currentGeneration ) {
						return;
					}

					currentRequest = null;

					updater.setLoadingState( false );
				} );
		},

		/**
		 * Broadcast a fresh stats payload and record the range in the URL.
		 *
		 * @since 4.10.0
		 *
		 * @param {object} response Raw `get_stats` AJAX response.
		 * @param {string} date     Posted "YYYY-MM-DD - YYYY-MM-DD" range string.
		 */
		applyStats( response, date ) {
			if ( wp_mail_smtp_dashboard.is_debug ) {
				window.console?.log?.( 'WP Mail SMTP Dashboard get_stats response:', response );
			}

			if ( ! response?.success ) {
				updater.handleAjaxFailure( response );

				return;
			}

			updater.clearInlineError();

			// Announce the fresh payload; listeners patch their own UI. The chosen date
			// range is passed alongside so listeners that fetch range-scoped follow-up
			// data use it directly instead of re-reading the URL (which this module only
			// updates afterwards).
			el.$document.trigger( 'wpMailSmtpDashboardDataReceived', [ response.data, date ] );

			updater.pushDateUrlState( date );
		},

		/**
		 * Handle a "success: false" AJAX response.
		 *
		 * @since 4.10.0
		 *
		 * @param {object} response Raw `get_stats` AJAX response.
		 */
		handleAjaxFailure( response ) {
			const code = response?.data?.code ?? '';

			updater.showInlineError( code === 'nonce' || code === 'cap' ? 'reload' : 'retry' );
		},

		/**
		 * Handle a jQuery .fail() callback (HTTP failure, abort excluded).
		 *
		 * @since 4.10.0
		 *
		 * @param {object} jqXHR Failed jqXHR.
		 */
		handleNetworkFailure( jqXHR ) {
			updater.showInlineError( jqXHR.status === 403 ? 'reload' : 'retry' );
		},

		/**
		 * Render a standard WP error notice at the top of the page. Previous stat
		 * values are preserved: the notice slots in above the cards, it doesn't
		 * replace them.
		 *
		 * @since 4.10.0
		 *
		 * @param {string} mode Either 'retry' (replay the last request) or 'reload' (window.location.reload).
		 */
		showInlineError( mode ) {
			updater.clearInlineError();

			const i18n = wp_mail_smtp_dashboard.i18n ?? {};
			const isReload = mode === 'reload';
			const $link = $( '<a href="#"></a>' )
				.text( isReload ? i18n.reload : i18n.retry )
				.on( 'click', ( event ) => {
					event.preventDefault();

					if ( isReload ) {
						window.location.reload();

						return;
					}

					updater.fetchStats( lastDate );
				} );

			const $notice = $( `<div class="notice notice-error ${ INLINE_ERROR_CLASS }" role="alert">` )
				.append(
					$( '<p>' )
						.text( ( isReload ? i18n.session_expired : i18n.network_failure ) + ' ' )
						.append( $link )
				);

			el.$attention.before( $notice );

			// Make sure the user actually sees the notice even if scrolled down.
			$notice.get( 0 )?.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		},

		/**
		 * Remove any previously-rendered inline error notice.
		 *
		 * @since 4.10.0
		 */
		clearInlineError() {
			el.$page.find( `.${ INLINE_ERROR_CLASS }` ).remove();
		},

		/**
		 * Whether the current URL asks for a forced cache recompute.
		 *
		 * Read per request rather than cached: `pushDateUrlState()` preserves other query
		 * args, so the URL stays the source of truth for the whole page view.
		 *
		 * @since 4.10.0
		 *
		 * @returns {boolean} True when `force-check=1` is present.
		 */
		isForceCheck() {
			return new URL( window.location.href ).searchParams.get( FORCE_CHECK_PARAM ) === '1';
		},

		/**
		 * Toggle the loader and the page-level busy cursor while a stats request is in
		 * flight.
		 *
		 * @since 4.10.0
		 *
		 * @param {boolean} on True to show the loader, false to clear it.
		 */
		setLoadingState( on ) {
			el.$page.toggleClass( app.classNames.loading, on );

			// WP core's own spinner: hidden until `is-active`.
			el.$page.find( SPINNER_SELECTOR ).toggleClass( 'is-active', on );
		},

		/**
		 * Push the chosen date range onto the URL without a reload.
		 *
		 * @since 4.10.0
		 *
		 * @param {string} date Posted "YYYY-MM-DD - YYYY-MM-DD" range string.
		 */
		pushDateUrlState( date ) {
			const url = new URL( window.location.href );

			// Skip when the URL already names this range. A history-restore refetch is
			// already sitting on the entry it restores, so pushing again would bury that
			// entry and break Back; re-applying the same range twice would stack a
			// duplicate entry the user has to press Back through for no visible change.
			if ( url.searchParams.get( 'date' ) === date ) {
				return;
			}

			url.searchParams.set( 'date', date );
			window.history.pushState( {}, '', url.toString() );
		},
	};

	return updater;
}
