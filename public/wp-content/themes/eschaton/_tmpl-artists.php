<?php
/* 
Template Name: Artists 
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-artists-intro content-intro">
		<?php if (get_field("pagetitle_tf")) { ?><h2 class="tac"><?php the_title(); ?></h2><?php } ?>
		<article class="wyg">
			<?php the_content(); ?>
		</article>
		<article class="artists-grid"><?php
										$args = array(
											'post_type' => 'artists',
											'post_status' => 'publish',
											'posts_per_page' => -1,
											'orderby' => 'menu_order',
											'order' => 'ASC',
										);
										query_posts($args);
										if (have_posts()) {
											while (have_posts()) : the_post();
												$pID = $post->ID;
										?>
					<div class="artist-single">
						<?php if (has_post_thumbnail() && "add_images_later" === true) {
													echo '<div class="img-wrap">';
													echo '<a href="' . get_permalink() . '">';
													echo wp_get_attachment_image(get_post_thumbnail_id($pID), 'thumbnail', false);
													echo '</a>';
													echo '</div>';
												} ?>
						<div class="txt-wrap wyg">
							<h2><a href="<?php echo get_permalink(); ?>" class="link-arrow"><?php the_title(); ?></a></h2>
							<?php echo apply_filters("the_content", get_the_excerpt()); ?>
						</div>
						<?php if (get_field("link_text")) {
													echo '<a href="' . get_field("link") . '" target="_blank">' . get_field("link_text") . '</a>';
												} ?>
					</div>

			<?php
											endwhile;
										}
										wp_reset_query();

			?>
		</article>
	</section>

<?php endwhile;
get_footer(); ?>