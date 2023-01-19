<?php
/* 
Template name: Visit (with booking)
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-manualtitle">
		<?php if (get_field("title_tf")) { ?>
			<h2 class="tac">
				<?php
				if (get_field("title")) {
					echo get_field("title");
				} else {
					echo get_the_title();
				}
				?>
			</h2>
		<?php } ?>
	</section>
	<section class="section-text wyg">
		<?php the_content(); ?>
	</section>

	<?php echo FLEX()->flexRows($post->ID); ?>


	<section class="section-date-grid">
		<a name="tour-dates" class="anchorlink">Tour Dates</a>
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

					$nowCheck = strtotime('now');
					// $nowCheck = strtotime('june 19, 2022');
					$tourstartexact = strtotime(get_field("date_tour"));
					if($tourstartexact >= $nowCheck){
						if(!get_field("group_tf") && !get_field("hidden_tf")){

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

					?>
							<article data-available="<?php echo get_field("inventory"); ?>" class=" tour-single hovParent<?php if ($tourSoldOut) {
																																echo ' sold-out';
																															} ?>" data-tourdate="<?php echo $dateTour; ?>" data-tourweekday="<?php echo date('l', strtotime($dateTour)); ?>">
								<span class="tour-date-day"><?php echo date('j', strtotime($dateTour)); ?></span>
								<span class="tour-date-weekday"><?php echo date('l', strtotime($dateTour)); ?></span>
								<span class="tour-language"><?php echo strtoupper($langTour); ?></span>
								<?php if ($tourSoldOut) {
									echo '<span class="tour-soldout">Sold Out</span>';
								} else { ?>
									<span class="tour-soldout hov-only"><?php
																		if ($tourSoldOut) {
																			echo '0';
																		} else if (get_field("inventory")) {
																			echo get_field("inventory");
																		} else {
																			echo get_field("group_limit_default", "options");
																		}
																		?> <?php

																		$textRemain = get_field("text_remain_grid", "options");
																		$current_language = get_locale();
																		// $remainText = get_field("text_remain","options");
																		if(strpos($current_language,'fr_') !== false){
																			echo $textRemain['fr'];
																		} else if(strpos($current_language,'de_') !== false){
																			echo $textRemain['de'];
																		} else {
																			echo $textRemain['en'];
																		}
																		?></span>
									<a href="<?php echo get_permalink(); ?>" class="tour-link"><?php echo get_the_title() ?></a>
								<?php } ?>
							</article>

					<?php
						}
					}
				endwhile;
			}
			wp_reset_query();

			?>
		</div>
	</section>
<?php
endwhile;
get_footer(); ?>