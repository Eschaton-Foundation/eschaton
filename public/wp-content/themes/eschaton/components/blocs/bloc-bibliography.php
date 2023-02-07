<div class="bibliography-single">

	<h3 class="item_title wyg">
		<?php the_title(); ?>
	</h3>

	<?php the_field('media_date'); ?> | 
	<?php the_field('media_name'); ?> | 
	<?php 
		$term_obj_list = get_the_terms( $post->ID, 'media_type' );
		$terms_string = join(', ', wp_list_pluck($term_obj_list, 'name')); 
		echo $terms_string;
	?>

	<?php if (get_field("media_link")) : ?>
		<div class="item_action">
			<a href="<?php the_field("media_link"); ?>" target="_blank">Read more</a>
		</div>
	<?php endif; ?>

</div>