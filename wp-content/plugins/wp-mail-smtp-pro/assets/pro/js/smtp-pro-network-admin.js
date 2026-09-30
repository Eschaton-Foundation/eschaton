/* globals wp_mail_smtp */
'use strict';

var WPMailSMTP = window.WPMailSMTP || {};
WPMailSMTP.Admin = WPMailSMTP.Admin || {};

/**
 * WP Mail SMTP Network Admin area module.
 *
 * @since 3.1.0
 */
WPMailSMTP.Admin.Network = WPMailSMTP.Admin.Network || ( function( document, window, $ ) {

	/**
	 * Public functions and properties.
	 *
	 * @since 3.1.0
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * Start the engine. DOM is not ready yet, use only to init something.
		 *
		 * @since 3.1.0
		 */
		init: function() {

			// Do that when DOM is ready.
			$( app.ready );
		},

		/**
		 * DOM is fully loaded.
		 *
		 * @since 3.1.0
		 */
		ready: function() {

			app.bindActions();
			app.markNetworkAdminRequests();

			if ( wp_mail_smtp.network_subsite_mode === '1' ) {
				app.initSubsiteMode();
			}
		},

		/**
		 * Tag every plugin ajax request with the admin it came from.
		 *
		 * This script only loads in the network admin, where `is_network_admin()` no longer
		 * answers once the request reaches admin-ajax.php. Subsite-mode pages add which site
		 * the network admin is looking at on top.
		 *
		 * @since 4.10.0
		 */
		markNetworkAdminRequests: function() {

			var params = '&network_admin=1';

			if ( wp_mail_smtp.network_subsite_mode === '1' ) {
				params += '&network_admin_subsite_related_request=1';
			}

			$.ajaxSetup( {
				beforeSend: function( jqXHR, s ) {
					if ( s.type === 'GET' && s.url.indexOf( 'action=wp_mail_smtp_' ) !== -1 ) {
						s.url = s.url + params;
					} else if ( s.type === 'POST' && typeof s.data === 'string' && s.data.indexOf( 'action=wp_mail_smtp_' ) !== -1 ) {
						s.data = s.data + params;
					}
				}
			} );
		},

		/**
		 * Process all generic actions/events.
		 *
		 * @since 3.1.0
		 */
		bindActions: function() {

			// Open email log edit links in new tab.
			$( 'body.network-admin .wp-mail-smtp-page-logs-archive' )
				.on( 'click', '.column-subject a[href*="mode=view"]', app.openLinkInNewTab );
		},

		/**
		 * Initialize network admin subsite related functionality.
		 *
		 * @since 3.1.0
		 */
		initSubsiteMode: function() {

			// Site selector select.
			var $siteSelector = $( '.wp-mail-smtp-network-admin-site-selector' );

			// Submit form on site selector change.
			$siteSelector.on( 'change', function() {
				$( this ).closest( 'form' ).submit();
			} );

			// Initialize site selector field.
			$siteSelector.select2( {
				dropdownCssClass: 'wp-mail-smtp-select2-dropdown',
				cacheDataSource: {},
				dataAdapter: $.fn.select2.amd.require( 'select2/data/cacheableAjax' ),
				ajax: {
					url: wp_mail_smtp.ajax_url + '?action=wp_mail_smtp_pro_get_sites_ajax&nonce=' + wp_mail_smtp.nonce,
					dataType: 'json'
				},
			} );
		},

		/**
		 * Open link in new tab.
		 *
		 * @since 3.1.0
		 *
		 * @param {object} event jQuery event.
		 */
		openLinkInNewTab: function( event ) {

			event.preventDefault();
			window.open( $( this ).attr( 'href' ) );
		}
	};

	// Provide access to public functions/properties.
	return app;
}( document, window, jQuery ) );

// Initialize.
WPMailSMTP.Admin.Network.init();
