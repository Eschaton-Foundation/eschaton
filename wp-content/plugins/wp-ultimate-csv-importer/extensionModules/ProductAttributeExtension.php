<?php
/**
 * WP Ultimate CSV Importer plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\FCSV;

if ( ! defined( 'ABSPATH' ) )
    exit; // Exit if accessed directly

class ProductAttributeExtension extends ExtensionHandler{
	private static $instance = null;

    public static function getInstance() {	
		if (ProductAttributeExtension::$instance == null) {
			ProductAttributeExtension::$instance = new ProductAttributeExtension;
		}
		return ProductAttributeExtension::$instance;
    }
	public function processExtension($data,$process_type=null) {
        $response = [];
		$import_type = $data;
		$import_type = $this->import_type_as($import_type);
		$importas = $this->import_post_types($import_type);	
		$taxonomies = get_object_taxonomies( $importas, 'names' );
		$count = 0;
		$product_statuses = array('publish', 'draft', 'future', 'private', 'pending');
		$products = [];
		$page = 1;
		$limit = 100;  // Set limit per page
		
		$variable_product_ids = []; // Initialize variable product ids array

    // Loop through products with pagination
		while ($page) {
			$paged_products = wc_get_products([
				'status' => $product_statuses,
				'limit' => $limit,
				'page' => $page, // Paginate by page number
			]);
			
			if (empty($paged_products)) {
				break; // Exit loop if no products are found
			}

			// Process each product
			foreach ($paged_products as $product) {
				$product_all_attributes = $product->get_attributes();
				$prod_attribute_name = [];
				foreach ($product_all_attributes as $attribute_key => $attribute) {
					$prod_attribute_name[] = str_replace('pa_', '', $attribute_key);
				}
				$prod_attribute_count = count($prod_attribute_name);
				if ($prod_attribute_count > $count) {
					$count = $prod_attribute_count;
				}
				if ($product->is_type('variable')) {
					$variable_product_ids[] = $product->get_id();
				}
			}

			$page++; // Move to the next page
		}
		$variation_id = [];
		foreach($variable_product_ids as $variable_product_id){
		
			if(!empty($variable_product_id)){
				$variable_product = wc_get_product($variable_product_id);
				$variation_id [] = $variable_product->get_children();
			}
			
		} 
		foreach($variation_id as $variation_ids){
			if(!empty($variation_ids)){
				$data = wc_get_product($variation_ids[0]);
				$product_attributes=$data->get_attributes();
				$attribute_name = array();  
				foreach ($product_attributes as $attribute_key => $attribute) {
					$attribute_name[]= str_replace('pa_', '',$attribute_key);	
				}
				$attribute_count = count($attribute_name);
				if($attribute_count > $count){
					$count = $attribute_count;
				}
			}
			
		}
		if($count == 0){
            $count =1;
        }

	if($process_type == 'Export'){
		$pro_attr_fields =array();
		for($i=1; $i<=$count;$i++){
			$pro_attr_fields += array(
				'Product Attribute Name' . $i => 'product_attribute_name' . $i,
				'Product Attribute Value' . $i => 'product_attribute_value' . $i,
				'Product Attribute Visible' . $i => 'product_attribute_visible' . $i
			);
		}
		$pro_attr_fields_line = $this->convert_static_fields_to_array($pro_attr_fields);
		$response['product_attr_fields'] = $pro_attr_fields_line; 
	}
	else{
		$pro_attr_fields =array();
		for($i=1; $i<=$count;$i++){
			$pro_attr_fields[]= $this->convert_static_fields_to_array(array(
				"Product Attribute Name$i" => "product_attribute_name$i",
					"Product Attribute Value$i" => "product_attribute_value$i",
					"Product Attribute Visible$i" => "product_attribute_visible$i",
			));
		}
		$response['product_attr_fields'] = $pro_attr_fields; 
	}
		$pro_attr_fields_line = $this->convert_static_fields_to_array($pro_attr_fields);
		$response['product_attr_fields'] = $pro_attr_fields_line; 
		return $response;	
	}
	
	/**
	* Product Attribute extension supported import types
	* @param string $import_type - selected import type
	* @return boolean
	*/
	public function extensionSupportedImportType($import_type){
		if(is_plugin_active('woocommerce/woocommerce.php')){
			$import_type = $this->import_name_as($import_type);
			if($import_type == 'WooCommerce') { 
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
}