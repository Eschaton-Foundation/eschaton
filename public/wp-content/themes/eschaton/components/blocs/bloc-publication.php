<div class="publication-single">

	<h3 class="item_title wyg">
		<?php the_title(); ?>
	</h3>

	<?php the_field('publication_date'); ?> | 
	<?php 
		$term_obj_list = get_the_terms( $post->ID, 'media_type' );
		$terms_string = join(', ', wp_list_pluck($term_obj_list, 'name')); 
		echo $terms_string;
	?> | 
	<?php 
		$term_obj_list = get_the_terms( $post->ID, 'language' );
		$terms_string = join(', ', wp_list_pluck($term_obj_list, 'name')); 
		echo $terms_string;
	?>

</div>