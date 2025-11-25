<?php
/**
 * WP Ultimate CSV Importer plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\FCSV;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class JetReviewsImport
{
    private static $instance = null, $media_instance;

    public static function getInstance()
    {
        if (JetReviewsImport::$instance == null) {
            JetReviewsImport::$instance = new JetReviewsImport;
            JetReviewsImport::$media_instance = MediaHandling::getInstance();
        }
        return JetReviewsImport::$instance;
    }

    function set_jet_reviews_values($post_values , $mode ,$unikey , $unikey_name, $line_number,$update_based_on,$check)
    {

        global $wpdb,$core_instance;
        $helpers_instance = ImportHelpers::getInstance();
        $core_instance = CoreFieldsImport::getInstance();
        $log_table_name = $wpdb->prefix . "import_detail_log";
        $updated_row_counts = $helpers_instance->update_count($unikey, $unikey_name);
        $created_count = $updated_row_counts['created'];
        $updated_count = $updated_row_counts['updated'];
        $skipped_count = $updated_row_counts['skipped'];

        if ($mode == 'Insert' && !empty($post_values)) {
            $result = $this->insert_jet_reviews_data($post_values, $wpdb, $core_instance, $helpers_instance, $created_count, $updated_count, $skipped_count, $log_table_name, $mode, $unikey, $unikey_name, $line_number, $update_based_on, $check);
        } else if ($mode == 'Update' && !empty($post_values)) {
            $result = $this->update_jet_reviews_data($post_values, $wpdb, $core_instance, $helpers_instance, $created_count, $updated_count, $skipped_count, $log_table_name, $mode, $unikey, $unikey_name, $line_number, $update_based_on, $check);
        }
        return $result;

    }
    public function insert_jet_reviews_data($data_array, $wpdb, $core_instance, $helpers_instance, $created_count, $updated_count, $skipped_count, $log_table_name, $mode, $unikey, $unikey_name, $line_number, $update_based_on, $check)
    {
        if (isset($data_array['ID'])) {
            unset($data_array['ID']);
        }
		$table_name = jet_reviews()->db->tables( 'reviews', 'name' );
		$query = jet_reviews()->db->wpdb()->insert( $table_name, $data_array );
		if ( ! $query ) {
            $wpdb = jet_reviews()->db->wpdb();
            $error_message = $wpdb->last_error;
            $core_instance->detailed_log[$line_number]['Message'] = ' Skipped ' .$error_message;
            $core_instance->detailed_log[$line_number]['state'] = 'Skipped';
            $wpdb->get_results("UPDATE $log_table_name SET skipped = $skipped_count WHERE $unikey_name = '$unikey'");
            return array('MODE' => $mode, 'ERROR_MSG' => 'No data to update');
		}

		$review_id = jet_reviews()->db->wpdb()->insert_id;  //Inserted

        $mode_of_affect = 'Inserted';
        $core_instance->detailed_log[$line_number]['state'] = 'Inserted';
        $wpdb->get_results("UPDATE $log_table_name SET created = $created_count WHERE $unikey_name = '$unikey'");
        $returnArr['ID'] = $review_id;
        $returnArr['MODE'] = $mode_of_affect;
        return $returnArr;
    }
    public function update_jet_reviews_data($data_array, $wpdb, $core_instance, $helpers_instance, $created_count, $updated_count, $skipped_count, $log_table_name, $mode, $unikey, $unikey_name, $line_number, $update_based_on, $check)
    {
        $review_id = $data_array['ID'];
        if (isset($data_array['ID'])) {
            unset($data_array['ID']);
        }
        $table_name = jet_reviews()->db->tables( 'reviews', 'name' );
        $existing_entry = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE id = %d",$review_id));

        /**Update */
        if(!empty( $review_id ) && $existing_entry > 0){ 
            $table_name = jet_reviews()->db->tables( 'reviews', 'name' );    
            $query = jet_reviews()->db->wpdb()->update(
                $table_name,
                $data_array,
                array(
                    'id' => $review_id,
                )
            );
            $mode_of_affect = 'Updated';
            $core_instance->detailed_log[$line_number]['state'] = 'Updated';
            $wpdb->get_results("UPDATE $log_table_name SET updated = $updated_count WHERE $unikey_name = '$unikey'");
            $returnArr['ID'] = $review_id;
            $returnArr['MODE'] = $mode_of_affect;
            return $returnArr;

        }
        /**Insert */
        else{ 
            $query = jet_reviews()->db->wpdb()->insert( $table_name, $data_array );
            $review_id = jet_reviews()->db->wpdb()->insert_id;
            $mode_of_affect = 'Inserted';
            $core_instance->detailed_log[$line_number]['state'] = 'Inserted';
            $wpdb->get_results("UPDATE $log_table_name SET created = $created_count WHERE $unikey_name = '$unikey'");
            $returnArr['ID'] = $review_id;
            $returnArr['MODE'] = $mode_of_affect;
            return $returnArr;
        }
    }
}
