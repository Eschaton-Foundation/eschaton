<?php
/**
 * WP Ultimate CSV Importer plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\FCSV;

if ( ! defined( 'ABSPATH' ) )
    exit; // Exit if accessed directly

class ZipHandler {

    private static $instance = null;
    private static $smack_csv_instance = null;

    public static function getInstance() {
		if (ZipHandler::$instance == null) {
			ZipHandler::$instance = new ZipHandler;
            ZipHandler::$smack_csv_instance = SmackCSV::getInstance();
			return ZipHandler::$instance;
		}
		return ZipHandler::$instance;
    }


    /**
	 * Extracts zip file.
	 * @param  string $path 
     * @param  string $extract_path 
	 * @return string
	 */
    public function zip_upload($path , $extract_path , $event_key =''){
    
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive;
            $res = $zip->open($path);
            if ($res === TRUE) {
                $response = $this->wp_csv_importer_generate_content($zip, $extract_path,$event_key);
                if($response == "UnSupported File Format"){
                    //rmdir($extract_path);
                    unlink($path);
                }
            } else {
                $response = 'Error Occured while extracting zip file.';
            }
        }else{
            $response = 'ZipArchive class not exists';
        }
       return $response;
    }


    /**
	 * Uploads zip file
	 * @param  string $zip 
     * @param  string $dir 
	 * @return string
	 */
   
    public function wp_csv_importer_generate_content($zip, $dir,$event_key=''){
        $get_upload_dir = wp_upload_dir();
        $supported_formats = array('csv', 'xml', 'txt', 'json');
        $check_for_extracted_files = false;
        for($i = 0; $i < $zip->numFiles; $i++)
        {
            $filterfiles = $zip->getNameIndex($i);

        
            $file_extension = pathinfo($filterfiles, PATHINFO_EXTENSION);
          
            
            if (in_array($file_extension, $supported_formats)){
                $zip->extractTo($dir,$filterfiles);
                chmod($dir , 0777);
                $check_for_extracted_files = true;
            }

          

            if (!empty($event_key)) {
                $files = scandir($dir);
              
            
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                    $old_file_path = $dir . '/' . $file; // Full path of the old file
            
                    if (is_file($old_file_path)) { // Ensure it's a file
                        $file_info = wp_check_filetype($old_file_path); // Check file type
                        $extension = pathinfo($old_file_path, PATHINFO_EXTENSION); // Get file extension
            
                      
                     
                        // Check file type based on extension
                        $allowed_extensions = ['csv', 'xml', 'xlsx', 'xls', 'tsv'];
                        if (in_array(strtolower($extension), $allowed_extensions)) {
                            
                            $random_key = $event_key; // Generate a unique random key
                            $new_file_name = $random_key; // Save file without extension
                            $new_file_path = $dir . '/' . $new_file_name; // Update path
            
                            // Rename the file securely
                            if (rename($old_file_path, $new_file_path)) {
                              //  echo "File renamed successfully: $old_file_path to $new_file_path\n";
                            } else {
                              //  echo "Failed to rename: $old_file_path\n";
                            }
                        }
                    }
                }
            }
            }
            
        }
    
        if($check_for_extracted_files){
            $filesAndFoldersPath = array();
            $zipExtractFolder = $dir;
            $get_upload_dirpath =  $get_upload_dir['basedir'];
            $get_upload_dirurl =  $get_upload_dir['baseurl'];
            $filesList = $this->wp_csv_importer_fetch_all_files($zipExtractFolder);
        
            $content = [];
           
            foreach($filesList as $singleFile){
                $get_file_name = explode('/',$singleFile);
                $c = count($get_file_name);
                $temp_file_name = $get_file_name[$c - 1];
                $file_extension = pathinfo($temp_file_name, PATHINFO_EXTENSION);
                if(empty($file_extension)){
                    $file_extension = 'xml';
                }
                if($file_extension == 'xlsx'){
                    $file_extension = 'csv';                    
                }
                $getFileRealPath = explode($get_upload_dirpath,$singleFile);
                $getFileRealPath = $get_upload_dirurl.$getFileRealPath[1];
            
                $file_names = array("name"=>'' , "path"=>'');
                $file_names['name'] .= $temp_file_name;
                $file_names['path'] .= $getFileRealPath;  
                array_push($content , $file_names);  
            }
        }
        else{
            $content = "UnSupported File Format";
        }
        $zip->close();   
        return $content;
    }


    /**
	 * Fetches all files from zip.
	 * @param  string $dir 
	 * @return string
	 */
    public function wp_csv_importer_fetch_all_files($dir){

        $root = scandir($dir);
        foreach($root as $value)
        {
            if($value === '.' || $value === '..')
                continue;

            if(is_file("$dir/$value"))      {
                $files[] = "$dir/$value";continue;
            }

            foreach($this->wp_csv_importer_fetch_all_files("$dir/$value") as $value)
            {
                $files[] = $value;
            }
        }
        return $files;
    }
}   