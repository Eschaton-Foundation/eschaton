<?php
/**
 * WP Ultimate Exporter plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\SMEXP;
if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly
// require_once dirname(__FILE__) . '/ExportExtension.php';
// require_once dirname(__FILE__) . '/PostExport.php';
// require_once dirname(__FILE__) . '/WooComExport.php';
// require_once dirname(__FILE__) . '/LearnPress.php';

// use Smackcoders\SMEXP\ExportExtension;
// use Smackcoders\SMEXP\PostExport;
// use Smackcoders\SMEXP\WooCommerceExport;
// use Smackcoders\SMEXP\LearnPressExport;


class WPQueryExport extends ExportExtension{
// class WPQueryExport {

	protected static $instance = null,$export_instance,$post_export,$woocom_export,$learnpress_export;	
	public static function getInstance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
			WPQueryExport::$export_instance = ExportExtension::getInstance();
            WPQueryExport::$post_export = PostExport::getInstance();
            WPQueryExport::$woocom_export = WooCommerceExport::getInstance();
            WPQueryExport::$learnpress_export = LearnPressExport::getInstance();

		}
		return self::$instance;
	}

	/**
	 * CustomerReviewExport constructor.
	 */
	public function __construct() {
		add_action('wp_ajax_wpquery_data', [$this,'wpquery_data_function']);
		add_action('wp_ajax_nopriv_wpquery_data', [$this, 'wpquery_data_function']); // If guests can access it
        // $this->plugin = Plugin::getInstance();

	}

    public function wpquery_data_function(){
        
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized access.'], 403);
			return;
		}

        $query_data =  sanitize_text_field($_POST['query_data']);
        $type = sanitize_text_field($_POST['type']);
        $query_args = str_replace(['\\"', ' '], ['"', ''], $query_data);

        // Step 2: Split by comma to get key-value pairs
        // $pairs = explode(',', $query_args);
        
        $args = [];
        
        // foreach ($pairs as $pair) {
        //     // Step 3: Split each pair by =>
        //     list($key, $value) = explode('=>', $pair);
            
        //     // Step 4: Trim quotes
        //     $key = trim($key, '"');
        //     $value = trim($value, '"');
            
        //     // Step 5: Add to array
        //     $args[$key] = $value;
        // }
              
        // $args = array($query_data)
        $step1 = str_replace('=>', ':', $query_args);
        $step2 = str_replace(
            ['array(', ')', "'",],
            ['[', ']', '"'],
            $step1
        );
        $json_string = '{' . $step2 . '}';
        $json_string = preg_replace('/,\s*\]/', ']', $json_string); // remove trailing commas    
        $args = json_decode(stripslashes($json_string), true);
        $result = $this->validate_wpai_query_args($args,$type);    
        
        if(isset($result->errors)){
            wp_send_json_error(['message' => 'Error']);  
        }
        else{
            $total_row =0;
            if($type == 'post'){
                if(!array_key_exists('posts_per_page',$args)){
                    $args['posts_per_page'] = -1;
                }
                $query = new \WP_Query($args);
                $posts = $query->get_posts(); 
                $total_row = count($posts);
                $module = $posts[0]->post_type;
                if($module == 'product' && is_plugin_active('woocommerce/woocommerce.php')){
                    $module = 'WooCommerce';
                    $optionalType = '';
                }
                elseif($module == "shop_coupon" && is_plugin_active('woocommerce/woocommerce.php')){
                    $module = 'WooCommerceCoupons';
                    $optionalType = ''; 
                }
                elseif($module == "shop_order_placehold" && is_plugin_active('woocommerce/woocommerce.php')){
                    $module = 'WooCommerceOrders';
                    $optionalType = ''; 
                }
                else{
                    if($module !== 'post' && $module !=='page'){
                        $optionalType = $module;
                        $module = 'CustomPosts';
        
                    }
                    if($module == 'post'){
                        $module = 'Posts';
                    }
                    if($module=='page'){
                        $module= 'Pages';
                    }
                }
            }
            elseif($type == "user"){
                if(!array_key_exists('number',$args)){
                    $args['number'] = -1;
                }
                $user_query = new \WP_User_Query($args);
                $users = $user_query->get_results();
                $module =  'Users';
                if(!empty($users)){
                    $total_row = count($users);
                }
        
            }
            elseif($type == "comment"){
                if(!array_key_exists('number',$args)){
                    // $args['number'] = -1;
                    // $args['cache_results'] = false;
                }
                $comment_query = new \WP_Comment_Query($args);
                $comments = $comment_query->comments;
                if(!empty($comments)){
                    $total_row = count($comments);
                }
                $module =  'Comments';
            }
            wp_send_json_success(['message' => 'success','total_row' => $total_row ." ".$module]);  
        }
        $query = new \WP_Query($query_args);
    }
    public function validate_wpai_query_args($args,$type) {
        if (($type == 'post') && (!is_array($args) || !isset($args['post_type']))) {
            return new \WP_Error('invalid_query', 'Invalid WP_Query arguments.');
        }
        elseif($type ==  'user' && (!is_array($args) || !isset($args['role']))){
            return new \WP_Error('invalid_query', 'Invalid WP_Query arguments.');
        }
        elseif($type ==  'comment' && (!is_array($args) || !isset($args['status']))){
            return new \WP_Error('invalid_query', 'Invalid WP_Query arguments.');
        }
        return $args;
    }
    public function exportwpquery($query_args){
        

        // $query_args = json_decode(stripslashes($query_args), true);
        // $query_args = str_replace(['\\"', ' '], ['"', ''], $query_args);
        // $pairs = explode(',', $query_args);
        // $args = [];
        // foreach ($pairs as $pair) {
        //     list($key, $value) = explode('=>', $pair);
        //     $key = trim($key, '"');
        //     $value = trim($value, '"');
        //     $args[$key] = $value;
        // }   
        $step1 = str_replace('=>', ':', $query_args);
        $step2 = str_replace(
            ['array(', ')', "'",],
            ['[', ']', '"'],
            $step1
        );
        $json_string = '{' . $step2 . '}';
        $json_string = preg_replace('/,\s*\]/', ']', $json_string); // remove trailing commas    
        $args = json_decode(stripslashes($json_string), true);
        // $converted = str_replace('=>', ':', $query_args);

       
        // $converted = preg_replace('/array\s*\(/', '{', $converted);
        // $converted = preg_replace('/\)/', '}', $converted);

       
        // $converted = preg_replace('/"([^"]*?)"\s*:/', '"$1":', $converted); // keys
        // $converted = preg_replace('/: \s*"([^"]*?)"/', ': "$1"', $converted); // values

       
        // $converted = preg_replace('/,\s*}/', '}', $converted);
        // $converted = preg_replace('/,\s*]/', ']', $converted);

       
       
        // $json_like = '{' . $converted . '}';

       
        // $args = json_decode($json_like, true);
        // $patterns = [
        //     '/array\s*\(/' => '[',
        //     '/\)/' => ']',
        //     '/=>/' => ':',
        // ];
        
       
        // $jsonLike = preg_replace(array_keys($patterns), array_values($patterns), $query_args);
        
       
        // $jsonString = '{' . $jsonLike . '}';
        
       
        // $jsonString = preg_replace('/"(\w+)"\s*:/', '"$1":', $jsonString);
        // $jsonString = preg_replace('/:\s*"([^"]+)"/', ': "$1"', $jsonString);
        
        // $args = json_decode($jsonString, true);
        if(!array_key_exists('posts_per_page',$args)){
            $args['posts_per_page'] = -1;
        }
        $query = new \WP_Query($args);
        $posts = $query->get_posts();
        WPQueryExport::$export_instance = ExportExtension::getInstance();
        WPQueryExport::$post_export = PostExport::getInstance();
        WPQueryExport::$woocom_export = WooCommerceExport::getInstance();
        WPQueryExport::$learnpress_export = LearnPressExport::getInstance();
        if(!empty($posts)){
            WPQueryExport::$export_instance->totalRowCount = count($posts);
        }
        $offset  = WPQueryExport::$export_instance->offset;
        $limit = WPQueryExport::$export_instance->limit;
        foreach($posts as $post_key => $post_val){
            $post_meta_data = get_post_meta($post_val->ID);
         
            $postId = $post_val->ID;
            $module = $post_val->post_type;
            if($module == 'product' && is_plugin_active('woocommerce/woocommerce.php')){
                $module = 'WooCommerce';
                $optionalType = '';
            }
            else{
                if($module !== 'post' && $module !=='page'){
                    $optionalType = $module;
                    $module = 'CustomPosts';

                }
                if($module == 'post'){
                    $module = 'Posts';
                }
                if($module=='page'){
                    $module= 'Pages';
                }
            }
           
            $postids [] = $post_val->ID;
        }
        WPQueryExport::$export_instance->generateHeaders($module, $this->optionalType);


        $post_ids = !empty($postids) ? array_slice($postids, $offset, $limit) : [];  
       

        foreach($post_ids as $postkey => $postId){
            // foreach($post_val as $postkey => $postval){
               
                // WPQueryExport::$export_instance->data[$post_val->ID][$postkey] = $postval;
                if ($module == 'Posts' || $module == 'WooCommerce' || $module == 'CustomPosts' || $module == 'Categories' || $exp_module == 'Tags' || $exp_module == 'Taxonomies' || $exp_module == 'Pages')
                {
                    WPQueryExport::$export_instance->getWPMLData($postId, $this->optionalType, $module);
                }
    
                if ($module == 'Posts' || $module == 'CustomPosts' || $module == 'Pages' || $module == 'WooCommerce')
                {
                    if(is_plugin_active('polylang/polylang.php') || is_plugin_active('polylang-pro/polylang.php') || is_plugin_active('polylang-wc/polylang-wc.php')){
                        WPQueryExport::$export_instance->getPolylangData($postId, $this->optionalType, $exp_module);
                    }
                }
                WPQueryExport::$export_instance->data[$postId] = WPQueryExport::$export_instance->getPostsDataBasedOnRecordId($postId,$module);	
                WPQueryExport::$post_export->getPostsMetaDataBasedOnRecordId($postId, $module, $optionalType);
                WPQueryExport::$export_instance->getTermsAndTaxonomies($postId, $module, $optionalType);
                if ($module == 'WooCommerce') WPQueryExport::$woocom_export->getProductData($postId, $module, $optionalType);
                // if ($module == 'WooCommerceRefunds') ExportExtension::$woocom_export->getWooComCustomerUser($postId, $this->module, $this->optionalType);
                if ($module == 'WooCommerceOrders') WPQueryExport::$woocom_export->getWooComOrderData($postId, $this->module, $this->optionalType);
                if ($module == 'WooCommerceVariations') WPQueryExport::$woocom_export->getVariationData($postId, $this->module, $this->optionalType);
                if($module == 'WooCommerceCoupons') WPQueryExport::$woocom_export->getCouponsData($postId, $this->module, $this->optionalType);
                if ($optionalType == 'lp_course') WPQueryExport::$learnpress_export->getCourseData($postId);
                if ($optionalType == 'lp_lesson') WPQueryExport::$learnpress_export->getLessonData($postId);
                if ($optionalType == 'lp_quiz') WPQueryExport::$learnpress_export->getQuizData($postId);
                if ($optionalType == 'lp_question') WPQueryExport::$learnpress_export->getQuestionData($postId);
                if ($optionalType == 'lp_order') WPQueryExport::$learnpress_export->getOrderData($postId);

                if ($optionalType == 'stm-courses') WPQueryExport::$woocom_export->getCourseDataMasterLMS($postId);

                if ($optionalType == 'stm-questions') WPQueryExport::$woocom_export->getQuestionDataMasterLMS($postId);

                if ($optionalType == 'stm-lessons') WPQueryExport::$woocom_export->getLessonDataMasterLMS($postId);
                if ($optionalType == 'stm-orders') WPQueryExport::$woocom_export->orderDataMasterLMS($postId);
                if ($optionalType == 'stm-quizzes') WPQueryExport::$woocom_export->quizzDataMasterLMS($postId);
                if ($optionalType == 'elementor_library') WPQueryExport::$woocom_export->elementor_export($postId);
                if ($optionalType == 'nav_menu_item') WPQueryExport::$woocom_export->getMenuData($postId);

                // if ($optionalType == 'widgets') self::$instance->getWidgetData($postId, $this->headers);
            // }
        }
        /** Added post format for 'standard' property */
        if ($module == 'Posts' || $module == 'CustomPosts' || $module == 'WooCommerce')
        {
            foreach (WPQueryExport::$export_instance->data as $id => $records)
            {
                if (!array_key_exists('post_format', $records))
                {
                    $records['post_format'] = 'standard';
                    
                    WPQueryExport::$export_instance->data[$id] = $records;
                }
            }

        }
        if($optionalType == 'course'){
            foreach(WPQueryExport::$export_instance->data as $id => $records){
                if(array_key_exists('_llms_instructors',$records)){
                    $instructor=unserialize($records['_llms_instructors']);
                    if(is_array($instructor)){
                        $arr_ins=array();
                        foreach($instructor as $ins_val){
                            $arr_val=array_values($ins_val);
                            unset($arr_val[0]);
                            unset($arr_val[2]);
                            $arr_ins[] = implode(',',$arr_val);

                        }
                        $records['_llms_instructors'] = implode('|',$arr_ins);
                        WPQueryExport::$export_instance->data[$id] = $records;

                    }


                }
            }
        }

           
        $result = WPQueryExport::$export_instance->finalDataToExport(WPQueryExport::$export_instance->data, $module,$optionalType);
        WPQueryExport::$export_instance->proceedExport($result);


    }
    public function exportwpquery_user($query_args){

        // $query_args = str_replace(['\\"', ' '], ['"', ''], $query_args);
        // $pairs = explode(',', $query_args);
        // $args = [];      
        // foreach ($pairs as $pair) {
        //     list($key, $value) = explode('=>', $pair);
        //     $key = trim($key, '"');
        //     $value = trim($value, '"');
        //     $args[$key] = $value;
        // }
        $step1 = str_replace('=>', ':', $query_args);
        $step2 = str_replace(
            ['array(', ')', "'",],
            ['[', ']', '"'],
            $step1
        );
        $json_string = '{' . $step2 . '}';
        $json_string = preg_replace('/,\s*\]/', ']', $json_string); 
        $args = json_decode(stripslashes($json_string), true);
        if(!array_key_exists('number',$args)){
            $args['number'] = -1;
        }
        $user_query = new \WP_User_Query($args);
        $users = $user_query->get_results();
        $module =  'Users';

        WPQueryExport::$export_instance = ExportExtension::getInstance();
        WPQueryExport::$post_export = PostExport::getInstance();
        WPQueryExport::$export_instance->generateHeaders($module, $this->optionalType);

        $offset  = WPQueryExport::$export_instance->offset;
        $limit = WPQueryExport::$export_instance->limit;
        if(is_countable($users)&& !empty($users)){
            WPQueryExport::$export_instance->totalRowCount = count($users);
            $userids = array();
            foreach($users as $user_key => $user_val){
                $userids [] = $user_val->ID;
                $user_data = $user_val->data;
                foreach($user_data as $userkey => $userval){
                    WPQueryExport::$export_instance->data[$user_val->ID][$userkey] = $userval;
                }
            }
            $user_ids = !empty($users) ? array_slice($userids, $offset, $limit) : [];   

        }
        global $wpdb;
        foreach($user_ids as $userkey => $userId){
            $userMeta = $wpdb->get_results("SELECT user_id, meta_key, meta_value FROM {$wpdb->prefix}users wp JOIN {$wpdb->prefix}usermeta wpm ON wpm.user_id = wp.ID WHERE ID = {$userId}");
            $wptypesfields = get_option('wpcf-usermeta');
            $wptypesfields = get_option('wpcf-usermeta');

            if (!empty($wptypesfields))
            {
                $i = 1;
                foreach ($wptypesfields as $key => $value)
                {
                    $typesf[$i] = 'wpcf-' . $key;
                    $typeOftypesField[$typesf[$i]] = $value['type'];
                    $i++;
                }
            }
            if (!empty($userMeta))
            {
                foreach ($userMeta as $userMetaInfo)
                {
                    if ($userMetaInfo->meta_key == $wpdb->prefix.'capabilities')
                    {
								
                        if(is_plugin_active('members/members.php')){
                            $data = unserialize($userMetaInfo->meta_value);
                            $roles = array_keys(array_filter($data));
                            $role = implode('|', $roles);
                            WPQueryExport::$export_instance->data[ $userId ][ 'multi_user_role' ] = $role;
                        }
                        else{
                            $userRole = WPQueryExport::$export_instance->getUserRole($userMetaInfo->meta_value);
                            WPQueryExport::$export_instance->data[ $userId ][ 'role' ] = $userRole;
                        }

                        }
                        elseif ($userMetaInfo->meta_key == 'description')
                        {
                            WPQueryExport::$export_instance->data[$userId]['biographical_info'] = $userMetaInfo->meta_value;
                        }
                        elseif ($userMetaInfo->meta_key == 'comment_shortcuts')
                        {
                            WPQueryExport::$export_instance->data[$userId]['enable_keyboard_shortcuts'] = $userMetaInfo->meta_value;
                        }
                        elseif ($userMetaInfo->meta_key == 'show_admin_bar_front')
                        {
                            WPQueryExport::$export_instance->data[$userId]['show_toolbar'] = $userMetaInfo->meta_value;
                        }
                        elseif ($userMetaInfo->meta_key == 'rich_editing')
                        {
                            WPQueryExport::$export_instance->data[$userId]['disable_visual_editor'] = $userMetaInfo->meta_value;
                        }
                        elseif ($userMetaInfo->meta_key == 'locale')
                        {
                            WPQueryExport::$export_instance->data[$userId]['language'] = $userMetaInfo->meta_value;
                        }
                        elseif (isset($typesf) && in_array($userMetaInfo->meta_key, $typesf))
                        {
                            $typeoftype = $typeOftypesField[$userMetaInfo->meta_key];
                            if (is_serialized($userMetaInfo->meta_value))
                            {
                                $typefileds = unserialize($userMetaInfo->meta_value);
                                $typedata = "";
                                foreach ($typefileds as $key2 => $value2)
                                {
                                    if (is_array($value2))
                                    {
                                        foreach ($value2 as $key3 => $value3)
                                        {
                                            $typedata .= $value3 . ',';
                                        }
                                    }
                                    else $typedata .= $value2 . ',';
                                }
                                if (preg_match('/wpcf-/', $userMetaInfo->meta_key))
                                {
                                    $userMetaInfo->meta_key = preg_replace('/wpcf-/', '', $userMetaInfo->meta_key);
                                    WPQueryExport::$export_instance->data[$userId][$userMetaInfo->meta_key] = substr($typedata, 0, -1);
                                }
                            }
                            elseif ($typeoftype == 'date')
                            {
                                WPQueryExport::$export_instance->data[$userId][$userMetaInfo->meta_key] = date('Y-m-d', $userMetaInfo->meta_value);
                            }
                            $multi_row = '_' . $userMetaInfo->meta_key . '-sort-order';

                            $multi_data = get_user_meta($userId, $multi_row);
                            $multi_data = $multi_data[0];
                            if (is_array($multi_data))
                            {
                                foreach ($multi_data as $k => $mid)
                                {
                                    $m_data = WPQueryExport::$export_instance->get_common_post_metadata($mid);
                                    if ($typeoftype == 'date') $multi_data[$k] = date('Y-m-d H:i:s', $m_data['meta_value']);
                                    else $multi_data[$k] = $m_data['meta_value'];
                                }
                                WPQueryExport::$export_instance->data[$userId][$userMetaInfo->meta_key] = implode('|', $multi_data);
                                if (preg_match('/wpcf-/', $userMetaInfo->meta_key))
                                {
                                    $userMetaInfo->meta_key = preg_replace('/wpcf-/', '', $userMetaInfo->meta_key);

                                    WPQueryExport::$export_instance->data[$userId][$userMetaInfo->meta_key] = implode('|', $multi_data);
                                }
                            }
                            else
                            {
                                if (preg_match('/wpcf-/', $userMetaInfo->meta_key))
                                {
                                    $userMetaInfo->meta_key = preg_replace('/wpcf-/', '', $userMetaInfo->meta_key);
                                    WPQueryExport::$export_instance->data[$userId][$userMetaInfo->meta_key] = $userMetaInfo->meta_value;
                                }
                            }
                        }

                        else
                        {

                            WPQueryExport::$export_instance->data[$userId][$userMetaInfo
        ->meta_key] = $userMetaInfo->meta_value;
                        }
                    }
						// Prepare the buddy meta details to be export
						if (is_plugin_active('buddypress/bp-loader.php'))
						{
							$query_to_fetch_buddy_meta = $wpdb->prepare("SELECT user_id,field_id,value,name FROM {$wpdb->prefix}bp_xprofile_data bxd inner join {$wpdb->prefix}users wp  on bxd.user_id = wp.ID inner join {$wpdb->prefix}bp_xprofile_fields bxf on bxf.id = bxd.field_id where user_id=%d", $userId);
							$buddy = $wpdb->get_results($query_to_fetch_buddy_meta);
							if (!empty($buddy))
							{
								foreach ($buddy as $buddyInfo)
								{
									foreach ($buddyInfo as $field_id => $value)
									{
										$this->data[$userId][$buddyInfo
	       ->name] = $buddyInfo->value;
									}
								}
							}
						}
						WPQueryExport::$post_export->getPostsMetaDataBasedOnRecordId($userId, $module, $this->optionalType);
					}
        }
        $result = WPQueryExport::$export_instance->finalDataToExport(WPQueryExport::$export_instance->data, $module,$this->optionalType);
        WPQueryExport::$export_instance->proceedExport($result);
    }
    public function exportwpquery_comment($query_args){

        // $query_args = json_decode(stripslashes($args), true);
        // $query_args = str_replace(['\\"', ' '], ['"', ''], $query_args);
        // $pairs = explode(',', $query_args);   
        // $args = [];
        // foreach ($pairs as $pair) {
        //     // Step 3: Split each pair by =>
        //     list($key, $value) = explode('=>', $pair);
        //     // Step 4: Trim quotes
        //     $key = trim($key, '"');
        //     $value = trim($value, '"');
        //     // Step 5: Add to array
        //     $args[$key] = $value;
        // }
        $step1 = str_replace('=>', ':', $query_args);
        $step2 = str_replace(
            ['array(', ')', "'",],
            ['[', ']', '"'],
            $step1
        );
        $json_string = '{' . $step2 . '}';
        $json_string = preg_replace('/,\s*\]/', ']', $json_string); // remove trailing commas
        $args = json_decode(stripslashes($json_string), true);
        if(!array_key_exists('number',$args)){
            // $args['number'] = -1;
            // $args['cache_results'] = false;
        }
      
        $comment_query = new \WP_Comment_Query($args);
        $comments = $comment_query->comments;
        $module =  'Comments';
        WPQueryExport::$export_instance = ExportExtension::getInstance();
        WPQueryExport::$post_export = PostExport::getInstance();
        WPQueryExport::$export_instance->generateHeaders($module, $this->optionalType);

        $offset  = WPQueryExport::$export_instance->offset;
        $limit = WPQueryExport::$export_instance->limit;
        if(is_countable($comments)){
            WPQueryExport::$export_instance->totalRowCount = count($comments);
            $commentids = array();
            foreach($comments as $comment_key => $comment_val){
                $commentids [] = $comment_val->comment_ID;
                foreach($comment_val as $commentkey => $commentval){
                    WPQueryExport::$export_instance->data[$comment_val->comment_ID][$commentkey] = $commentval;
                }
                
            }
            $comment_ids = !empty($comments) ? array_slice($commentids, $offset, $limit) : [];  
            foreach($comment_ids as $comment_key => $comment_id){
                foreach($comments as $comment_key => $comment_val){
                    if($comment_id == $comment_val->comment_ID){
                        $limited_comments = $comment_val;
                        if (!empty($limited_comments))
                        {
                            foreach ($limited_comments as $commentInfo)
                            {
                                $user_id = $commentInfo->user_id;
                                if (!empty($user_id))
                                {
                                    $users_login = $wpdb->get_results("SELECT user_login FROM {$wpdb->prefix}users WHERE ID = '$user_id'");
                                    foreach ($users_login as $users_key => $users_value)
                                    {
                                        foreach ($users_value as $u_key => $u_value)
                                        {
                                            $users_id = $u_value;
                                        }
                                    }
                                }
                                foreach ($commentInfo as $commentKey => $commentVal)
                                {
                                    WPQueryExport::$export_instance->data[$commentInfo->comment_ID][$commentKey] = $commentVal;
                                    WPQueryExport::$export_instance->data[$commentInfo->comment_ID]['user_id'] = isset($users_id) ? $users_id : '';
                                }
                                $get_comment_rating = get_comment_meta($commentInfo->comment_ID, 'rating', true);
                                if(!empty($get_comment_rating)){
                                    WPQueryExport::$export_instance->data[$commentInfo->comment_ID]['comment_rating'] = $get_comment_rating;
                                }
                            }
                        }
                    }
                }

            }
           
			$result = WPQueryExport::$export_instance->finalDataToExport(WPQueryExport::$export_instance->data, $module,$this->optionalType);
			if ($mode == null) WPQueryExport::$export_instance->proceedExport($result);
			else return $result; 

        }
    }
}

global $wpquery_exp_class;
$wpquery_exp_class = new WPQueryExport();
