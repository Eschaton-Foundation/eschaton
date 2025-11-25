<?php
/**
 * WP Ultimate CSV Importer plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\FCSV;

if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly

class MediaHandling{
	private static $instance=null,$smack_instance;
	public $header_array;
	public $value_array;

	public function __construct(){
		
		include_once(ABSPATH . 'wp-admin/includes/image.php');
		add_action('wp_ajax_zip_upload' , array($this , 'zipImageUpload'));	
		add_action('wp_ajax_image_options', array($this , 'imageOptions'));
		add_action('wp_ajax_delete_image' , array($this , 'deleteImage'));
	}

	public static function imageOptions()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		$media_settings['use_ExistingImage'] = sanitize_text_field($_POST['use_ExistingImage']);
		$media_settings['overwriteImage'] = sanitize_text_field($_POST['overwriteImage']);
		$media_settings['enable_postcontent_image'] = sanitize_text_field($_POST['postContent_image_option']);
		$media_settings['newImage'] = sanitize_text_field($_POST['newImage']);
		$media_settings['title'] = sanitize_text_field($_POST['title']);
		$media_settings['caption'] = sanitize_text_field($_POST['caption']);
		$media_settings['alttext'] = sanitize_text_field($_POST['alttext']);
		$media_settings['description'] = sanitize_text_field($_POST['description']);
		$media_settings['file_name'] = sanitize_text_field($_POST['file_name']);
		$media_settings['media_handle_option'] = sanitize_text_field($_POST['media_handle_option']);
		$image_info = array(
			'media_settings'  => $media_settings
		);
		update_option('smack_image_options', $image_info);
		$result['success'] = 'true';
		echo wp_json_encode($result);
		wp_die();
	}

	public static function getInstance()
	{
		if (MediaHandling::$instance == null) {
			MediaHandling::$instance = new MediaHandling;
			MediaHandling::$smack_instance = SmackCSV::getInstance();
			return MediaHandling::$instance;
		}
		return MediaHandling::$instance;
	}




	public function zipImageUpload()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		// Check if the ZIP extension is loaded
		if (!extension_loaded('zip')) {
			$result['success'] = false;
			$result['message'] = 'The PHP ZIP extension is not installed. Please contact your server administrator to install it.';
			echo wp_json_encode($result);
			wp_die();
		}
		// Check if a file was uploaded
		if (isset($_FILES['zipFile']['name'])) {
			$zip_file_name = $_FILES['zipFile']['name'];
			$file_ext = pathinfo($zip_file_name, PATHINFO_EXTENSION);

			// Validate file extension
			if (strtolower($file_ext) !== 'zip') {
				$result['success'] = false;
				$result['message'] = 'Invalid file format. Please upload a zip file.';
				echo wp_json_encode($result);
				wp_die();
			}

			$hash_key = MediaHandling::$smack_instance->convert_string2hash_key($zip_file_name);
			$media_dir = wp_get_upload_dir();
			$upload_dir = MediaHandling::$smack_instance->create_upload_dir();
			$path = $upload_dir . $hash_key . '.zip';
			$extract_path = $media_dir['path'] . '/';

			if (file_exists($path)) {
				chmod($path, 0777);
			}

			move_uploaded_file($_FILES['zipFile']['tmp_name'], $path);
			$zip = new \ZipArchive;
			$res = $zip->open($path);

			if ($res === TRUE) {
				$filename = [];
				$size = [];
				$kbsize = [];

				for ($i = 0; $i < $zip->numFiles; $i++) {

					$filename[$i] = $zip->getNameIndex($i);
					if (substr($zip->getNameIndex($i), -1) == '/' || !$this->isValidImageFile($filename[$i])) {
						continue;
					}
					$sanitized_filename = str_replace(' ', '-', basename($filename[$i]));
					$sanitized_filename = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', $sanitized_filename);
					$full_extract_path = $extract_path . $sanitized_filename;
					$fp = $zip->getStream($filename[$i]);
					$size[$i] = $zip->statIndex($i)['size'];
					$kbsize[$i] = $this->convertToReadableSize($size[$i]);
					$ofp = fopen($full_extract_path, 'w');
					while (!feof($fp)) {
						fwrite($ofp, fread($fp, 8192));
					}

					fclose($fp);
					fclose($ofp);
				}
				$filename = $this->ValidImageFileAdded($filename);
				$kbsize = $this->ImageFileSizeAdded($filename, $kbsize);
				$zip->close();
				$result['success'] = true;
				$result['zip_file_name'] = $zip_file_name;
				$result['count'] = count($filename);
				$result['filename'] = $filename;
				$result['size'] = $kbsize;
			} else {
				$result['success'] = false;
				$result['message'] = 'Failed to open the zip file.';
			}
		} else {
			$result['success'] = false;
			$result['message'] = 'No file uploaded.';
		}

		echo wp_json_encode($result);
		wp_die();
	}
	private function ImageFileSizeAdded($filenames, $sizes)
	{
		$newSizes = [];
		$sizes = array_values($sizes);
		$sizeIndex = 0;
		foreach ($filenames as $index => $filename) {
			if (substr($filename, -1) === '/') {
				// Skip if it's a directory
				continue;
			} else {
				if (isset($sizes[$sizeIndex])) {
					$newSizes[$index] = $sizes[$sizeIndex];
					$sizeIndex++;
				}
			}
		}
		return $newSizes;
	}
	private function ValidImageFileAdded($filename)
	{
		$validExtensions = ['jpg', 'jpeg', 'png', 'svg','webp','bmp'];
		$filteredFiles = [];
		for ($i = 0; $i < count($filename); $i++) {
			$fileInfo = pathinfo($filename[$i]);
			if ( strpos($filename[$i], '__MACOSX/') === 0 || (substr($fileInfo['basename'], 0, 1) === '.') && (!in_array(strtolower($fileInfo['extension']), $validExtensions))) {
				continue;
			} else {
				if (substr($filename[$i], -1) === '/' || in_array(strtolower($fileInfo['extension']), $validExtensions)) {
					$filteredFiles[$i] = $filename[$i];
				}
			}
		}
		$filenames = array_values($filteredFiles);
		return $filenames;
	}
	private function isValidImageFile($filename)
	{
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		if (in_array($ext, ['jpg', 'jpeg', 'png', 'svg','webp', 'bmp'])) {
			return true;
		} else {
			return false;
		}
	}
	public function convertToReadableSize($size)
	{
		$base = log($size) / log(1024);
		$suffix = array("", "KB", "MB", "GB", "TB");
		$f_base = floor($base);
		return round(pow(1024, $base - floor($base)), 1) . $suffix[$f_base];
	}

	public function deleteImage()
	{
		if (!is_user_logged_in() || !current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized access.'], 403);
			return;
		}
	
		// Verify nonce for CSRF protection
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
	
		// Decode JSON safely
		$images = json_decode(stripslashes($_POST['images']), true);
		if (!empty($images)) {
		if (empty($images) || !is_array($images)) {
			wp_send_json_error(['message' => 'Invalid input.'], 400);
			return;
		}
	
		// Get the media upload directory
		$media_dir = wp_get_upload_dir();
		$upload_path = realpath($media_dir['path']); // Get absolute path
	
		if (!$upload_path) {
			wp_send_json_error(['message' => 'Upload directory not found.'], 500);
			return;
		}
	
		foreach ($images as $image) {
			// Sanitize the filename to prevent directory traversal
			$deleteimage = sanitize_file_name(basename($image));
			$file_path = realpath(trailingslashit($upload_path) . $deleteimage);
	
			// Ensure the file is inside the uploads directory
			if (!$file_path || strpos($file_path, $upload_path) !== 0) {
				wp_send_json_error(['message' => 'Invalid file path.'], 403);
				return;
			}
	
			// Delete the file if it exists
			if (file_exists($file_path) && is_file($file_path)) {
				unlink($file_path);
			}
			$result['success'] = 'true';
		} 
	}
	else {
			$result['success'] = 'true';
		}


		echo wp_json_encode($result);
		wp_die();
	}

	public function media_handling($img_url, $post_id, $data_array = null, $plugin = null, $import_type = null, $acf_wpname_element = null, $templatekey = null, $unikey = null, $unikey_name = null, $header_array = null, $value_array = null, $wpml_array = null, $image_metas = null, $line_number = null, $indexs = null, $acf_image_meta = null, $media_type = null, $media_id = null, $jet_child_object_id = null, $parent_object_id = null, $media_mode = null,$featured= false)
	{
		global $wpdb;
		$encodedurl = urlencode($img_url);
		$img_url = urldecode($encodedurl);
		$url = parse_url($img_url);
		$media_handle = get_option('smack_image_options');
		if (isset($url['scheme']) && ($url['scheme'] == 'http' || $url['scheme'] == 'https')) {
			if (strstr($img_url, 'https://drive.google.com')) {
				preg_match('~/d/\K[^/]+(?=/)~', $img_url, $result_id);
				$image_title = isset($result_id[0]) ? $result_id[0] : basename($img_url);
			} else {
				$image_name = basename($img_url);
				$image_title = sanitize_file_name(pathinfo($image_name, PATHINFO_FILENAME));
			}
		} else {
			$image_title = preg_replace('/\\.[^.\\s]{3,4}$/', '', $img_url);
		}

		if (strpos($img_url, 'wp-ultimate-csv-importer-pro/assets/images/loading-image.jpg') !== false) {
			$existing_loading_image_id = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_name LIKE 'loading-image%' AND guid LIKE '%loading-image%' ", ARRAY_A);
			if (!empty($existing_loading_image_id[0]['ID'])) {
				$attach_id = $existing_loading_image_id[0]['ID'];
				return $attach_id;
			}
		}

		// Download external images to your media if true
		if ($media_handle['media_settings']['media_handle_option'] == 'true') {
			if ($media_handle['media_settings']['use_ExistingImage'] == 'true') {
				if (empty($img_url)) {
					$attach_id = $img_url;
					if (!empty($data_array)) {
						foreach ($data_array as $values) {
							$attachment = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "postmeta WHERE meta_key='$values'");

							if ($attachment) {
								delete_post_thumbnail($post_id);
							} else {
								set_post_thumbnail($post_id, $attach_id);
								$this->imageMetaImport($attach_id, $media_handle, $header_array, $value_array,$featured, $media_type);
							}
							$this->imageMetaImport($attach_id, $media_handle, $header_array, $value_array,$featured, $media_type);
							return $attach_id;
						}
					}
				}

				if (is_numeric($img_url)) {
					$attach_id = $img_url;
					if ($attach_id == 0) {
						delete_post_thumbnail($post_id);
					} else {
						if (!empty($data_array['featured_image'])) {
							set_post_thumbnail($post_id, $attach_id);
						}
					}
					$this->imageMetaImport($attach_id, $media_handle, $header_array, $value_array,$featured, $media_type);
					return $attach_id;
				} else {
					$attachment_id = $wpdb->get_results("select ID from {$wpdb->prefix}posts where post_title='$image_title'", ARRAY_A);
				}
				if (!empty($attachment_id) && empty($media_mode)) {
					$wpml_multi_lang_mode = true;
					foreach ($attachment_id as $value) {
						if (!empty($wpml_array['language_code'])) {
							$attached_id = $value['ID'];
							$lang_code = $wpml_array['language_code'];
							$get_image_element_id = $wpdb->get_results("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type = 'post_attachment' AND element_id = $attached_id AND language_code = '$lang_code' ", ARRAY_A);
							if (!empty($get_image_element_id[0]['element_id'])) {
								$wpml_multi_lang_mode = false;
								$get_lang_image_id = $get_image_element_id[0]['element_id'];
								return $get_lang_image_id;
							}
						} else {
							$attach_id = $value['ID'];
							if (!wp_get_attachment_url($attach_id)) {
								$attach_id = $this->image_function($img_url, $post_id, $data_array, '', 'use_existing_image', $header_array, $value_array, $wpml_array, $unikey, $image_metas, $line_number, $plugin, $import_type, $acf_wpname_element, $templatekey, $indexs, $acf_image_meta, $media_type, $media_id, $jet_child_object_id, $parent_object_id, $media_mode,$featured);
							} else {
								if (!empty($data_array['featured_image'])) {
									set_post_thumbnail($post_id, $attach_id);
								}
							}
						}
					}

					if ((!empty($wpml_array['language_code'])) && ($wpml_multi_lang_mode)) {
						$attach_id = $this->image_function($img_url, $post_id, $data_array, '', 'use_existing_image', $header_array, $value_array, $wpml_array, $unikey, $image_metas, $line_number, $plugin, $import_type, $acf_wpname_element, $templatekey, $indexs, $acf_image_meta, $media_type, $media_id, $jet_child_object_id, $parent_object_id, $media_mode,$featured);
						return $attach_id;
					}

					$this->imageMetaImport($attach_id, $media_handle, $header_array, $value_array,$featured, $media_type);
				} else {
					$attach_id = $this->image_function($img_url, $post_id, $data_array, '', 'use_existing_image', $header_array, $value_array, $wpml_array, $unikey, $image_metas, $line_number, $plugin, $import_type, $acf_wpname_element, $templatekey, $indexs, $acf_image_meta, $media_type, $media_id, $jet_child_object_id, $parent_object_id, $media_mode,$featured);
					if (!empty($attach_id)) {
						$this->imageMetaImport($attach_id, $media_handle, $header_array, $value_array,$featured, $media_type);
					}
				}
			} elseif ($media_handle['media_settings']['overwriteImage'] == 'true') {

				// // Get the file name from the URL
				$fimg_name = @basename($img_url);
				$pathinfo = pathinfo($fimg_name);
				$imagename = $pathinfo['filename'];
				$image_title = str_replace(' ', '-', trim($imagename));
				// Replace dots with dashes and ensure lowercase
				$fil_name = strtolower(str_replace('.', '-', $image_title));
				// Remove any characters that are not alphanumeric, dashes, or underscores
				$fil_name = preg_replace('/[^a-z0-9-_]/', '', $fil_name);
				$fil_name = esc_sql($fil_name);
				$guid_like = esc_sql('%' . $wpdb->esc_like($fimg_name) . '%');
				if (!empty($media_handle['media_settings']['title'])) {
					$image_title   = $media_handle['media_settings']['title'];
				}
				$get_id = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'attachment' AND post_name = '$fil_name' LIMIT 1", ARRAY_A);
				if (!empty($get_id)) {
					$attach_id = $get_id[0]['ID'];
					$this->overwrite($attach_id, $img_url);
					if (!empty($data_array['featured_image'])) {
						set_post_thumbnail($post_id, $attach_id);
					}
				} else {
					$attach_id = $this->image_function($img_url, $post_id, $data_array, '', '', $header_array, $value_array, [], $unikey, $image_metas, $line_number, $plugin, $import_type, $acf_wpname_element, $templatekey, $indexs, $acf_image_meta, $media_type, $media_id, $jet_child_object_id, $parent_object_id, $media_mode,$featured);
				}
				$this->imageMetaImport($attach_id, $media_handle, $header_array, $value_array,$featured, $media_type);
			} else {
				$attach_id = $this->image_function($img_url, $post_id, $data_array, '', '', $header_array, $value_array, [], $unikey, $image_metas, $line_number, $plugin, $import_type, $acf_wpname_element, $templatekey, $indexs, $acf_image_meta, $media_type, $media_id, $jet_child_object_id, $parent_object_id, $media_mode,$featured);
			}
		} else { // Use local url if available, leave the post as attachment free 

			$guid_url = $img_url;
			$attachment_id = $wpdb->get_results("select ID from {$wpdb->prefix}posts where guid = '$guid_url'", ARRAY_A);

			if (!empty($attachment_id)) {

				foreach ($attachment_id as $value) {
					$attach_id = $value['ID'];
					if ($_wp_attachment_metadata = get_post_meta($attach_id, '_wp_attachment_metadata', true)) {
						// When an attachment is available on Media and not has attachment link
						if (!is_array($_wp_attachment_metadata)) {
							$attach_id = $this->image_function($img_url, $post_id, $data_array, '', '', $header_array, $value_array, [], $unikey, $image_metas, $line_number, $plugin, $import_type, $acf_wpname_element, $templatekey, $indexs, $acf_image_meta, $media_type, $media_id, $jet_child_object_id, $parent_object_id, $media_mode,$featured);
						}
					}
				}
			}
		}
		$attach_id = isset($attach_id) ? $attach_id : '';
		return $attach_id;
	}

	public function mediaReport()
	{
		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		global $wpdb;
		$list_of_images = $wpdb->get_results("select * from {$wpdb->prefix}ultimate_csv_importer_media_report  ", ARRAY_A);

		if (!empty($list_of_images)) {
			foreach ($list_of_images as $list_key => $list_val) {
				if (!empty($list_val['hash_key'])) {
					$file_name = $wpdb->get_results("select file_name from {$wpdb->prefix}smackcsv_file_events where hash_key = '{$list_val['hash_key']}'", ARRAY_A);
				}

				$filename[$list_key] = $file_name[0]['file_name'];
				$module[$list_key] = $list_val['module'];
				$success_count[$list_key] = $list_val['success_count'];
				$fail_count[$list_key] = $list_val['fail_count'];
				$image_type[$list_key] = $list_val['image_type'];
				$image_status[$list_key] = $list_val['status'];
			}
			$response['file_name'] = $filename;
			$response['module'] = $module;
			$response['success_count'] = $success_count;
			$response['fail_count'] = $fail_count;
			$response['image_type'] = $image_type;
			$response['status'] = $image_status;
		} else {
			$response['success_count'] = array();
		}
		echo wp_json_encode($response);
		wp_die();
	}

	public function getAuthor($post_author)
	{
		$helpers_instance = ImportHelpers::getInstance();
		if (isset($post_author)) {
			$user_records = $helpers_instance->get_from_user_details($post_author);
			$post_author = $user_records['user_id'];
		}
		return $post_author;
	}

	public function image_function($f_img, $post_id, $data_array = null, $option_name = null, $use_existing_image = false, $header_array = null, $value_array = null, $wpml_array = null, $unikey = null, $image_metas = null, $line_number = null, $plugin = null, $import_type = null, $acf_wpname_element = null, $hashkey = null, $indexs = null, $acf_image_meta = null, $media_type = null, $media_id = null, $jet_child_object_id = null, $parent_object_id = null, $media_mode = null,$featured = false)
	{
		global $wpdb;
		if (isset($data_array['post_author'])) {
			$data_array['post_author'] = $this->getAuthor($data_array['post_author']);
		} else {
			if (!empty($data_array) && is_array($data_array)) {
				$data_array['post_author'] = "admin";
			} else {
				$data_array = array();
				$data_array['post_author'] = "admin";
			}
		}
		$media_handle = get_option('smack_image_options');
		$media_settings = [];
		if (!empty($header_array) && !empty($value_array)) {
			$media_settings = array_combine($header_array, $value_array);
		} else {
			if (!empty($unikey) && !is_array($unikey)) {
				$get_array_value = get_option('smack_media_seo' . $unikey);
				if (!empty($get_array_value)) {
					$header_array = $get_array_value['header_array'];
					$value_array = $get_array_value['value_array'];
					$media_settings = array_combine($header_array, $value_array);
				}
			}
		}

		if (!empty($media_handle['media_settings']['alttext'])) {
			$alttext['_wp_attachment_image_alt'] = $media_handle['media_settings']['alttext'];
		}
		if (!empty($media_handle['postcontent_image_alt'])) {
			$alttext['_wp_postcontent_image_alt'] = $media_handle['postcontent_image_alt'];
		}

		if (preg_match_all('/\b(?:(?:https?|http|ftp|file):\/\/|www\.|ftp\.)[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$]/i', $f_img, $matchedlist, PREG_PATTERN_ORDER)) {
			$f_img = trim($f_img);
		} else {
			$media_dir = wp_get_upload_dir();
			$names = glob($media_dir['path'] . '/' . '*.*');
			foreach ($names as $values) {
				if (!empty($f_img) && strpos($values, $f_img) !== false) {

					if (!empty($media_handle['media_settings']['file_name'])) {
						$file_type = wp_check_filetype($f_img, null);
						if (pathinfo($media_handle['media_settings']['file_name'], PATHINFO_EXTENSION) !== $file_type['ext']) {
							$fimg_name = $media_handle['media_settings']['file_name'] . '.' . $file_type['ext'];
						} else {
							$fimg_name = $media_handle['media_settings']['file_name'];
						}
						$f_img = $media_dir['url'] . '/' . $fimg_name;
					} else {
						$f_img = $media_dir['url'] . '/' . $f_img;
					}
				}
			}
		}
		$image_name = pathinfo($f_img);
		if (!empty($media_handle['media_settings']['file_name']) && ($featured || $media_type == 'External')) {
			$file_type = wp_check_filetype($f_img, null);
			$ext = !empty($file_type['ext']) ? '.' . $file_type['ext'] : '';
		
			if (isset($media_settings[$media_handle['media_settings']['file_name']])) {
				$fimg_name = $media_settings[$media_handle['media_settings']['file_name']] . $ext;
			} else {
				$fimg_name = str_replace(' ', '', $media_handle['media_settings']['file_name'] ?? '');
				$fimg_name .= $ext;
			}
		}
		 elseif (!empty($image_metas)) {
			$file_type = wp_check_filetype($f_img, null);
			$ext = '.' . $file_type['ext'];
			$fimg_name = $image_metas . $ext;
		} else {
			$fimg_name = $image_name['basename'];

			$fimg_name_without_ext = $image_name['filename'];
			if (empty($fimg_name_without_ext)) {
				$fimg_name_without_ext = $fimg_name;
			}
		}
		
		$file_type = wp_check_filetype($fimg_name, null);
		if ($use_existing_image) {
			if (!empty($media_handle['media_settings']['file_name']) && $media_type == 'External') {
				$image_file_name = $media_handle['media_settings']['file_name'];
				if (strpos($image_file_name, ' ') !== false) {
					$image_file_name = str_replace(' ', '-', $image_file_name);
					$image_file_name = preg_replace('/[^a-zA-Z0-9._\-]/', '', $image_file_name);
				}
				$attachment_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid LIKE '%" . esc_sql($wpdb->esc_like($image_file_name)) . "%'");
				if ($attachment_id != false) {
					$fimg_name = $image_file_name;
				} else {
					$fimg_name = $image_file_name;
				}
			} else {
				if (empty($file_type['ext']) && empty($media_handle['media_settings']['file_name'])) {
					$fimg_name = @basename($f_img);
					$fimg_name = str_replace(' ', '-', trim($fimg_name));
					$fimg_name = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', $fimg_name);
				}

				if (strstr($f_img, 'https://drive.google.com')) {
					preg_match('/[?&]id=([^&]+)/', $f_img, $matches);
					$fimg_name = isset($matches[1]) ? $matches[1] : basename($f_img);
				}
			}
			$f_img_check = str_replace(" ", "%20", $fimg_name);
			$attachment_id = null;

			$wp_content_url = content_url();
			if ((!empty($media_mode) || $media_mode == 'MediaUpdate')) {
				$attachment_ids = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'attachment' AND post_title != 'image-failed' AND guid LIKE '%$f_img_check%'", ARRAY_A);
				if (!empty($attachment_ids[0]['ID'])) {
					return $attachment_ids[0]['ID'];
				}
			} else if (strpos($f_img, $wp_content_url) !== FALSE) {
				$attachment_ids = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'attachment' AND guid = '$f_img' ", ARRAY_A);
			} else {
				$attachment_ids = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'attachment' AND guid LIKE '%$f_img_check%'", ARRAY_A);
			}
			if (!empty($attachment_ids[0]['ID'])) {
				if (($media_type == 'Local' || $media_type == 'External') && empty($media_id) && empty($post_id)) {
					return $attachment_ids[0]['ID'];
				} else if ($plugin == 'Featured') {
					return $attachment_ids[0]['ID'];
				} else if (empty($media_id) && empty($media_type)) {
					$attach_id = $attachment_ids[0]['ID'];
					$table_name = $wpdb->prefix . 'smackcsv_file_events';
					$post_title = $wpdb->get_var("SELECT post_title FROM {$wpdb->prefix}posts WHERE ID = '{$post_id}' AND post_status != 'trash'");
					$file_name = $wpdb->get_var("SELECT file_name FROM $table_name WHERE hash_key = '$hashkey'");
					$shortcode_table = $wpdb->prefix . "ultimate_csv_importer_shortcode_manager";
					$check_id = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE ID ='{$attach_id}' AND post_title ='image-failed' AND post_type = 'attachment' AND guid LIKE '%$f_img_check%'", ARRAY_A);
					if (!empty($check_id)) {
						$failed_ids = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ultimate_csv_importer_shortcode_manager WHERE post_id='{$post_id}' AND media_id = '{$attach_id}'");
						if (!empty($failed_ids) && $failed_ids[0]->post_id != $post_id) {
							$attach_id = $check_id[0]['ID'];
							$insert_status = $wpdb->insert(
								$shortcode_table,
								array('post_id' => $post_id, 'post_title' => $post_title, 'image_shortcode' => $plugin . '_image__' . $acf_wpname_element, 'media_id' => $attach_id, 'original_image' => $f_img, 'indexs' => $indexs, 'image_meta' => $acf_image_meta, 'hash_key' => $hashkey, 'import_type' => $import_type, 'file_name' => $file_name, 'jet_child_object_id' => $jet_child_object_id, 'jet_parent_object_id' => $parent_object_id),
								array('%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
							);
							if ($insert_status) {
								$this->store_failed_image_ids($attach_id);
								$this->failed_media_data($line_number, $post_id, $post_title, $attach_id, $f_img);
							}
						} elseif (!empty($post_id) && !empty($failed_ids) && ($failed_ids[0]->post_id == $post_id) && ($check_id[0]['ID'] == $failed_ids[0]->media_id)) {
							$attach_id = $check_id[0]['ID'];
							$insert_status = $wpdb->insert(
								$shortcode_table,
								array('post_id' => $post_id, 'post_title' => $post_title, 'image_shortcode' => $plugin . '_image__' . $acf_wpname_element, 'media_id' => $attach_id, 'original_image' => $f_img, 'indexs' => $indexs, 'image_meta' => $acf_image_meta, 'hash_key' => $hashkey, 'import_type' => $import_type, 'file_name' => $file_name, 'jet_child_object_id' => $jet_child_object_id, 'jet_parent_object_id' => $parent_object_id),
								array('%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
							);
							if ($insert_status) {
								$this->store_failed_image_ids($attach_id);
								$this->failed_media_data($line_number, $post_id, $post_title, $attach_id, $f_img);
							}
						} elseif (empty($failed_ids)) {
							$insert_status = $wpdb->insert(
								$shortcode_table,
								array('post_id' => $post_id, 'post_title' => $post_title, 'image_shortcode' => $plugin . '_image__' . $acf_wpname_element, 'media_id' => $attach_id, 'original_image' => $f_img, 'indexs' => $indexs, 'image_meta' => $acf_image_meta, 'hash_key' => $hashkey, 'import_type' => $import_type, 'file_name' => $file_name, 'jet_child_object_id' => $jet_child_object_id, 'jet_parent_object_id' => $parent_object_id),
								array('%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
							);
							if ($insert_status) {
								$this->store_failed_image_ids($attach_id);
								$this->failed_media_data($line_number, $post_id, $post_title, $attach_id, $f_img);
							}
						}
					}
					return $attach_id;
				}
			}

			if (is_array($attachment_ids) && !empty($attachment_ids))
				$attachment_id = $attachment_ids[0]['ID'];
			if (strpos($f_img, 'loading-image.jpg') !== FALSE) {
			} else {
				$this->imageMetaImport($attachment_id, $media_handle, $header_array, $value_array,$featured, $media_type);
			}
			if ($attachment_id) {
				if (!empty($data_array['featured_image'])) {
					set_post_thumbnail($post_id, $attachment_id);
					return $attachment_id;
				} else {

					if (!empty($wpml_array['language_code'])) {
						$lang_code = $wpml_array['language_code'];
						$image_trid_id = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_type = 'post_attachment' AND element_id = $attachment_id ");
						$image_lang_id = $wpdb->get_var("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type = 'post_attachment' AND trid = $image_trid_id AND language_code = '$lang_code' ");
						return $image_lang_id;
					} else {
						return $attachment_id;
					}
				}
			}
		}

		$attachment_title = sanitize_file_name(pathinfo($fimg_name, PATHINFO_FILENAME));

		$file_type = wp_check_filetype($fimg_name, null);
		$dir = wp_upload_dir();
		$dirname = date('Y') . '/' . date('m');
		$uploads_use_yearmonth = get_option('uploads_use_yearmonth_folders');
		if ($uploads_use_yearmonth == 1) {
			$uploaddir_paths = $dir['basedir'] . '/' . $dirname;
			$uploaddir_url = $dir['baseurl'] . '/' . $dirname;
		} else {
			$uploaddir_paths = $dir['basedir'];
			$uploaddir_url = $dir['baseurl'];
		}
		$f_img = str_replace(" ", "%20", $f_img);
		if (!empty($media_handle['media_settings']['file_name']) && isset($media_handle['media_settings']['file_name'])) {
			$file_type = wp_check_filetype($f_img, null);
			if (empty($file_type['ext']) && empty($media_handle['media_settings']['file_name'])) {
				$file_type['ext'] = 'jpeg';
			}
			$ext = '.' . $file_type['ext'];
			if (!empty($media_handle['media_settings']['file_name']) && ($featured || $media_type == 'External')) {
				if (strrpos($media_handle['media_settings']['file_name'], '.')) {
					$fimg_name = $media_handle['media_settings']['file_name'];
				} else {
					$fimg_name = $media_handle['media_settings']['file_name'] . $ext;
				}
			}
		} else if (empty($file_type['ext']) && empty($media_handle['media_settings']['file_name'])) {
			if (strstr($f_img, 'https://drive.google.com')) {
				preg_match('/[?&]id=([^&]+)/', $f_img, $matches);
				$fimg_name = isset($matches[1]) ? $matches[1] : basename($f_img);
			} else {
				$fimg_name = @basename($f_img);
				$fimg_name = str_replace(' ', '-', trim($fimg_name));
				$fimg_name = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', $fimg_name);
			}
		}
		if ($uploaddir_paths != "" && $uploaddir_paths) {
			if (strpos($fimg_name, ' ') !== false) {
				$fimg_name = str_replace(' ', '-', $fimg_name);
				$fimg_name = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', $fimg_name);
			}
			$uploaddir_path = $uploaddir_paths . "/" . $fimg_name;
		}
		// if ($uploaddir_paths != "" && $uploaddir_paths) {
		// 	$uploaddir_path = $uploaddir_paths . "/" . $fimg_name;
		// }
		if (strstr($f_img, 'https://drive.google.com')) {

			if (strstr($f_img, 'https://drive.google.com/uc?export=download&id')) {
				$rawdata = file_get_contents($f_img);
			}
			else if (strpos($f_img, 'https://drive.google.com/file/d/') !== false) {
				// Extract the file ID using string functions
				$file_id_start = strpos($f_img, '/d/') + 3; // Find position after '/d/'
				$file_id_end = strpos($f_img, '/view', $file_id_start); // Find position of '/view'
				$file_id = substr($f_img, $file_id_start, $file_id_end - $file_id_start);
				$f_img = "https://drive.google.com/uc?export=download&id=$file_id";
				//$response = wp_remote_get($f_img);
				$response = wp_remote_get($f_img, array('timeout' => 30));
				$rawdata = wp_remote_retrieve_body($response);
			}
			 else {
				$page_content = file_get_contents($f_img);
				if (empty($page_content)) {
					preg_match('~/d/\K[^/]+(?=/)~', $f_img, $result_id);
					$image_link_id = isset($result_id[0]) ? $result_id[0] : $f_img;
					$rawdata = file_get_contents(html_entity_decode('https://drive.google.com/uc?export=download&id=' . $image_link_id));
				} else {

					$dom_obj = new \DOMDocument();
					$dom_obj->loadHTML($page_content);
					$meta_val = null;
					foreach ($dom_obj->getElementsByTagName('meta') as $meta) {
						if ($meta->getAttribute('property') == 'og:image') {
							$meta_val = $meta->getAttribute('content');
						}
					}
					$ch = curl_init($meta_val);
					curl_setopt($ch, CURLOPT_HEADER, 0);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					$rawdata = curl_exec($ch);

					// if(empty($rawdata)){
					// 	preg_match('~/d/\K[^/]+(?=/)~', $f_img, $result_id);
					// 	$image_link_id = isset($result_id[0]) ? $result_id[0] : $f_img;	
					// 	$image_link = 'https://drive.google.com/uc?export=download&id=' . $image_link_id;
					// 	$rawdata = file_get_contents($image_link);	
					// }
				}
			}
		} elseif (isset($media_dir['url']) && strstr($f_img, $media_dir['url'])) {
			if ($plugin == 'Media' && !empty($media_id)) { // write update media
				$attach_id = $media_id;
				$post_info = array('ID' => $attach_id, 'post_title'   => $attachment_title);
				wp_update_post($post_info);
				$new_guid = $uploaddir_url . '/' . $fimg_name;
				$wpdb->update($wpdb->posts, array('guid' => $new_guid), array('ID' => $attach_id), array('%s'), array('%d'));
				update_attached_file($attach_id, $uploaddir_path);
				$attach_data = wp_generate_attachment_metadata($attach_id, $uploaddir_path);
				wp_update_attachment_metadata($attach_id, $attach_data);
			} else {
				$post_info = array(
					'guid'           => $uploaddir_url . "/" .  $fimg_name,
					'post_mime_type' => $file_type['type'],
					'post_title'     => $attachment_title,
					'post_content'   => '',
					'post_status'    => 'inherit',
					'post_author'  => $data_array['post_author']
				);
				$attach_id = wp_insert_attachment($post_info, $uploaddir_path, $post_id);
				$attach_data = wp_generate_attachment_metadata($attach_id, $uploaddir_path);
				wp_update_attachment_metadata($attach_id,  $attach_data);
			}
			if (!empty($data_array['featured_image'])) {
				set_post_thumbnail($post_id, $attach_id);
			}

			if (strpos($f_img, 'loading-image.jpg') !== FALSE) {
			} else {
				$this->imageMetaImport($attach_id, $media_handle, $header_array, $value_array,$featured, $media_type);
			}
			return $attach_id;
		} else if (strstr($f_img, 'https://www.dropbox.com/')) {
			$page_content   = file_get_contents($f_img);
			$dom_obj = new \DOMDocument();
			$dom_obj->loadHTML($page_content);
			$meta_val = null;
			foreach ($dom_obj->getElementsByTagName('meta') as $meta) {
				if ($meta->getAttribute('property') == 'og:image') {
					$meta_val = $meta->getAttribute('content');
				}
			}
			$response = wp_remote_get($meta_val);
			$rawdata =  wp_remote_retrieve_body($response);
		} else {
			if ($file_type['ext'] == 'jpeg' || $file_type['ext'] == 'jpg' || $file_type['ext'] == 'bmp') {
				$response = wp_remote_get($f_img, array('timeout' => 120));
			} 
			else if ($file_type['ext'] == 'gif') {
				$response = wp_remote_get($f_img, array('timeout' => 300));
			}
			else if ($file_type['ext'] == 'mp4') {
				$response = wp_remote_get($f_img, array('timeout' => 120));
			} else {
				$response = wp_remote_get($f_img, array('timeout' => 60));
			}

			$rawdata =  wp_remote_retrieve_body($response);
		}
		$http_code = wp_remote_retrieve_response_code($response);
		if (strpos($rawdata, '<img src=') !== false) {
			$raw_file = explode('<img src', $rawdata);
			$urls = rtrim(end($raw_file), '>');
			$urls = ltrim($urls, '=');
			$urls = str_replace(' ', '', $urls);
			//$rawdata=file_get_contents($urls);
			$response = wp_remote_get($urls, array('timeout' => 50));
			$rawdata =  wp_remote_retrieve_body($response);
			$http_code = wp_remote_retrieve_response_code($response);
		}
		if ($http_code == 404 || $http_code == 403 || $http_code == 500 || $http_code == 401 || $http_code == 408 || $http_code == 502 || $http_code == 503 || $http_code == 504) {
			return null;
		}
		if ($http_code != 200 && strpos($rawdata, 'Not Found') != 0) {
			return null;
		}

		if ($rawdata == false) {
			return null;
		} else {
			if ($plugin == 'Media' && !empty($media_id)) {
				$old_file_path = get_attached_file($media_id);
				if (file_exists($old_file_path)) {
					unlink($old_file_path);
				}
			} else {
				if (file_exists($uploaddir_path)) {
					$i = 1;
					$exist = true;
					while ($exist) {
						$fimg_name = $attachment_title . "-" . $i . "." . $file_type['ext'];
						$uploaddir_path = $uploaddir_paths . "/" . $fimg_name;

						if (file_exists($uploaddir_path)) {
							$i = $i + 1;
						} else {
							$exist = false;
						}
					}
				}
			}
			$fp = fopen($uploaddir_path, 'x');
			if ($fp === false) {
				return null;
			}
			fwrite($fp, $rawdata);
			fclose($fp);
		}

		if (empty($file_type['type'])) {
			$file_type['type'] = 'image/jpeg';
		}
		if ($plugin == 'Media' && !empty($media_id)) {
			$attach_id = $media_id;
			$post_info = array('ID' => $attach_id, 'post_title'   => $attachment_title);
			wp_update_post($post_info);
			$new_guid = $uploaddir_url . '/' . $fimg_name;
			$wpdb->update($wpdb->posts, array('guid' => $new_guid), array('ID' => $attach_id), array('%s'), array('%d'));
			update_attached_file($attach_id, $uploaddir_path);
			$attach_data = wp_generate_attachment_metadata($attach_id, $uploaddir_path);
			wp_update_attachment_metadata($attach_id, $attach_data);
		} else {
			$post_info = array(
				'guid'           => $uploaddir_url . "/" .  $fimg_name,
				'post_mime_type' => $file_type['type'],
				'post_title'     => $attachment_title,
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_author'  => $data_array['post_author']
			);
			$attach_id = wp_insert_attachment($post_info, $uploaddir_path, $post_id);
			$attach_data = wp_generate_attachment_metadata($attach_id, $uploaddir_path);

			wp_update_attachment_metadata($attach_id,  $attach_data);
		}
		//added..
		if (strstr($f_img, 'https://drive.google.com')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime = finfo_file($finfo, $uploaddir_path);

			if ($mime == 'image/jpeg') {
				$extension = 'jpg';
			} elseif ($mime == 'image/png') {
				$extension = 'png';
			} else {
				$explode_ext = explode('/', $mime);
				$extension = $explode_ext[1];
			}
			$image_file_path = get_attached_file($attach_id);
			if ($image_file_path) {
				$extension_exists = '.' . $extension;
				if (strpos($image_file_path, $extension_exists) !== false) {
				} else {
					if (substr($image_file_path, -1) == '.') {
						$new_image_file_path = $image_file_path . $extension;
					} else {
						$new_image_file_path = $image_file_path . '.' . $extension;
					}

					rename($image_file_path, $new_image_file_path);
					update_attached_file($attach_id, $new_image_file_path);
				}
			}
		}
		if (strpos($f_img, 'loading-image.jpg') !== FALSE) {
		} else {
			$this->imageMetaImport($attach_id, $media_handle, $header_array, $value_array,$featured, $media_type);
		}

		if (!empty($data_array['featured_image'])) {
			set_post_thumbnail($post_id, $attach_id);
		}

		if (!empty($wpml_array['language_code']) && empty($media_id)) {
			$lang_code = $wpml_array['language_code'];
			$image_trid_id = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_type = 'post_attachment' AND element_id = $attach_id ");

			$image_lang_id = $wpdb->get_var("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type = 'post_attachment' AND trid = $image_trid_id AND language_code = '$lang_code' ");
			return $image_lang_id;
		} else {
			return $attach_id;
		}
	}

	public function imageMetaImport($attach_id, $media_handle, $header_array = null, $value_array = null , $featured = false , $media_type = null)
	{
		$media_handle = get_option('smack_image_options');
		if ( $attach_id && ( $featured || $media_type == 'Local' || $media_type == 'External' ) ) {
			if ($media_handle['media_settings']['media_handle_option']) {
				if (isset($media_handle['media_settings']['alttext'])) {
					$alttext['_wp_attachment_image_alt'] = $media_handle['media_settings']['alttext'];
				}
				if (isset($media_handle['postcontent_image_alt'])) {
					$alttext['_wp_postcontent_image_alt'] = $media_handle['postcontent_image_alt'];
				}
				if (isset($media_handle['media_settings']['caption']) || isset($media_handle['media_settings']['description'])) {
					wp_update_post(array(
						'ID'           => $attach_id,
						'post_content' => $media_handle['media_settings']['description'],
						'post_excerpt' => $media_handle['media_settings']['caption']
					));
				}
				if (!empty($media_handle['media_settings']['title'])) {
					wp_update_post(array(
						'ID'           => $attach_id,
						'post_title'   => $media_handle['media_settings']['title']
					));
				}
				if ($attach_id != null && isset($alttext['_wp_attachment_image_alt'])) {
					update_post_meta($attach_id, '_wp_attachment_image_alt', $alttext['_wp_attachment_image_alt']);
				}
	
				if ($attach_id != null && isset($alttext['_wp_postcontent_image_alt'])) {
					update_post_meta($attach_id, '_wp_attachment_image_alt', $alttext['_wp_postcontent_image_alt']);
				}
			}
		}

	}

	public function acfgalleryMetaImports($attach_id, $media_handle, $plugin)
	{
		if (is_array($attach_id)) {

			foreach ($attach_id as $attachid => $attachvalid) {
				if (isset($media_handle[$plugin . '_gallery_caption'][$attachid]) || isset($media_handle[$plugin . '_gallery_description'][$attachid])) {
					wp_update_post(array(
						'ID'           => $attachvalid,
						'post_content' => $media_handle[$plugin . '_gallery_description'][$attachid],
						'post_excerpt' => $media_handle[$plugin . '_gallery_caption'][$attachid]
					));
				}
				if (isset($media_handle[$plugin . '_gallery_title'][$attachid])) {
					wp_update_post(array(
						'ID'           => $attachvalid,
						'post_title'   => $media_handle[$plugin . '_gallery_title'][$attachid]
					));
				}
				if ($attachvalid != null && isset($media_handle[$plugin . '_gallery_file_name'][$attachid])) {
					global $wpdb;
					$guid = $wpdb->get_results("select guid from {$wpdb->prefix}posts where ID= '$attachvalid'", ARRAY_A);
					$dirname = date('Y') . '/' . date('m');
					$file_type = wp_check_filetype($guid[0]['guid'], null);
					$ext = '.' . $file_type['ext'];
					$dir = wp_upload_dir();

					// $uploaddir_paths = $dir ['basedir'] . '/' . $dirname ;
					// $uploaddir_url = $dir ['baseurl'] . '/' . $dirname;
					$uploads_use_yearmonth = get_option('uploads_use_yearmonth_folders');
					if ($uploads_use_yearmonth == 1) {
						$uploaddir_paths = $dir['basedir'] . '/' . $dirname;
						$uploaddir_url = $dir['baseurl'] . '/' . $dirname;
					} else {
						$uploaddir_paths = $dir['basedir'];
						$uploaddir_url = $dir['baseurl'];
					}
					$guids = $uploaddir_url . '/' . $media_handle[$plugin . '_gallery_file_name'][$attachid] . $ext;
					$wpdb->update($wpdb->posts, ['guid' => $guids], ['ID' => $attachvalid]);
					$fimg_name = $dirname . '/' . $media_handle[$plugin . '_gallery_file_name'][$attachid] . $ext;
					update_post_meta($attachvalid, '_wp_attached_file', $fimg_name);
				}
				if ($attachvalid != null && isset($media_handle[$plugin . '_gallery_alt_text'][$attachid])) {
					update_post_meta($attachvalid, '_wp_attachment_image_alt', $media_handle[$plugin . '_gallery_alt_text'][$attachid]);
				}
			}
		} else {

			if (isset($media_handle[$plugin . '_gallery_caption'][0]) || isset($media_handle[$plugin . '_gallery_description'][0])) {
				wp_update_post(array(
					'ID'           => $attach_id,
					'post_content' => $media_handle[$plugin . '_gallery_description'][0],
					'post_excerpt' => $media_handle[$plugin . '_gallery_caption'][0]
				));
			}
			if (isset($media_handle[$plugin . '_title'][0])) {
				wp_update_post(array(
					'ID'           => $attach_id,
					'post_title'   => $media_handle[$plugin . '_gallery_title'][0]
				));
			}
			if ($attach_id != null && isset($media_handle[$plugin . '_gallery_file_name'][0])) {
				global $wpdb;
				$guid = $wpdb->get_results("select guid from {$wpdb->prefix}posts where ID= '$attach_id'", ARRAY_A);
				$dirname = date('Y') . '/' . date('m');
				$file_type = wp_check_filetype($guid[0]['guid'], null);
				$ext = '.' . $file_type['ext'];
				$dir = wp_upload_dir();

				// $uploaddir_paths = $dir ['basedir'] . '/' . $dirname ;
				// $uploaddir_url = $dir ['baseurl'] . '/' . $dirname;
				$uploads_use_yearmonth = get_option('uploads_use_yearmonth_folders');
				if ($uploads_use_yearmonth == 1) {
					$uploaddir_paths = $dir['basedir'] . '/' . $dirname;
					$uploaddir_url = $dir['baseurl'] . '/' . $dirname;
				} else {
					$uploaddir_paths = $dir['basedir'];
					$uploaddir_url = $dir['baseurl'];
				}
				$guids = $uploaddir_url . '/' . $media_handle[$plugin . '_gallery_file_name'][0] . $ext;
				$wpdb->update($wpdb->posts, ['guid' => $guids], ['ID' => $attach_id]);
				$fimg_name = $dirname . '/' . $media_handle[$plugin . '_gallery_file_name'][0] . $ext;
				update_post_meta($attach_id, '_wp_attached_file', $fimg_name);
			}
			if ($attach_id != null && isset($media_handle[$plugin . '_gallery_alt_text'][0])) {
				update_post_meta($attach_id, '_wp_attachment_image_alt', $media_handle[$plugin . '_gallery_alt_text'][0]);
			}
		}
	}

	public function acfimageMetaImports($attach_id, $media_handle, $plugin)
	{
		if (is_array($attach_id)) {
			foreach ($attach_id as $attachid => $attachvalid) {
				if (isset($media_handle[$plugin . '_caption'][$attachid]) || isset($media_handle[$plugin . '_description'][$attachid])) {
					wp_update_post(array(
						'ID'           => $attachvalid,
						'post_content' => $media_handle[$plugin . '_description'][$attachid],
						'post_excerpt' => $media_handle[$plugin . '_caption'][$attachid]
					));
				}
				if (isset($media_handle[$plugin . '_title'][$attachid])) {
					wp_update_post(array(
						'ID'           => $attachvalid,
						'post_title'   => $media_handle[$plugin . '_title'][$attachid]
					));
				}
				if ($attachvalid != null && isset($media_handle[$plugin . '_file_name'][$attachid])) {
					global $wpdb;
					$guid = $wpdb->get_results("select guid from {$wpdb->prefix}posts where ID= '$attachvalid'", ARRAY_A);
					$dirname = date('Y') . '/' . date('m');
					$file_type = wp_check_filetype($guid[0]['guid'], null);
					$ext = '.' . $file_type['ext'];
					$dir = wp_upload_dir();

					// $uploaddir_paths = $dir ['basedir'] . '/' . $dirname ;
					// $uploaddir_url = $dir ['baseurl'] . '/' . $dirname;
					$uploads_use_yearmonth = get_option('uploads_use_yearmonth_folders');
					if ($uploads_use_yearmonth == 1) {
						$uploaddir_paths = $dir['basedir'] . '/' . $dirname;
						$uploaddir_url = $dir['baseurl'] . '/' . $dirname;
					} else {
						$uploaddir_paths = $dir['basedir'];
						$uploaddir_url = $dir['baseurl'];
					}
					$guids = $uploaddir_url . '/' . $media_handle[$plugin . '_file_name'][$attachid] . $ext;
					$wpdb->update($wpdb->posts, ['guid' => $guids], ['ID' => $attachvalid]);
					$fimg_name = $dirname . '/' . $media_handle[$plugin . '_file_name'][$attachid] . $ext;
					update_post_meta($attachvalid, '_wp_attached_file', $fimg_name);
				}
				if ($attachvalid != null && isset($media_handle[$plugin . '_alt_text'][$attachid])) {
					update_post_meta($attachvalid, '_wp_attachment_image_alt', $media_handle[$plugin . '_alt_text'][$attachid]);
				}
			}
		} else {

			if (isset($media_handle[$plugin . '_caption'][0]) || isset($media_handle[$plugin . '_description'][0])) {
				wp_update_post(array(
					'ID'           => $attach_id,
					'post_content' => $media_handle[$plugin . '_description'][0],
					'post_excerpt' => $media_handle[$plugin . '_caption'][0]
				));
			}
			if (isset($media_handle[$plugin . '_title'][0])) {
				wp_update_post(array(
					'ID'           => $attach_id,
					'post_title'   => $media_handle[$plugin . '_title'][0]
				));
			}
			if ($attach_id != null && isset($media_handle[$plugin . '_file_name'][0])) {
				global $wpdb;
				$guid = $wpdb->get_results("select guid from {$wpdb->prefix}posts where ID= '$attach_id'", ARRAY_A);
				$dirname = date('Y') . '/' . date('m');
				$file_type = wp_check_filetype($guid[0]['guid'], null);
				$ext = '.' . $file_type['ext'];
				$dir = wp_upload_dir();

				// $uploaddir_paths = $dir ['basedir'] . '/' . $dirname ;
				// $uploaddir_url = $dir ['baseurl'] . '/' . $dirname;
				$uploads_use_yearmonth = get_option('uploads_use_yearmonth_folders');
				if ($uploads_use_yearmonth == 1) {
					$uploaddir_paths = $dir['basedir'] . '/' . $dirname;
					$uploaddir_url = $dir['baseurl'] . '/' . $dirname;
				} else {
					$uploaddir_paths = $dir['basedir'];
					$uploaddir_url = $dir['baseurl'];
				}
				$guids = $uploaddir_url . '/' . $media_handle[$plugin . '_file_name'][0] . $ext;
				$wpdb->update($wpdb->posts, ['guid' => $guids], ['ID' => $attach_id]);
				$fimg_name = $dirname . '/' . $media_handle[$plugin . '_file_name'][0] . $ext;
				update_post_meta($attach_id, '_wp_attached_file', $fimg_name);
			}
			if ($attach_id != null && isset($media_handle[$plugin . '_alt_text'][0])) {
				update_post_meta($attach_id, '_wp_attachment_image_alt', $media_handle[$plugin . '_alt_text'][0]);
			}
		}
	}
	function overwrite($post_id, $img_url)
	{
		global $wpdb;
		$sql = "SELECT post_mime_type FROM {$wpdb->prefix}posts WHERE ID = $post_id";
		list($current_filetype) = $wpdb->get_row($sql, ARRAY_N);
		$current_filename = wp_get_attachment_url($post_id);
		$current_guid = $current_filename;
		$current_filename = substr($current_filename, (strrpos($current_filename, "/") + 1));
		$ID = $post_id;
		$current_file = get_attached_file($ID);
		$current_path = substr($current_file, 0, (strrpos($current_file, "/")));
		$current_file = preg_replace("|(?<!:)/{2,}|", "/", $current_file);
		$current_filename = basename($current_file);
		$current_metadata = wp_get_attachment_metadata($post_id);

		$new_filename = basename($img_url);
		$file_type = wp_check_filetype($new_filename, null);
		$new_filetype = $file_type["type"];
		if (empty($new_filetype['ext'])) {
			$new_filename = @basename($img_url);
			$new_filename = str_replace(' ', '-', trim($new_filename));
			$new_filename = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', $new_filename);
		}
		if (empty($new_filetype)) {
			$new_filetype = 'image/jpeg';
		}
		if (preg_match_all('/\b(?:(?:https?|http|ftp|file):\/\/|www\.|ftp\.)[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$]/i', $img_url, $matchedlist, PREG_PATTERN_ORDER)) {
			$img_url = $img_url;
		} else {
			$media_dir = wp_get_upload_dir();
			$names = glob($media_dir['path'] . '/' . '*.*');
			foreach ($names as $values) {
				$exp = basename($values);
				if ($exp == $img_url) {
					$attach_data = wp_generate_attachment_metadata($ID, $values);
					wp_update_attachment_metadata($ID,  $attach_data);
				}

				if (!empty($f_img) && strpos($values, $img_url) !== false) {
					$img_url = $media_dir['url'] . '/' . $img_url;
				}
			}
			return true;
		}
		$original_file_perms = fileperms($current_file) & 0777;
		$this->emr_delete_current_files($current_file, $post_id, $current_metadata);
		//	$new_filename = wp_unique_filename( $current_path, $new_filename );
		$new_file = $current_path . "/" . $new_filename;

		$data = file_get_contents($img_url);
		file_put_contents($new_file, $data);
		@chmod($current_file, $original_file_perms);
		$new_filetitle = preg_replace('/\.[^.]+$/', '', basename($new_file));
		$new_guid = str_replace($current_filename, $new_filename, $current_guid);
		$post_date = gmdate('Y-m-d H:i:s');
		$sql = $wpdb->prepare(
			"UPDATE {$wpdb->prefix}posts SET post_title = '$new_filetitle', post_name = '$new_filetitle', guid = '$new_guid', post_mime_type = '$new_filetype', post_date = '$post_date', post_date_gmt = '$post_date' WHERE ID = %d;",
			$post_id
		);
		$wpdb->query($sql);
		$sql = $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '_wp_attached_file' AND post_id = %d;",
			$post_id
		);
		$old_meta_name = $wpdb->get_row($sql, ARRAY_A);
		$old_meta_name = $old_meta_name["meta_value"];
		// Make new postmeta _wp_attached_file
		$new_meta_name = str_replace($current_filename, $new_filename, $old_meta_name);
		$sql = $wpdb->prepare(
			"UPDATE {$wpdb->prefix}postmeta SET meta_value = '$new_meta_name' WHERE meta_key = '_wp_attached_file' AND post_id = %d;",
			$post_id
		);
		$wpdb->query($sql);
		$new_metadata = wp_generate_attachment_metadata($post_id, $new_file);
		wp_update_attachment_metadata($post_id, $new_metadata);
		$current_base_url = $this->emr_get_match_url($current_guid); //  .wp-contet.uplodas/ dae name without ext
		$sql = $wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->prefix}posts WHERE post_status = 'publish' AND post_content LIKE %s;",
			'%' . $current_base_url . '%'
		);
		$rs = $wpdb->get_results($sql, ARRAY_A);
		$number_of_updates = 0;

		if (! empty($rs)) {
			$search_urls  = $this->emr_get_file_urls($current_guid, $current_metadata);
			$replace_urls = $this->emr_get_file_urls($new_guid, $new_metadata);
			$replace_urls = $this->emr_normalize_file_urls($search_urls, $replace_urls);
			foreach ($rs as $rows) {
				$number_of_updates = $number_of_updates + 1;
				$post_content = $rows["post_content"];
				$post_content = addslashes(str_replace($search_urls, $replace_urls, $post_content));
				$sql = $wpdb->prepare(
					"UPDATE {$wpdb->prefix}posts SET post_content = '$post_content' WHERE ID = %d;",
					$rows["ID"]
				);
				$wpdb->query($sql);
			}
		}
		update_attached_file($post_id, $new_file);
	}

	function emr_delete_current_files($current_file, $post_id, $metadata = null)
	{
		$current_path = substr($current_file, 0, (strrpos($current_file, "/")));
		if (file_exists($current_file)) {
			clearstatcache();
			if (is_writable($current_file)) {
				unlink($current_file);
			} else {
				printf(esc_html__('The file %1$s can not be deleted by the web server, most likely because the permissions on the file are wrong.'), $current_file);
				exit;
			}
		}
		// Delete old resized versions if this was an image
		$suffix = substr($current_file, (strlen($current_file) - 4));
		$prefix = substr($current_file, 0, (strlen($current_file) - 4));
		if (strtolower($suffix) === ".pdf") {
			$prefix .= "-pdf";
			$suffix = ".jpg";
		}
		$imgAr = array(".png", ".gif", ".jpg", ".jpeg");
		if (in_array($suffix, $imgAr)) {
			// It's a png/gif/jpg based on file name
			// Get thumbnail filenames from metadata
			if (empty($metadata)) {
				$metadata = wp_get_attachment_metadata($post_id);
			}
			if (is_array($metadata)) { // Added fix for error messages when there is no metadata (but WHY would there not be? I don't know…)
				foreach ($metadata["sizes"] as $thissize) {
					// Get all filenames and do an unlink() on each one;
					$thisfile = $thissize["file"];
					// Create array with all old sizes for replacing in posts later
					$oldfilesAr[] = $thisfile;
					// Look for files and delete them
					if (strlen($thisfile)) {
						$thisfile = $current_path . "/" . $thissize["file"];
						if (file_exists($thisfile)) {
							unlink($thisfile);
						}
					}
				}
			}
		}
	}

	function emr_get_match_url($url)
	{
		$url = $this->emr_remove_scheme($url);
		$url = $this->emr_maybe_remove_query_string($url);
		$url = $this->emr_remove_size_from_filename($url, true);
		$url = $this->emr_remove_domain_from_filename($url);
		return $url;
	}

	function emr_remove_scheme($url)
	{
		return preg_replace('/^(?:http|https):/', '', $url);
	}

	function emr_maybe_remove_query_string($url)
	{
		$parts = explode('?', $url);
		return reset($parts);
	}

	function emr_remove_size_from_filename($url, $remove_extension = false)
	{
		$url = preg_replace('/^(\S+)-[0-9]{1,4}x[0-9]{1,4}(\.[a-zA-Z0-9\.]{2,})?/', '$1$2', $url);
		if ($remove_extension) {
			$ext = pathinfo($url, PATHINFO_EXTENSION);
			$url = str_replace(".$ext", '', $url);
		}
		return $url;
	}

	function emr_remove_domain_from_filename($url)
	{
		$url = str_replace($this->emr_remove_scheme(get_bloginfo('url')), '', $url);
		return $url;
	}


	function emr_get_file_urls($guid, $metadata)
	{
		$urls = array();
		$guid = $this->emr_remove_scheme($guid);
		$guid = $this->emr_remove_domain_from_filename($guid);
		$urls['guid'] = $guid;
		if (empty($metadata)) {
			return $urls;
		}
		$base_url = dirname($guid);
		if (! empty($metadata['file'])) {
			$urls['file'] = trailingslashit($base_url) . wp_basename($metadata['file']);
		}

		if (! empty($metadata['sizes'])) {
			foreach ($metadata['sizes'] as $key => $value) {
				$urls[$key] = trailingslashit($base_url) . wp_basename($value['file']);
			}
		}
		return $urls;
	}

	function emr_normalize_file_urls($old, $new)
	{
		$result = array();
		if (empty($new['guid'])) {
			return $result;
		}
		$guid = $new['guid'];
		foreach ($old as $key => $value) {
			$result[$key] = empty($new[$key]) ? $guid : $new[$key];
		}
		return $result;
	}

	public function get_filename_path($image_url, $media_type = null)
	{
		$media_handle = get_option('smack_image_options');
		if (!empty($media_handle['media_settings']['file_name']) && $media_type == 'External') {
			$fimg_name = $media_handle['media_settings']['file_name'];
			$fimg_name = str_replace(' ', '-', trim($fimg_name));
			$fimg_name = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', $fimg_name);
			$dir = wp_upload_dir();
			$dirname = date('Y') . '/' . date('m');
			$uploads_use_yearmonth = get_option('uploads_use_yearmonth_folders');
			if ($uploads_use_yearmonth == 1) {
				$uploaddir_paths = $dir['basedir'] . '/' . $dirname;
				$uploaddir_url = $dir['baseurl'] . '/' . $dirname;
			} else {
				$uploaddir_paths = $dir['basedir'];
				$uploaddir_url = $dir['baseurl'];
			}
			if ($uploaddir_paths != "" && $uploaddir_paths) {
				$uploaddir_path = $uploaddir_paths . "/" . $fimg_name;
			}
			return ['uploaddir_path' => $uploaddir_path, 'uploaddir_url' => $uploaddir_url, 'fimg_name' => $fimg_name];
		} else {
			$image_name = pathinfo($image_url);
			$fimg_name = $image_name['basename'];
			$fimg_name_without_ext = $image_name['filename'];
			if (empty($fimg_name_without_ext)) {
				$fimg_name_without_ext = $fimg_name;
			}
			$file_type = wp_check_filetype($fimg_name, null);
			if (empty($file_type['ext'])) {
				$fimg_name = @basename($image_url);
				$fimg_name = str_replace(' ', '-', trim($fimg_name));
				$fimg_name = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', $fimg_name);
			}

			if (strstr($image_url, 'https://drive.google.com')) {
				preg_match('/[?&]id=([^&]+)/', $image_url, $matches);
				$fimg_name = isset($matches[1]) ? $matches[1] : basename($image_url);
			}
			$dir = wp_upload_dir();
			$dirname = date('Y') . '/' . date('m');
			$uploads_use_yearmonth = get_option('uploads_use_yearmonth_folders');
			if ($uploads_use_yearmonth == 1) {
				$uploaddir_paths = $dir['basedir'] . '/' . $dirname;
				$uploaddir_url = $dir['baseurl'] . '/' . $dirname;
			} else {
				$uploaddir_paths = $dir['basedir'];
				$uploaddir_url = $dir['baseurl'];
			}
			if ($uploaddir_paths != "" && $uploaddir_paths) {
				$uploaddir_path = $uploaddir_paths . "/" . $fimg_name;
			}

			return ['uploaddir_path' => $uploaddir_path, 'uploaddir_url' => $uploaddir_url, 'fimg_name' => $fimg_name];
		}
	}
	public function image_meta_table_entry($line_number, $post_values, $post_id, $acf_wpname_element, $acf_csv_name, $hash_key, $plugin, $get_import_type, $templatekey = null, $gmode = null, $header_array = null, $value_array = null, $imgformat = null, $typecct = null, $indexs = null, $media_type = null, $jet_child_object_id = null, $parent_object_id = null)
	{
		global $wpdb;
		$acf_wpname_element = isset($acf_wpname_element) ? $acf_wpname_element : '';
		$table_name = $wpdb->prefix . 'smackcsv_file_events';
		$file_name = $wpdb->get_var("SELECT file_name FROM $table_name WHERE hash_key = '$hash_key'");
		$get_path_values = $this->get_filename_path($acf_csv_name, $media_type);
		$uploaddir_path = $get_path_values['uploaddir_path'] ?? '';
		$uploaddir_url = $get_path_values['uploaddir_url'] ?? '';
		$fimg_name = $get_path_values['fimg_name'] ?? '';
		$file_type = 'image/jpeg';

		if ($plugin == 'Media') {
			return $this->handle_media_image($acf_csv_name, $post_id, $post_values, $plugin, $get_import_type, $acf_wpname_element, $hash_key, $header_array, $value_array, $line_number, $indexs, $media_type, $uploaddir_url, $fimg_name, $file_type, $uploaddir_path);
		}

		if (isset($post_id) && !empty($acf_csv_name) && !empty($plugin)) {
			$post_title = $wpdb->get_var("SELECT post_title FROM {$wpdb->prefix}posts WHERE ID = '{$post_id}' AND post_status != 'trash'");
			$shortcode_table = $wpdb->prefix . "ultimate_csv_importer_shortcode_manager";
			$failed_ids = $wpdb->get_results("SELECT post_title, post_id, image_shortcode, media_id, original_image FROM {$wpdb->prefix}ultimate_csv_importer_shortcode_manager WHERE image_shortcode = 'Featured_image_' AND post_id = '{$post_id}' AND original_image = '{$acf_csv_name}'");
			if ($plugin == 'inline') {
				return $this->handle_inline_image($acf_csv_name, $post_id, $post_values, $plugin, $get_import_type, $acf_wpname_element, $hash_key, $header_array, $value_array, $line_number, $indexs, $uploaddir_url, $fimg_name, $file_type, $uploaddir_path, $shortcode_table, $post_title, $file_name);
			}

			if ($plugin == 'Featured') {
				return $this->handle_featured_image($acf_csv_name, $post_id, $post_values, $plugin, $get_import_type, $acf_wpname_element, $hash_key, $header_array, $value_array, $line_number, $uploaddir_url, $fimg_name, $file_type, $uploaddir_path, $shortcode_table, $post_title, $file_name, $failed_ids);
			}

			return $this->handle_custom_image($acf_csv_name, $post_id, $post_values, $plugin, $get_import_type, $acf_wpname_element, $hash_key, $header_array, $value_array, $line_number, $imgformat, $typecct, $indexs, $uploaddir_url, $fimg_name, $file_type, $uploaddir_path, $shortcode_table, $post_title, $file_name, $jet_child_object_id, $parent_object_id);
		}

		return '';
	}

	public function handle_media_image($acf_csv_name, $post_id, $post_values, $plugin, $get_import_type, $acf_wpname_element, $hash_key, $header_array, $value_array, $line_number, $indexs, $media_type, $uploaddir_url, $fimg_name, $file_type, $uploaddir_path)
	{
		$media_handle = get_option('smack_image_options');
		$attach_id = $this->media_handling($acf_csv_name, $post_id, $post_values, $plugin, $get_import_type, $acf_wpname_element, $hash_key, '', '', $header_array, $value_array, '', '', $line_number, $indexs, '', $media_type);
		if (!empty($attach_id)) {
			return $attach_id;
		}

		$post_info = array(
			'guid' => $uploaddir_url . "/" . $fimg_name,
			'post_mime_type' => $file_type,
			'post_title' => 'image-failed',
			'post_content' => '',
			'post_status' => 'inherit',
			'post_author' => $post_values['author'] ?? ''
		);
		$attach_id = wp_insert_attachment($post_info, $uploaddir_path, $post_id);
		if (!empty($media_handle)) {
			$media_handle['media_settings']['title'] = 'image-failed';
			update_option('smack_image_options', $media_handle);
			$this->imageMetaImport($attach_id, $media_handle, $header_array, $value_array,'',$media_type);
		}
		return $attach_id;
	}

	public function handle_inline_image($acf_csv_name, $post_id, $post_values, $plugin, $get_import_type, $acf_wpname_element, $hash_key, $header_array, $value_array, $line_number, $indexs, $uploaddir_url, $fimg_name, $file_type, $uploaddir_path, $shortcode_table, $post_title, $file_name)
	{
		global $wpdb;
		$failed_inline_ids = $wpdb->get_results("SELECT post_title, post_id, image_shortcode, media_id, original_image FROM {$wpdb->prefix}ultimate_csv_importer_shortcode_manager WHERE image_shortcode = 'inline_image_' AND post_id = '{$post_id}'");

		$attach_id = $this->media_handling($acf_csv_name, $post_id, $post_values, $plugin, $get_import_type, $acf_wpname_element, $hash_key, '', '', $header_array, $value_array, '', '', $line_number, $indexs);
		if (!empty($attach_id)) {
			return $attach_id;
		}

		if (empty($failed_inline_ids) || $failed_inline_ids[0]->original_image != $acf_csv_name) {
			$post_info = array(
				'guid' => $uploaddir_url . "/" . $fimg_name,
				'post_mime_type' => $file_type,
				'post_title' => 'image-failed',
				'post_content' => '',
				'post_status' => 'inherit',
				'post_author' => $post_values['author'] ?? ''
			);
			$attach_id = wp_insert_attachment($post_info, $uploaddir_path, $post_id);
		}

		if (empty($failed_inline_ids)) {
			$insert_status = $wpdb->insert($shortcode_table, array(
				'post_id' => $post_id,
				'post_title' => $post_title,
				'image_shortcode' => $plugin . '_image_' . $acf_wpname_element,
				'media_id' => $attach_id,
				'original_image' => $acf_csv_name,
				'indexs' => $indexs,
				'hash_key' => $hash_key,
				'import_type' => $get_import_type,
				'file_name' => $file_name
			), array('%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s'));

			if ($insert_status) {
				$this->store_failed_image_ids($attach_id);
				$this->failed_media_data($line_number, $post_id, $post_title, $attach_id, $acf_csv_name);
			}
		} elseif (isset($failed_inline_ids[0]->post_id) && $failed_inline_ids[0]->post_id == $post_id) {
			$this->store_failed_image_ids($failed_inline_ids[0]->media_id);
			$this->failed_media_data($line_number, $failed_inline_ids[0]->post_id, $failed_inline_ids[0]->post_title, $failed_inline_ids[0]->media_id, $failed_inline_ids[0]->original_image);
		}

		return $attach_id;
	}

	public function handle_featured_image($acf_csv_name, $post_id, $post_values, $plugin, $get_import_type, $acf_wpname_element, $hash_key, $header_array, $value_array, $line_number, $uploaddir_url, $fimg_name, $file_type, $uploaddir_path, $shortcode_table, $post_title, $file_name, $failed_ids)
	{
		global $wpdb;
		if (empty($failed_ids)) {
			$attach_id = $this->media_handling($acf_csv_name, $post_id, $post_values, $plugin, $get_import_type, $acf_wpname_element, $hash_key, '', '', $header_array, $value_array, '', '', $line_number,'','','','','','','',$featured_image = true);
			if (!empty($attach_id)) {
				return $attach_id;
			}
		}

		if (empty($attach_id) || !empty($failed_ids)) {
			$post_info = array(
				'guid' => $uploaddir_url . "/" . $fimg_name,
				'post_mime_type' => $file_type,
				'post_title' => 'image-failed',
				'post_content' => '',
				'post_status' => 'inherit',
				'post_author' => $post_values['author'] ?? ''
			);
			$attach_id = wp_insert_attachment($post_info, $uploaddir_path, $post_id);
			if (empty($failed_ids)) {
				$insert_status = $wpdb->insert($shortcode_table, array(
					'post_id' => $post_id,
					'post_title' => $post_title,
					'image_shortcode' => $plugin . '_image_' . $acf_wpname_element,
					'media_id' => $attach_id,
					'original_image' => $acf_csv_name,
					'hash_key' => $hash_key,
					'import_type' => $get_import_type,
					'file_name' => $file_name
				), array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s'));

				if ($insert_status) {
					$this->store_failed_image_ids($attach_id);
					$this->failed_media_data($line_number, $post_id, $post_title, $attach_id, $acf_csv_name);
				}
			}
		}

		return $attach_id;
	}

	public function handle_custom_image($acf_csv_name, $post_id, $post_values, $plugin, $get_import_type, $acf_wpname_element, $hash_key, $header_array, $value_array, $line_number, $imgformat, $typecct, $indexs, $uploaddir_url, $fimg_name, $file_type, $uploaddir_path, $shortcode_table, $post_title, $file_name, $jet_child_object_id, $parent_object_id)
	{
		global $wpdb;

		if (strpos($plugin, 'jetengine_') !== false) {
			if (isset($header_array) && isset($value_array)) {
				$image_meta_value = array(
					'headerarray' => $header_array,
					'valuearray' => $value_array,
					'tablename' => $typecct,
					'returnformat' => $imgformat
				);
				$acf_image_meta  = json_encode($image_meta_value);
			}
		}
		$acf_wpname_element = isset($acf_wpname_element) ? $acf_wpname_element : '';
		$acf_image_meta = isset($acf_image_meta) ? $acf_image_meta : '';
		$failed_id = $wpdb->get_results("SELECT post_title, post_id, image_shortcode, media_id, original_image FROM {$wpdb->prefix}ultimate_csv_importer_shortcode_manager WHERE post_id = '{$post_id}' AND original_image = '{$acf_csv_name}' AND image_shortcode = '" . esc_sql($plugin . '_image__' . $acf_wpname_element) . "'");
		$attach_id = $this->media_handling($acf_csv_name, $post_id, $post_values, $plugin, $get_import_type, $acf_wpname_element, $hash_key, '', '', $header_array, $value_array, '', '', $line_number, $indexs, $acf_image_meta, '', '', $jet_child_object_id, $parent_object_id);

		if (!empty($attach_id)) {
			return $attach_id;
		}

		if (empty($failed_id)) {
			$post_info = array(
				'guid' => $uploaddir_url . "/" . $fimg_name,
				'post_mime_type' => $file_type,
				'post_title' => 'image-failed',
				'post_content' => '',
				'post_status' => 'inherit',
				'post_author' => $post_values['author'] ?? ''
			);
			$attach_id = wp_insert_attachment($post_info, $uploaddir_path, $post_id);

			$insert_status = $wpdb->insert($shortcode_table, array(
				'post_id' => $post_id,
				'post_title' => $post_title,
				'image_shortcode' => $plugin . '_image__' . $acf_wpname_element,
				'media_id' => $attach_id,
				'original_image' => $acf_csv_name,
				'indexs' => $indexs,
				'image_meta' => $acf_image_meta,
				'hash_key' => $hash_key,
				'import_type' => $get_import_type,
				'file_name' => $file_name,
				'jet_child_object_id' => $jet_child_object_id,
				'jet_parent_object_id' => $parent_object_id
			), array('%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'));

			if ($insert_status) {
				$this->store_failed_image_ids($attach_id);
				$this->failed_media_data($line_number, $post_id, $post_title, $attach_id, $acf_csv_name);
			}
		} else {
			$media_id = $failed_id[0]->media_id;
			$attachment_id = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE ID = $media_id AND post_title = 'image-failed' AND post_type = 'attachment' AND guid LIKE '%$fimg_name%'", ARRAY_A);
			$attach_id = $attachment_id[0]['ID'];
		}

		return $attach_id;
	}

	public function store_image_ids($attach_id)
	{
		//get number of images count
		$stored_ids = get_option('total_attachment_ids', '');
		$att_id = $attach_id;
		if ($stored_ids === '') {
			add_option('total_attachment_ids', serialize(array($att_id)));
			$stored_ids = unserialize(get_option('total_attachment_ids', ''));
		} else {
			$get_stored_ids = unserialize(get_option('total_attachment_ids', ''));
			if (is_array($get_stored_ids) && !empty($att_id)) {
				$att_id = is_array($att_id) ? $att_id : array($att_id);
				$stored_ids = array_merge($get_stored_ids, $att_id);
			} else {
				$stored_ids = $att_id;
			}
			update_option('total_attachment_ids', serialize($stored_ids));
			$stored_ids = unserialize(get_option('total_attachment_ids', ''));
		}
	}
	public function store_failed_data($data, $option_name)
	{
		$stored_data = get_option($option_name, '');
		// Initialize array for new data if no stored data exists
		if ($stored_data === '') {
			add_option($option_name, serialize(array($data)));
			$stored_data = unserialize(get_option($option_name, ''));
		} else {
			// Unserialize stored data
			$stored_data = unserialize($stored_data);
			$data = is_array($data) ? $data : array($data);
			$stored_data = array_merge($stored_data, $data);
			update_option($option_name, serialize($stored_data));
			$stored_data = unserialize(get_option($option_name, ''));
		}

		return $stored_data;
	}

	public function store_failed_image_ids($attach_id)
	{
		$option_name = 'failed_attachment_ids';
		$stored_data = $this->store_failed_data($attach_id, $option_name);
		return $stored_data;
	}

	public function failed_media_data($line_number, $post_id, $post_title, $media_id, $actual_url)
	{
		global $core_instance;
		$core_instance = CoreFieldsImport::getInstance();

		$option_name = 'failed_line_number';
		$stored_ids = $this->store_failed_data($media_id, $option_name);

		$count = count($stored_ids);

		$data = array('post_id' => $post_id, 'post_title' => $post_title, 'media_id' => $media_id, 'actual_url' => $actual_url);
		$core_instance->failed_media_data[$count] = $data;
	}
}
