<?php 
die(); // no longer use this one
include('admin-header.php'); ?>

<style>
	body {
		padding: 50px;
	}

	a {
		font-size: 12px;
		color: #999;
	}
</style>
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
	echo '<table border="1" cellpadding="3" style="margin:auto;">';
	while (have_posts()) : the_post();
		$pID = $post->ID;
		$tourComms = get_field("tour_comms");
		if ($tourComms) {
			$hasChanged = false;
			echo '<h2>' . get_the_title($pID) . '</h2>';

			if (!empty($tourComms)) {
				foreach ($tourComms as $tourKey => $tourComm) {
					echo '<tr>
						<td>' . $tourComm['email_address'] . '</td>';

					echo '<td>';
					if (isset($tourComm['email_1_tf']) && $tourComm['email_1_tf']) {
						echo 'Received email #1';
					} else {
						echo 'Needs email #1. Sending now...';
						// Trigger email function

						$tourComms[$tourKey]['email_1_tf'] = true;
						$hasChanged = true;
					}
					echo '</td>';
					echo '<td>';
					if (isset($tourComm['email_2_tf']) && $tourComm['email_2_tf']) {
						echo 'Received email #2';
					} else {
						echo 'Needs email #2. Not sending now.';
					}
					echo '</td>';
					echo '<td>';
					if (isset($tourComm['email_3_tf']) && $tourComm['email_3_tf']) {
						echo 'Received email #3';
					} else {
						echo 'Needs email #3. Not sending now.';
					}
					echo '</td>';

					echo '</tr>';
				}
			}

			if ($hasChanged) {
				// echo 'Updating post ' . $pID . ' with new data';
				// print_r($tourComms);
				update_field("tour_comms", $tourComms, $pID);
			}
			// print_r(get_field("tour_comms", $pID));
		}

	endwhile;
	echo '</table>';
}
wp_reset_query();
?>
<?php include('admin-footer.php'); ?>