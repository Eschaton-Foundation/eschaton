<?php 
include('admin-header.php'); 

// if(!is_user_logged_in()){
// 	die();
// }

$allowEmail = false;
$serverVerify = false;
$siteURL = get_bloginfo( 'url' );
if($siteURL === 'https://eschaton-foundation.com'){
	$serverVerify = true;
}
// echo $siteURL;
if(isset($_GET['allowemail']) && $_GET['allowemail'] === 'proceed'){
	$allowEmail = true;
}

$args = array(
	'post_type' => 'tours',
	'post_status' => 'publish',
	'posts_per_page' => -1,
	'meta_key' => 'date_tour',
	'orderby' => 'meta_value',
	'order' => 'ASC',
);
query_posts($args);
if (have_posts()) {
	$currMonth = null;
	echo '<table border="1" cellpadding="3" style="margin:auto;">';
	while (have_posts()) : the_post();
		$pID = get_the_id();
		// echo '<br>'.$pID;
		$nowCheck = strtotime('now');
		$emailCutoff = strtotime('now + 7 days');
		// $nowCheck = strtotime('june 19, 2022');
		// $nowCheck = strtotime('June 2, 2022');
		// $emailCutoff = strtotime('June 4, 2022');
		// $nowCheck = strtotime('june 19, 2022');
		$tourstartexact = strtotime(get_field("date_tour", $pID));
		if($tourstartexact >= $nowCheck && $tourstartexact < $emailCutoff){
			$tourComms = get_field("tour_comms");
			if ($tourComms) {
				$hasChanged = false;
				// echo '<h2>' . get_the_title($pID) . '</h2>';
				$bookingSummary = '';
				$debugEntries = '';

				if (!empty($tourComms)) {
					foreach ($tourComms as $tourKey => $tourComm) {
						if (!isset($tourComm['email_1_tf']) || !$tourComm['email_1_tf']) {
							$form_id = 7;
							if(get_field("group_tf",$pID)){
								$form_id = 8;
							}
							$tourID = $tourComm;
							$assocEmail = $tourID['email_address'];
							$search_criteria = array(
								'status'        => 'active',
								'field_filters' => array(
									'mode' => 'all',
									array(
										'key'   => '21',
										'value' => $pID
									),
									array(
										'key'   => '7',
										'value' => $tourID['email_address']
									),
								)
							);
							$entryName = null;
							$assocName = '';
							$entries = GFAPI::get_entries($form_id, $search_criteria);
							if (!empty($entries)) {
								$debugEntries .= 'Have entries, looping...
								';
								foreach ($entries as $entry) {
									// echo "Comparing to " . $tourID['email_address'];
									// print_r($tourID);
									// print_r($entry);

									$debugEntries .= '


									' . print_r($entry, true);
									
									$bookingSummary = '';
									if (isset($entry[6]) && $entry[6] !== '') {
										$entryName = $entry[6];
									}
									
									if (isset($entry[24]) && $entry[24] !== '') {
										$emailLang = $entry[24];
									}
									$orderDate = $entry['date_created'];

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
		
									if ($assocPost) {
										// echo 'Has associated post ' . $assocPost;
										$numTicketsNeeded = 0;
										$availableInventory = get_field("inventory", $assocPost);
										if (!$availableInventory) {
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

										if($orderDate){
											$bookingSummary .= $dateBooked[$tourLang] . ': ' . $orderDate . '<br>';
										}
										if($assocName){
											$bookingSummary .= $name[$tourLang] . ': ' . $assocName . '<br>';
										}
										if($numTicketsNeeded){
											$bookingSummary .= $tix_num[$tourLang] . ': ' . $numTicketsNeeded . '<br>';
										}
										if($tourDate){
											$bookingSummary .= $date_tour[$tourLang] . ': ' . $tourDate . '<br>';
										}
										if($time_tour){
											$bookingSummary .= $time_tour . '<br>';
										}
										// if($tourInOut){
										// 	$bookingSummary .= $tourInOut . '<br>';
										// }
										if($orderID){
											$bookingSummary .= $reference[$tourLang] . ': ' . $orderID;
										}
										if($guestOut){
											$bookingSummary .= $guestOut;
										}
									}
								}
								
								// $debugEntries
							} else {
								// No associated form submission
								// $bookingSummary = '';
								$bookingSummary = '';
								$bookingSummary .= $assocEmail . '<br>';
								if($tourDate){
									$bookingSummary .= $date_tour[$tourLang] . ': ' . $tourDate . '<br>';
								}
								if($time_tour){
									$bookingSummary .= $time_tour . '<br>';
								}

								$debugEntries .= 'No associated form entries
								
								' . print_r($search_criteria,true);
							}

							if($siteURL === 'https://eschaton.test'){
								//file_put_contents('entry-debug.txt', date('Y-m-d h:ia',$nowCheck) . '
								//' . $debugEntries);
							}


							// echo $bookingSummary;
	




							$orderConfirmation = '';
							if($bookingSummary && $bookingSummary !== ''){
								$orderConfirmation = '
									<div style="padding:16px;background:#ebebe8;border-radius:4px;">
									<table width="100%">
									<tr>
									<td style="font-family: \'Adobe Garamond Pro\', Constantia, \'Times New Roman\', Times, serif;font-size:22px;">
										<div class="booking-summary-wrap">
											' . $bookingSummary . '
										</div>
									</td>
									</tr>
									</table></div>';
							}

							$email_PreVisit = get_field("visit_email_text", "options");

							if ($tourLang === 'de') {
								$subjectOut = $email_PreVisit['email_subject_de'];
								$emailHTML = $email_PreVisit['german'];
							} else if ($tourLang === 'fr') {
								$subjectOut = $email_PreVisit['email_subject_fr'];
								$emailHTML = $email_PreVisit['french'];
							} else {
								$subjectOut = $email_PreVisit['email_subject'];
								$emailHTML = $email_PreVisit['english'];
							}
							if(!$assocName || $assocName === ''){
								$assocName = $assocEmail;
							}

							$emailHTML = str_replace("{*NAME*}", $assocName, $emailHTML);
							$emailHTML = str_replace("{*TOURDATE*}", $orderDate, $emailHTML);
							$emailHTML = str_replace("{*TOURTIME*}", $time_tour, $emailHTML);
							$emailHTML = str_replace("{*ORDERSUMMARY*}", $orderConfirmation, $emailHTML);
							$emailHTML = str_replace("(number of tickets)", $numTicketsNeeded, $emailHTML);
							$emailHTML = str_replace("eschaton.test", 'eschaton-foundation.com', $emailHTML);


							$to = $assocEmail;
							// $to = 'dan@chips.nyc';
							$subject = $subjectOut;
							$body = $emailHTML;
							$headers = array('Content-Type: text/html; charset=UTF-8');
							
							if ($allowEmail && $serverVerify) {
								$tourComms[$tourKey]['email_1_tf'] = true;
								$hasChanged = true;
								wp_mail($to, $subject, $body, $headers);
								// echo $body;
							} else {
								if($siteURL === 'https://eschaton.test'){
									// file_put_contents('eschaton.txt', date('Y-m-d h:ia',$nowCheck) . '
									// ' . $emailHTML);
								}
								// if($tourKey < 2){
									// wp_mail('office@eschaton-foundation.com', $subject, $body, $headers);
									// $tourComms[$tourKey]['email_1_tf'] = true;
									// $hasChanged = true;
								// }
								echo $emailHTML;
							}
							


							// $serverVerify

							// echo '
							// <tr>
							// 	<td>' . get_the_title($pID) . '</td>
							// 	<td>' . $tourComm['email_address'] . '</td>';

							// echo '<td>';
							
							

								
							
							echo '</td>';
							echo '</tr>';
							// $tourComms[$tourKey]['email_1_tf'] = false;
							// $hasChanged = true;
						}
					}
				}

				if ($hasChanged) {
					// echo 'Updating post ' . $pID . ' with new data';
					// print_r($tourComms);
					update_field("tour_comms", $tourComms, $pID);
				}
				// print_r(get_field("tour_comms", $pID));
			}
		}

	endwhile;
	echo '</table>';
}
wp_reset_query();
?>
<?php include('admin-footer.php'); ?>