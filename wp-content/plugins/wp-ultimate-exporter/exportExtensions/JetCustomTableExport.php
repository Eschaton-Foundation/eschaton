<?php
/**
 * WP Ultimate Exporter plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */
namespace Smackcoders\SMEXP;

use Smackcoders\WCSV\WC_Coupon;

if (! defined('ABSPATH'))
    exit; // Exit if accessed directly

/**
 * Class JetCustomTableExport
 * @package Smackcoders\WCSV
 */
class JetCustomTableExport
{
    protected static $instance = null, $mapping_instance, $export_handler, $export_instance, $post_export;
    public $totalRowCount;
    public $plugin;

    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self;
            JetCustomTableExport::$export_instance = ExportExtension::getInstance();
            JetCustomTableExport::$post_export = PostExport::getInstance();
        }
        return self::$instance;
    }

    /**
     * JetBookingExport constructor.
     */
    public function __construct()
    {
        $this->plugin = Plugin::getInstance();
    }

    public function get_custom_table_meta_fields($module,$id, $table_name, $optionalType)
    {
        global $wpdb;
        $meta_fields = jet_engine()->meta_boxes->get_meta_fields_for_object($optionalType);
        $meta_key = $meta_value = [];
        $field_types = [];
        $query = "SELECT * FROM $table_name WHERE object_ID = %d";
        $get_cpt_fields = $wpdb->get_results($wpdb->prepare($query, $id), ARRAY_A);

        $jet_rep_cptfields = [];
        $jet_rep_cpttypes = [];

        // Convert meta fields into an associative array [field_name => type]
        foreach ($meta_fields as $meta) {
            if (isset($meta['name'], $meta['type'])) {
                $field_types[$meta['name']] = $meta['type'];
            }
        }

        foreach ($meta_fields as $meta) {
            $jet_cptfield_names = $meta['name'];
            $jet_field_type = $meta['type'];

            if ($jet_field_type === 'repeater' && isset($meta['repeater-fields'])) {
                foreach ($meta['repeater-fields'] as $rep_field) {
                    if (isset($rep_field['name'], $rep_field['type'])) {
                        $jet_rep_cptfields[$rep_field['name']] = $rep_field['name'];
                        $jet_rep_cpttypes[$rep_field['name']] = $rep_field['type'];
                    }
                }
            }
        }

        // Store repeater fields in the export instance
        $jet_rep_cptfields = !empty($jet_rep_cptfields) ? $jet_rep_cptfields : [];
        $jet_rep_cpttypes = !empty($jet_rep_cpttypes) ? $jet_rep_cpttypes : [];
        foreach ($meta_fields as $meta) {
            if (isset($meta['name'], $meta['type'])) {
                $field_types[$meta['name']] = $meta['type'];
            }
        }
        // Process and export data
        foreach ($get_cpt_fields as $row) {
            foreach ($row as $meta_key => $meta_value) {
                if (isset($field_types[$meta_key])) {
                    $field_type = $field_types[$meta_key];
                    if ($field_type === 'checkbox') {
                        $checkValue = unserialize($meta_value);
                        $check = '';
                        foreach($checkValue as $checkkey => $metvalue){
                            if(is_numeric($checkkey)){
                                $check .= $metvalue.',';	
                                $rcheck = substr($check,0,-1);
                                JetCustomTableExport::$export_instance->data[$id][ $meta_key ] = $rcheck;
                            }
                            else{
                                if($metvalue == 'true'){
    
                                    $exp_value[] = $checkkey;
                                }
                                if(isset($exp_value) && is_array($exp_value)){
                                    $meta_value = implode(',',$exp_value );
                                }
                                JetCustomTableExport::$export_instance->data[$id][ $meta_key ] = $meta_value;
                            }     
                        }
                    }
                    elseif ($field_type === 'gallery') {
                        $gallery= explode(',',$meta_value);
                        foreach($gallery as $gallerykey => $galleryval){
                            if(is_numeric($galleryval)){
                                 $galleries[] = wp_get_attachment_url( $galleryval );
                            }
                            elseif(is_serialized($galleryval)){
                                $gal_value=unserialize($galleryval);
                                foreach($gal_value as $key=>$gal_val){
                                    if(is_array($gal_val)){
                                        $galleries[] = $gal_val['url'];
                                    }
                                    else{
                                        $galleries[] = $gal_val;
                                    }
                                    
                                }	
                            }
                            else{
                                $galleries[] = $galleryval;
                            }
                            if(is_array($galleries)){
                                $meta_value =!empty($galleries) ? implode(',',$galleries ) : '';	
                            }
                            JetCustomTableExport::$export_instance->data[$id][ $meta_key ] = $meta_value;
                        }
                    } 
                    elseif ($field_type === 'media') {
                        $array_val= $meta_value;					
                        if(is_numeric($array_val)){
                            $meta_value = wp_get_attachment_url( $array_val );
                        }
                        elseif(is_serialized($array_val)){
                            $media_value=unserialize($array_val);
                            $meta_value = array_key_exists('url',$media_value) ? $media_value['url'] : "";	
                            
                        }
                        else{
                            $meta_value=$array_val;
                        }
                        JetCustomTableExport::$export_instance->data[$id][$meta_key] = $meta_value;
                    }
                    elseif ($field_type === 'select') {
                        if(is_serialized($meta_value)){
                            $meta_value = unserialize($meta_value);
                            foreach($meta_value as $metkey => $metselectvalue){
                                $select[] = $metselectvalue;
                                $meta_value = !empty($select) ? implode(',',$select ) : '';	
                                JetCustomTableExport::$export_instance->data[$id][ $meta_key ] = $meta_value;
                            }						
                        }
                        else{
                            JetCustomTableExport::$export_instance->data[$id][ $meta_key ] = $meta_value;						
                        }
                    }
                    elseif($field_type === 'posts') {
                        if(is_serialized($meta_value)){
                            $meta_value = unserialize($meta_value);
                            foreach($meta_value as $postkey => $metpostvalue){
                                if(is_numeric($metpostvalue)){
                                    $title = $wpdb->get_row($wpdb->prepare("select post_title from {$wpdb->prefix}posts where ID = %d",$metpostvalue));
                                    $test[] = $title->post_title;
                                }
                            }
                            $meta_value = !empty($test) ? implode(',',$test ) : '';			
                            JetCustomTableExport::$export_instance->data[$id][ $meta_key ] = $meta_value;	
                        }
                        else{
                            $post_value = $meta_value;
                            $post_title = $wpdb->get_var("SELECT post_title FROM {$wpdb->prefix}posts WHERE ID = $post_value");
                            JetCustomTableExport::$export_instance->data[$id][ $meta_key ] = $post_title;	
                        }
                    }
                    elseif($field_type == 'date'){
                        if(!empty($meta_value)){
                            if(strpos($meta_value, '-') !== FALSE){
                            }else{
                                if(is_numeric($meta_value)){
                                    $meta_value = date('Y-m-d', $meta_value);
                                }
                            }
                        }
                        JetCustomTableExport::$export_instance->data[$id][ $meta_key ] = $meta_value;
                    }
                    elseif($field_type == 'datetime-local'){
                        if(!empty($meta_value)){
                            if(strpos($meta_value, '-') !== FALSE){
                            }else{
                                $meta_value = date('Y-m-d H:i', $meta_value);
                            }
                            $meta_value = str_replace(' ', 'T', $meta_value);
                        }
                        JetCustomTableExport::$export_instance->data[$id][ $meta_key ] = $meta_value;
                    }
                    elseif($field_type == 'repeater'){
                        foreach ($get_cpt_fields as $row) {
                            foreach ($row as $meta_key => $meta_value) {
                                if (!isset($field_types[$meta_key])) continue;
                        
                                $field_type = $field_types[$meta_key];
                        
                                if ($field_type === 'repeater') {
                                    $unser = @unserialize($meta_value);
                                    if (!is_array($unser)) continue;
                        
                                    $collected_values = [];
                        
                                    foreach ($unser as $idkey => $array) {
                                        if (!empty($array)) {
                                            foreach ($array as $array_key => $array_val) {
                                                if (isset($jet_rep_cpttypes[$array_key]) && $jet_rep_cpttypes[$array_key] === 'text') {
                                                    $collected_values[$array_key][] = $array_val;
                                                }
                                                else if(isset($jet_rep_cpttypes[$array_key]) && $jet_rep_cpttypes[$array_key] === 'checkbox') {
                                                   // foreach($array_val as $arrval){
                                                        $exp_value = [];
                                                    
                                                        foreach($array_val as $key => $metvalue){
                                                            if($metvalue == 'true'){
                                                                $exp_value[] = $key;
                                                                
                                                            }
                                                            
                                                        }
                                                        $collected_values[$array_key][] = implode(',',$exp_value );	
                                                }
                                                else if(isset($jet_rep_cpttypes[$array_key]) && $jet_rep_cpttypes[$array_key] === 'media') {
                                                    $medias = [];
                                                        if(is_numeric($array_val)){
                                                            $medias[] = wp_get_attachment_url($array_val);
                                                        }
                                                        elseif(is_array($array_val)){
                                                            $medias[] = $array_val['url'];	
                                                            
                                                        }
                                                        else{
                                                            $medias[]=$array_val;
                                                        }
                                                    $collected_values[$array_key][] = implode('|',$medias );
                                                }
                                                else if(isset($jet_rep_cpttypes[$array_key]) && $jet_rep_cpttypes[$array_key] === 'gallery'){
                                                    $galleries =[];
												
                                                    if(is_array($array_val)){
                                                        foreach($array_val as $key => $gallryvalue){
                                                            $galleries[] = $gallryvalue['url'];
                                                        }
                                                        
                                                    }
                                                    else{
                                                        $gallery= explode(',',$array_val);
                                                        foreach($gallery as $gallerykey => $galleryval){
                                                            if(is_numeric($galleryval)){
                                                                $galleries[] = wp_get_attachment_url($galleryval);
                                                            }
                                                            else{
                                                                $galleries[]=$galleryval;
                                                            }
                                                            
                                                            
                                                        }
                                                    }
                                                    $collected_values[$array_key][] = implode(',',$galleries );
                                                }
                                                else if(isset($jet_rep_cpttypes[$array_key]) && $jet_rep_cpttypes[$array_key] === 'posts'){
                                                    $test =[];
                                                    if(is_array($array_val)){
                                                        
                                                        foreach($array_val as $postkey => $metpostvalue){
                                                            if(is_numeric($metpostvalue)){
                                                                $title = $wpdb->get_row($wpdb->prepare("select post_title from {$wpdb->prefix}posts where ID = %d ORDER BY ID DESC",$metpostvalue));
                                                                $test[] = $title->post_title;

                                                            }	
                                                                
                                                        }
                                                        $collected_values[$array_key][] = implode(',',$test );
                                                        
                                                    }
                                                    else{
                                                    
                                                        if(is_numeric($array_val)){
                                                            $title = $wpdb->get_row($wpdb->prepare("select post_title from {$wpdb->prefix}posts where ID = %d ORDER BY ID DESC",$array_val));
                                                            $testing = $title->post_title;
                                                        }
                                                        $collected_values[$array_key][] = $testing;
                                                    }
                                                }
                                                else if(isset($jet_rep_cpttypes[$array_key]) && $jet_rep_cpttypes[$array_key] === 'select'){
                                                    if(is_array($array_val)){
                                                        $select =[];
                                                        foreach($array_val as $metselectvalue){
                                                            $select[] = $metselectvalue;
                                                            $collected_values[$array_key][] = implode(',',$select );
                                                        }
                                                    }
                                                    else{
                                                        $collected_values[$array_key][]= $array_val;
                                                    }    
                                                }
                                                else if(isset($jet_rep_cpttypes[$array_key]) && $jet_rep_cpttypes[$array_key] === 'date'){
                                                    $repdateval = array();
                                                        if(!empty($array_val)){
                                                            $repdateval[] = $array_val;
                                                        }else{
                                                            $repdateval[] = "";
                                                        }
                                                    if(!empty($repdateval)){
                                                        $collected_values[$array_key][] = implode('|',$repdateval);
                                                    }
                                                }
                                                else if(isset($jet_rep_cpttypes[$array_key]) && $jet_rep_cpttypes[$array_key] === 'datetime-local'){
                                                    $repdateval = array();
                                                    if(!empty($array_val)){
                                                        $timestamp = strtotime($array_val); // Convert to Unix timestamp                                                      
                                                        $arrval = date('Y-m-d H:i', $timestamp); // Format the date properly
                                                        
                                                        $repdateval[] = $arrval;
                                                    }else{
                                                        $repdateval[] = "";
                                                    }
                                                if(!empty($repdateval)){
                                                    $collected_values[$array_key][] = implode('|',$repdateval);
                                                }
                                                }
                                                // Process other fields 
                                            }
                                        }
                                    }
                                    foreach ($collected_values as $key => $values) {
                                        JetCustomTableExport::$export_instance->data[$id][$key] = implode('|', $values);
                                    }
                                }
                            }
                        }
                        
                    }
                    else if (is_object($meta_value) && isset($meta_value)) {    
                        // Check if $meta_value is a JSON string
                        if (is_string($meta_value) && json_decode($meta_value)) {
                            $meta_value = json_decode($meta_value, true);
                        }
    
                        $is_unserialized = is_array($meta_value);
    
                        if ($is_unserialized) {
                            $output_array = [];
    
                            foreach ($meta_value as $key => $val) {
                                // If the value is an array (like 'week_days'), use '|' as a separator
                                if (is_array($val)) {
                                    $output_array[] = implode('|', $val); // Use '|' for arrays
                                } else {
                                    // Otherwise, just add the value as is
                                    $output_array[] = $val;
                                }
                            }
    
                            // Join values with commas for CSV format
                            $value_all = implode(',', $output_array);
    
                            JetCustomTableExport::$export_instance->data[$id][$meta_key] = $value_all;
                        }
                    }
                    else {
                        JetCustomTableExport::$export_instance->data[$id][$meta_key] = $meta_value; // Default case
                    }
                }
            }
        }
    }
}