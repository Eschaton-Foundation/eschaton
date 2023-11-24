<?php
/*
	Version 0.2
		Remove "Howdy" and dashboard widgets

*/

$menuPlaceholder = array();
class CHIPSWPOverridesPage
{
	private $options;

	public function __construct()
	{
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

	public function add_plugin_page()
	{
		// This page will be under "Settings"
		add_options_page(
			'Settings Admin', 
			'CHIPS WP Overrides', 
			'manage_options', 
			'chips-wp-overrides-admin', 
			array( $this, 'create_admin_page' )
		);
	}
	
	public function create_admin_page(){
		$this->options = get_option( 'chips_wp_overrides_option_name', array(
			'scriptsFooter' => 1,
			'removeRSS' => 1,
			'removeEmoji' => 1,
			'removeAddMedia' => 0
		));
		?>
		<div class="wrap">
			<h2>CHIPS WP Overrides</h2>           
			<form method="post" action="options.php">
			<?php // This prints out all hidden setting fields
				settings_fields( 'chips_wp_overrides_option_group' );   
				do_settings_sections( 'chips-wp-overrides-admin' );
				submit_button(); 
			?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init()
	{        
		register_setting(
			'chips_wp_overrides_option_group', // Option group
			'chips_wp_overrides_option_name', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

		add_settings_section(
			'wp_overrides_section_id', // ID
			'Disable Sidebar Items', // Title
			array( $this, 'print_wp_overrides' ), // Callback
			'chips-wp-overrides-admin' // Page
		); 	

	}

	public function print_wp_overrides() {
		$options = get_option( 'chips_wp_overrides_option_name', array(
			'scriptsFooter' => 1,
			'removeRSS' => 1,
			'removeEmoji' => 1,
			'removeAddMedia' => 0
		));	

		global $submenu, $menuPlaceholder;
		if ( current_user_can('manage_options') ) { 

			echo '<table>';
			foreach($menuPlaceholder as $key=>$item) {
				if ($item[0] && $item[0] !== "Settings") {
					print '<tr>
						<td>
							<input type="checkbox" name="chips_wp_overrides_option_name[menuItem' . $key . ']" id="menuItem' . $key . '" value="1"' . checked( 1, $options["menuItem" . $key], false ) . '/>
						</td>
						<td>
							<label for="menuItem' . $key . '">Hide ' . $item[0] . ' from Admins</label>
						</td>
						<td> &nbsp; </td>
						<td>
							<input type="checkbox" name="chips_wp_overrides_option_name[menuItem' . $key . '_chips]" id="menuItem' . $key . '_chips" value="1"' . checked( 1, $options["menuItem" . $key.'_chips'], false ) . '/>
						</td>
						<td>
							<label for="menuItem' . $key . '_chips">Hide ' . $item[0] . ' from CHIPS</label>
						</td>
					</tr>';
				}
			}
			echo '</table>
			<table>';
			print '<tr>
						<td colspan="3">
							<h2>Markup Cleaner</h2>
						</td>
					</tr>
					<tr>
						<td>
							<input type="checkbox" name="chips_wp_overrides_option_name[scriptsFooter]" id="scriptsFooter" value="1"' . checked( 1, $options['scriptsFooter'], false ) . '/>
						</td>
						<td>
							<label for="scriptsFooter">Move scripts to footer</label>
						</td>
					</tr>
					<tr>
						<td><input type="checkbox" name="chips_wp_overrides_option_name[removeRSS]" id="removeRSS" value="1"' . checked( 1, $options['removeRSS'], false ) . '/></td>
						<td>
							<label for="removeRSS">Remove RSS from head</label>
						</td>
					</tr>
					<tr>
						<td><input type="checkbox" name="chips_wp_overrides_option_name[removeEmoji]" id="removeEmoji" value="1"' . checked( 1, $options['removeEmoji'], false ) . '/></td>
						<td>
							<label for="removeEmoji">Remove Emoji scripts</label>
						</td>
					</tr>
					<tr>
						<td><input type="checkbox" name="chips_wp_overrides_option_name[removeAddMedia]" id="removeAddMedia" value="1"' . checked( 1, $options['removeAddMedia'], false ) . '/></td>
					</td>
					<td>
						<label for="removeAddMedia">Remove \'Add Media\' button</label>
					</td>
				</tr>
			</table>';
		}
	}


	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 */
	public function sanitize( $input )
	{
		global $submenu, $menuPlaceholder;
		$new_input = array();
		$removeArr = array();
		foreach($menuPlaceholder as $key=>$item) {
			if ($item[0]) {
				if( isset( $input['menuItem' . $key] ) ) {
					$new_input['menuItem' . $key] = sanitize_text_field( $input['menuItem' . $key] );
					$removeArr[] = $item[0];
				}
				if( isset( $input['menuItem' . $key.'_chips'] ) ) {
					$new_input['menuItem' . $key.'_chips'] = sanitize_text_field( $input['menuItem' . $key.'_chips'] );
					$removeArr[] = $item[0];
				}
			}
		}

		if( isset( $input['scriptsFooter'] ) ) {
			$new_input['scriptsFooter'] = sanitize_text_field( $input['scriptsFooter'] );
		}
		if( isset( $input['removeRSS'] ) ) {
			$new_input['removeRSS'] = sanitize_text_field( $input['removeRSS'] );
		}
		if( isset( $input['removeEmoji'] ) ) {
			$new_input['removeEmoji'] = sanitize_text_field( $input['removeEmoji'] );
		}
		if( isset( $input['removeAddMedia'] ) ) {
			$new_input['removeAddMedia'] = sanitize_text_field( $input['removeAddMedia'] );
		}

		return $new_input;
	}

	public function print_section_info(){
		// print 'Enter your settings below:';
	}
	
}

if( is_admin() )
	$chips_wp_overrides_settings_page = new CHIPSWPOverridesPage();

	function remove_admin_menu_items() {

		global $menu;
		global $menuPlaceholder;
		$menuPlaceholder = $menu;
		// if(wp_get_current_user()->user_login == 'chips') {
		// 	return;
		// }
		// error_log(print_r($removeItems,true));
		$isCHIPS = (wp_get_current_user()->user_login == 'chips');
		$removeItems = get_option( 'chips_wp_overrides_option_name' );
		if (is_array($removeItems) && !empty($removeItems)) {
			$remove_menu_items = array('Links','Comments');
			
			end ($menu);

			foreach($menu as $key=>$val) {
				foreach($removeItems as $rKey => $rVal) {
					if('menuItem' . $key == $rKey && !$isCHIPS) {
						unset($menu[$key]);
					} else if($isCHIPS && (strpos($rKey, '_chips') !== false) && 'menuItem' . $key . '_chips' == $rKey){
						unset($menu[$key]);
					}
				}
				
			}
		}

		remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
		remove_meta_box('dashboard_activity', 'dashboard', 'normal');
		remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
		remove_meta_box('dashboard_primary', 'dashboard', 'core');
	}

	add_filter('gettext', 'change_howdy', 10, 3);
	function change_howdy($translated, $text, $domain) {
		if (!is_admin() || 'default' != $domain)
			return $translated;

		if (false !== strpos($translated, 'Howdy'))
			return str_replace('Howdy, ', '', $translated);

		return $translated;
	}

	$removeItems = get_option( 'chips_wp_overrides_option_name', array(
		'scriptsFooter' => 1,
		'removeRSS' => 1,
		'removeEmoji' => 1,
		'removeAddMedia' => 0
	) );
	if (is_array($removeItems) && !empty($removeItems)) {
		if (!empty($removeItems['scriptsFooter']) && isset($removeItems['scriptsFooter'])) {
			remove_action('wp_head', 'wp_print_scripts');
			remove_action('wp_head', 'wp_print_head_scripts', 9);
			remove_action('wp_head', 'wp_enqueue_scripts', 1);
			add_action('wp_footer', 'wp_print_scripts', 5);
			add_action('wp_footer', 'wp_enqueue_scripts', 5);
			add_action('wp_footer', 'wp_print_head_scripts', 5);
		}
		if (!empty($removeItems['removeRSS']) && isset($removeItems['removeRSS'])) {
			remove_action( 'wp_head', 'feed_links_extra', 3);
			remove_action( 'wp_head', 'feed_links', 2);
		}
		if (!empty($removeItems['removeEmoji']) && isset($removeItems['removeEmoji'])) {
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			add_filter( 'emoji_svg_url', '__return_false' );
		}
		if (!empty($removeItems['removeAddMedia']) && isset($removeItems['removeAddMedia'])) {
			add_action('admin_head', 'RemoveAddMediaButtons');
		}
	}

	//Removed by default
	remove_action( 'wp_head', 'rsd_link');
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'index_rel_link' );

	remove_action( 'wp_head', 'parent_post_rel_link', 10);
	remove_action( 'wp_head', 'start_post_rel_link', 10);
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10);
	remove_action( 'wp_head', 'wp_generator');

	remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
	remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
	remove_filter('wp_head', 'wp_widget_recent_comments_style' );

	//widget cleanup
	add_action( 'widgets_init', 'chips_widget_cleanup' );
	add_action('init', 'disable_embeds_init', 9999);
	add_filter('stylesheet_uri','wpi_stylesheet_uri',10,2);
	add_action('admin_menu', 'remove_admin_menu_items');


	function RemoveAddMediaButtons(){ remove_action( 'media_buttons', 'media_buttons' );    }
	function chips_widget_cleanup() {  
		global $wp_widget_factory;  
		remove_action( 'wp_head', array( $wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style' ) );  
	}  
	function disable_embeds_init() {
		remove_action('rest_api_init', 'wp_oembed_register_route');
		remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
		remove_action('wp_head', 'wp_oembed_add_discovery_links');
		remove_action('wp_head', 'wp_oembed_add_host_js');
	}
	// function is_login_page() {
	//     return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
	// }
	function wpi_stylesheet_uri($stylesheet_uri, $stylesheet_dir_uri){
		return $stylesheet_dir_uri.'/css/styles.css';
	}

?>