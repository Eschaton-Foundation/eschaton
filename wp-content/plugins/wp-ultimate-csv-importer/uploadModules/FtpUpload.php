<?php
/**
 * WP Ultimate CSV Importer plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\FCSV;
require_once(__DIR__.'/../vendor/autoload.php');

use League\Csv\Writer;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

if ( ! defined( 'ABSPATH' ) )
    exit; // Exit if accessed directly

class FtpUpload implements Uploads{

    private static $instance = null;
    private static $smack_csv_instance = null;

    private function __construct(){
            add_action('wp_ajax_get_ftp_url',array($this,'upload_function'));
            add_action('wp_ajax_get_ftp_details',array($this,'getFtpDetails'));     
    }

    public static function getInstance() {
        if (FtpUpload::$instance == null) {
            FtpUpload::$instance = new FtpUpload;
            FtpUpload::$smack_csv_instance = SmackCSV::getInstance();
            return FtpUpload::$instance;
        }
        return FtpUpload::$instance;
    }

    function convertXlsxToCsv($path, $upload_dir_path,$event_key)
	{
        $spreadsheet = IOFactory::load($path);
		$csv_file_path = $upload_dir_path . '/' . $event_key;
		$csv_writer = new Csv($spreadsheet);
		$csv_writer->setDelimiter(',');
		$csv_writer->setEnclosure('"');
		$csv_writer->setLineEnding("\r\n");
		$csv_writer->setIncludeSeparatorLine(false);
		$csv_writer->save($csv_file_path);
	}

    /**
     * Security check for FTP operations
     * Validates nonce and user capabilities
     */
    private function check_ftp_security() {
        // Security: Check nonce for CSRF protection using wp_verify_nonce
        if (!wp_verify_nonce($_POST['securekey'], 'smack-ultimate-csv-importer')) {
            wp_die(__('Security check failed'));
        }
        
        // Security: Check user capabilities - only administrators should access FTP functionality
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
    }

    /**
	 * Upload file from FTP.
	 */
    public function upload_function(){
        $this->check_ftp_security();
        
        $host_name = sanitize_text_field($_POST['HostName']);
        $host_port = isset($_POST['HostPort']) ? intval(sanitize_text_field($_POST['HostPort'])) : 0;
        $host_username = sanitize_text_field($_POST['HostUserName']);
        $host_password = sanitize_text_field($_POST['HostPassword']);
        $host_path = sanitize_text_field($_POST['HostPath']);
        $action = sanitize_text_field($_POST['action']);
        update_option('sm_ftp_hostname', $host_name);
        update_option('sm_ftp_hostport', $host_port);
        update_option('sm_ftp_hostusername', $host_username);
        update_option('sm_ftp_hostpath', $host_path);
        update_option('sm_ftp_hostpassword', $host_password);
        update_option('action', $action);
        global $wpdb;

        $file_table_name = $wpdb->prefix ."smackcsv_file_events";    
        // set up basic connection
        $conn_id = ftp_connect($host_name , $host_port);
        $response = [];
        if(!$conn_id){
            $response['success'] = false;
            $response['message'] = 'FTP connection failed';
            echo wp_json_encode($response);
        }else{
            // login with username and password
            $login_result = ftp_login($conn_id, $host_username, $host_password);
            $ftp_file_name = basename($host_path);

            $validate_instance = ValidateFile::getInstance();
            $zip_instance = ZipHandler::getInstance();
            $validate_format = $validate_instance->validate_file_format($ftp_file_name);

            if($validate_format == 'yes'){

                $upload_dir = FtpUpload::$smack_csv_instance->create_upload_dir();
                if($upload_dir){
                    ftp_pasv($conn_id, true);
            
                    $version = '1';
                    $path = explode($ftp_file_name, $host_path);
                    $path = isset($path[0]) ? $path[0] : '';
                    $file_extension = pathinfo($ftp_file_name, PATHINFO_EXTENSION);
                    $allowed_exts = ['csv', 'xml', 'xlsx', 'xls', 'txt', 'tsv'];
                    if ( ! in_array( $file_extension, $allowed_exts, true ) ) {
                        wp_die( __( 'Invalid file type.', 'wp-ultimate-csv-importer' ), 400 );
                    }
                    if(empty($file_extension)){
                        $file_extension = 'xml';
                    }
                    // if($file_extension == 'xlsx'){
                    //     $file_extension = 'csv';                    
                    // }
                    $file_extn = '.' . $file_extension;
                    $get_local_filename = explode($file_extn, $ftp_file_name);
                    $local_file_name = $get_local_filename[0] . '-1' . $file_extn;
                    $version = '1'; 
                    $event_key = FtpUpload::$smack_csv_instance->convert_string2hash_key($local_file_name);
                    
                    if($file_extension == 'zip'){
                        $zip_response = [];
        
                        $path = $upload_dir . $event_key . '.zip';
                        $extract_path = $upload_dir . $event_key;
                        $server_file = $host_path;
                        
$file_extension = strtolower($file_extension); 
$ftp_mode = in_array($file_extension, ['csv', 'txt']) ? FTP_ASCII : FTP_BINARY;
$ret = ftp_nb_get($conn_id, $local_file, $server_file, $ftp_mode);
                        $ret = ftp_nb_continue($conn_id);
                        if($ret == FTP_FINISHED){
                            chmod($path, 0777);
                            $zip_response['success'] = true;
                            $zip_response['filename'] = $ftp_file_name;
                            $zip_response['file_type'] = 'zip';
                            $zip_response['info'] = $zip_instance->zip_upload($path , $extract_path);
                        }else{
                            $zip_response['success'] = false;
                            $zip_response['message'] = 'Cannot Download the file , file not found';
                        }
        
                        echo wp_json_encode($zip_response); 
                        wp_die();
        
                    }

                    $upload_dir_path = $upload_dir. $event_key;
                    if (!is_dir($upload_dir_path)) {
                        wp_mkdir_p( $upload_dir_path);
                    }
                    chmod($upload_dir_path, 0777);

                    $wpdb->insert( $file_table_name , array( 'file_name' => $ftp_file_name , 'hash_key' => $event_key , 'status' => 'Downloading' , 'lock' => true  ) );
                    $last_id = $wpdb->get_results("SELECT id FROM $file_table_name ORDER BY id DESC LIMIT 1",ARRAY_A);
                    $lastid=$last_id[0]['id'];
                    $local_file = $upload_dir. $event_key .'/'. $event_key;
                    $server_file = $host_path;
                    
                    $fs = ftp_size($conn_id , $server_file);    
$file_extension = strtolower($file_extension);
$ftp_mode = in_array($file_extension, ['csv', 'txt']) ? FTP_ASCII : FTP_BINARY;
$ret = ftp_nb_get($conn_id, $local_file, $server_file, $ftp_mode);
                   
                    $filesize = filesize($local_file);
                    if ($filesize > 1024 && $filesize < (1024 * 1024)) {
                        $fileSize = round(($filesize / 1024), 2) . ' kb';
                    } else {
                        if ($filesize > (1024 * 1024)) {
                            $fileSize = round(($filesize / (1024 * 1024)), 2) . ' mb';
                        } else {
                            $fileSize = $filesize . ' byte';
                        }
                    }
                    while($ret == FTP_MOREDATA){
                    clearstatcache();
                    $dld = intval($fileSize);
                    if($dld > 0){
                        $i = ($dld/$fs)*100;
                        $wpdb->get_results("UPDATE $file_table_name SET  progress='$i' , `lock`=true WHERE id = '$lastid'");
                    }
                    
                    $ret = ftp_nb_continue($conn_id);
                    
                    }
                    if($ret == FTP_FINISHED){
                        if ($file_extension == 'xlsx' || $file_extension == 'xls') {
							$this->convertXlsxToCsv($local_file ,$upload_dir_path, $event_key);				
                            $file_extension = 'csv';
                        }
                        chmod($local_file, 0777);
                        $validate_file = $validate_instance->file_validation($local_file , $file_extension);

                        $file_size = filesize($local_file);
                        $files_size = $validate_instance->formatSizeUnits($file_size);
                        
                        if($validate_file == "yes"){

                            $wpdb->get_results("UPDATE $file_table_name SET status='Downloaded',`lock`=false WHERE id = '$lastid'");
                            $get_result = $validate_instance->import_record_function($event_key , $ftp_file_name);
                            $response['success'] = true;
                            $response['filename'] = $ftp_file_name;
                            $response['hashkey'] = $event_key;
                            $response['posttype'] = $get_result['Post Type'];
                            $response['taxonomy'] = $get_result['Taxonomy'];
                            $response['selectedtype'] = $get_result['selected type'];
                            $response['file_type'] = $file_extension;
                            $response['file_size'] = $files_size;
                            $response['message'] = 'Downloaded Successfully';
                            if ($file_extension === 'csv' || $file_extension === 'tsv') {
    $delimiter = DesktopUpload::detect_csv_delimiter($local_file);
    update_option("smack_csv_delimiter_{$event_key}", $delimiter);
}

                            echo wp_json_encode($response);
                        }else{
                            $response['success'] = false;
                            $response['message'] = $validate_file;
                            echo wp_json_encode($response); 
                            $upload_dir = wp_upload_dir();
                            $base_dir   = trailingslashit($upload_dir['basedir']);

                            $real_base = realpath($base_dir);
                            $real_path = realpath($path);

                            if ($real_path !== false && strpos($real_path, $real_base) === 0) {
                                unlink($real_path); // safe delete inside uploads only
                            }
                            $wpdb->get_results("UPDATE $file_table_name SET status='Download Failed' WHERE id = '$lastid'");
                        }
                    } else {
                        $wpdb->get_results("UPDATE $file_table_name SET status='Download Failed' WHERE id = '$lastid'");
                        $response['message'] = 'Cannot Download the file , file not found';
                        echo wp_json_encode($response);
                    }
                    // close the connection
                    ftp_close($conn_id);
                }else{
                    $response['success'] = false;
                    $response['message'] = "Please create Upload folder with writable permission";
                    echo wp_json_encode($response); 
                }

            }else{
                $response['success'] = false;
                $response['message'] = $validate_format;
                echo wp_json_encode($response); 
            }
        }
        wp_die();
    }

    public function getFtpDetails(){
        $this->check_ftp_security();
        
        $result['HostName'] = get_option('sm_ftp_hostname');
        $result['HostPort'] = get_option('sm_ftp_hostport');
        $result['HostUserName'] = get_option('sm_ftp_hostusername');
        $result['HostPath'] = get_option('sm_ftp_hostpath');
        $result['HostPassword'] = get_option('sm_ftp_hostpassword');
        $result['action'] = get_option('action');
        echo wp_json_encode($result);
        wp_die();
    }
}
