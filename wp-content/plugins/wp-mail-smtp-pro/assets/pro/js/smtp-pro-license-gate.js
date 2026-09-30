/* global wp_mail_smtp_license_gate */
'use strict';

var WPMailSMTP = window.WPMailSMTP || {};
WPMailSMTP.Admin = WPMailSMTP.Admin || {};

/**
 * License gate. Neutralises the Pro settings that need an active license before they can be edited.
 *
 * @since 4.10.0
 */
WPMailSMTP.Admin.LicenseGate = WPMailSMTP.Admin.LicenseGate || ( function( document, window, $ ) {

	/**
	 * Public functions and properties.
	 *
	 * @since 4.10.0
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * Start the engine. DOM is not ready yet, use only to init something.
		 *
		 * @since 4.10.0
		 */
		init: function() {

			// Runs in the head, before the body is parsed, so the veil is on the first paint.
			if ( wp_mail_smtp_license_gate.gates.some( ( gate ) => app.isPageGate( gate ) ) ) {
				$( 'html' ).addClass( 'wpms-license-gate-page' );
			}

			$( app.ready );
		},

		/**
		 * DOM is fully loaded.
		 *
		 * @since 4.10.0
		 */
		ready: function() {

			$.each( wp_mail_smtp_license_gate.gates, ( index, gate ) => {

				// The controls a gate cannot cover, which are better gone than blocked.
				if ( gate.remove && gate.remove.length ) {
					$( gate.remove.join( ', ' ) ).remove();
				}

				$( gate.selector ).each( ( i, element ) => app.close( $( element ), gate ) );
			} );

		},

		/**
		 * Whether a gate covers a whole page's own form, rather than a container inside one.
		 *
		 * @since 4.10.0
		 *
		 * @param {object} gate The gate.
		 *
		 * @returns {boolean} True for a whole page gate.
		 */
		isPageGate: function( gate ) {

			return [ 'page', 'page_card' ].includes( gate.type );
		},

		/**
		 * Veil one gate's region, badge its heading, and swallow every interaction inside it.
		 *
		 * @since 4.10.0
		 *
		 * @param {jQuery} $region The gated container.
		 * @param {object} gate    The gate covering it.
		 */
		close: function( $region, gate ) {

			$region.addClass( 'wpms-license-gate' ).attr( 'aria-disabled', 'true' );

			const $heading = app.findHeading( $region ),
				$section = $heading.closest( '.section-heading' );

			if ( $heading.length ) {

				// The heading names the feature, so it stays legible and usable.
				( $section.length ? $section : $heading ).addClass( 'wpms-license-gate__exempt' );

				// The card already states the ask in full, so those pages get no badge.
				if ( gate.type !== 'page_card' ) {
					$heading.append( app.getBadge() );
				}
			}

			// `mousedown` is what a mouse-driven select popup opens on, and the only one cancelable
			// early enough to stop it: `change` commits the value before it fires.
			$.each( [ 'mousedown', 'click', 'keydown', 'beforeinput', 'paste', 'drop', 'change', 'focusin' ], ( index, eventType ) => {

				// Native, not `.on()`: jQuery has no capture phase, and the guard has to see the
				// event before the control's own handler acts on it.
				$region[ 0 ].addEventListener( eventType, ( event ) => app.guard( event, gate.feature ), true );
			} );
		},

		/**
		 * The badge that marks a gated region as needing an active license.
		 *
		 * Tailwind scans this file, so the classes have to stay one literal.
		 *
		 * @since 4.10.0
		 *
		 * @returns {string} The badge markup.
		 */
		getBadge: function() {

			return '<span class="wpms:ml-[var(--wpms-spacing-10,10px)] wpms:inline-block wpms:align-middle wpms:rounded-[8px] wpms:bg-[#999999] wpms:px-1.5 wpms:py-0.5 wpms:text-[10px] wpms:font-bold wpms:leading-normal wpms:uppercase wpms:text-white wpms-reset">' +
				wp_mail_smtp_license_gate.badge_label + '</span>';
		},

		/**
		 * The heading that names a region's feature.
		 *
		 * @since 4.10.0
		 *
		 * @param {jQuery} $region The gated container.
		 *
		 * @returns {jQuery} The heading, empty when the region has none.
		 */
		findHeading: function( $region ) {

			const $own = $region.find( 'h2' ).first();

			// `prevAll` runs nearest-first, so `first` is the section heading directly above.
			return $own.length ? $own : $region.prevAll( '.section-heading' ).first().find( 'h2' ).first();
		},

		/**
		 * Block the interaction and, on a click, ask for a license key.
		 *
		 * @since 4.10.0
		 *
		 * @param {Event}  event   The intercepted event.
		 * @param {string} feature The gated feature, named in the modal's copy.
		 */
		guard: function( event, feature ) {

			// The heading and the License Key row sit inside the region but above the veil.
			if ( $( event.target ).closest( '.wpms-license-gate__exempt' ).length ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();

			// A page that already shows the License Key card in place does not also pop up a modal.
			if ( event.type === 'click' && ! $( '.wpms-license-gate__key-card' ).length ) {
				app.showModal( feature );
			}
		},

		/**
		 * Ask for a license key in a dialog.
		 *
		 * @since 4.10.0
		 *
		 * @param {string} featureName The gated feature, named in the body copy.
		 */
		showModal: function( featureName ) {

			const template = document.querySelector( '.js-wp-mail-smtp-license-gate-modal' );

			if ( ! template ) {
				return;
			}

			const $card = $( template.content.cloneNode( true ) ).children();

			$card.find( '.wpms-license-key-card__body' ).contents().each( ( index, node ) => {

				// Only the text node carries the token; the Learn more link must keep its markup.
				if ( node.nodeType === Node.TEXT_NODE ) {
					node.nodeValue = node.nodeValue.replace( /%name%/g, featureName );
				}
			} );

			$.alert( {
				backgroundDismiss: true,
				escapeKey: true,
				animationBounce: 1,
				closeIcon: true,
				boxWidth: '550px',
				title: false,
				buttons: false,
				content: $card,
				onOpenBefore: function() {
					this.$body.addClass( 'wp-mail-smtp-license-key-card-dialog' );
				},
				onOpen: function() {
					$card.find( '.js-wp-mail-smtp-license-key' ).trigger( 'focus' );
				}
			} );
		}
	};

	return app;

}( document, window, jQuery ) );

// Initialize.
WPMailSMTP.Admin.LicenseGate.init();
