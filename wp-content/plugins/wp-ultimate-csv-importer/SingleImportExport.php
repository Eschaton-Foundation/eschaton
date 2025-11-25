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

class SingleImportExport {
	private static $singlecsv_instance = null;


	public function __construct() {
	
		add_action('wp_ajax_handle_export_csv', array($this,'export_single_post_as_csv'));
		add_action('wp_ajax_handle_import_csv', array($this,'import_single_post_as_csv'));

	}

	public static function getInstance() {

		if (SingleImportExport::$singlecsv_instance == null) {
			SingleImportExport::$singlecsv_instance = new SingleImportExport;
			return SingleImportExport::$singlecsv_instance;
		}
		return SingleImportExport::$singlecsv_instance;
	}


	public static function import_single_post_as_csv() {
		
		if (!is_user_logged_in() || !current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized access.'], 403);
			return;
		}

		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		

		if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
			wp_send_json_error(['message' => 'File upload error.']);
			return;
		}

		$file_name = sanitize_file_name($_FILES['file']['name']);
		$file_tmp  = $_FILES['file']['tmp_name'];
		$file_info = wp_check_filetype_and_ext($file_tmp, $file_name, ['csv' => 'text/csv']);
		if (!$file_info['ext'] || $file_info['ext'] !== 'csv') {
			wp_send_json_error(['message' => 'Invalid file type. Only CSV files are allowed.']);
			return;
		}
		$upload = wp_upload_dir();
		$upload_dir = $upload['basedir'];
		if (is_user_logged_in() && current_user_can('administrator'))
		{
			$upload_dir = $upload_dir . '/smack_uci_uploads/imports/';
			if (!is_dir($upload_dir)) {
				wp_mkdir_p($upload_dir);
				chmod($upload_dir, 0755);

				$index_php_file = $upload_dir . 'index.php';
				if (!file_exists($index_php_file)) {
					$file_content = '<?php' . PHP_EOL . '?>';
					file_put_contents($index_php_file, $file_content);
				}
			}
		}
		if ($mode != 'CLI') {
			chmod($upload_dir, 0777);
		}

		$upload_dir_path = $upload_dir. $file_name;
		if (!is_dir($upload_dir_path)) {
			wp_mkdir_p( $upload_dir_path);
		}
		chmod($upload_dir_path, 0777);	

		$csv_file = $upload_dir_path.'/'.$file_name;
		if(move_uploaded_file($_FILES['file']['tmp_name'], $csv_file)){
			if (($handle = fopen($csv_file, 'r')) !== false) {

				$headers = fgetcsv($handle, 1000, ',');
				while (($row = fgetcsv($handle, 1000, ',')) !== false) {
					$data = array_combine($headers, $row);
					foreach ($data as $key => $value) {

						if (strpos($key, 'core_') === 0) {
							$post_data[str_replace('core_', '', $key)] = $value;
						}

					}


					$post_id = wp_insert_post($post_data);

					if (!is_wp_error($post_id)) {
						// Set meta data
foreach ($data as $key => $value) {
    if (strpos($key, 'meta_') === 0 && $value !== '') {
        $meta_key = substr($key, 5); 

        if ($meta_key === '_elementor_data') {
            update_post_meta($post_id, $meta_key, wp_slash($value));
        } elseif (in_array($meta_key, [
            '_elementor_css',
            '_elementor_page_assets',
            '_elementor_controls_usage'
        ], true)) {
            // SECURITY FIX: Use JSON instead of unserialize to prevent PHP Object Injection
            // Try JSON decode first (modern Elementor format)
            $decoded_value = json_decode($value, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_value)) {
                // Valid JSON
                update_post_meta($post_id, $meta_key, $decoded_value);
            } elseif (is_array($value)) {
                update_post_meta($post_id, $meta_key, $value);
            } else {
                // Store as array with single value (safest option)
                update_post_meta($post_id, $meta_key, [$value]);
            }
        } elseif ($meta_key === '_elementor_element_cache') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                update_post_meta($post_id, $meta_key, $decoded);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        } else {
            update_post_meta($post_id, $meta_key, sanitize_text_field($value));
        }
    }
}


						// Set terms
						if (!empty($data['terms_data'])) {
							$terms = json_decode($data['terms_data'], true);
							foreach ($terms as $term) {
								$parent_id = 0;

								// Handle parent term if necessary
								if (!empty($term['parent']) && is_taxonomy_hierarchical($term['taxonomy'])) {
									$parent_term = get_term($term['parent'], $term['taxonomy']);
									$parent_id = $parent_term ? $parent_term->term_id : 0;
								}


								if (!empty($term['term_name'])) {
									// Check if the term already exists
									$existing_term = get_term_by('name', $term['term_name'], $term['taxonomy']);

									if ($existing_term) {
										// Assign the existing term to the post
										wp_set_object_terms($post_id, [$existing_term->term_id], $term['taxonomy'], true);
									} else {
										// Insert the term if it doesn't exist
										$term_id = wp_insert_term($term['term_name'], $term['taxonomy'], [
											'slug'   => $term['term_slug'],
											'parent' => $parent_id,
										]);

										if (!is_wp_error($term_id)) {
											// Assign the newly created term to the post
											wp_set_object_terms($post_id, [$term_id['term_id']], $term['taxonomy'], true);
										}
									}
								}



							}
						}

						// Set featured image
						if (!empty($data['core_featured_image'])) {
							$attachment_id = attachment_url_to_postid($data['core_featured_image']);
							if ($attachment_id) {
								set_post_thumbnail($post_id, $attachment_id);
							}
						}
					}
				}
				fclose($handle);
			}
			$response['success'] = true;    
			$response['message'] = 'Inserted Post   '. $post_id;
			$response['redirect_link'] = get_edit_post_link( $post_id, true );
			wp_redirect($response['redirect_link']);
			echo wp_json_encode($response);
			wp_die();
		}
	}



	public static function export_single_post_as_csv() {

		if (!is_user_logged_in() || !current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized access.'], 403);
			return;
		}

		check_ajax_referer('smack-ultimate-csv-importer', 'securekey');
		global $wpdb;
		$post_id = intval($_POST['post_id']);
		$post = get_post($post_id);

		if (!is_user_logged_in() || !current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized access.']);
			return;
		}

		if (!$post_id || get_post_status($post_id) !== 'publish') {
			wp_send_json_error(['message' => 'Invalid or unpublished post ID.']);
			wp_die();
		}

		// Open output stream
		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename=post-data-' . $post_id . '.csv');


		$meta_keys = $wpdb->get_col(
			$wpdb->prepare("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d", $post_id)
		);

		// Fetch taxonomies
		$taxonomies = get_object_taxonomies($post->post_type);

		// Headers
		$headers = array_merge(
			['core_post_title', 'core_post_content', 'core_post_name', 'core_post_excerpt', 'core_post_status', 'core_post_type', 'core_featured_image', 'core_ping_status', 'core_comment_status','core_post_format','core_post_author', 'core_post_date', 'core_post_parent',  'core_menu_order','core_wp_page_template' ],
			array_map(fn($meta) => "meta_$meta", $meta_keys),
			['terms_data']
		);

		// Core data
		$featured_image = wp_get_attachment_url(get_post_thumbnail_id($post_id));
		$row = [
			'core_post_title'   => $post->post_title,
			'core_post_content' => $post->post_content,
			'core_post_name'    => $post->post_name,
			'core_post_excerpt'    => $post->post_excerpt,
			'core_post_status'  => $post->post_status,
			'core_post_type'    => $post->post_type,
			'core_featured_image' => $featured_image ?: '',	
			'core_ping_status'    => $post->ping_status,
			'core_comment_status'    => $post->comment_status,
			'core_post_format'    => $post->post_format,
			'core_post_author' => get_the_author_meta('display_name', $post->post_author),
			'core_post_date' => $post->post_date,
			'core_post_parent' => $post->post_parent,
			'core_menu_order' => $post->menu_order,
			'core_wp_page_template' => $post->wp_page_template



		];

		// Meta data
foreach ($meta_keys as $meta_key) {
    $meta_value = get_post_meta($post_id, $meta_key, true);

    if (is_array($meta_value) || is_object($meta_value)) {
        $meta_value = wp_json_encode($meta_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $row["meta_$meta_key"] = $meta_value;
}


		// Terms data
		$terms_data = [];
		foreach ($taxonomies as $taxonomy) {
			$terms = wp_get_object_terms($post_id, $taxonomy);
			foreach ($terms as $term) {
				$term_meta = get_term_meta($term->term_id);
				$terms_data[] = [
					'taxonomy'   => $taxonomy,
					'term_name'  => $term->name,
					'term_slug'  => $term->slug,
					'parent'     => $term->parent,
					'term_meta'  => $term_meta,
				];
			}
		}
		$row['terms_data'] = json_encode($terms_data);

		

		if (is_user_logged_in() && current_user_can('administrator'))
		{
			$upload_dir = ABSPATH . 'wp-content/uploads/smack_uci_uploads/exports/';
			if (!is_dir($upload_dir))
			{
				wp_mkdir_p($upload_dir);
			}
			$base_dir = wp_upload_dir();
			$upload_url = $base_dir['baseurl'] . '/smack_uci_uploads/exports/';
			chmod($upload_dir, 0777);
		}

		$file_path = $upload_dir. $post_id . '.csv';
		$output = fopen($file_path, 'w');
		fputcsv($output, $headers);
		fputcsv($output, $row);
		fclose($output);
		$response['success'] = true;

		$upload_url = $base_dir['baseurl'] . '/smack_uci_uploads/exports/';
		$fileURL = $upload_url . $post_title. $post_id . '.csv';
		$response['file_path'] = $fileURL;
		echo wp_json_encode($response);
		wp_die();


	}
}
