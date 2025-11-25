<?php

/**
 * WP Ultimate CSV Importer plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\FCSV;

if (! defined('ABSPATH'))
	exit; // Exit if accessed directly

$import_extensions = glob(__DIR__ . '/importExtensions/*.php');

foreach ($import_extensions as $import_extension_value) {
	include_once($import_extension_value);
}

class SaveMapping
{
	private static $instance = null, $validatefile;
	private static $smackcsv_instance = null;
	private static $core = null;
	public $media_log,$manage_filter;
	public $check_manage_filter = false;
	private function __construct()
	{
		add_action('wp_ajax_saveMappedFields', array($this, 'save_fields_function'));
		add_action('wp_ajax_StartImport', array($this, 'background_starts_function'));
		add_action('wp_ajax_GetProgress', array($this, 'import_detail_function'));
		add_action('wp_ajax_ImportState', array($this, 'import_state_function'));
		add_action('wp_ajax_ImportStop', array($this, 'import_stop_function'));
		add_action('wp_ajax_checkmain_mode', array($this, 'checkmain_mode'));
		add_action('wp_ajax_close_notification_action',array($this, 'handle_close_notification_action'));
		add_action('wp_ajax_bulk_file_import', array($this, 'bulk_file_import_function'));
		add_action('wp_ajax_bulk_import', array($this, 'bulk_import'));
		add_action('wp_ajax_PauseImport', array($this, 'pause_import'));
		add_action('wp_ajax_ResumeImport', array($this, 'resume_import'));
		add_action('wp_ajax_DeactivateMail', array($this, 'deactivate_mail'));
		add_action('wp_ajax_smackuci_check_review_popup', array($this, 'smackuci_check_review_popup'));
add_action('wp_ajax_nopriv_smackuci_check_review_popup', array($this, 'smackuci_check_review_popup'));


	}

	public static function getInstance()
	{
		if (SaveMapping::$instance == null) {
			SaveMapping::$instance = new SaveMapping;
			SaveMapping::$smackcsv_instance = SmackCSV::getInstance();
			SaveMapping::$validatefile = new ValidateFile;
			return SaveMapping::$instance;
		}
		return SaveMapping::$instance;
	}

public function smackuci_check_review_popup() {
	check_ajax_referer('smack-ultimate-csv-importer', 'securekey');

    global $wpdb;

    $table = $wpdb->prefix . "smackuci_events";

    $dont_show = get_option('smackuci_dont_show_again', false);

    if (isset($_POST['Later']) && $_POST['Later'] === "true") {
        $import_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");

        update_option('smackuci_last_feedback_check', $import_count);

        wp_send_json_success(['reset' => true]);
    }

    if (isset($_POST['DontNotopen']) && $_POST['DontNotopen'] === "true") {
        update_option('smackuci_dont_show_again', true);
        wp_send_json_success(['dont_show' => true]);
    }

    $import_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");

    $last_shown_at = (int) get_option('smackuci_last_feedback_check', 0);

    if ($import_count >= ($last_shown_at + 10) && !$dont_show) {
        update_option('smackuci_last_feedback_check', $import_count);
        wp_send_json_success(['show_popup' => true]);
    }

    wp_send_json_error(['show_popup' => false]);
}



	public function handle_close_notification_action() {
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');

		$get_option = get_option('updateMessageDisplay');
		if ($get_option === 'true') {
			update_option('updateMessageDisplay', 'false');
		}
		$get_option = get_option('updateMessageDisplay');
		echo wp_json_encode(array('success' => true));
		wp_die();
	}
	public function checkmain_mode()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		$ucisettings = get_option('sm_uci_pro_settings');
		if (isset($ucisettings['enable_main_mode']) && $ucisettings['enable_main_mode'] == 'true') {
			$result['success'] = true;
		} else {
			$result['success'] = false;
		}
		$get_option = get_option('updateMessageDisplay');
		if ($get_option === false) {
		//	$result['notice_message'] = $this->updateMessage();
			$result['notice_display'] = true;
			add_option('updateMessageDisplay', 'true');
		}else {
			// Option exists, check its value
			if ($get_option === 'true') {
			//	$result['notice_message'] = $this->updateMessage(); // Option is true
				$result['notice_display'] = true;
			}else{
			//	$result['notice_message'] = $this->updateMessage();
				$result['notice_display'] = false;
			}
		}
		echo wp_json_encode($result);
		wp_die();
	}

	public function updateMessage(){
		
		$message = '';
		$response = wp_safe_remote_get('https://www.smackcoders.com/wp-versions/wp-ultimate-csv-importer-free.json');
		
		if ( is_wp_error( $response ) ) {
			return $message;
		}
		$response = json_decode($response);
		$current_plugin_version = '7.14';
        if($current_plugin_version < $response->version[0]) {
			
            $message = $response->message[0];
        }
		return $message;
	}

	
	/**
	 * Save the mapped fields on using new mapping
	 * @return boolean
	 */
	public function pause_import()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		global $wpdb;
		$response = [];
		$hash_key = sanitize_key($_POST['HashKey']);
		$log_table_name = $wpdb->prefix . "import_detail_log";
		$wpdb->get_results("UPDATE $log_table_name SET running = 0  WHERE hash_key = '$hash_key'");
		$response['pause_state'] = true;
		echo wp_json_encode($response);
		wp_die();
	}

	public function resume_import()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		global $wpdb;
		$response = [];
		$hash_key = sanitize_key($_POST['HashKey']);
		$log_table_name = $wpdb->prefix . "import_detail_log";
		$wpdb->get_results("UPDATE $log_table_name SET running = 1  WHERE hash_key = '$hash_key'");
		$response['resume_state'] = true;
		$response['page_number'] = get_option('sm_bulk_import_page_number') + 1;
		echo wp_json_encode($response);
		wp_die();
	}
	public function save_fields_function()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		$hash_key      = sanitize_key($_POST['HashKey']);
		$type          = sanitize_text_field($_POST['Types']);
		$map_fields    = sanitize_text_field($_POST['MappedFields']);
		//$map_filter    = sanitize_text_field($_POST['MappedFilter']);
		$mapping_type = sanitize_text_field($_POST['MappingType']);
		$counter = isset($counter) ? $counter : 0;
		$selected_mode = isset($_POST['selectedMode']) ? sanitize_text_field($_POST['selectedMode']) : '';
		global $wpdb;
		if ($selected_mode == 'simpleMode') {
			$fileiteration = 5;
			update_option('sm_bulk_import_free_iteration_limit', $fileiteration);
			$media_settings['media_handle_option'] = 'true';
			$media_settings['use_ExistingImage'] = 'true';
			$image_info = array(
				'media_settings'  => $media_settings
			);
			update_option('smack_image_options', $image_info);
		}
		$template_table_name = $wpdb->prefix . "ultimate_csv_importer_mappingtemplate";
		$file_table_name = $wpdb->prefix . "smackcsv_file_events";

		$filters = json_decode(stripslashes($_POST['MappedFilter']), true);
		$mapping_filter = serialize($filters);	

		$mapped_fields = json_decode(stripslashes($map_fields), true);
		$helpers_instance = ImportHelpers::getInstance();
		$response = [];
		$counter = 0;
		foreach ($mapped_fields as $maps) {
			foreach ($maps as $header_keys => $value) {
				if (strpos($header_keys, '->cus2') !== false) {
					if (!empty($value)) {
						$helpers_instance->write_to_customfile($value);
					}
				}
			}
		}
		$has_bundlemeta = array_key_exists('BUNDLEMETA', $mapped_fields);
		foreach ($mapped_fields as $key => $value) {
			if ($key === 'ECOMMETA') {
				$map_data[$key] = $value;
				if ($has_bundlemeta) {
					$map_data['BUNDLEMETA'] = $mapped_fields['BUNDLEMETA'];
				}
			}
			if ($key !== 'BUNDLEMETA') {
				
				$map_data[$key] = $value;
			}
		}		
		$get_detail   = $wpdb->get_results("SELECT file_name FROM $file_table_name WHERE `hash_key` = '$hash_key'");
		$get_file_name = $get_detail[0]->file_name;
		$get_hash = $wpdb->get_results("SELECT eventKey FROM $template_table_name");
		$mapping_fields = serialize($map_data);
		$time = date('Y-m-d h:i:s');
		$get_file_name = $get_detail[0]->file_name;
		
		// Using prepare to safely insert the values
		if(!empty($get_hash)){
			foreach ($get_hash as $hash_values) {
				$inserted_hash_values[] = $hash_values->eventKey;
			}
			if (in_array($hash_key, $inserted_hash_values)) {
				$query = $wpdb->prepare(
					"UPDATE $template_table_name SET mapping = %s, mapping_filter = %s, createdtime = %s, module = %s, mapping_type = %s WHERE eventKey = %s",
					$mapping_fields,
					$mapping_filter,
					$time,
					$type,
					$mapping_type,
					$hash_key
				);
			} else {
				$query = $wpdb->prepare(
					"INSERT INTO $template_table_name (mapping, mapping_filter , createdtime, module, csvname, eventKey, mapping_type) 
					VALUES (%s, %s, %s, %s, %s, %s, %s)",
					$mapping_fields,
					$mapping_filter,
					$time,
					$type,
					$get_file_name,
					$hash_key,
					$mapping_type
				);
			}
		}else{
			$query = $wpdb->prepare(
				"INSERT INTO $template_table_name (mapping, mapping_filter , createdtime, module, csvname, eventKey, mapping_type) 
				VALUES (%s, %s, %s, %s, %s, %s, %s)",
				$mapping_fields,
				$mapping_filter,
				$time,
				$type,
				$get_file_name,
				$hash_key,
				$mapping_type
			);
		}

		$wpdb->query($query);

		$fileiteration = '5';
		update_option('sm_bulk_import_free_iteration_limit', $fileiteration);
		$response['success'] = true;
		$response['file_iteration'] = (int)$fileiteration;
		echo wp_json_encode($response);
		wp_die();
	}

	/**
	 * Provides import record details
	 */
	public function import_detail_function()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		$hash_key = sanitize_key($_POST['HashKey']);
		$response = [];

		global $wpdb;
		$log_table_name = $wpdb->prefix . "import_detail_log";
		$importlog_table_name = $wpdb->prefix . "import_log_detail";
		$file_table_name = $wpdb->prefix . "smackcsv_file_events";

		$file_records = $wpdb->get_results("SELECT mode FROM $file_table_name WHERE hash_key = '$hash_key' ", ARRAY_A);
		$mode = $file_records[0]['mode'];

		if ($mode == 'Insert') {
			$method = 'Import';
		}
		if ($mode == 'Update') {
			$method = 'Update';
		}

		$total_records = $wpdb->get_results("SELECT file_name , total_records , processing_records ,status ,remaining_records , filesize FROM $log_table_name WHERE hash_key = '$hash_key' ", ARRAY_A);
		$log_records = $wpdb->get_results("SELECT message , status , verify , categories , tags FROM $importlog_table_name WHERE  hash_key = '$hash_key' ", ARRAY_A);

		$response['success'] = true;
		$response['file_name'] = $total_records[0]['file_name'];
		$response['total_records'] = $total_records[0]['total_records'];
		$response['processing_records'] = $total_records[0]['processing_records'];
		$response['remaining_records'] = $total_records[0]['remaining_records'];
		$response['status'] = $total_records[0]['status'];
		$response['filesize'] = $total_records[0]['filesize'];
		$response['method'] = $method;

		if ($total_records[0]['status'] == 'Completed') {
			$response['progress'] = false;
		} else {
			$response['progress'] = true;
		}
		$response['Info'] = $log_records;

		echo wp_json_encode($response);
		wp_die();
	}

	/**
	 * Checks whether the import function is paused or resumed
	 */
	public function import_state_function()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		$response = [];
		$hash_key = sanitize_key($_POST['HashKey']);

		$upload = wp_upload_dir();
		$upload_base_url = $upload['baseurl'];
		$upload_url = $upload_base_url . '/smack_uci_uploads/imports/';
		$upload_dir = SaveMapping::$smackcsv_instance->create_upload_dir();

		$import_txt_path = $upload_dir . 'import_state.txt';
		chmod($import_txt_path, 0777);
		$import_state_arr = array();

		/* Gets string 'true' when Resume button is clicked  */
		if (sanitize_text_field($_POST['State']) == 'true') {
			//first check then set on
			$open_file = fopen($import_txt_path, "w");
			$import_state_arr = array('import_state' => 'on', 'import_stop' => 'on');
			$state_arr = serialize($import_state_arr);
			fwrite($open_file, $state_arr);
			fclose($open_file);

			$response['import_state'] = false;
		}
		/* Gets string 'false' when Pause button is clicked  */
		if (sanitize_text_field($_POST['State']) == 'false') {

			//first check then set off
			$open_file = fopen($import_txt_path, "w");
			$import_state_arr = array('import_state' => 'off', 'import_stop' => 'on');
			$state_arr = serialize($import_state_arr);
			fwrite($open_file, $state_arr);
			fclose($open_file);
			if ($log_link_path != null) {
				$response['show_log'] = true;
			} else {
				$response['show_log'] = false;
			}
			$response['import_state'] = true;
			$response['log_link'] = $log_link_path;
			$response['url'] = $upload_url;
		}
		echo wp_json_encode($response);
		wp_die();
	}


	/**
	 * Checks whether the import function is stopped or the page is refreshed
	 */
	public function import_stop_function()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		global $wpdb;
		$upload_dir = SaveMapping::$smackcsv_instance->create_upload_dir();
		/* Gets string 'false' when page is refreshed */
		if (sanitize_text_field($_POST['Stop']) == 'false') {
			$import_txt_path = $upload_dir . 'import_state.txt';
			chmod($import_txt_path, 0777);
			$import_state_arr = array();

			$open_file = fopen($import_txt_path, "w");
			$import_state_arr = array('import_state' => 'on', 'import_stop' => 'off');
			$state_arr = serialize($import_state_arr);
			fwrite($open_file, $state_arr);
			fclose($open_file);
		}
		wp_die();
	}


	/**
	 * Starts the import process
	 */

	public function bulk_import()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');

		global $wpdb, $core_instance, $uci_woocomm_meta, $uci_woocomm_bundle_meta, $product_attr_instance, $wpmlimp_class;
		$header_array = [];
		$upload_dir = SaveMapping::$smackcsv_instance->create_upload_dir();
		$hash_key  = sanitize_key($_POST['HashKey']);
		$check = sanitize_text_field($_POST['Check']);
		$media_type = sanitize_text_field($_POST['MediaType']);
		$selected_type = sanitize_text_field($_POST['Types']);
		$page_number = isset($_POST['PageNumber']) ? intval(sanitize_text_field($_POST['PageNumber'])) : 0;
		$rollback_option = sanitize_text_field($_POST['RollBack']);
		$check_filter = sanitize_text_field($_POST['mappingFilterCheck']);
		$this->check_manage_filter = $check_filter == 'false' ? false : true;
		$unmatched_row_value = get_option('sm_uci_pro_settings');
		$unmatched_row = isset($unmatched_row_value['unmatchedrow']) ? $unmatched_row_value['unmatchedrow'] : '';
		$helpers_instance = ImportHelpers::getInstance();
		$core_instance = CoreFieldsImport::getInstance();
		$import_config_instance = ImportConfiguration::getInstance();
		$log_manager_instance = LogManager::getInstance();
		$file_table_name = $wpdb->prefix . "smackcsv_file_events";
		$template_table_name = $wpdb->prefix . "ultimate_csv_importer_mappingtemplate";
		$log_table_name = $wpdb->prefix . "import_detail_log";
		$response = [];
		$get_id = $wpdb->get_results("SELECT id , mode ,file_name , total_rows FROM $file_table_name WHERE `hash_key` = '$hash_key'");
		$get_mode = $get_id[0]->mode;
		$total_rows = $get_id[0]->total_rows;
		$page_number = isset($_POST['PageNumber']) ? intval(sanitize_text_field($_POST['PageNumber'])) : 0;
		$total_pages = ceil($total_rows / 5);
		$file_name = $get_id[0]->file_name;
		$file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
		$gmode = 'Normal';
		if (empty($file_extension)) {
			$file_extension = 'xml';
		}
		if($file_extension == 'xlsx' || $file_extension == 'xls'){
			$file_extension = 'csv';                    
		}
		$upload_dir = SaveMapping::$smackcsv_instance->create_upload_dir();
		$file_size = filesize($upload_dir . $hash_key . '/' . $hash_key);
		$filesize = $helpers_instance->formatSizeUnits($file_size);
		update_option('sm_bulk_import_page_number', $page_number);
		$remain_records = $total_rows - 1;
		$wpdb->insert($log_table_name, array('file_name' => $file_name, 'hash_key' => $hash_key, 'total_records' => $total_rows, 'filesize' => $filesize, 'processing_records' => 1, 'remaining_records' => $remain_records, 'status' => 'Processing'));
		$background_values = $wpdb->get_results("SELECT mapping , mapping_filter, module  FROM $template_table_name WHERE `eventKey` = '$hash_key' ");
		foreach ($background_values as $values) {
			$mapped_fields_values = $values->mapping;
			$mapping_filter = $values->mapping_filter;
			$selected_type = $values->module;
		}
		$map = unserialize($mapped_fields_values);
		$this->manage_filter = unserialize($mapping_filter);
		if ($rollback_option == 'true') {
			$tables = $import_config_instance->get_rollback_tables($selected_type);
			$import_config_instance->set_backup_restore($tables, $hash_key, 'backup');
		}

		$addHeader = false;
		$file_iteration = get_option('sm_bulk_import_free_iteration_limit');
		if ($file_extension == 'csv' || $file_extension == 'txt') {
			if (version_compare(PHP_VERSION, '8.1.0', '<')) {  // Only do this if PHP version is less than 8.1.0
				if (!ini_get("auto_detect_line_endings")) {
					ini_set("auto_detect_line_endings", true);
				}
			}
			if (($h = fopen($upload_dir . $hash_key . '/' . $hash_key, "r")) !== FALSE) {
				$delimiters = array(',', '\t', ';', '|', ':', '&nbsp');
				$file_path = $upload_dir . $hash_key . '/' . $hash_key;
				$delimiter = SaveMapping::$validatefile->getFileDelimiter($file_path, 5);
				$array_index = array_search($delimiter, $delimiters);
				$line_number = (($file_iteration * $page_number) - $file_iteration) + 1;
				$limit = ($file_iteration * $page_number);
				if ($page_number == 1) {
					$addHeader = true;
				}
				$info = [];
				$i = 0;
				if ($array_index == 5) {
					$delimiters[$array_index] = ' ';
				}
				if ($delimiter == '\t') {
					$delimiter = '~';
					$temp = $file_path . 'temp';
					if (($handles = fopen($temp, 'r')) !== FALSE) {
						while (($data = fgetcsv($handles, 0, $delimiter)) !== FALSE) {
							$trimmed_info = array_map('trim', $data);
							array_push($info, $trimmed_info);
							if ($i == 0) {
								$header_array = $info[$i];
								$i++;
								continue;
							}

							if ($i >= $line_number && $i <= $limit) {
								$value_array = $info[$i];
								$openAIKeys = array();
								$openAIValues = array();
								$openAInumberKeys = array();
								$openAInumberValues = array();
								$flag = false;
								$map_openAI = false;
								foreach ($map as $subarray) {
									foreach ($subarray as $key => $value) {
										if (strpos($key, '->OPENAIA') !== false) {
											$map_openAI = 1;
											break;
										}
									}
								}
								if ($map_openAI == true) {
									foreach ($map as $mainKey => $mainValue) {
										foreach ($mainValue as $subKey => $subValue) {
											if (substr($subKey, -8) === '->OPENAIA') {
												$flag = true;
												$value_header = str_replace("->OPENAIA", "", $subKey);
												$openAIKeys[] = $value_header;
												$openAIValues[] = $subValue;
											}
											if (substr($subKey, -5) === '->num') {
												$flag = true;
												$value_header = str_replace("->num", "", $subKey);
												$openAInumberKeys[] = $value_header;
												$openAInumberValues[] = $subValue;
											}
										}
									}
									$core_instance->generated_content = $flag;
									$combinedArray = array_combine($openAIKeys, $openAIValues);
									if (isset($combinedArray['featured_image'])) {
										$featuredImageValue = $combinedArray['featured_image'];
										$index = array_search($featuredImageValue, $header_array);

										if ($index !== false) {
											if (isset($value_array[$index])) {
												$resultArray = $value_array[$index];
											} else {
												$resultArray = '';
											}
										} else {
											$resultArray = '';
										}

										$resultArray = [$mainKey => $resultArray];
										foreach ($resultArray as $index => $valueName) {
											$OpenAIHelper = new OpenAIHelper;

											$responsevalueArray[] = $OpenAIHelper->generateImage($valueName);
										}

										$value = 'featured_image';
										$index = array_search($value, $header_array);
										if ($index !== false) {
											$value_array[$index] = array_shift($responsevalueArray);
										}

										foreach ($responsevalueArray as $value) {
											$index = array_search($value, $header_array);
											$value_array[$index] = $value;
										}
										unset($combinedArray['featured_image']);
									}

									$openAIValues = array_values($combinedArray);
									$openAIKeys = array_keys($combinedArray);

									$resultArrays = [];

									foreach ($openAIValues as $mainKey) {
										$index = array_search($mainKey, $header_array);
										$resultArray = [];
										if ($index !== false) {
											if (isset($value_array[$index])) {
												$resultArray = $value_array[$index];
											} else {
												$resultArray = '';
											}
										} else {
											$resultArray = '';
										}
										$resultArrays[] = [$mainKey => $resultArray];
									}

									$outputArray = array();
									foreach ($resultArrays as $key => $resultArray) {
										$dynamicKey = key($resultArray);
										$outputArray[$key] = array(
											$openAInumberValues[$key] => $resultArray[$dynamicKey]
										);
									}
									$resultArrays = $outputArray;
									$responsevalueArray = [];
									foreach ($resultArrays as $index => $valueName) {
										foreach ($valueName as $val => $word) {
											$OpenAIHelper = new OpenAIHelper;
											$responsevalueArray[] = $OpenAIHelper->generateContent($word, $val);
										}
									}
									$core_instance->OPENAIA_response = $responsevalueArray;
									foreach ($openAIKeys as $value) {
										$index = array_search($value, $header_array);
										if ($index !== false) {
											$value_array[$index] = array_shift($responsevalueArray);
										}
									}
									foreach ($responsevalueArray as $value) {
										foreach ($openAIKeys as $mainKey) {
											$index = array_search($mainKey, $header_array);
											$value_array[$index] = $value;
										}
									}
								}
								foreach ($map as $group_name => $group_value) {
									if ($group_name == 'CORE') {
										$get_arr = $this->main_import_process($map, $header_array, $value_array, $selected_type, $get_mode, $i, $check, $hash_key, $unmatched_row, '', '', $media_type);
										$post_id = $get_arr['id'];
										$core_instance->detailed_log = $get_arr['detail_log'];
										$failed_media_log  = $get_arr['failed_media_log'];
										$core_instance->media_log = $get_arr['media_log'];
										$media_log = $core_instance->media_log;
									}
								}


								$helpers_instance->get_post_ids($post_id, $hash_key);

								$remaining_records = $total_rows - $i;
								$fields = $wpdb->get_results("UPDATE $log_table_name SET processing_records = $i , remaining_records = $remaining_records , status = 'Processing' WHERE hash_key = '$hash_key'");

								if ($i == $total_rows) {
									$fields = $wpdb->get_results("UPDATE $log_table_name SET status = 'Completed' WHERE hash_key = '$hash_key'");
								}
								if (count($core_instance->detailed_log) > $file_iteration) {
									$log_manager_instance->get_event_log($hash_key, $file_name, $file_extension, $get_mode, $total_rows, $selected_type, $core_instance->detailed_log, $addHeader);
									$log_manager_instance->manage_records($hash_key, $selected_type, $file_name, $total_rows);
									$addHeader = false;
									$core_instance->detailed_log = [];
									$core_instance->media_log = [];
									$failed_media_log = [];
								}
							}

							if ($i > $limit) {
								break;
							}

							$i++;
						}
					}
				} else {
					while (($data = fgetcsv($h, 0, $delimiters[$array_index])) !== FALSE) {
						$trimmed_info = array_map('trim', $data);
						array_push($info, $trimmed_info);
						if ($i == 0) {
							$header_array = $info[$i];
							$i++;
							continue;
						}

						if ($i >= $line_number && $i <= $limit) {
							$value_array = $info[$i];
							$openAIKeys = array();
							$openAIValues = array();
							$openAInumberKeys = array();
							$openAInumberValues = array();
							$flag = false;
							$map_openAI = false;
							foreach ($map as $subarray) {
								foreach ($subarray as $key => $value) {
									if (strpos($key, '->OPENAIA') !== false) {
										$map_openAI = 1;
										break;
									}
								}
							}
							if ($map_openAI == true) {
								foreach ($map as $mainKey => $mainValue) {
									foreach ($mainValue as $subKey => $subValue) {
										if (substr($subKey, -8) === '->OPENAIA') {
											$flag = true;
											$value_header = str_replace("->OPENAIA", "", $subKey);
											$openAIKeys[] = $value_header;
											$openAIValues[] = $subValue;
										}
										if (substr($subKey, -5) === '->num') {
											$flag = true;
											$value_header = str_replace("->num", "", $subKey);
											$openAInumberKeys[] = $value_header;
											$openAInumberValues[] = $subValue;
										}
									}
								}
								$core_instance->generated_content = $flag;
								$combinedArray = array_combine($openAIKeys, $openAIValues);
								if (isset($combinedArray['featured_image'])) {
									$featuredImageValue = $combinedArray['featured_image'];
									$index = array_search($featuredImageValue, $header_array);

									if ($index !== false) {
										if (isset($value_array[$index])) {
											$resultArray = $value_array[$index];
										} else {
											$resultArray = '';
										}
									} else {
										$resultArray = '';
									}

									$resultArray = [$mainKey => $resultArray];
									foreach ($resultArray as $index => $valueName) {
										$OpenAIHelper = new OpenAIHelper;

										$responsevalueArray[] = $OpenAIHelper->generateImage($valueName);
									}

									$value = 'featured_image';
									$index = array_search($value, $header_array);
									if ($index !== false) {
										$value_array[$index] = array_shift($responsevalueArray);
									}

									foreach ($responsevalueArray as $value) {
										$index = array_search($value, $header_array);
										$value_array[$index] = $value;
									}
									unset($combinedArray['featured_image']);
								}

								$openAIValues = array_values($combinedArray);
								$openAIKeys = array_keys($combinedArray);

								$resultArrays = [];

								foreach ($openAIValues as $mainKey) {
									$index = array_search($mainKey, $header_array);
									$resultArray = [];
									if ($index !== false) {
										if (isset($value_array[$index])) {
											$resultArray = $value_array[$index];
										} else {
											$resultArray = '';
										}
									} else {
										$resultArray = '';
									}
									$resultArrays[] = [$mainKey => $resultArray];
								}

								$outputArray = array();
								foreach ($resultArrays as $key => $resultArray) {
									$dynamicKey = key($resultArray);
									$outputArray[$key] = array(
										$openAInumberValues[$key] => $resultArray[$dynamicKey]
									);
								}
								$resultArrays = $outputArray;
								$responsevalueArray = [];
								foreach ($resultArrays as $index => $valueName) {
									foreach ($valueName as $val => $word) {
										$OpenAIHelper = new OpenAIHelper;
										$responsevalueArray[] = $OpenAIHelper->generateContent($word, $val);
									}
								}
								$core_instance->OPENAIA_response = $responsevalueArray;
								foreach ($openAIKeys as $value) {
									$index = array_search($value, $header_array);
									if ($index !== false) {
										$value_array[$index] = array_shift($responsevalueArray);
									}
								}
								foreach ($responsevalueArray as $value) {
									foreach ($openAIKeys as $mainKey) {
										$index = array_search($mainKey, $header_array);
										$value_array[$index] = $value;
									}
								}
							}
							foreach ($map as $group_name => $group_value) {
								if ($group_name == 'CORE') {
									$get_arr = $this->main_import_process($map, $header_array, $value_array, $selected_type, $get_mode, $i, $check, $hash_key, $unmatched_row, '', '', $media_type);
									$post_id = $get_arr['id'];
									$core_instance->detailed_log = $get_arr['detail_log'];
									$failed_media_log  = $get_arr['failed_media_log'];
									$core_instance->media_log = $get_arr['media_log'];
									$media_log = $core_instance->media_log;
								}
							}
							$helpers_instance->get_post_ids($post_id, $hash_key);

							$remaining_records = $total_rows - $i;
							$fields = $wpdb->get_results("UPDATE $log_table_name SET processing_records = $i , remaining_records = $remaining_records , status = 'Processing' WHERE hash_key = '$hash_key'");

							if ($i == $total_rows) {
								$fields = $wpdb->get_results("UPDATE $log_table_name SET status = 'Completed' WHERE hash_key = '$hash_key'");
							}
							if (count($core_instance->detailed_log) > $file_iteration) {
								$log_manager_instance->get_event_log($hash_key, $file_name, $file_extension, $get_mode, $total_rows, $selected_type, $core_instance->detailed_log, $addHeader);
								$addHeader = false;
								$core_instance->detailed_log = [];
								$failed_media_log = [];
								$media_log = [];
							}
						}

						if ($i > $limit) {
							break;
						}

						$i++;
					}
				}
				$running = $wpdb->get_row("SELECT running FROM $log_table_name WHERE hash_key = '$hash_key' ");
				$check_pause = $running->running;
				if ($check_pause == 0) {
					$response['success'] = false;
					$response['pause_message'] = 'Record Paused';
					echo wp_json_encode($response);
					wp_die();
				}
				fclose($h);
			}
		}
		if($file_extension == 'tsv'){
			if (version_compare(PHP_VERSION, '8.1.0', '<')) {  // Only do this if PHP version is less than 8.1.0
				if (!ini_get("auto_detect_line_endings")) {
					ini_set("auto_detect_line_endings", true);
				}
			}
			if (($h = fopen($upload_dir . $hash_key . '/' . $hash_key, "r")) !== FALSE) {
				$file_path = $upload_dir . $hash_key . '/' . $hash_key;
				$delimiter = SaveMapping::$validatefile->getFileDelimiter($file_path, 5);
				$file_iteration = get_option('sm_bulk_import_free_iteration_limit');
				$line_number = (($file_iteration * $page_number) - $file_iteration) + 1;
				$limit = ($file_iteration * $page_number);
				if ($page_number == 1) {
					$addHeader = true;
				}
				$info = [];
				$i = 0;
				while (($data = fgetcsv($h, 0, "\t")) !== FALSE) {

					$trimmed_info = array_map('trim', $data);
					array_push($info, $trimmed_info);
					if ($i == 0) {
						$header_array = $info[$i];
						$i++;
						continue;
					}

					if ($i >= $line_number && $i <= $limit) {
						$value_array = $info[$i];
						$openAIKeys = array();
						$openAIValues = array();
						$openAInumberKeys = array();
						$openAInumberValues = array();
						$flag = false;
						$map_openAI = false;
						foreach ($map as $subarray) {
							foreach ($subarray as $key => $value) {
								if (strpos($key, '->OPENAIA') !== false) {
									$map_openAI = 1;
									break;
								}
							}
						}
						if ($map_openAI == true) {
							foreach ($map as $mainKey => $mainValue) {
								foreach ($mainValue as $subKey => $subValue) {
									if (substr($subKey, -8) === '->OPENAIA') {
										$flag = true;
										$value_header = str_replace("->OPENAIA", "", $subKey);
										$openAIKeys[] = $value_header;
										$openAIValues[] = $subValue;
									}
									if (substr($subKey, -5) === '->num') {
										$flag = true;
										$value_header = str_replace("->num", "", $subKey);
										$openAInumberKeys[] = $value_header;
										$openAInumberValues[] = $subValue;
									}
								}
							}
							$core_instance->generated_content = $flag;
							$combinedArray = array_combine($openAIKeys, $openAIValues);
							if (isset($combinedArray['featured_image'])) {
								$featuredImageValue = $combinedArray['featured_image'];
								$index = array_search($featuredImageValue, $header_array);

								if ($index !== false) {
									if (isset($value_array[$index])) {
										$resultArray = $value_array[$index];
									} else {
										$resultArray = '';
									}
								} else {
									$resultArray = '';
								}

								$resultArray = [$mainKey => $resultArray];
								foreach ($resultArray as $index => $valueName) {
									$OpenAIHelper = new OpenAIHelper;

									$responsevalueArray[] = $OpenAIHelper->generateImage($valueName);
								}

								$value = 'featured_image';
								$index = array_search($value, $header_array);
								if ($index !== false) {
									$value_array[$index] = array_shift($responsevalueArray);
								}

								foreach ($responsevalueArray as $value) {
									$index = array_search($value, $header_array);
									$value_array[$index] = $value;
								}
								unset($combinedArray['featured_image']);
							}

							$openAIValues = array_values($combinedArray);
							$openAIKeys = array_keys($combinedArray);

							$resultArrays = [];

							foreach ($openAIValues as $mainKey) {
								$index = array_search($mainKey, $header_array);
								$resultArray = [];
								if ($index !== false) {
									if (isset($value_array[$index])) {
										$resultArray = $value_array[$index];
									} else {
										$resultArray = '';
									}
								} else {
									$resultArray = '';
								}
								$resultArrays[] = [$mainKey => $resultArray];
							}

							$outputArray = array();
							foreach ($resultArrays as $key => $resultArray) {
								$dynamicKey = key($resultArray);
								$outputArray[$key] = array(
									$openAInumberValues[$key] => $resultArray[$dynamicKey]
								);
							}
							$resultArrays = $outputArray;
							$responsevalueArray = [];
							foreach ($resultArrays as $index => $valueName) {
								foreach ($valueName as $val => $word) {
									$OpenAIHelper = new OpenAIHelper;
									$responsevalueArray[] = $OpenAIHelper->generateContent($word, $val);
								}
							}
							$core_instance->OPENAIA_response = $responsevalueArray;
							foreach ($openAIKeys as $value) {
								$index = array_search($value, $header_array);
								if ($index !== false) {
									$value_array[$index] = array_shift($responsevalueArray);
								}
							}
							foreach ($responsevalueArray as $value) {
								foreach ($openAIKeys as $mainKey) {
									$index = array_search($mainKey, $header_array);
									$value_array[$index] = $value;
								}
							}
						}
						foreach ($map as $group_name => $group_value) {
							if ($group_name == 'CORE') {
								$get_arr = $this->main_import_process($map, $header_array, $value_array, $selected_type, $get_mode, $i, $check, $hash_key, $unmatched_row, '', '', $media_type);
								$post_id = $get_arr['id'];
								$core_instance->detailed_log = $get_arr['detail_log'];
								$failed_media_log  = $get_arr['failed_media_log'];
								$core_instance->media_log = $get_arr['media_log'];
								$media_log = $core_instance->media_log;
							}
						}
						$helpers_instance->get_post_ids($post_id, $hash_key);

						$remaining_records = $total_rows - $i;
						$fields = $wpdb->get_results("UPDATE $log_table_name SET processing_records = $i , remaining_records = $remaining_records , status = 'Processing' WHERE hash_key = '$hash_key'");

						if ($i == $total_rows) {
							$fields = $wpdb->get_results("UPDATE $log_table_name SET status = 'Completed' WHERE hash_key = '$hash_key'");
						}
						if (count($core_instance->detailed_log) > $file_iteration) {
							$log_manager_instance->get_event_log($hash_key, $file_name, $file_extension, $get_mode, $total_rows, $selected_type, $core_instance->detailed_log, $addHeader);
							$addHeader = false;
							$core_instance->detailed_log = [];
							$failed_media_log = [];
							$media_log = [];
						}
					}

					if ($i > $limit) {
						break;
					}

					$i++;
				}
				$running = $wpdb->get_row("SELECT running FROM $log_table_name WHERE hash_key = '$hash_key' ");
				$check_pause = $running->running;
				if ($check_pause == 0) {
					$response['success'] = false;
					$response['pause_message'] = 'Record Paused';
					echo wp_json_encode($response);
					wp_die();
				}
				fclose($h);
			}
		}
		if ($file_extension == 'xml') {
			$path = $upload_dir . $hash_key . '/' . $hash_key;
			// $lined_number = ((5 * $page_number) - 5);
			// $limit = (5 * $page_number) - 1;

			$lined_number = ($file_iteration * ($page_number - 1));
			$limit = min(($file_iteration * $page_number) - 1, $total_rows - 1); // Ensure limit does not exceed total rows
		
			$header_array = [];
			$value_array = [];
			$i = 0;
			$info = [];
			$addHeader = false;

			for ($line_number = 0; $line_number < $total_rows; $line_number++) {

				if ($page_number == 1 && $line_number == 0) {
					$addHeader = true;
				}

				if ($i >= $lined_number && $i <= $limit) {
					$xml_class = new XmlHandler();
					$parse_xml = $xml_class->parse_xmls($hash_key, $i);
					$j = 0;
					foreach ($parse_xml as $xml_key => $xml_value) {
						if (is_array($xml_value)) {
							foreach ($xml_value as $e_key => $e_value) {
								$header_array['header'][$j] = $e_value['name'];
								$value_array['value'][$j] = $e_value['value'];
								$j++;
							}
						}
					}
					$xml = simplexml_load_file($path);
					foreach ($xml->children() as $child) {
						$tag = $child->getName();
					}
					$total_xml_count = $this->get_xml_count($path, $tag);
					if ($total_xml_count == 0 || $tag == 'channel') {
						$sub_child = $this->get_child($child, $path);
						$tag = $sub_child['child_name'];
						$total_xml_count = $sub_child['total_count'];
					}
					$doc = new \DOMDocument();
					$doc->load($path);
					foreach ($map as $field => $value) {
						foreach ($value as $head => $val) {
							if (preg_match('/{/', $val) && preg_match('/}/', $val)) {
								preg_match_all('/{(.*?)}/', $val, $matches);
								$line_numbers = $i + 1;
								$val = preg_replace("{" . "(" . $tag . "[+[0-9]+])" . "}", $tag . "[" . $line_numbers . "]", $val);
								for ($k = 0; $k < count($matches[1]); $k++) {
									$matches[1][$k] = preg_replace("(" . $tag . "[+[0-9]+])", $tag . "[" . $line_numbers . "]", $matches[1][$k]);
									$value = $this->parse_element($doc, $matches[1][$k], $i);
									$search = '{' . $matches[1][$k] . '}';
									$val = str_replace($search, $value, $val);
								}
								$mapping[$field][$head] = $val;
							} else {
								$mapping[$field][$head] = $val;
							}
						}
					}

					array_push($info, $value_array['value']);
					$get_arr = $this->main_import_process($mapping, $header_array['header'], $value_array['value'], $selected_type, $get_mode, $i, $check, $hash_key, $unmatched_row, '', '', '');
					$post_id = $get_arr['id'];
					$core_instance->detailed_log = $get_arr['detail_log'];
					$failed_media_log  = $get_arr['failed_media_log'];
					$media_log = $get_arr['media_log'];

					$helpers_instance->get_post_ids($post_id, $hash_key);
					$line_numbers = $i + 1;
					//$remaining_records = $total_rows - $line_numbers;
					$remaining_records = max($total_rows - $line_numbers, 0);
					$wpdb->get_results("UPDATE $log_table_name SET processing_records = $line_numbers, remaining_records = $remaining_records, status = 'Processing' WHERE hash_key = '$hash_key'");

					if ($i == $total_rows - 1) {
						$wpdb->get_results("UPDATE $log_table_name SET status = 'Completed' WHERE hash_key = '$hash_key'");
					}
					if (is_countable($core_instance->detailed_log) && count($core_instance->detailed_log) > $file_iteration ) {
						$log_manager_instance->get_event_log($hash_key, $file_name, $file_extension, $get_mode, $total_rows, $selected_type, $core_instance->detailed_log, $addHeader);
						$log_manager_instance->manage_records($hash_key, $selected_type, $file_name, $total_rows);
						$addHeader = false;
						$core_instance->detailed_log = [];
						$failed_media_log = [];
						$media_log = [];
					}
				}
				if ($i > $limit) {
					break;
				}
				$i++;
			}
			$running = $wpdb->get_row("SELECT running FROM $log_table_name WHERE hash_key = '$hash_key' ");
			$check_pause = $running->running;
			if ($check_pause == 0) {
				$response['success'] = false;
				$response['pause_message'] = 'Record Paused';
				echo wp_json_encode($response);
				wp_die();
			}
		}

		if (($unmatched_row == 'true') && ($page_number >= $total_pages)) {

			$post_entries_table = $wpdb->prefix . "ultimate_post_entries";
			$post_entries_value = $wpdb->get_results("select ID from {$wpdb->prefix}ultimate_post_entries ", ARRAY_A);
			$type = $wpdb->get_var("select type from {$wpdb->prefix}ultimate_post_entries ");
			if (!empty($post_entries_value)) {
				foreach ($post_entries_value as $post_entries) {
					$entries_array[] = $post_entries['ID'];
				}

				$unmatched_object = new ExtensionHandler;
				$import_type = $unmatched_object->import_type_as($selected_type);
				$import_type_value = $unmatched_object->import_post_types($import_type);
				$import_name_as = $unmatched_object->import_name_as($import_type);
				if ($type == 'cct') {
					$jettable = $wpdb->prefix . 'jet_cct_' . $import_type;
					$get_total_row_count =  $wpdb->get_col("SELECT DISTINCT _ID FROM $jettable WHERE cct_status != 'trash' ");
					$unmatched_id = array_diff($get_total_row_count, $test);
					foreach ($unmatched_id as $keys => $values) {
						$wpdb->get_results("DELETE FROM $jettable WHERE `_ID`='$values' ");
					}
				}else{
					if ($import_type_value == 'category' || $import_type_value == 'post_tag' || $import_type_value == 'product_cat' || $import_type_value == 'product_tag') {

						$get_total_row_count =  $wpdb->get_col("SELECT term_id FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy = '$import_type_value'");
						if (is_array($entries_array)) {
							$unmatched_id = array_diff($get_total_row_count, $entries_array);
						}
	
						foreach ($unmatched_id as $keys => $values) {
							$wpdb->get_results("DELETE FROM {$wpdb->prefix}terms WHERE `term_id` = '$values' ");
						}
					}
					if ($import_type_value == 'post' || $import_type_value == 'product' || $import_type_value == 'page' || $import_name_as == 'CustomPosts') {
	
						$get_total_row_count =  $wpdb->get_col("SELECT DISTINCT ID FROM {$wpdb->prefix}posts WHERE post_type = '{$import_type_value}' AND post_status != 'trash' ");
						if (is_array($entries_array)) {
							$unmatched_id = array_diff($get_total_row_count, $entries_array);
						}
						foreach ($unmatched_id as $keys => $values) {
							$wpdb->get_results("DELETE FROM {$wpdb->prefix}posts WHERE `ID` = '$values' ");
						}
					}
				}
				$wpdb->get_results("DELETE FROM {$wpdb->prefix}ultimate_post_entries");
			}
		}

		if (!empty($core_instance->detailed_log) && is_countable($core_instance->detailed_log)) {
			if (count($core_instance->detailed_log) > 0) {
				$log_manager_instance->get_event_log($hash_key, $file_name, $file_extension, $get_mode, $total_rows, $selected_type, $core_instance->detailed_log, $addHeader);
			}
		}
		$log_manager_instance->manage_records($hash_key, $selected_type, $file_name, $total_rows);
		$count = count($info);

		for ($i = 1; $i <= $count; $i++) {
			if (isset($info[$i]) && (is_array($info)) && (is_array($info[$i]))) {
				foreach ($info[$i] as $key => $value) {
					if (preg_match("/<img/", $value)) {
						// SaveMapping::$smackcsv_instance->image_schedule();
						// $image = $wpdb->get_results("select * from {$wpdb->prefix}ultimate_csv_importer_shortcode_manager where hash_key = '{$hash_key}'");
						// if (!empty($image)) {
						// 	SaveMapping::$smackcsv_instance->delete_image_schedule();
						// }
					}
				}
			}
		}
		// print_r($media_log);
		// exit();
		if (!empty($media_log[$line_number]) && count($media_log) > 0) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'failed_media';
			$data_to_insert = array();

			foreach ($media_log as $media_item) {
				$media_id = isset($media_item['media_id']) ? $media_item['media_id'] : null;
				$post_title = isset($media_item['title']) ? $media_item['title'] : null;
				$status = isset($media_item['status']) ? $media_item['status'] : null;
				$file_name = isset($media_item['file_name']) ? $media_item['file_name'] : null;
				$file_url = isset($media_item['file_url']) ? $media_item['file_url'] : null;
				$actual_url = isset($media_item['actual_url']) ? $media_item['actual_url'] : null;
				$caption = isset($media_item['caption']) ? $media_item['caption'] : null;
				$alt_text = isset($media_item['alt_text']) ? $media_item['alt_text'] : null;
				$description = isset($media_item['description']) ? $media_item['description'] : null;

				$data_to_insert[] = $wpdb->prepare(
					"(%s, %s, %d, %s, %s, %s, %s, %s, %s, %s)",
					$hash_key,
					$post_title,
					$media_id,
					$status,
					$file_url,
					$file_name,
					$actual_url,
					$caption,
					$alt_text,
					$description
				);
			}

			if (!empty($data_to_insert)) {
				$query = "INSERT INTO $table_name (event_id, title, media_id, status, file_url, file_name, actual_url, caption, alt_text, description) VALUES " . implode(", ", $data_to_insert);
				$wpdb->query($query);
			}
		}



		$log_value = $log_manager_instance->displayLogValue();

		// print_r($log_value);
		// exit();

		//for log
		global $wpdb;
		$table_name = $wpdb->prefix . 'summary';

		//  data for batch insert
		$data_to_insert = array();

		foreach ($log_value as $item) {
			$inserted_id = isset($item['id']) ? $item['id'] : null;
			$post_title = isset($item['post_title']) ? $item['post_title'] : null;
			$post_type = isset($item['post_type']) ? $item['post_type'] : null;
			$status = isset($item['Status']) ? $item['Status'] : null;
			$total_images = isset($item['total_image']) ? $item['total_image'] : 0;
			$failed_images = isset($item['failed_image_count']) ? $item['failed_image_count'] : 0;

			// $categorie = ($post_type == 'Categories') ? 1 : 0;
			if ($post_type == 'Categories') {
				$categorie = 1;
			} elseif ($post_type == 'Tags') {

				$categorie = 2;
			} elseif ($post_type == 'users') {
				$categorie = 3;
			} elseif ($post_type == 'Comment' || $post_type == 'WooCommerce Reviews') {
				$categorie = 4;
			} else {
				$categorie = 0;
			}


			$data_to_insert[] = $wpdb->prepare(
				"(%d, %s, %s, %s, %s, %d, %d, %d)",
				$inserted_id,
				$hash_key,
				$post_title,
				$post_type,
				$status,
				$categorie,
				$total_images,
				$failed_images
			);
		}

		// Batch insert for boosting speed
		if (!empty($data_to_insert)) {
			$query = "INSERT INTO $table_name (post_id, event_id, post_title, post_type, status, is_category, associated_media, failed_media) VALUES " . implode(", ", $data_to_insert);
			$wpdb->query($query);
		}
		//for failed media log
		global $wpdb;
		$table_name = $wpdb->prefix . 'failed_media';

		if (!empty($failed_media_log)) {
			// Data for batch insert
			$data_to_insert = array();

			foreach ($failed_media_log as $item) {
				$inserted_id = $item['post_id'];
				$post_title = $item['post_title'];
				$media_id = $item['media_id'];
				$image_url = $item['actual_url'];
				$status = 'failed';
				// Correct the number of placeholders and arguments
				$data_to_insert[] = $wpdb->prepare(
					"(%d, %s, %s,  %d, %s , %s)",
					$inserted_id,
					$hash_key,
					$post_title,
					$media_id,
					$image_url,
					$status
				);
			}

			// Batch insert for boosting speed
			if (!empty($data_to_insert)) {
				$query = "INSERT INTO $table_name (post_id, event_id, title,  media_id, actual_url,status) VALUES " . implode(", ", $data_to_insert);
				$wpdb->query($query);
			}
		}


		// $download_log_url = $log_manager_instance->Insert_log_details($log_value,$line_number,$hash_key);
		// $failed_media_link = $log_manager_instance->failedMediaExport($failed_media_log,$line_number,$hash_key);

		// if (!empty($failed_media_log) && is_array($failed_media_log)) {
		// 	$response['media-progress'] = true;
		// } else {
		// 	$response['media-progress'] = false;
		// }




		// $upload = wp_upload_dir();
		// $upload_base_url = $upload['baseurl'];
		$response['success'] = true;
		$response['log_value'] = $log_value;
		$response['media_link'] = $media_link ?? null;
		$response['download_log_link'] = $download_log_url ?? null;
		if ($rollback_option == 'true') {
			$response['rollback'] = true;
		}
		$total_records = $wpdb->get_results("SELECT status FROM $log_table_name WHERE hash_key = '$hash_key' ", ARRAY_A);
		if ($total_records[0]['status'] == 'Completed') {
			if (get_option('failed_line_number')) {
				delete_option('failed_line_number');
			}
			if (get_option('total_attachment_ids')) {
				delete_option('total_attachment_ids');
			}
			if (get_option('failed_attachment_ids')) {
				delete_option('failed_attachment_ids');
			}
		}

		echo wp_json_encode($response);


		wp_die();
	}

	/**
	 * Starts the import process
	 */

	public function background_starts_function()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		$hash_key  = sanitize_key($_POST['HashKey']);
		$check = sanitize_text_field($_POST['Check']);
		$unmatched_row_value = get_option('sm_uci_pro_settings');
		$unmatched_row = $unmatched_row_value['unmatchedrow'];
		$file_iteration = get_option('sm_bulk_import_free_iteration_limit');
		global $wpdb;

		//first check then set on	
		$upload_dir = SaveMapping::$smackcsv_instance->create_upload_dir();
		$import_txt_path = $upload_dir . 'import_state.txt';
		chmod($import_txt_path, 0777);
		$import_state_arr = array();

		$open_file = fopen($import_txt_path, "w");
		$import_state_arr = array('import_state' => 'on', 'import_stop' => 'on');
		$state_arr = serialize($import_state_arr);
		fwrite($open_file, $state_arr);
		fclose($open_file);

		$helpers_instance = ImportHelpers::getInstance();
		$core_instance = CoreFieldsImport::getInstance();
		$import_config_instance = ImportConfiguration::getInstance();
		$log_manager_instance = LogManager::getInstance();
		global $core_instance;
		global $uci_woocomm_meta, $uci_woocomm_bundle_meta, $product_attr_instance, $wpmlimp_class;

		$file_table_name = $wpdb->prefix . "smackcsv_file_events";
		$template_table_name = $wpdb->prefix . "ultimate_csv_importer_mappingtemplate";
		$log_table_name = $wpdb->prefix . "import_detail_log";

		$response = [];

		$background_values = $wpdb->get_results("SELECT mapping , module  FROM $template_table_name WHERE `eventKey` = '$hash_key' ");
		foreach ($background_values as $values) {
			$mapped_fields_values = $values->mapping;
			$selected_type = $values->module;
		}

		$get_id = $wpdb->get_results("SELECT id , mode ,file_name , total_rows FROM $file_table_name WHERE `hash_key` = '$hash_key'");
		$get_mode = $get_id[0]->mode;
		$total_rows = $get_id[0]->total_rows;
		$file_name = $get_id[0]->file_name;
		$file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
		if (empty($file_extension)) {
			$file_extension = 'xml';
		}
		if($file_extension == 'xlsx' || $file_extension == 'xls'){
			$file_extension = 'csv';                    
		}
		$file_size = filesize($upload_dir . $hash_key . '/' . $hash_key);
		$filesize = $helpers_instance->formatSizeUnits($file_size);

		$remain_records = $total_rows - 1;
		$fields = $wpdb->insert($log_table_name, array('file_name' => $file_name, 'hash_key' => $hash_key, 'total_records' => $total_rows, 'filesize' => $filesize, 'processing_records' => 1, 'remaining_records' => $remain_records));

		$map = unserialize($mapped_fields_values);
		if ($file_extension == 'csv' || $file_extension == 'txt') {
			if (!ini_get("auto_detect_line_endings")) {
				ini_set("auto_detect_line_endings", true);
			}
			$info = [];
			if (($h = fopen($upload_dir . $hash_key . '/' . $hash_key, "r")) !== FALSE) {
				// Convert each line into the local $data variable
				$line_number = 0;
				$header_array = [];
				$value_array = [];
				$addHeader = true;

				$delimiters = array(',', '\t', ';', '|', ':', '&nbsp');
				$file_path = $upload_dir . $hash_key . '/' . $hash_key;
				$delimiter = SaveMapping::$validatefile->getFileDelimiter($file_path, 5);
				$array_index = array_search($delimiter, $delimiters);
				if ($array_index == 5) {
					$delimiters[$array_index] = ' ';
				}
				while (($data = fgetcsv($h, 0, $delimiters[$array_index])) !== FALSE) {
					// Read the data from a single line
					array_push($info, $data);

					if ($line_number == 0) {
						$header_array = $info[$line_number];
					} else {
						$value_array = $info[$line_number];
						$get_arr = $this->main_import_process($map, $header_array, $value_array, $selected_type, $get_mode, $line_number, $check, $hash_key, $unmatched_row, '', '', '');
						$post_id = $get_arr['id'];
						$core_instance->detailed_log = $get_arr['detail_log'];
						$media_log = $get_arr['media_log'];
						$failed_media_log  = $get_arr['failed_media_log'];
						$helpers_instance->get_post_ids($post_id, $hash_key);

						$import_table_name = $wpdb->prefix . "import_postID";
						$medias_fields = $wpdb->get_results("INSERT INTO $import_table_name (post_id , line_number) VALUES ($post_id  , $line_number )");

						$remaining_records = $total_rows - $line_number;
						$fields = $wpdb->get_results("UPDATE $log_table_name SET processing_records = $line_number , remaining_records = $remaining_records , status = 'Processing' WHERE hash_key = '$hash_key'");

						if ($line_number == $total_rows) {
							$fields = $wpdb->get_results("UPDATE $log_table_name SET status = 'Completed' WHERE hash_key = '$hash_key'");
						}

						if (count($core_instance->detailed_log) > $file_iteration) {
							$log_manager_instance->get_event_log($hash_key, $file_name, $file_extension, $get_mode, $total_rows, $selected_type, $core_instance->detailed_log, $addHeader);
							$addHeader = false;
							$core_instance->detailed_log = [];
							$failed_media_log = [];
							$media_log = [];
						}
					}

					// get the pause or resume state
					$open_txt = fopen($import_txt_path, "r");
					$read_text_ser = fread($open_txt, filesize($import_txt_path));
					$read_state = unserialize($read_text_ser);
					fclose($open_txt);

					if ($read_state['import_stop'] == 'off') {
						return;
					}

					while ($read_state['import_state'] == 'off') {
						$open_txts = fopen($import_txt_path, "r");
						$read_text_sers = fread($open_txts, filesize($import_txt_path));
						$read_states = unserialize($read_text_sers);
						fclose($open_txts);

						if ($read_states['import_state'] == 'on') {
							break;
						}

						if ($read_states['import_stop'] == 'off') {
							return;
						}
					}
					$line_number++;
				}
				fclose($h);
			}
		}
		if($file_extension == 'tsv'){
			if (!ini_get("auto_detect_line_endings")) {
				ini_set("auto_detect_line_endings", true);
			}
			$info = [];
			if (($h = fopen($upload_dir . $hash_key . '/' . $hash_key, "r")) !== FALSE) {
				// Convert each line into the local $data variable
				$line_number = 0;
				$header_array = [];
				$value_array = [];
				$addHeader = true;
				$file_path = $upload_dir . $hash_key . '/' . $hash_key;
				$delimiter = SaveMapping::$validatefile->getFileDelimiter($file_path, 5);
				while (($data = fgetcsv($h, 0, "\t")) !== FALSE) {
					// Read the data from a single line
					
					array_push($info, $data);
					if ($line_number == 0) {
						$header_array = $info[$line_number];
					} else {
						$value_array = $info[$line_number];
						$get_arr = $this->main_import_process($map, $header_array, $value_array, $selected_type, $get_mode, $line_number, $check, $hash_key, $unmatched_row, '', '', '');
						$post_id = $get_arr['id'];
						$core_instance->detailed_log = $get_arr['detail_log'];
						$media_log = $get_arr['media_log'];
						$failed_media_log  = $get_arr['failed_media_log'];
						$helpers_instance->get_post_ids($post_id, $hash_key);

						$import_table_name = $wpdb->prefix . "import_postID";
						$medias_fields = $wpdb->get_results("INSERT INTO $import_table_name (post_id , line_number) VALUES ($post_id  , $line_number )");

						$remaining_records = $total_rows - $line_number;
						$fields = $wpdb->get_results("UPDATE $log_table_name SET processing_records = $line_number , remaining_records = $remaining_records , status = 'Processing' WHERE hash_key = '$hash_key'");

						if ($line_number == $total_rows) {
							$fields = $wpdb->get_results("UPDATE $log_table_name SET status = 'Completed' WHERE hash_key = '$hash_key'");
						}

						if (count($core_instance->detailed_log) > $file_iteration) {
							$log_manager_instance->get_event_log($hash_key, $file_name, $file_extension, $get_mode, $total_rows, $selected_type, $core_instance->detailed_log, $addHeader);
							$addHeader = false;
							$core_instance->detailed_log = [];
							$failed_media_log = [];
							$media_log = [];
						}
					}

					// get the pause or resume state
					$open_txt = fopen($import_txt_path, "r");
					$read_text_ser = fread($open_txt, filesize($import_txt_path));
					$read_state = unserialize($read_text_ser);
					fclose($open_txt);

					if ($read_state['import_stop'] == 'off') {
						return;
					}

					while ($read_state['import_state'] == 'off') {
						$open_txts = fopen($import_txt_path, "r");
						$read_text_sers = fread($open_txts, filesize($import_txt_path));
						$read_states = unserialize($read_text_sers);
						fclose($open_txts);

						if ($read_states['import_state'] == 'on') {
							break;
						}

						if ($read_states['import_stop'] == 'off') {
							return;
						}
					}
					$line_number++;
				}
				fclose($h);
			}
		}
		if ($file_extension == 'xml') {
			$path = $upload_dir . $hash_key . '/' . $hash_key;
			$xml_instance = XmlHandler::getInstance();

			$line_number = 0;
			$header_array = [];
			$value_array = [];
			$addHeader = true;
			for ($line_number = 0; $line_number < $total_rows; $line_number++) {
				$xml_class = new XmlHandler();
				$parse_xml = $xml_class->parse_xmls($hash_key, $line_number);

				$i = 0;
				foreach ($parse_xml as $xml_key => $xml_value) {
					if (is_array($xml_value)) {
						foreach ($xml_value as $e_key => $e_value) {
							$header_array['header'][$i] = $e_value['name'];
							$value_array['value'][$i] = $e_value['value'];
							$i++;
						}
					}
				}
				$xml = simplexml_load_file($path);
				foreach ($xml->children() as $child) {
					$tag = $child->getName();
				}
				$total_xml_count = $this->get_xml_count($path, $tag);
				if ($total_xml_count == 0  || $tag == 'channel') {
					$sub_child = $this->get_child($child, $path);
					$tag = $sub_child['child_name'];
					$total_xml_count = $sub_child['total_count'];
				}
				$doc = new \DOMDocument();
				$doc->load($path);

				foreach ($map as $field => $value) {
					foreach ($value as $head => $val) {
						if (preg_match('/{/', $val) && preg_match('/}/', $val)) {
							preg_match_all('/{(.*?)}/', $val, $matches);
							$line_numbers = $line_number + 1;
							$val = preg_replace("{" . "(" . $tag . "[+[0-9]+])" . "}", $tag . "[" . $line_numbers . "]", $val);
							for ($i = 0; $i < count($matches[1]); $i++) {
								$matches[1][$i] = preg_replace("(" . $tag . "[+[0-9]+])", $tag . "[" . $line_numbers . "]", $matches[1][$i]);
								$value = $this->parse_element($doc, $matches[1][$i], $line_number);
								$search = '{' . $matches[1][$i] . '}';
								$val = str_replace($search, $value, $val);
							}
							$mapping[$field][$head] = $val;
						} else {
							$mapping[$field][$head] = $val;
						}
					}
				}
				$get_arr = $this->main_import_process($mapping, $header_array['header'], $value_array['value'], $selected_type, $get_mode, $line_number, $check, $hash_key, $unmatched_row, '', '', '');
				$post_id = $get_arr['id'];
				$core_instance->detailed_log = $get_arr['detail_log'];
				$media_log = $get_arr['media_log'];
				$failed_media_log  = $get_arr['failed_media_log'];
				$helpers_instance->get_post_ids($post_id, $hash_key);
				$line_numbers = $line_number + 1;
				$remaining_records = $total_rows - $line_numbers;
				$fields = $wpdb->get_results("UPDATE $log_table_name SET processing_records = $line_number + 1 , remaining_records = $remaining_records, status = 'Processing' WHERE hash_key = '$hash_key'");

				if ($line_number == $total_rows - 1) {
					$fields = $wpdb->get_results("UPDATE $log_table_name SET status = 'Completed' WHERE hash_key = '$hash_key'");
				}

				if (count($core_instance->detailed_log) > $file_iteration) {
					$log_manager_instance->get_event_log($hash_key, $file_name, $file_extension, $get_mode, $total_rows, $selected_type, $core_instance->detailed_log, $line_number);
					$addHeader = false;
					$core_instance->detailed_log = [];
					$failed_media_log = [];
					$media_log = [];
				}

				$open_txt = fopen($import_txt_path, "r");
				$read_text_ser = fread($open_txt, filesize($import_txt_path));
				$read_state = unserialize($read_text_ser);
				fclose($open_txt);

				if ($read_state['import_stop'] == 'off') {
					return;
				}

				while ($read_state['import_state'] == 'off') {
					$open_txts = fopen($import_txt_path, "r");
					$read_text_sers = fread($open_txts, filesize($import_txt_path));
					$read_states = unserialize($read_text_sers);
					fclose($open_txts);

					if ($read_states['import_state'] == 'on') {
						break;
					}

					if ($read_states['import_stop'] == 'off') {
						return;
					}
				}
			}
		}

		if (!empty($core_instance->detailed_log) && is_countable($core_instance->detailed_log)) {
			if (count($core_instance->detailed_log) > 0) {
				$log_manager_instance->get_event_log($hash_key, $file_name, $file_extension, $get_mode, $total_rows, $selected_type, $core_instance->detailed_log, $addHeader);
			}
		}
		
		$log_manager_instance->manage_records($hash_key, $selected_type, $file_name, $total_rows);

		$count = count($info);

		for ($i = 1; $i <= $count; $i++) {

			if (is_array($info)) {
				foreach ($info[$i] as $key => $value) {
					if (preg_match("/<img/", $value)) {
						// SaveMapping::$smackcsv_instance->image_schedule();
						// $image = $wpdb->get_results("select * from {$wpdb->prefix}ultimate_csv_importer_shortcode_manager where hash_key = '{$hash_key}'");
						// if (!empty($image)) {
						// 	SaveMapping::$smackcsv_instance->delete_image_schedule();
						// }
					}
				}
			}
		}

		$upload = wp_upload_dir();
		$upload_base_url = $upload['baseurl'];
		$upload_url = $upload_base_url . '/smack_uci_uploads/imports/';
		$response['success'] = true;
		$result['url'] = $upload_url;
		unlink($import_txt_path);
		echo wp_json_encode($response);
		wp_die();
	}

	public function get_child($child, $path)
	{
		foreach ($child->children() as $sub_child) {
			$sub_child_name = $sub_child->getName();
		}
		$total_xml_count = $this->get_xml_count($path, $sub_child_name);
		if ($total_xml_count == 0 || $sub_child_name == 'channel') {
			$this->get_child($sub_child, $path);
		} else {
			$result['child_name'] = $sub_child_name;
			$result['total_count'] = $total_xml_count;
			return $result;
		}
	}

	public function get_xml_count($eventFile, $child_name)
	{
		$doc = new \DOMDocument();
		$doc->load($eventFile);
		$nodes = $doc->getElementsByTagName($child_name);
		$total_row_count = $nodes->length;
		return $total_row_count;
	}

	public function parse_element($xml, $query)
	{
		$query = strip_tags($query);
		$xpath = new \DOMXPath($xml);
		$entries = $xpath->query($query);
		$content = $entries->item(0)->textContent;
		return $content;
	}
	public function manage_filteration($manage_filter, $header_array, $value_array, $core_instance, $line_number, $hash_key) {
		global $wpdb;
		$helpers_instance = ImportHelpers::getInstance();
		$log_table_name = $wpdb->prefix . "import_detail_log";
		$unikey_name = 'hash_key';
		$unikey_value = $hash_key;
		
		$updated_row_counts = $helpers_instance->update_count($unikey_value, $unikey_name);
		$skipped_count = $updated_row_counts['skipped'];
		
		$conditions = [];
		foreach ($manage_filter as $filter) {
			$element = $filter['element'];
			$rule = strtolower(trim($filter['rule']));
			$value = trim($filter['value']);
			$condition = isset($filter['condition']) ? strtoupper(trim($filter['condition'])) : '';
			
			$key = array_search($element, $header_array);
			if ($key === false) continue;
			
			$actual_value = trim($value_array[$key]);
			switch ($rule) {
				case 'equals':
					$match = $actual_value == $value;
					break;
				case 'not_equals':
					$match = $actual_value != $value;
					break;
				case 'greater_than':
					$match = is_numeric($actual_value) && is_numeric($value) && $actual_value > $value;
					break;
				case 'less_than':
					$match = is_numeric($actual_value) && is_numeric($value) && $actual_value < $value;
					break;
				case 'equals_or_greater_than':
					$match = is_numeric($actual_value) && is_numeric($value) && $actual_value >= $value;
					break;
				case 'equals_or_less_than':
					$match = is_numeric($actual_value) && is_numeric($value) && $actual_value <= $value;
					break;
				case 'is_empty':
					$match = empty($actual_value);
					break;
				case 'is_not_empty':
					$match = !empty($actual_value);
					break;
				case 'contains':
					$match = str_contains($actual_value, $value);
					break;
				case 'not_contains':
					$match = !str_contains($actual_value, $value);
					break;
				default:
					$match = false;
			}

			$conditions[] = [
				"match" => (bool) $match,
				"condition" => $condition
			];
		}
		if (empty($conditions)) {
			return;
		}
		
		// First, evaluate AND conditions
		$filtered_conditions = [$conditions[0]['match']];
		for ($i = 0; $i < count($conditions) - 1; $i++) {
			if ($conditions[$i]['condition'] === 'AND') {
				$filtered_conditions[count($filtered_conditions) - 1] &= $conditions[$i + 1]['match'];
			} else {
				$filtered_conditions[] = $conditions[$i + 1]['match'];
			}
		}
		
		// Then evaluate OR conditions
		$result = false;
		foreach ($filtered_conditions as $value) {
			$result |= $value;
		}
		
		if (!$result) {
			$core_instance->detailed_log[$line_number] = [
				'Message' => "Skipped: Data does not match filter conditions.",
				'state' => 'Skipped'
			];
			$wpdb->query($wpdb->prepare("UPDATE $log_table_name SET skipped = %d WHERE $unikey_name = %s", $skipped_count, $unikey_value));
		}
	}	
	public function main_import_process($map, $header_array, $value_array, $selected_type, $get_mode, $line_number, $check, $hash_key, $unmatched_row, $gmode = null, $templatekey = null, $media_type = null)
	{
		$return_arr = [];
		$core_instance = CoreFieldsImport::getInstance();
		$order_meta = $attr_data =$meta_data = '';
		$jetengine_map = [];
		$meta_data = '';
		$att_data = '';
		$woocom_image = '';
		$bsi_data = '';
		$post_id='';
		global $core_instance, $uci_woocomm_meta, $uci_woocomm_bundle_meta, $product_attr_instance, $wpmlimp_class;
		/*** check manage filteration */
		$this->check_manage_filter ? $this->manage_filteration($this->manage_filter,$header_array,$value_array,$core_instance,$line_number,$hash_key) : '';
		if (preg_match("/(Can't|Skipped|Duplicate)/", $core_instance->detailed_log[$line_number]['Message']) === 0) { 
			foreach ($map as $group_name => $group_value) {
				if ($group_name == 'CORE') {
					$wpml_map = isset($map['WPML']) ? $map['WPML'] : '';
					$media_map = isset($map['FEATURED_IMAGE_META']) ? $map['FEATURED_IMAGE_META'] : '';
					$core_instance = CoreFieldsImport::getInstance();
					if ($selected_type == 'WooCommerce Orders') {
						$order_meta = !empty($map['ORDERMETA']) ? $map['ORDERMETA'] : '';
					}
					if($selected_type=='WooCommerce Product'){
						$meta_data = isset($map['ECOMMETA'])?$map['ECOMMETA']:'';
						$attr_data = isset($map['ATTERMETA'])?$map['ATTERMETA']:'';
					}
					if($selected_type=='WooCommerce Customer'){
						$bsi_data = isset($map['BSI'])?$map['BSI']:'';
					}
					$post_id = $core_instance->set_core_values($header_array, $value_array, $map['CORE'], $selected_type, $get_mode, $line_number, $check, $hash_key, $unmatched_row, $gmode, $templatekey, $wpml_map, $media_map, $media_type, $order_meta,$meta_data,$attr_data,$bsi_data);
				}
			}
			foreach ($map as $group_name => $group_value) {

				switch ($group_name) {
	case 'ELEMENTOR':
							$elementor_instance = ElementorImport::getInstance();
							$elementor_instance->set_elementor_value($header_array, $value_array, $map['ELEMENTOR'], $post_id, $selected_type, $hash_key, $gmode, $templatekey);
							break;
	
					case 'AIOSEO':
						$all_seo_instance = AllInOneSeoImport::getInstance();
						$all_seo_instance->set_all_seo_values($header_array, $value_array, $map['AIOSEO'], $post_id, $selected_type, $get_mode);
						break;
	
					case 'RANKMATH':
						$rankmath_instance = RankMathImport::getInstance();
						$rankmath_instance->set_rankmath_values($header_array, $value_array, $map['RANKMATH'], $post_id, $selected_type);
						break;
	
					// case 'ECOMMETA':
					// 	$variation_id = isset($variation_id) ? $variation_id : '';
					// 	$uci_woocomm_meta->set_product_meta_values($header_array, $value_array, $map['ECOMMETA'], $post_id, $variation_id, $selected_type, $line_number, $get_mode, $hash_key);
					// 	break;
					// case 'ATTRMETA':
					// 	$product_meta_instance = ProductMetaImport::getInstance();
					// 	$variation_id = isset($variation_id) ? $variation_id : '';
					// 	$wpml_map = isset($map['WPML']) ? $map['WPML'] : '';
					// 	$woocom_image = isset($map['PRODUCTIMAGEMETA']) ? $map['PRODUCTIMAGEMETA'] : [];
					// 	$product_meta_instance->set_product_meta_values($header_array, $value_array, $map['ATTRMETA'], $post_id, $variation_id, $selected_type, $line_number, $get_mode, $hash_key);
					// 	break;
	
					case 'JE':
						$jet_engine_instance = JetEngineImport::getInstance();
						$jet_engine_instance->set_jet_engine_values($header_array, $value_array, $map['JE'], $post_id, $selected_type, $get_mode, $hash_key, $line_number);
						break;
						
					case 'POLYLANG':
						$polylang_instance = PolylangImport::getInstance();
						$polylang_instance->set_polylang_values($header_array, $value_array, $map['POLYLANG'], $post_id, $selected_type);
						break;
	
					case 'COUPONMETA':
						$variation_id = isset($variation_id) ? $variation_id : '';
						$product_meta_instance = ProductMetaImport::getInstance();
						$poly_array = isset($map['POLYLANG']) ? $map['POLYLANG'] : [];
						$uci_woocomm_meta->set_product_meta_values($header_array, $value_array, $map['COUPONMETA'], $post_id, $variation_id, $selected_type, $line_number, $get_mode, $hash_key);
						break;
	
					case 'PPOMMETA':
						$meta_type = 'PPOMMETA';
						$uci_woocomm_meta->set_product_meta_values($header_array, $value_array, $map['PPOMMETA'], $post_id, '', $meta_type, $line_number, $get_mode, $hash_key);
						break;
						
					case 'EPOMETA':	
							$meta_type = 'EPOMETA';
							$uci_woocomm_meta->set_product_meta_values($header_array, $value_array, $map['EPOMETA'], $post_id, '', $meta_type, $line_number, $get_mode, $hash_key);
							break;
					case 'WCPAMETA' :
							$meta_type = 'WCPAMETA';
							$uci_woocomm_meta->set_product_meta_values($header_array, $value_array, $map['WCPAMETA'], $post_id, '', $meta_type, $line_number, $get_mode, $hash_key);
							break;
	
					case 'BUNDLEMETA':
						$bundle_type = 'BUNDLEMETA';
						$uci_woocomm_meta->set_product_meta_values($header_array, $value_array, $map['BUNDLEMETA'], $post_id, '', $bundle_type, $line_number, $get_mode, $hash_key);
						break;

					case 'EVENTS':
							if (is_plugin_active('events-manager/events-manager.php') && $selected_type == 'event') {
							$merge = [];
							$merge = array_merge($map['CORE'], $map['EVENTS']);
							$map['TERMS'] = isset($map['TERMS']) ? $map['TERMS'] : '';
							$events_instance = EventsManagerImport::getInstance();
							$events_instance->set_events_values($header_array, $value_array, $merge, $post_id, $selected_type, $get_mode, $map['TERMS'], $gmode);
							break;
							}elseif (is_plugin_active('the-events-calendar/the-events-calendar.php') && $selected_type == 'tribe_events') {
							$merge = [];
							$merge = array_merge($map['CORE'], $map['EVENTS']);
							$map['TERMS'] = isset($map['TERMS']) ? $map['TERMS'] : '';
							$events_instance = EventCalendarImport::getInstance();
							$events_instance->set_events_values($header_array, $value_array, $merge, $post_id, $selected_type, $get_mode, $map['TERMS'], $gmode);
							break;
							}
	
					case 'JECCT':
						$jet_engine_cct_instance = JetEngineCCTImport::getInstance();
						$jet_engine_cct_instance->set_jet_engine_cct_values($header_array, $value_array, $map['JECCT'], $post_id, $selected_type, $get_mode, $hash_key, $line_number);
						break;
	
					case 'JECPT':
						$jet_engine_cpt_instance = JetEngineCPTImport::getInstance();
						$jet_engine_cpt_instance->set_jet_engine_cpt_values($header_array, $value_array, $map['JECPT'], $post_id, $selected_type, $get_mode, $hash_key, $line_number);
						break;
						case 'JEREVIEW':
							$jet_engine_instance = JetReviewsImport::getInstance();
							$jet_engine_instance->set_jet_reviews_values($header_array, $value_array, $map['JEREVIEW'], $post_id, $selected_type, $get_mode, $hash_key, $line_number, $gmode, $templatekey);
							break;
						case 'JEBOOKING':
							$jet_engine_instance = JetBookingImport::getInstance();
							$jet_engine_instance->set_jet_booking_values($header_array, $value_array, $map['JEBOOKING'], $post_id, $selected_type, $get_mode, $hash_key, $line_number, $gmode, $templatekey);
							break;
							
					case 'CFS':
						$cfs_instance = CFSImport::getInstance();
						$cfs_instance->set_cfs_values($line_number, $header_array, $value_array, $map['CFS'], $post_id, $selected_type, $hash_key);
						break;
	
					case 'BSI':
						global $billing_class,$customer_billing_class;
						if($selected_type == 'WooCommerce Customer'){
							//$customer_billing_class->set_bsi_values($header_array, $value_array, $map['BSI'], $post_id, $selected_type);
						}else{
							$billing_class->set_bsi_values($header_array, $value_array, $map['BSI'], $post_id, $selected_type);
						}
						break;
	
					case 'WPMEMBERS':
						global $wpmember_class;
						$wpmember_class->set_wpmembers_values($line_number, $header_array, $value_array, $map['WPMEMBERS'], $post_id, $selected_type, $hash_key);
						break;
					
					case 'MEMBERS':
						global $member_class;
						$member_class->set_multirole_values($header_array, $value_array, $map['MEMBERS'], $post_id, $selected_type);
						break;
	
					case 'TERMS':
						$terms_taxo_instance = TermsandTaxonomiesImport::getInstance();
						$poly_array = isset($map['POLYLANG']) ? $map['POLYLANG'] : '';
						$terms_taxo_instance->set_terms_taxo_values($header_array, $value_array, $map['TERMS'], $post_id, $selected_type, $get_mode, $line_number, $poly_array);
						break;
	
					case 'CORECUSTFIELDS':
						$wordpress_custom_instance = WordpressCustomImport::getInstance();
						$wordpress_custom_instance->set_wordpress_custom_values($header_array, $value_array, $map['CORECUSTFIELDS'], $post_id, $selected_type, $hash_key, $line_number, $templatekey, $gmode);
						break;
	
					case 'FORUM':
						$bbpress_instance = BBPressImport::getInstance();
						$bbpress_instance->set_bbpress_values($header_array, $value_array, $map['FORUM'], $post_id, $selected_type, $get_mode);
						break;
	
					case 'TOPIC':
						$bbpress_instance = BBPressImport::getInstance();
						$bbpress_instance->set_bbpress_values($header_array, $value_array, $map['TOPIC'], $post_id, $selected_type, $get_mode);
						break;
	
					case 'REPLY':
						$bbpress_instance = BBPressImport::getInstance();
						$bbpress_instance->set_bbpress_values($header_array, $value_array, $map['REPLY'], $post_id, $selected_type, $get_mode);
						break;
					case 'BP':
						global $buddy_class;
						$buddy_class->set_buddy_values($header_array, $value_array, $map['BP'], $post_id, $selected_type);
						break;
					case 'LPCOURSE':
						$learn_merge = [];
						$learn_merge = array_merge($map['LPCOURSE'], $map['LPCURRICULUM']);
	
						$learnpress_instance = LearnPressImport::getInstance();
						$learnpress_instance->set_learnpress_values($header_array, $value_array, $learn_merge, $post_id, $selected_type);
						break;
	
					case 'LPLESSON':
						$learnpress_instance = LearnPressImport::getInstance();
						$learnpress_instance->set_learnpress_values($header_array, $value_array, $map['LPLESSON'], $post_id, $selected_type);
						break;
	
					case 'LPQUIZ':
						$learnpress_instance = LearnPressImport::getInstance();
						$learnpress_instance->set_learnpress_values($header_array, $value_array, $map['LPQUIZ'], $post_id, $selected_type);
						break;
	
					case 'LPQUESTION':
						$learnpress_instance = LearnPressImport::getInstance();
						$learnpress_instance->set_learnpress_values($header_array, $value_array, $map['LPQUESTION'], $post_id, $selected_type);
						break;
	
					case 'LPORDER':
						$learnpress_instance = LearnPressImport::getInstance();
						$learnpress_instance->set_learnpress_values($header_array, $value_array, $map['LPORDER'], $post_id, $selected_type);
						break;
	
					case 'LIFTERLESSON':
						$lifterlms_instance = LifterLmsImport::getInstance();
						$lifterlms_instance->set_lifterlms_values($header_array, $value_array, $map['LIFTERLESSON'], $post_id, $selected_type, $get_mode);
						break;
	
					case 'LIFTERCOURSE':
						$lifterlms_instance = LifterLmsImport::getInstance();
						$lifterlms_instance->set_lifterlms_values($header_array, $value_array, $map['LIFTERCOURSE'], $post_id, $selected_type, $get_mode);
						break;
	
					case 'LIFTERCOUPON':
						$lifterlms_instance = LifterLmsImport::getInstance();
						$lifterlms_instance->set_lifterlms_values($header_array, $value_array, $map['LIFTERCOUPON'], $post_id, $selected_type, $get_mode);
						break;
	
					case 'JOB':
						$job_listing_instance = JobListingImport::getInstance();
						$job_listing_instance->set_job_listing_values($header_array, $value_array, $map['JOB'], $post_id, $selected_type);
						break;
	
					case 'WPML':
						$wpmlimp_class = WPMLImport::getInstance();
						$wpmlimp_class->set_wpml_values($header_array, $value_array, $map['WPML'], $post_id, $selected_type, $line_number);
						break;
	
					case 'METABOX':
						$metabox_instance = MetaBoxImport::getInstance();
						$metabox_instance->set_metabox_values($line_number, $header_array, $value_array, $map['METABOX'], $post_id, $selected_type, $hash_key);
						break;
	
					case 'PODS':
						$map['WPML'] = isset($map['WPML']) ? $map['WPML'] : '';
						$pods_instance = PodsImport::getInstance();
						$pods_instance->set_pods_values($line_number, $header_array, $value_array, $map['PODS'], $post_id, $selected_type, $hash_key, $map['WPML']);
						break;
					case 'ACF':
						$acf_image = isset($map['ACFIMAGEMETA']) ? $map['ACFIMAGEMETA'] : '';
						$acf_instance = ACFImport::getInstance();
						$acf_instance->set_acf_values($header_array, $value_array, $map['ACF'], $acf_image, $post_id, $selected_type, $get_mode, $hash_key, $line_number);
						break;
					case 'TYPES':
						$types_image = isset($map['TYPESIMAGEMETA']) ? $map['TYPESIMAGEMETA'] : '';
						$toolset_instance = ToolsetImport::getInstance();
						$toolset_instance->set_toolset_values($header_array, $value_array, $map['TYPES'], $types_image, $post_id, $selected_type, $get_mode, $hash_key, $line_number, $gmode, $templatekey);
						break;
					case 'FIFUPOSTS':
						$fifu_instance = FIFUImport::getInstance();
						$fifu_instance->set_fifu_values($header_array, $value_array, $map['FIFUPOSTS'], $post_id, $selected_type, $get_mode);
						break;
					case 'FIFUPAGE':
						$fifu_instance = FIFUImport::getInstance();
						$fifu_instance->set_fifu_values($header_array, $value_array, $map['FIFUPAGE'], $post_id, $selected_type, $get_mode);
						break;
					case 'FIFUCUSTOMPOST':
						$fifu_instance = FIFUImport::getInstance();
						$fifu_instance->set_fifu_values($header_array, $value_array, $map['FIFUCUSTOMPOST'], $post_id, $selected_type, $get_mode);
						break;
					case 'SLIMSEO':
        $slimseo_instance = SlimSeoImport::getInstance();
        $slimseo_instance->set_slimseo_values(
            $header_array,
            $value_array,
            $map['SLIMSEO'],
            $post_id,
            $selected_type,
            $hash_key,
            $gmode,
            $templatekey,
            $line_number
        );
        break;

					case 'YOASTSEO':
						$yoast_instance = YoastSeoImport::getInstance();
						$yoast_instance->set_yoast_values($line_number, $header_array, $value_array, $map['YOASTSEO'], $post_id, $selected_type, $hash_key, $gmode, $templatekey);
						break;
					case 'JEREL':
						$jet_engine_rel_instance = JetEngineRELImport::getInstance();
						$jet_engine_rel_instance->set_jet_engine_rel_values($header_array, $value_array, $map['JEREL'], $post_id, $selected_type, $get_mode, $hash_key, $line_number, $gmode, $templatekey = null);
	
					case 'LISTINGMETA':
						$listing_instance = ListingImport::getInstance();
						$listing_instance->set_listing_values($header_array, $value_array, $map['LISTINGMETA'], $post_id, $selected_type);
						break;
				}
			}
			if (get_option('total_attachment_ids')) {
				$stored_ids = unserialize(get_option('total_attachment_ids', ''));
				delete_option('total_attachment_ids');
				$core_instance->detailed_log[$line_number]['total_image'] = (is_array($stored_ids) && count($stored_ids) > 0) ? count($stored_ids) : '';
				$core_instance->detailed_log[$line_number]['failed_image_count'] = null;
			}
			if (get_option('failed_attachment_ids')) {
				$stored_ids = unserialize(get_option('failed_attachment_ids', ''));
				delete_option('failed_attachment_ids');
				$core_instance->detailed_log[$line_number]['failed_image_count'] = (is_array($stored_ids) && count($stored_ids) > 0) ? count($stored_ids) : '';
			}
			$return_arr['failed_media_log'] = 	!empty($core_instance->failed_media_data) ? $core_instance->failed_media_data : [];
			$return_arr['media_log'] = 	!empty($core_instance->media_log) ? $core_instance->media_log : [];
			$return_arr['id'] = $post_id;	
		}
		$return_arr['detail_log'] = $core_instance->detailed_log;
		return $return_arr;
	}

	public	function bulk_file_import_function()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		global $wpdb;
		$helpers_instance = ImportHelpers::getInstance();
		$hash_key = sanitize_key($_POST['HashKey']);
		$fileiteration = sanitize_key($_POST['FileIteration']);
		$template_table_name = $wpdb->prefix . "ultimate_csv_importer_mappingtemplate";
		$background_values = $wpdb->get_results("SELECT module  FROM $template_table_name WHERE `eventKey` = '$hash_key' ");
		foreach ($background_values as $values) {
			$selected_type = $values->module;
		}
		update_option('sm_bulk_import_free_iteration_limit', $fileiteration);
		$file_table_name = $wpdb->prefix . "smackcsv_file_events";
		$get_id = $wpdb->get_results("SELECT id , mode ,file_name , total_rows FROM $file_table_name WHERE `hash_key` = '$hash_key'");
		$total_rows = $get_id[0]->total_rows;
		$file_name = $get_id[0]->file_name;
		$file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
		$upload_dir = SaveMapping::$smackcsv_instance->create_upload_dir();
		$file_size = filesize($upload_dir . $hash_key . '/' . $hash_key);
		$filesize = $helpers_instance->formatSizeUnits($file_size);
		$response['total_rows'] = (int)$total_rows;
		$response['file_extension'] = $file_extension;
		$response['file_name'] = $file_name;
		$response['filesize'] = $filesize;
		if ($selected_type == 'elementor_library') {
			$response['file_iteration'] = 1000000;
		} else {
			$response['file_iteration'] = (int)$fileiteration;
		}
		if (get_option('total_attachment_ids')) {
			delete_option('total_attachment_ids');
		}
		echo wp_json_encode($response);
		wp_die();
	}

	public function deactivate_mail()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		$headers = array("Content-type: text/html; charset=UTF-8");
		$headers .= 'MIME-Version: 1.0' . "\r\n";
		$to = 'support@smackcoders.com';
		$subject = 'Reason for csv importer plugin deactivation';
		$message = sanitize_text_field($_REQUEST["reason"]);
		wp_mail($to, $subject, $message, $headers);
		$response = array('success' => true, 'code' => 200);
		echo wp_json_encode($response);
		wp_die();
	}
}
