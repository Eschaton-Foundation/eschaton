<?php

/**
 * WP Ultimate CSV Importer plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\FCSV;

if (! defined('ABSPATH'))
	exit; // Exit if accessed directly

class EventCalendarExtension extends ExtensionHandler
{
	private static $instance = null;

	public static function getInstance()
	{

		if (EventCalendarExtension::$instance == null) {
			EventCalendarExtension::$instance = new EventCalendarExtension;
		}
		return EventCalendarExtension::$instance;
	}

	/**
	 * Provides PPOM Meta fields for specific post type
	 * @param string $data - selected import type
	 * @return array - mapping fields
	 */


	public function processExtension($data)
	{
		$response = [];

		if ($data === 'tribe_events') {
			$events_manager_Fields = array(
				'EventOrigin' => '_EventOrigin',
				'edit_last' => '_edit_last',
				'post_title' => 'post_title',
				'post_status' => 'post_status',
				'wp_page_template' => '_wp_page_template',
				'post_content' => 'post_content',
				'post_excerpt' => 'post_excerpt',
				'EventShowMapLink' => '_EventShowMapLink',
				'EventShowMap' => '_EventShowMap',
				'EventVenueID' => '_EventVenueID',
				'EventOrganizerID' => '_EventOrganizerID',
				'EventAllDay' => '_EventAllDay',
				'EventStartDate' => '_EventStartDate',
				'EventEndDate' => '_EventEndDate',
				'EventStartDateUTC' => '_EventStartDateUTC',
				'EventEndDateUTC' => '_EventEndDateUTC',
				'EventCurrencySymbol' => '_EventCurrencySymbol',
				'EventCurrencyCode' => '_EventCurrencyCode',
				'EventCurrencyPosition' => '_EventCurrencyPosition',
				'EventCost' => '_EventCost',
				'EventURL' => '_EventURL',
				'EventTimezone' => '_EventTimezone',
				'EventTimezoneAbbr' => '_EventTimezoneAbbr',
			);

			$events_manager_values = $this->convert_static_fields_to_array($events_manager_Fields);

			$response['events_manager_fields'] = $events_manager_values;

		}

		return $response;
	}

	/**
	 * PPOM Meta extension supported import types
	 * @param string $import_type - selected import type
	 * @return boolean
	 */
	public function extensionSupportedImportType($import_type)
	{
		if (is_plugin_active('the-events-calendar/the-events-calendar.php')) {
			if ($import_type == 'nav_menu_item') {
				return false;
			}

			// $import_type = $this->import_name_as($import_type);

			if ($import_type == 'tribe_events') {
				return true;
			} else {
				return false;
			}
		}
	}
}
