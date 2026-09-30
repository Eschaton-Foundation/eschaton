/* global flatpickr */

/**
 * WP Mail SMTP Dashboard date-range control module (Pro).
 *
 * A preset resolves to explicit `Y-m-d` bounds with the same day math Email Reports
 * uses server-side (`Table.php::parse_report_params()`); `custom` opens flatpickr's
 * own range picker instead. Either way the result is handed to `app.updater`, which
 * does the actual request and broadcasts the response; this module only reacts to
 * that broadcast to redraw the graph.
 *
 * @since 4.10.0
 *
 * @param {object} document Document object.
 * @param {object} window   Window object.
 * @param {jQuery} $        jQuery object.
 * @param {object} app      Shared dashboard page app (el, vars, classNames, updater, emailsOverview).
 *
 * @returns {object} Public module API.
 */
export default function( document, window, $, app ) { // eslint-disable-line no-unused-vars, max-lines-per-function
	const local = {
		$select: $(),
		$input:  $(),
	};

	const dateRange = {

		/**
		 * CSS selectors.
		 *
		 * @since 4.10.0
		 *
		 * @type {object}
		 */
		selectors: {
			root:   '.wpms-dashboard-date-range',
			select: '.wpms-dashboard-date-range__select',
			input:  '.wpms-dashboard-date-range__input',
		},

		/**
		 * Bind the control, if present on this render.
		 *
		 * @since 4.10.0
		 */
		init() {
			const $root = $( dateRange.selectors.root );

			if ( ! $root.length ) {
				return;
			}

			local.$select = $root.find( dateRange.selectors.select );
			local.$input = $root.find( dateRange.selectors.input );

			local.$select.on( 'change', dateRange.onSelectChange );

			app.el.$document.on( 'wpMailSmtpDashboardDataReceived', dateRange.onDataReceived );

			// The input arrives carrying a range only when a custom one is already in
			// effect, and flatpickr reads its own selection back out of that value.
			if ( local.$input.val() ) {
				dateRange.createFlatpickr();
			}
		},

		/**
		 * Handle a preset/custom switch: a preset resolves and fetches immediately,
		 * `custom` reveals the range picker and waits for two picked dates instead.
		 *
		 * @since 4.10.0
		 */
		onSelectChange() {
			const value = local.$select.val();

			if ( value === 'custom' ) {
				local.$input.removeClass( app.classNames.hide );
				dateRange.createFlatpickr();
				local.$input.trigger( 'focus' );

				return;
			}

			local.$input.addClass( app.classNames.hide );

			app.updater.fetchStats( dateRange.presetRange( Number( value ) ) );
		},

		/**
		 * Build the `"Y-m-d - Y-m-d"` bounds for a day-count preset: from N days ago
		 * through yesterday, mirroring Email Reports' own server-side conversion.
		 *
		 * @since 4.10.0
		 *
		 * @param {number} days Preset day count.
		 *
		 * @returns {string} Range string.
		 */
		presetRange( days ) {
			const to = new Date();

			to.setDate( to.getDate() - 1 );

			const from = new Date( to );

			from.setDate( from.getDate() - ( days - 1 ) );

			return `${ dateRange.formatDate( from ) } - ${ dateRange.formatDate( to ) }`;
		},

		/**
		 * Format a Date as `Y-m-d` in the viewer's own timezone, not `toISOString()`'s UTC
		 * one, which is a day off for part of every day outside UTC.
		 *
		 * @since 4.10.0
		 *
		 * @param {Date} date Date to format.
		 *
		 * @returns {string} Formatted date.
		 */
		formatDate( date ) {
			const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
			const day = String( date.getDate() ).padStart( 2, '0' );

			return `${ date.getFullYear() }-${ month }-${ day }`;
		},

		/**
		 * Lazily create the flatpickr range picker on the custom-range input. A no-op past
		 * the first call: flatpickr stores its instance on the element itself.
		 *
		 * @since 4.10.0
		 */
		createFlatpickr() {
			const input = local.$input.get( 0 );

			if ( ! input || input._flatpickr || typeof flatpickr === 'undefined' ) {
				return;
			}

			local.$input.flatpickr( {
				mode:       'range',
				dateFormat: 'Y-m-d',
				maxDate:    new Date(),
				locale:     { rangeSeparator: ' - ' },
				onClose( selectedDates ) {
					if ( selectedDates.length === 2 ) {
						app.updater.fetchStats( local.$input.val() );
					}
				},
			} );
		},

		/**
		 * Redraw the Emails Overview chart with the range's fresh series.
		 *
		 * @since 4.10.0
		 *
		 * @param {jQuery.Event} event Custom event.
		 * @param {object}       data  Dashboard `get_stats` response data.
		 */
		onDataReceived( event, data ) {
			if ( ! Array.isArray( data?.series ) || ! app.emailsOverview ) {
				return;
			}

			const canvas = document.querySelector( app.emailsOverview.selector );

			if ( canvas ) {
				app.emailsOverview.draw( canvas, data.series );
			}
		},
	};

	return dateRange;
}
