/*
add_filter('gform_validation_3', 'eschaton_booking_validator');
function eschaton_booking_validator($validation_result)
{
$form = $validation_result['form'];

// print_r($form);


// field_3_9 Tour ID
// field_3_10 Tour Date
//
// field_3_12 Number of Tickets (Adult)
// field_3_13 Number of Tickets (Reduced)
// field_3_14 Number of Tickets (Free)


echo 'attempt to validate: <br>';
echo rgpost('input_9') . '<br>' . rgpost('input_10') . '<br>' . rgpost('input_quantity_12') . '<br>' . rgpost('input_quantity_13') . '<br>' . rgpost('input_quantity_14');

if (rgpost('input_9') == '' || rgpost('input_10') == '' || rgpost('input_12') == '' || rgpost('input_13') == '' || rgpost('input_14') == '') {
$validation_result['is_valid'] = false;
// print_r($form);
foreach ($form['fields'] as &$field) {
if ($field->id == 9) {
$field->failed_validation = true;
$field->validation_message = 'Please select a Tour date';
break;
} else if ($field->id == 10) {
$field->failed_validation = true;
$field->validation_message = 'Please select a Tour date';
break;
} else if ($field->id == 12) {
$field->failed_validation = true;
$field->validation_message = 'Number of Adult tickets is required';
break;
} else if ($field->id == 13) {
$field->failed_validation = true;
$field->validation_message = 'Number of Reduced-price tickets is required';
break;
} else if ($field->id == 14) {
$field->failed_validation = true;
$field->validation_message = 'Number of free tickets is required';
break;
}
}
}
// if (rgpost('input_6') == '') {
// $validation_result['is_valid'] = false;
// foreach ($form['fields'] as &$field) {
// if ($field->id == 6) {
// $field->failed_validation = true;
// $field->validation_message = 'Please select a Tour date';
// break;
// }
// }
// }

//Assign modified $form object back to the validation result
$validation_result['form'] = $form;
return $validation_result;
}


*/


add_filter('gform_pre_render_6', 'populate_posts');
add_filter('gform_pre_validation_6', 'populate_posts');
add_filter('gform_pre_submission_filter_6', 'populate_posts');
add_filter('gform_admin_pre_render_6', 'populate_posts');
function populate_posts($form)
{
global $tourChoices;
foreach ($form['fields'] as &$field) {
if ($field->type === 'select' && $field->id == 2) {
// print_r($field);
$field->placeholder = 'Tour Date';
$field->choices = $tourChoices;
// $field->isSelected = 249;
}
}
return $form;
}

add_filter('gform_validation_6', 'eschaton_booking_validator');
function eschaton_booking_validator($validation_result)
{
$form = $validation_result['form'];

// print_r($form);

/*
field_3_9 Tour ID
field_3_10 Tour Date

field_3_12 Number of Tickets (Adult)
field_3_13 Number of Tickets (Reduced)
field_3_14 Number of Tickets (Free)
*/
$tourSelectedDate = rgpost('input_2');


// if (rgpost('input_2') == '') {
// $validation_result['is_valid'] = false;
// print_r($form);
foreach ($form['fields'] as &$field) {
if ($field->id == 2) {
$field->failed_validation = true;

$numTicketsNeeded = 0;
$availableInventory = get_field("inventory", $tourSelectedDate);
if (!$availableInventory) {
$availableInventory = get_field("group_limit_default", "options");
}

$tixAdult = getQtyfromVal(rgpost('input_3'));
$tixReduced = getQtyfromVal(rgpost('input_4'));
$tixChild = getQtyfromVal(rgpost('input_12'));
$tixResident = getQtyfromVal(rgpost('input_13'));

$numTicketsNeeded = $numTicketsNeeded + $tixAdult + $tixReduced + $tixChild + $tixResident;

if ($numTicketsNeeded > $availableInventory) {
$validation_result['is_valid'] = false;
$field->validation_message = 'I need ' . $tixAdult . ' adult and ' . $tixReduced . ' reduced, and ' . $tixChild . ' child, and ' . $tixResident . ' residents, totaling ' . $numTicketsNeeded . ', ' . $availableInventory . ' available';
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
if ($field->id == 2 && $field->failed_validation) {
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

add_action('gform_after_submission_6', 'tour_after', 10, 2);
function tour_after($entry, $form)
{
$allowEmail = true;
$updateInventory = true;


$post = get_post($entry['post_id']);
$assocPost = rgar($entry, '2');

$assocEmail = rgar($entry, '7');
$assocName = rgar($entry, '6');
$orderDate = get_the_title($assocPost);
$orderID = $entry['id'];

if ($assocPost) {
$numTicketsNeeded = 0;
$availableInventory = get_field("inventory", $assocPost);
if (!$availableInventory) {
$availableInventory = get_field("group_limit_default", "options");
}

$tixAdult = getQtyfromVal(rgar($entry, '3'));
$tixReduced = getQtyfromVal(rgar($entry, '4'));
$tixChild = getQtyfromVal(rgar($entry, '12'));
$tixResident = getQtyfromVal(rgar($entry, '13'));

$tourLang = get_field("language", $assocPost);

$numTicketsNeeded = $numTicketsNeeded + $tixAdult + $tixReduced + $tixChild + $tixResident;

// print_r($entry);

if ($updateInventory) {
if ($numTicketsNeeded <= $availableInventory) { $newInventory=$availableInventory - $numTicketsNeeded; update_field("inventory", $newInventory, $assocPost); } else { // flag - emergency email dan because somehow bought with no inventory $to='dan@chips.nyc' ; $subject='Critical - oversold tour' ; $body=print_r($entry, true); $headers=array('Content-Type: text/html; charset=UTF-8'); wp_mail($to, $subject, $body, $headers); } $existing=get_field('tour_comms', $assocPost); if (!$existing) $existing=[]; $existing[]=array('email_address'=> $assocEmail);
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
	if ($tixResident && $tixResident > 0) {
	$tixLabel = get_field("ticket_types_4", "options");
	if ($tourLang === 'de') {
	$labelOut = $tixLabel['german'];
	} else if ($tourLang === 'fr') {
	$labelOut = $tixLabel['french'];
	} else {
	$labelOut = $tixLabel['english'];
	}

	$tixOut .= $tixResident . ' ' . $labelOut . '<br>';
	}
	$numTix = $tixAdult + $tixReduced + $tixChild + $tixResident;


	$orderConfirmation = '
	<table width="100%">
		<tr>
			<td style="font-family: \'Adobe Garamond Pro\', Constantia, \'Times New Roman\', Times, serif;font-size:22px;">
				' . $assocName . '<br>
				' . $tixOut . '<br>
				' . $orderDate . '<br>
				Order No: ' . $orderID . '
			</td>
		</tr>
	</table>';


	$orderConfirmEmail = get_field("order_confirmation", "options");

	$emailHTML = $orderConfirmEmail['english'];
	$emailHTML = str_replace("{*NAME*}", $assocName, $emailHTML);
	$emailHTML = str_replace("{*TOURDATE*}", $orderDate, $emailHTML);
	$emailHTML = str_replace("{*ORDERSUMMARY*}", $orderConfirmation, $emailHTML);
	$emailHTML = str_replace("(number of tickets)", $numTix, $emailHTML);



	// (date of purchase)
	// Name - Number of Tickets - Date and Time of Tour - Reference Number
	if ($tourLang === 'de') {
	$subjectOut = $orderConfirmEmail['email_subject_de'];
	} else if ($tourLang === 'fr') {
	$subjectOut = $orderConfirmEmail['email_subject_fr'];
	} else {
	$subjectOut = $orderConfirmEmail['email_subject'];
	}

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
			}

			// print_r($entry);

			//changing post content
			// $post->post_content = 'Blender Version:' . rgar($entry, '7') . "<br /> <img src='" . rgar($entry, ' 8') . "'> <br/> <br/> " . rgar($entry, '13' ) . " <br/> <img src='" . rgar($entry, '5' ) . "'>" ; // wp_update_post($post); }