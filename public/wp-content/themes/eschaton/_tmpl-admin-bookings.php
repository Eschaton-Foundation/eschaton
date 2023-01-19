<?php
/* 
Template name: Admin Bookings
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-date-grid">
		<?php 
		//if (current_user_can('editor') || current_user_can('administrator')) { 
			if(is_user_logged_in()){
		?>
			<?php if (isset($_GET['tourdetail'])) { ?>
			<h2>Admin view: <?php echo get_the_title($_GET['tourdetail']); ?></h2>
			<?php } ?>
			<?php
			if (isset($_GET['tourdetail'])) {
				echo '<div style="text-align:center"><a target="_blank" href="' . get_edit_post_link($_GET['tourdetail']) . '">Edit</a></div>';
				$tourID = sanitize_text_field($_GET['tourdetail']);

				if(get_field("notes",$tourID)){
					echo '
					<dl>
						<dt>Notes</dt>
						<dd>' . get_field("notes", $tourID) . '</dd>
					</dl>';
				}

				$form_id = 7;

				$search_criteria = array(
					'status'        => 'active',
					'field_filters' => array(
						'mode' => 'any',
						array(
							'key'   => '21',
							'value' => $tourID
						),
					)
				);
				$entries         = GFAPI::get_entries($form_id, $search_criteria);
				$groupOut = false;
				if(empty($entries)){
					$entries         = GFAPI::get_entries(8, $search_criteria);
					$groupOut = true;
				}

				if (!empty($entries)) {
					foreach ($entries as $entry) {
						echo '<div class="admin-booking-list">';
						if (isset($entry[6]) && $entry[6] !== '') {
							echo '<dl><dt class="label">Full name</dt> <dd class="value">' . $entry[6] . '</dd></dl>';
						}
						if (isset($entry[7]) && $entry[7] !== '') {
							echo '<dl><dt class="label">Email address</dt> <dd class="value">' . $entry[7] . '</dd></dl>';
						}
						if (isset($entry[16]) && $entry[16] !== '') {
							echo '<dl><dt class="label">Telephone</dt> <dd class="value">' . $entry[16] . '</dd></dl>';
						}
						
						if (isset($entry['3.3']) && $entry['3.3'] !== '') {
							echo '<dl><dt class="label">' . $entry['3.1'] . '</dt> <dd class="value">' . $entry['3.3'] . '</dd></dl>';
						}
						if (isset($entry['4.3']) && $entry['4.3'] !== '') {
							echo '<dl><dt class="label">' . $entry['4.1'] . '</dt> <dd class="value">' . $entry['4.3'] . '</dd></dl>';
						}

						if (isset($entry['12.3']) && $entry['12.3'] !== '') {
							echo '<dl><dt class="label">' . $entry['12.1'] . '</dt> <dd class="value">' . $entry['12.3'] . '</dd></dl>';
						}

						// if (isset($entry[5]) && $entry[5] !== '') {
						// 	echo '<dl><dt class="label">Label</dt> <dd class="value">' . $entry[5] . '</dd></dl>';
						// }
						if (isset($entry['10.1']) && $entry['10.1'] !== '') {
							echo '<dl><dt class="label">Accessibility</dt> <dd class="value">' . $entry['10.1'] . '</dd></dl>';
						}
						if (isset($entry['10.2']) && $entry['10.2'] !== '') {
							echo '<dl><dt class="label">Label</dt> <dd class="value">' . $entry['10.2'] . '</dd></dl>';
						}
						if (isset($entry['10.3']) && $entry['10.3'] !== '') {
							echo '<dl><dt class="label">Label</dt> <dd class="value">' . $entry['10.3'] . '</dd></dl>';
						}
						if (isset($entry[11]) && $entry[11] !== '') {
							echo '<dl><dt class="label">Accessibility considerations</dt> <dd class="value">' . $entry[11] . '</dd></dl>';
						}
						// if (isset($entry['payment_method']) && $entry['payment_method'] !== '') {
						// 	echo '<dl><dt class="label">Payment method</dt> <dd class="value">' . $entry['payment_method'] . '</dd></dl>';
						// }
						if (isset($entry[23]) && $entry[23] !== '') {
							echo '<dl><dt class="label">Guest names</dt><dd class="value">' . nl2br($entry[23]) . '</dd></dl>';
						}

						if($groupOut){
							if (isset($entry[18]) && $entry[18] !== '') {
								echo '<dl><dt class="label">Tour Date</dt> <dd class="value">' . $entry[18] . '</dd></dl>';
							}
							if (isset($entry[26]) && $entry[26] !== '') {
								echo '<dl><dt class="label">Institution</dt> <dd class="value">' . $entry[26] . '</dd></dl>';
							}
							if (isset($entry[25]) && $entry[25] !== '') {
								echo '<dl><dt class="label">Cost</dt> <dd class="value">' . $entry[25] . '</dd></dl>';
							}
						}

						echo '</div>';
					}
				}
				// print_r($entries);
			} else {
			?>
				<h2>Admin view: Tours</h2>
				<div class="bookings-grid">
					<?php
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
						while (have_posts()) : the_post();
							$pID = $post->ID;

							$dateTour = get_field("date_tour");
							$timeTour = get_field("time_tour");
							$langTour = get_field("language");
							$itemInventory = get_field("inventory");
							$tourSoldOut = get_field("soldout_tf");
							$hidden_tf = get_field("hidden_tf");


							if (!$tourSoldOut && $itemInventory == '0') {
								$tourSoldOut = true;
							}

							$tourMonth = date('F', strtotime($dateTour));

							if ($tourMonth !== $currMonth) {
								echo '<span class="month-separator">' . $tourMonth . '</span>';
								$currMonth = $tourMonth;
							}

							$availableInventory = get_field("inventory");
								// if ($availableInventory === 0) {
								// 	echo '0 found, num not string';
								// } else if ($availableInventory === '0') {
								// 	echo '0 found, string not num';
								// } else if ($availableInventory === null) {
								// 	$availableInventory = get_field("group_limit_default", "options");
								// 	echo 'Null found';
								// }
					?>
							<article style="pointer-events:all !important;" data-available="<?php 
								if(get_field("inventory") || get_field("inventory") === '0'){
									echo get_field("inventory");
								 } else {
									 echo "18";
								 } ?>" class="look-for-sold tour-single hovParent" data-tourdate="<?php echo $dateTour; ?>" data-tourweekday="<?php echo date('l', strtotime($dateTour)); ?>">
								<span class="tour-date-day"><?php echo date('j', strtotime($dateTour)); ?></span>
								<span class="tour-date-weekday"><?php echo date('l', strtotime($dateTour)); ?></span>
								<span class="tour-language"><?php echo strtoupper($langTour); ?></span>
								<span class="tour-soldout"><?php
															$totalBookings = get_field("tour_comms");
															if($totalBookings && !empty($totalBookings)){
																echo count($totalBookings) . ' Bookings<br>';
															}
															if ($tourSoldOut) {
																echo '0';
															} else if (get_field("inventory")) {
																echo get_field("inventory");
															} else {
																echo get_field("group_limit_default", "options");
															}
															?> Remain</span>
								<a href="?tourdetail=<?php echo get_the_id(); ?>" class="tour-link"><?php echo get_the_title() ?></a>
							</article>

					<?php
						endwhile;
					}
					wp_reset_query();

					?>
				</div>
			<?php } ?>
		<?php } else { ?>
			<h2><a href="/wp-login.php">Log in to view this page</a></h2>

		<?php } ?>
	</section>
<?php
endwhile;
get_footer(); ?>