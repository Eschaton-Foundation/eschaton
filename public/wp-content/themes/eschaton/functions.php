<?php
add_theme_support('post-thumbnails');
add_theme_support('menus');
add_post_type_support('page', 'excerpt');

include("_posttypes.php");
include("_taxonomies.php");
require_once('classes/flex.php');

include "snippets/shortcodes.php";
include "snippets/collapse-flexible-rows.php";

add_action('init', 'chipsbase_styles');
function chipsbase_styles()
{
	if (!is_admin()) {
		wp_enqueue_style('chips-styles', get_template_directory_uri() . '/css/styles.css?v=0.047');
	}
}
function chipsScripts()
{
	if (!is_admin()) {
		wp_deregister_script('jquery');
		wp_register_script(
			'jquery',
			'//code.jquery.com/jquery-3.6.0.min.js',
			false,
			'3.6.0'
		);
		// wp_register_script('jquery', get_bloginfo('template_directory') . '/js/vendor/jquery.min.js');
		wp_enqueue_script('jquery');
		wp_register_script('zoomjs', get_bloginfo('template_directory') . '/js/vendor/panzoom.min.js');
		wp_enqueue_script('zoomjs', null, null, null, true);
		wp_register_script('scriptsJS', get_bloginfo('template_directory') . '/js/app.js?v=0.020');
		wp_enqueue_script('scriptsJS', null, null, null, true);

		wp_enqueue_script('filters', get_template_directory_uri() . '/js/filters.js', [], rand(), true);
		wp_localize_script('filters', 'ajax_var', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wp-pageviews-nonce' ),
		));

	}
}
add_action('init', 'chipsScripts');

if (function_exists('acf_add_options_page')) {
	acf_add_options_page(array(
		'page_title' => 'Global Options',
		'icon_url' => 'dashicons-admin-generic',
	));
	acf_add_options_page(array(
		'page_title' => 'Tour Options',
		'icon_url' => 'dashicons-admin-generic',
	));
	acf_add_options_page(array(
		'page_title' => 'Emails Formatted',
		'icon_url' => 'dashicons-email-alt',
	));
}

function splitMore($passedText)
{
	$morestring = '<!--more-->';
	$explode_content = explode($morestring, $passedText);
	$content_before = apply_filters('the_content', $explode_content[0]);

	$outText = $content_before;
	if (!empty($explode_content[1])) {
		$outText = $outText;
		$outText .= '<span class="readmore-toggle color-subdue"></span>';

		$content_after = apply_filters('the_content', $explode_content[1]);
		$outText .= '<div class="readmore-content">' . $content_after . '</div>';
	}
	return $outText;
}

function orientClass($inputRatio)
{
	if ($inputRatio > 1.36) {
		return "vertX";
	} else if ($inputRatio > 1.05) {
		return "vert";
	} else if ($inputRatio > 1) {
		return "squareplus";
	} else if ($inputRatio > 0.95) {
		return "squareminus";
	} else {
		return "hor";
	}
}


// Attempt to modify translation to enable wysiwyg
//trp-translation-input
/*
	<script type="module">
    import { Editor } from 'https://cdn.skypack.dev/@tiptap/core?min'
    import StarterKit from 'https://cdn.skypack.dev/@tiptap/starter-kit?min'
    const editor = new Editor({
      element: document.querySelector('.element'),
      extensions: [
        StarterKit,
      ],
      content: '<p>Hello World!</p>',
    })
  </script>
*/
function mod_translation_textarea($hook)
{
	// Only add to the edit.php admin page.
	// See WP docs.
	echo '***' . $hook;
	if ('edit.php' !== $hook) {
		return;
	}
	wp_enqueue_script('my_custom_script', plugin_dir_url(__FILE__) . '/myscript.js');
}

// Force Gravity Forms to init scripts in the footer and ensure that the DOM is loaded before scripts are executed.
add_filter('gform_init_scripts_footer', '__return_true');
add_filter('gform_cdata_open', 'wrap_gform_cdata_open', 1);
add_filter('gform_cdata_close', 'wrap_gform_cdata_close', 99);
// add_filter("gform_tabindex", create_function("", "return 1;"));

function wrap_gform_cdata_open($content = '')
{
	if (!do_wrap_gform_cdata()) {
		return $content;
	}
	$content = 'document.addEventListener( "DOMContentLoaded", function() { ' . $content;
	return $content;
}

function wrap_gform_cdata_close($content = '')
{
	if (!do_wrap_gform_cdata()) {
		return $content;
	}
	$content .= ' }, false );';
	return $content;
}

function do_wrap_gform_cdata()
{
	if (
		is_admin()
		|| (defined('DOING_AJAX') && DOING_AJAX)
		|| isset($_POST['gform_ajax'])
		|| isset($_GET['gf_page']) // Admin page (eg. form preview).
		|| doing_action('wp_footer')
		|| did_action('wp_footer')
	) {
		return false;
	}
	return true;
}

/*
add_filter('gform_pre_render_7', 'populate_posts');
add_filter('gform_pre_validation_7', 'populate_posts');
add_filter('gform_pre_submission_filter_7', 'populate_posts');
add_filter('gform_admin_pre_render_7', 'populate_posts');
function populate_posts($form)
{
	global $tourChoices;
	foreach ($form['fields'] as &$field) {
		if ($field->id == 18) {
			print_r($field);
			$field->placeholder = 'Tour Date';
			$field->defaultValue = 'Testing form dynamic';
			// $field->choices = $tourChoices;
			// $field->isSelected = 249;
		}
	}
	return $form;
}
*/
add_filter('gform_field_value_tourdate', 'gf_set_tourdate');
function gf_set_tourdate($value)
{
	return get_the_title();
}
add_filter('gform_field_value_tourid', 'gf_set_tourid');
function gf_set_tourid($value)
{
	return get_the_ID();
}
// add_filter('gform_field_valuelangprefgroup', 'gf_setlangprefgroup');
// function gf_setlangprefgroup($value)
// {
// 	return get_field("lang_pref");
// }
// add_filter('gform_field_groupcost', 'gf_setcostgroup');
// function gf_setcostgroup($value)
// {
// 	return get_field("groupcost");
// }



add_filter('gform_field_value_langpref', 'gf_set_langpref');
function gf_set_langpref($value)
{
	return get_locale();
}

add_filter('gform_validation_7', 'eschaton_booking_validator');
function eschaton_booking_validator($validation_result)
{
	$form = $validation_result['form'];

	// print_r($form);

	$tourSelectedDate = rgpost('input_21');


	// if (rgpost('input_2') == '') {
	// $validation_result['is_valid'] = false;
	// print_r($form);
	foreach ($form['fields'] as &$field) {
		if ($field->id == 21) {
			$field->failed_validation = true;

			$numTicketsNeeded = 0;
			$availableInventory = get_field("inventory", $tourSelectedDate);
			if ($availableInventory === null && $availableInventory !== '0') {
				$availableInventory = get_field("group_limit_default", "options");
			}

			$tixAdult = rgpost('input_3_3');
			$tixReduced = rgpost('input_4_3');
			$tixChild = rgpost('input_12_3');
			// $tixResident = getQtyfromVal(rgpost('input_13'));

			$numTicketsNeeded = $numTicketsNeeded + $tixAdult + $tixReduced + $tixChild;

			if ($numTicketsNeeded > $availableInventory) {
				$validation_result['is_valid'] = false;
				$field->validation_message = '' . $tixAdult . ' adult and ' . $tixReduced . ' reduced, and ' . $tixChild . ' child, totaling ' . $numTicketsNeeded . ', ' . $availableInventory . ' available';
				break;
			} else if($numTicketsNeeded < 1){
				$validation_result['is_valid'] = false;
				$field->validation_message = 'Please select at least 1 type of ticket.';
				break;
			}
		}
	}
	// }

	//Assign modified $form object back to the validation result
	$validation_result['form'] = $form;
	return $validation_result;
}

add_filter('gform_validation_message', function ($message, $form) {
	if (gf_upgrade()->get_submissions_block()) {
		return $message;
	}

	$tempMessage = '';
	$overrideMessage = false;
	foreach ($form['fields'] as $field) {
		if ($field->id == 21 && $field->failed_validation) {
			$overrideMessage = true;
			$tempMessage .= sprintf('<small class="blok">%s</small>', $field->validation_message);
		}
	}

	if ($overrideMessage) {
		return $tempMessage;
	} else {
		return $message;
	}

	return $message;
}, 10, 2);

function getQtyfromVal($inputVal)
{
	if (strpos($inputVal, '|') === false) {
		return null;
	}
	$returnArr = explode('|', $inputVal);
	if (!empty($returnArr)) {
		return intval($returnArr[0]);
	}
	return null;
}

add_action('gform_after_submission_7', 'tour_after', 10, 2);
function tour_after($entry, $form)
{
	$allowEmail = true;
	// $allowEmail = false;
	$updateInventory = true;


	$post = get_post($entry['post_id']);
	$assocPost = rgar($entry, '21');
	$langPref = rgar($entry, '24');

	$assocEmail = rgar($entry, '7');
	$assocName = rgar($entry, '6');
	$assocTel = rgar($entry, '16');
	$guestNames = nl2br(rgar($entry, '23'));
	$orderDate = date('F j, Y');
	$tourDate = date('F j, Y',strtotime(get_field("date_tour",$assocPost)));
	$orderID = $entry['id'];

	$sendReducedEmail = false;

	if ($assocPost) {
		// echo 'Has associated post ' . $assocPost;
		$numTicketsNeeded = 0;
		$availableInventory = get_field("inventory", $assocPost);
		if ($availableInventory === null && $availableInventory !== '0') {
			$availableInventory = get_field("group_limit_default", "options");
		}

		$tixAdult = rgar($entry, '3.3');
		$tixReduced = rgar($entry, '4.3');
		$tixChild = rgar($entry, '12.3');
		// $tixResident = getQtyfromVal(rgar($entry, '13'));

		$tourLang = get_field("language", $assocPost);
		$tourLangOut = $tourLang;

		if($langPref){
			if(strpos($langPref,'de_') !== false){
				$tourLang = 'de';
			} else if(strpos($langPref,'fr_') !== false){
				$tourLang = 'fr';
			} else {
				$tourLang = 'en';
			}
		}

		$numTicketsNeeded = $numTicketsNeeded + $tixAdult + $tixReduced + $tixChild;

		// print_r($entry);

		if ($updateInventory) {
			if ($numTicketsNeeded <= $availableInventory) {
				$newInventory = $availableInventory - $numTicketsNeeded;
				update_field("inventory", $newInventory, $assocPost);
			} else {
				// flag - emergency email dan because somehow bought with no inventory
				$to = 'dan@chips.nyc';
				$subject = 'Critical - oversold tour';
				$body = 'Num needed: ' . $numTicketsNeeded . '<br>
				Num Available: ' . $availableInventory . '<br>
				' . print_r($entry, true);
				$headers = array('Content-Type: text/html; charset=UTF-8');

				wp_mail($to, $subject, $body, $headers);
			}
			$existing = get_field('tour_comms', $assocPost);

			if (!$existing) $existing = [];
			$existing[] = array('email_address' => $assocEmail);
			$updated = $existing;

			update_field('tour_comms', $updated, $assocPost);
		}

		
		$tixOut = '';
		if ($tixAdult && $tixAdult > 0) {

			$tixLabel = get_field("ticket_types_1", "options");
			if ($tourLang === 'de') {
				$labelOut = $tixLabel['german'];
			} else if ($tourLang === 'fr') {
				$labelOut = $tixLabel['french'];
			} else {
				$labelOut = $tixLabel['english'];
			}

			$tixOut .= $tixAdult . ' ' . $labelOut . '<br>';
		}
		if ($tixReduced && $tixReduced > 0) {
			$tixLabel = get_field("ticket_types_2", "options");
			if ($tourLang === 'de') {
				$labelOut = $tixLabel['german'];
			} else if ($tourLang === 'fr') {
				$labelOut = $tixLabel['french'];
			} else {
				$labelOut = $tixLabel['english'];
			}

			$tixOut .= $tixReduced . ' ' . $labelOut . '<br>';


			$sendReducedEmail = true;
		}
		if ($tixChild && $tixChild > 0) {
			$tixLabel = get_field("ticket_types_3", "options");
			if ($tourLang === 'de') {
				$labelOut = $tixLabel['german'];
			} else if ($tourLang === 'fr') {
				$labelOut = $tixLabel['french'];
			} else {
				$labelOut = $tixLabel['english'];
			}

			$tixOut .= $tixChild . ' ' . $labelOut . '<br>';
		}
		// if ($tixResident && $tixResident > 0) {
		// 	$tixLabel = get_field("ticket_types_4", "options");
		// 	if ($tourLang === 'de') {
		// 		$labelOut = $tixLabel['german'];
		// 	} else if ($tourLang === 'fr') {
		// 		$labelOut = $tixLabel['french'];
		// 	} else {
		// 		$labelOut = $tixLabel['english'];
		// 	}

		// 	$tixOut .= $tixResident . ' ' . $labelOut . '<br>';
		// }
		$numTix = $tixAdult + $tixReduced + $tixChild;

		/*
			Date de l'achat : [inserts système]
			Nom : [encarts système]
			Nombre de billets : [encarts système]
			Date de la visite : [inserts système]
			Référence de réservation : [encarts système]
		*/

		$booking_summary = get_field("text_booking_summary", "options");
		$name = get_field("text_name", "options");
		$dateBooked = get_field("text_date", "options");
		$tix_num = get_field("text_tix_num", "options");
		$date_tour = get_field("text_date_tour", "options");
		$reference = get_field("text_reference", "options");
		$guestname = get_field("text_guests", "options");
		$textTourIn = get_field("tour_in", "options");
		$time_tour = get_field("time_tour", $assocPost);
		if(!$time_tour){
			$time_tour = get_field("tour_start_global", "options");
		}
		$tourInOut = $textTourIn[$tourLang];
		// $tourInTrans = trp('Tour in ' . )
		$tempTourIn = '';
		if ($tourLangOut === 'fr') {
			$tempTourIn = 'Tour in French';
		} else if ($tourLangOut === 'de') {
			$tempTourIn = 'Tour in German';
		} else {
			$tempTourIn = 'Tour in English';
		}
		$tourInOut = $tempTourIn;

			$guestOut = '';
			if($guestNames && $guestNames !== ''){
				$guestOut = '<br>' .  $guestname[$tourLang] . ': <br><blockquote style="margin:0 15px;">' . $guestNames . '</blockquote>';
			}

		$orderConfirmation = '
		<div style="padding:16px;background:#ebebe8;border-radius:4px;">
		<table width="100%">
		<tr>
		<td style="font-family: \'Adobe Garamond Pro\', Constantia, \'Times New Roman\', Times, serif;font-size:22px;">
			<div class="booking-summary-wrap">
			' . $dateBooked[$tourLang] . ': ' . $orderDate . '<br>
			' . $name[$tourLang] . ': ' . $assocName . '<br>
			' . $tix_num[$tourLang] . ': ' . $numTicketsNeeded . '<br>
			' . $date_tour[$tourLang] . ': ' . $tourDate . '<br>
			' . $time_tour . '<br>
			' . $tourInOut . '<br>
			' . $reference[$tourLang] . ': ' . $orderID . '
			' . $guestOut . '
			</div>
		</td>
		</tr>
		</table></div>';


		$orderConfirmEmail = get_field("order_confirmation", "options");

		if ($tourLang === 'de') {
			$subjectOut = $orderConfirmEmail['email_subject_de'];
			$emailHTML = $orderConfirmEmail['german'];
		} else if ($tourLang === 'fr') {
			$subjectOut = $orderConfirmEmail['email_subject_fr'];
			$emailHTML = $orderConfirmEmail['french'];
		} else {
			$subjectOut = $orderConfirmEmail['email_subject'];
			$emailHTML = $orderConfirmEmail['english'];
		}

		$emailHTML = str_replace("{*NAME*}", $assocName, $emailHTML);
		$emailHTML = str_replace("{*TOURDATE*}", $orderDate, $emailHTML);
		$emailHTML = str_replace("{*TOURTIME*}", $time_tour, $emailHTML);
		$emailHTML = str_replace("{*ORDERSUMMARY*}", $orderConfirmation, $emailHTML);
		$emailHTML = str_replace("(number of tickets)", $numTix, $emailHTML);
		$emailHTML = str_replace("eschaton.test", 'eschaton-foundation.com', $emailHTML);



		// (date of purchase)
		// Name      - Number of Tickets     - Date and Time of Tour    - Reference Number

		$to = $assocEmail;
		$subject = $subjectOut;
		$body = $emailHTML;
		$headers = array('Content-Type: text/html; charset=UTF-8');

		// $headers[] = 'From: Me Myself <me@example.net>';
		// $headers[] = 'Cc: John Q Codex <jqc@wordpress.org>';
		// $headers[] = 'Cc: iluvwp@wordpress.org'; // note you can just use a simple email address

		if ($allowEmail) {
			wp_mail($to, $subject, $body, $headers);
			// echo $body;
		} else {
			echo $body;
		}


		if($sendReducedEmail && $allowEmail){
			$orderConfirmEmailReduced = get_field("reduced_email_text", "options");

			if ($tourLang === 'de') {
				$subjectOut = $orderConfirmEmailReduced['email_subject_de'];
				$emailHTMLReduced = $orderConfirmEmailReduced['german'];
			} else if ($tourLang === 'fr') {
				$subjectOut = $orderConfirmEmailReduced['email_subject_fr'];
				$emailHTMLReduced = $orderConfirmEmailReduced['french'];
			} else {
				$subjectOut = $orderConfirmEmailReduced['email_subject'];
				$emailHTMLReduced = $orderConfirmEmailReduced['english'];
			}

			$emailHTMLReduced = str_replace("{*NAME*}", $assocName, $emailHTMLReduced);

			$to = $assocEmail;
			$subjectReduced = $subjectOut;
			$bodyReduced = $emailHTMLReduced;
			$headers = array('Content-Type: text/html; charset=UTF-8');

			wp_mail($to, $subjectReduced, $bodyReduced, $headers);
		}
	}

	// print_r($entry);

	//changing post content
	// $post->post_content = 'Blender Version:' . rgar($entry, '7') . "<br/> <img src='" . rgar($entry, '8') . "'> <br/> <br/> " . rgar($entry, '13') . " <br/> <img src='" . rgar($entry, '5') . "'>";
	// wp_update_post($post);
}

add_action('gform_after_submission_8', 'grouptour_after', 10, 2);
function grouptour_after($entry, $form)
{
	$allowEmail = true;
	// $allowEmail = false;
	$updateInventory = false;


	$post = get_post($entry['post_id']);
	$assocPost = rgar($entry, '21');
	$langPref = rgar($entry, '24');

	$assocEmail = rgar($entry, '7');
	$assocName = rgar($entry, '6');
	$assocInstitution = rgar($entry, '26');
	$assocTel = rgar($entry, '16');
	$guestNames = nl2br(rgar($entry, '23'));
	$orderDate = date('F j, Y');
	$tourDate = date('F j, Y',strtotime(get_field("date_tour",$assocPost)));
	$orderID = $entry['id'];

	if ($assocPost) {
		// echo 'Has associated post ' . $assocPost;
		$tourLang = get_field("language", $assocPost);
		$tourLangOut = $tourLang;

		if($langPref){
			if(strpos($langPref,'de_') !== false){
				$tourLang = 'de';
			} else if(strpos($langPref,'fr_') !== false){
				$tourLang = 'fr';
			} else {
				$tourLang = 'en';
			}
		}

		// Add email address to booking form
		$existing = get_field('tour_comms', $assocPost);

		if (!$existing) $existing = [];
		$existing[] = array('email_address' => $assocEmail);
		$updated = $existing;

		// update_field('tour_comms', $updated, $assocPost);

		
		$booking_summary = get_field("text_booking_summary", "options");
		$name = get_field("text_name", "options");
		$dateBooked = get_field("text_date", "options");
		$date_tour = get_field("text_date_tour", "options");
		$time_tour = get_field("time_tour", $assocPost);
		if(!$time_tour){
			$time_tour = get_field("tour_start_global", "options");
		}
		$reference = get_field("text_reference", "options");
		$guestname = get_field("text_guests", "options");
		$textTourIn = get_field("tour_in", "options");
		$tourInOut = $textTourIn[$tourLang];
		// $tourInTrans = trp('Tour in ' . )
		$tempTourIn = '';
		if ($tourLangOut === 'fr') {
			$tempTourIn = 'Tour in French';
		} else if ($tourLangOut === 'de') {
			$tempTourIn = 'Tour in German';
		} else {
			$tempTourIn = 'Tour in English';
		}
		$tourInOut = $tempTourIn;

			$guestOut = '';
			if($guestNames && $guestNames !== ''){
				$guestOut = '<br>' .  $guestname[$tourLang] . ': <br><blockquote style="margin:0 15px;">' . $guestNames . '</blockquote>';
			}

		$orderConfirmation = '
		<div style="padding:16px;background:#ebebe8;border-radius:4px;">
		<table width="100%">
		<tr>
		<td style="font-family: \'Adobe Garamond Pro\', Constantia, \'Times New Roman\', Times, serif;font-size:22px;">
			<div class="booking-summary-wrap">
			' . get_the_title($assocPost) . '<br>
			' . $dateBooked[$tourLang] . ': ' . $orderDate . '<br>
			' . $name[$tourLang] . ': ' . $assocName . '<br>
			' . $assocInstitution . '<br>
			' . $date_tour[$tourLang] . ': ' . $tourDate . '<br>
			' . $time_tour . '<br>
			' . $tourInOut . '<br>
			' . $reference[$tourLang] . ': ' . $orderID . '
			</div>
		</td>
		</tr>
		</table></div>';


		$orderConfirmEmail = get_field("order_confirmation_group", "options");

		if ($tourLang === 'de') {
			$subjectOut = $orderConfirmEmail['email_subject_de'];
			$emailHTML = $orderConfirmEmail['german'];
		} else if ($tourLang === 'fr') {
			$subjectOut = $orderConfirmEmail['email_subject_fr'];
			$emailHTML = $orderConfirmEmail['french'];
		} else {
			$subjectOut = $orderConfirmEmail['email_subject'];
			$emailHTML = $orderConfirmEmail['english'];
		}

		$emailHTML = str_replace("{*NAME*}", $assocName, $emailHTML);
		$emailHTML = str_replace("{*TOURDATE*}", $orderDate, $emailHTML);
		$emailHTML = str_replace("{*TOURTIME*}", $time_tour, $emailHTML);
		$emailHTML = str_replace("{*ORDERSUMMARY*}", $orderConfirmation, $emailHTML);
		$emailHTML = str_replace("eschaton.test", 'eschaton-foundation.com', $emailHTML);


		$to = $assocEmail;
		$subject = $subjectOut;
		$body = $emailHTML;
		$headers = array('Content-Type: text/html; charset=UTF-8');

		if ($allowEmail) {
			wp_mail($to, $subject, $body, $headers);
			// echo $body;
		} else {
			echo $body;
		}
	}
}


function outputTourDate($inDate,$outPart = 0){
	$calcDate = strtotime($inDate);
	$outArray = array();
	$outArray[] = date('l', $calcDate);
	$outArray[] = date('F', $calcDate);
	$outArray[] = date('j', $calcDate);	

	return $outArray[$outPart];
}


// function my_theme_modify_stripe_fields_styles( $styles ) {
//     return array(
//         'base' => array(
//             'iconColor'     => '#666EE8',
//             'color'         => 'red',
//             'fontSize'      => '15px',
//             '::placeholder' => array(
//                 'color' => 'red',
//             ),
//         ),
//     );
// }

// add_filter( 'gform_stripe_elements_styling', 'my_theme_modify_stripe_fields_styles' );

add_filter( 'gform_stripe_elements_style', 'set_stripe_styles', 10, 2 );
function set_stripe_styles( $cardStyles, $formId){

     $cardStyles['base'] = [
          'color'          => '#000000',
          'fontSize'       => '16px',
          'fontFamily'     => 'Garamond,Times,serif',
          'fontSmoothing'  => '',
          'fontStyle'      => '',
          'fontVariant'    => '',
          'fontWeight'     => '',
          'iconColor'      => '',
          'lineHeight'     => '',
          'letterSpacing'  => '',
          'textAlign'      => '',
          'padding'        => '15px',
          'textDecoration' => '',
          'textShadow'     => '',
          'textTransform'  => '',

          ':hover' => [
               'color' => '',
          ],

          ':focus' => [
               'color' => '',
          ],

          '::placeholder' => [
               'color' => '#989791',
          ],

          '::selection' => [
               'color' => '',
          ],

          ':-webkit-autofill' => [
               'color' => '',
          ],

          ':disabled' => [
               'color' => '',
          ],
     ];

     $cardStyles['complete'] = [

     ];

     $cardStyles['empty'] = [

     ];

     $cardStyles['invalid'] = [

     ];

     return $cardStyles;

}

function custom_post_order($query){
    /* 
        Set post types.
        _builtin => true returns WordPress default post types. 
        _builtin => false returns custom registered post types. 
    */
    $post_types = get_post_types(array('_builtin' => true), 'names');
    /* The current post type. */
    $post_type = $query->get('post_type');
    /* Check post types. */
	if($post_type == 'tours'){

		$query->set('meta_key', 'date_tour');
		$query->set('orderby', 'meta_value');

		/* Post Order: ASC / DESC */
        if($query->get('order') == ''){
            $query->set('order', 'ASC');
        }
    }
}
if(is_admin()){
    add_action('pre_get_posts', 'custom_post_order');
}

add_filter( 'manage_tours_posts_columns', 'set_custom_edit_tours_columns' );
function set_custom_edit_tours_columns($columns) {
    unset( $columns['author'] );
    $columns['datetour'] = __( 'Tour Date', 'your_text_domain' );
    $columns['language'] = __( 'Language', 'your_text_domain' );
    $columns['inventory'] = __( 'Inventory', 'your_text_domain' );

    return $columns;
}

// Add the data to the custom columns for the tours post type:
add_action( 'manage_tours_posts_custom_column' , 'custom_tours_column', 10, 2 );
function custom_tours_column( $column, $post_id ) {
    switch ( $column ) {
        case 'datetour' :
			if(get_field("date_tour",$post_id)){
	            echo date('F j, Y',strtotime(get_field("date_tour",$post_id)));
			}
            break;
        case 'language' :
            echo get_field("language",$post_id);
            break;
        case 'inventory' :
			if(get_field("soldout_tf",$post_id)){
				echo 'Sold out';
			} else {
				echo get_field("inventory",$post_id);
			}
            break;
    }
}







/*
 * Ajax Functions
 */

 add_action("wp_ajax_loadposts", "loadposts");
 add_action("wp_ajax_nopriv_loadposts", "loadposts");

 function loadposts() {

	 $term = $_POST['term'];
	 $termID = $_POST['termID'];

	 $args = array( 
		 'post_type' => 'bibliography',
		 'posts_per_page' => -1,
		 'meta_key' => 'media_date',
		 'orderby' => 'meta_value',
		 'order' => 'DESC',
	);

	 if( $term != "all" ) {
		 $args['tax_query'] = array(
			 array(
				 'taxonomy' => 'media_type',
				 'field'    => 'term_id',
				 'terms'    => array( $termID ),
			 )
		 );
	 }

	 ob_start();
	 
	 set_query_var('loop_args', $args);
	 get_template_part('components/loops/loop', 'bibliography');

	 $response = ob_get_clean();
	 die(json_encode($response));
 }