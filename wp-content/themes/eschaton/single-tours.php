<?php get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-manualtitle">
		<h2 class="tac"><?php the_field("booking_title","options"); ?></h2>
	</section>
	<?php
	global $tourChoices;
	$pageID = get_the_id();


	$dateTour = get_field("date_tour");
	$timeTour = get_field("time_tour");
	$langTour = get_field("language");
	$itemInventory = get_field("inventory");
	$tourSoldOut = get_field("soldout_tf");
	$hidden_tf = get_field("hidden_tf");
	$isGroupTour = get_field("group_tf");


	if (!$tourSoldOut && $itemInventory == '0') {
		$tourSoldOut = true;
	}

	$tourMonth = date('F', strtotime($dateTour));
	?>

	<section class="section-tourform">
		<div class="tour-summary">
			<div class="summary-date">
			<?php if($isGroupTour){ ?>
				<span class="blok group-tour-title"><?php the_title(); ?></span>
			<?php } ?>
				<div class="tour-date-day blok"><?php 
					// setlocale(LC_ALL, get_locale());
					$lang = get_locale();
					if(strpos($lang,'fr_') !== false){
						echo '<span>'.outputTourDate($dateTour,0) .'</span>';
						echo '. ';
						echo '<span>'.outputTourDate($dateTour,1) .'</span>';
						echo ' ';
						echo '<span>'.outputTourDate($dateTour,2) .'</span>';
					} else if(strpos($lang,'de_') !== false){
						echo '<span>'.outputTourDate($dateTour,0) .'</span>';
						echo ' ';
						echo '<span>'.outputTourDate($dateTour,1) .'</span>';
						echo ' ';
						echo '<span>'.outputTourDate($dateTour,2) .'</span>';
					} else {
						echo '<span>'.outputTourDate($dateTour,0) .'</span>';
						echo ', ';
						echo '<span>'.outputTourDate($dateTour,1) .'</span>';
						echo ' ';
						echo '<span>'.outputTourDate($dateTour,2) .'</span>';
					}
				?></div>
				<span class="tour-date-time blok"><?php
													if (get_field("time_tour")) {
														echo get_field("time_tour");
													} else {
														echo get_field("tour_start_global", "options");
													}
													?></span>
				<span class="tour-language-full">
					<?php
					$textTourIn = get_field("tour_in", "options");
					$lang = get_locale();
					if($lang === 'fr_FR'){
					} else if($lang === 'de_DE'){
					} else {
					}
					if ($langTour === 'fr') {
						// echo $textTourIn['fr'];
						echo 'Tour in French';
					} else if ($langTour === 'de') {
						// echo $textTourIn['de'];
						echo 'Tour in German';
					} else {
						// echo $textTourIn['en'];
						echo 'Tour in English';
					}
					?>
				</span>
				<?php if ($tourSoldOut) {
					echo '<span class="tour-soldout">' . get_field("sold_out_text", "options") . '</span>';
				} ?>
				<?php if($isGroupTour) {
					echo '<span class="grouptour-total blok">€' . get_field("groupcost") . '</span>';
				} ?>
			</div>
			<?php if(!$isGroupTour){ ?>
				<a href="<?php echo get_permalink(195); ?>#tour-dates" class="date-change"><?php echo get_field("change_date_text", "options"); ?></a>
			<?php 
			} ?>
			<?php
			$availableInventory = get_field("inventory");
			if ($availableInventory === null && $availableInventory !== '0') {
				$availableInventory = get_field("group_limit_default", "options");
			}
			if(!$isGroupTour){
				echo '<div class="remaining-inventory">' . $availableInventory . ' <span>' . get_field("remaining_tickets_text", "options") . '</span></div>';
			} else {
				// echo '<div class="group-tour-cost">€' . get_field("groupcost") . '</div>';
			}

			?>
		</div>
		<?php 
			if(!$isGroupTour){
				if($availableInventory !== '0' && $availableInventory !== 0){
					echo do_shortcode('[gravityform id="7" title="false" description="false"]');	
				}
			} else {
				
				$groupCost = get_field("groupcost");
				echo do_shortcode('[gravityform id="8" title="false" description="false" field_values="groupcost=' . $groupCost . '"]');
			}
		?>
	</section>
<?php endwhile;
get_footer(); ?>