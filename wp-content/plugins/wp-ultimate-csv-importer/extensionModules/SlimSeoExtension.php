<?php
/******************************************************************************************
 * Copyright (C) Smackcoders. - All Rights Reserved under Smackcoders Proprietary License
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * You can contact Smackcoders at email address info@smackcoders.com.
 *******************************************************************************************/

namespace Smackcoders\FCSV;

if ( ! defined( 'ABSPATH' ) )
    exit; // Exit if accessed directly

class SlimSeoExtension extends ExtensionHandler {
	private static $instance = null;

    public static function getInstance() {

		if (SlimSeoExtension::$instance == null) {
			SlimSeoExtension::$instance = new SlimSeoExtension;
		}
		return SlimSeoExtension::$instance;
    }

	/**
	 * Provides Slim SEO fields for specific import type (post/term/user)
	 * @param string $data - selected import type
	 * @return array - mapping fields
	 */
public function processExtension( $data ) {

$slimseoFields = array(
    'SEO Title'            => 'title',
    'Meta Description'     => 'description',
    'Canonical URL'        => 'canonical',
    'Noindex'              => 'noindex',
    'Facebook Image'       => 'facebook_image',
    'Twitter Image'        => 'twitter_image'
);


    $mapping_array = $this->convert_static_fields_to_array($slimseoFields);

    $response = [];
    $response['slim_seo_fields'] = $mapping_array;

    return $response;
}


	/**
	 * Slim SEO extension supported import types
	 * @param string $import_type - selected import type
	 * @return boolean
	 */
    public function extensionSupportedImportType( $import_type ) {
		$plugin_active = is_plugin_active( 'slim-seo/slim-seo.php' ) || is_plugin_active( 'slim-seo-pro/slim-seo.php' ) || is_plugin_active( 'slim-seo-pro/main.php' );

		if ( ! $plugin_active ) {
			return false;
		}

		if ( $import_type == 'nav_menu_item' ) {
			return false;
		}

		$import_type = $this->import_name_as( $import_type );

		$supported = array(
			'Posts', 'Pages', 'CustomPosts', 'event', 'event-recurring', 'location',
			'WooCommerce', 'WooCommerceattribute', 'WooCommercetags', 'WPeCommerce',
			'Taxonomies', 'Tags', 'Categories', 'Users'
		);

		if ( $import_type == 'ticket' ) {
			if ( is_plugin_active( 'events-manager/events-manager.php' ) ) {
				return false;
			}
			return true;
		}

		return in_array( $import_type, $supported, true );
    }
}
