<?php

namespace WPMailSMTP\Pro\Admin\SetupWizard;

/**
 * Pro side of the hosted setup wizard.
 *
 * @since 4.10.0
 */
class Hosted {

	/**
	 * REST API instance.
	 *
	 * @since 4.10.0
	 *
	 * @var RestApi
	 */
	private $api;

	/**
	 * Redirect bridge instance.
	 *
	 * @since 4.10.0
	 *
	 * @var RedirectBridge
	 */
	private $bridge;

	/**
	 * Constructor.
	 *
	 * @since 4.10.0
	 */
	public function __construct() {

		$this->api    = new RestApi();
		$this->bridge = new RedirectBridge();
	}

	/**
	 * Register hooks.
	 *
	 * @since 4.10.0
	 */
	public function hooks() {

		$this->api->hooks();
		$this->bridge->hooks();
	}
}
