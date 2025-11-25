<?php
/**
 * WP Ultimate CSV Importer plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\FCSV;
 
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class EPOExtension extends ExtensionHandler{
    private static $instance = null;

    public static function getInstance() {
        if (EPOExtension::$instance === null) {
            EPOExtension::$instance = new EPOExtension();
        }
        return EPOExtension::$instance;
    }

    /**
     * Provides EPO Meta fields for specific post type
     * @param string $data - selected import type
     * @return array - mapping fields
     */
    public function processExtension($data) {
        // Fetch the option data
        $option_data = get_option('thwepof_custom_sections');

        // Unserialize the data safely
        $unserialized_data = maybe_unserialize($option_data);

        $pro_meta_fields = []; // Initialize the variable

        // Validate the structure of the unserialized data
        if (is_array($unserialized_data) && isset($unserialized_data['default']) && is_object($unserialized_data['default']) && isset($unserialized_data['default']->fields)) {
            foreach ($unserialized_data['default']->fields as $field_key => $field_object) {
                // Collect the field keys
                $pro_meta_fields[$field_key] = $field_key;
            }
        }

        $pro_meta_fields = $this->convert_static_fields_to_array($pro_meta_fields);

        // Prepare the response
		$response['epo_meta_fields'] = $pro_meta_fields;
        return $response;
    }

    /**
     * EPO Meta extension supported import types
     * @param string $import_type - selected import type
     * @return boolean
     */
    public function extensionSupportedImportType($import_type) {
        if (is_plugin_active('woo-extra-product-options/woo-extra-product-options.php')) {


            $import_type = $this->import_name_as($import_type);

            if ($import_type === 'WooCommerceOrders') {
                return true;
            }
            else {
                return false;
            }
        }

        return false;
    }

}

