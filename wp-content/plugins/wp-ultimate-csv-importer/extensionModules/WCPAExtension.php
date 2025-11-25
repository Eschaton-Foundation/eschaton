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
class WCPAExtension extends ExtensionHandler {
    private static $instance = null;

    public static function getInstance() {
        if (WCPAExtension::$instance === null) {
            WCPAExtension::$instance = new WCPAExtension();
        }
        return WCPAExtension::$instance;
    }

    /**
     * Provides WCPA Meta fields for specific post type
     * @param string $data - selected import type
     * @return array - mapping fields
     */
    public function processExtension($data) {
        global $wpdb;
    
        // Query to fetch meta values from wp_postmeta with meta key '_wcpa_fb_json_data'
        $meta_results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = %s",
                '_wcpa_fb_json_data'
            ),
            ARRAY_A
        );
    
        // Initialize the headers array
        $headers = [];
    
        // Validate and process the meta results
        if (!empty($meta_results)) {
            foreach ($meta_results as $meta) {
                if (!empty($meta['meta_value'])) {
                    $decoded_meta = json_decode($meta['meta_value'], true); // Decode JSON data
                    if (is_array($decoded_meta)) {
                        foreach ($decoded_meta as $section) {
                            if (isset($section['fields']) && is_array($section['fields'])) {
                                foreach ($section['fields'] as $fieldGroup) {
                                    foreach ($fieldGroup as $field) {
                                        if (isset($field['label']) && !empty($field['label'])) {
                                            $headers[] = $field['label'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    
        // Prepare the response in the required format
        $response = [];
        foreach ($headers as $header) {
            $response[] = [
                'label' => $header,
                'name'  => sanitize_title($header), // Generate a machine-friendly name
            ];
        }    
        // Return the headers
        return ['wcpa_meta_fields' => $response];
    }
    
    /**
     * WCPA Meta extension supported import types
     * @param string $import_type - selected import type
     * @return boolean
     */
    public function extensionSupportedImportType($import_type) {
        if (is_plugin_active('woo-custom-product-addons/start.php')) {
            $import_type = $this->import_name_as($import_type);

            if ($import_type === 'WooCommerceOrders') {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }
}
