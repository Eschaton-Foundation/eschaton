<?php
/* 
Template name: Tour Booking Confirmation
*/
get_header();
if (have_posts()) while (have_posts()) : the_post();
	if (get_field("title_tf")) { ?>
		<section class="section-manualtitle">
			<h2 class="tac">
				<?php the_field("title"); ?>
			</h2>
		</section>
	<?php }

	if (isset($_GET['tour'])) {
		$tourID = sanitize_text_field($_GET['tour']);
		$tixAdult = isset($_GET['num1']) ? sanitize_text_field($_GET['num1']) : null;
		$tixReduced = isset($_GET['num2']) ? sanitize_text_field($_GET['num2']) : null;
		$dateTour = get_field("date_tour", $tourID);
		$timeTour = get_field("time_tour", $tourID);
		$langTour = get_field("language", $tourID);
	?>
		<section class="section-text booking-summary">
			<?php echo date('F j, Y', strtotime($dateTour)); ?><br />
			<?php
			if ($tixAdult) {
				echo 'Adult tickets: ' . $tixAdult . '<br />';
			}
			if ($tixReduced) {
				echo 'Reduced tickets: ' . $tixReduced . '<br />';
			}
			?>
		</section>
	<?php
	}

	if (strlen(get_the_content()) > 10) { ?>
		<section class="section-text wyg">
			<?php the_content(); ?>
		</section>
<?php }
	echo FLEX()->flexRows($post->ID);
endwhile;
get_footer(); ?>