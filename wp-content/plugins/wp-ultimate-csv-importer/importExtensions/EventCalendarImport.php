<?php

/******************************************************************************************
 * Copyright (C) Smackcoders. - All Rights Reserved under Smackcoders Proprietary License
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * You can contact Smackcoders at email address info@smackcoders.com.
 *******************************************************************************************/

namespace Smackcoders\FCSV;

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

class EventCalendarImport
{
    private static $events_instance = null;

    public static function getInstance()
    {

        if (EventCalendarImport::$events_instance == null) {
            EventCalendarImport::$events_instance = new EventCalendarImport;
            return EventCalendarImport::$events_instance;
        }
        return EventCalendarImport::$events_instance;
    }

    public function set_events_values($header_array, $value_array, $map, $post_id, $type, $mode, $term_map, $gmode)
    {
        $post_values = [];
        $helpers_instance = ImportHelpers::getInstance();
        $post_values = $helpers_instance->get_header_values($map, $header_array, $value_array);
        $this->events_manager_import($post_values, $post_id, $mode, $type, $header_array, $value_array, $term_map, $gmode);
    }

    public function events_manager_import($data_array, $pID, $mode, $type, $header_array, $value_array, $term_map, $gmode)
    {
        if (empty($pID) || $type !== 'tribe_events') {
            return;
        }

        foreach ($data_array as $meta_key => $meta_value) {
            // Only save if it starts with "_Event" or is special meta
            if (strpos($meta_key, '_Event') === 0 || in_array($meta_key, ['_EventOrigin', '_wp_page_template', '_edit_last'])) {
                if ($meta_value !== '' && $meta_value !== null) {
                    update_post_meta($pID, $meta_key, sanitize_text_field($meta_value));
                }
            }
        }

        if (!empty($data_array['_EventStartDate']) && !empty($data_array['_EventEndDate'])) {
            $start = strtotime($data_array['_EventStartDate']);
            $end   = strtotime($data_array['_EventEndDate']);
            if ($start && $end && $end > $start) {
                $duration = $end - $start;
                update_post_meta($pID, '_EventDuration', $duration);
            }
        }

        if (!empty($term_map)) {
            foreach ($term_map as $taxonomy => $mapped_field) {
                if (!empty($data_array[$mapped_field])) {
                    wp_set_post_terms($pID, $data_array[$mapped_field], $taxonomy, false);
                }
            }
        }

    }
}
