<?php
/* 
Template Name: Exhibitions OLD
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<article class="section-exhibition-intro content-intro">
		<h2 class="tac"><?php the_title(); ?></h2>
		<section class="wyg">
			<?php the_content(); ?>
		</section>

		<section class="exhibitions-grid">
			
			<?php
				$args = array(
					'post_type' => 'exhibitions',
					'post_status' => 'publish',
					'posts_per_page' => -1,
					'meta_key' => 'date_start',
					'orderby' => 'meta_value',
					'order' => 'DESC',
				);
				
				query_posts($args);
				if (have_posts()) :
					$currTerms = null;
					while (have_posts()) : the_post();
						$pID = $post->ID;
						$pTitle = get_the_title($pID);
						$terms = wp_get_post_terms($pID, 'exhyear', array());
						$termsOut = '';
						$termClass = '';
						
						if (!empty($terms)) {
							foreach ($terms as $termKey => $term) {
								if ($termKey > 0) {
									$termsOut .= ', ';
								}
								$termsOut .= $term->name;
								$termClass = $term->slug;
							}
						}

													// $exhArr[] = array(
													// 	'terms' => $termsOut,
													// 	'datestart' => get_field("date_start"),
													// 	'link' => get_permalink(),
													// 	'title' => get_the_title(),
													// 	'venue' => get_field("venue"),
													// 	'loc' => get_field("location"),
													// );

						if ($termsOut !== $currTerms) {
							echo '<div class="exhib-newyear"><span>' . $termsOut . '</span></div>';
							$currTerms = $termsOut;
						}
											
						get_template_part('components/blocs/bloc', 'exhibition');

				endwhile;
			endif; 
			wp_reset_query();

			?>
		</section>
	</article>

<?php endwhile;
get_footer(); ?>