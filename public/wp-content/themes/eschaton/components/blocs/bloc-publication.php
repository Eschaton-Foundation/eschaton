<div class="publication-single">

	<h3 class="item_title wyg">
		<em><?php the_title(); ?></em>, <?php the_field('publication_author'); ?>
	</h3>

	<?php 
		$term_obj_list = get_the_terms( $post->ID, 'publication_date' );
		$terms_string = join(', ', wp_list_pluck($term_obj_list, 'name')); 
		echo $terms_string;
	?>
	
	<span class="separator"></span> 
	
	<?php 
		$term_obj_list = get_the_terms( $post->ID, 'media_type' );
		$terms_string = join(', ', wp_list_pluck($term_obj_list, 'name')); 
		echo $terms_string;
	?> 
	
	<span class="separator"></span> 
	
	<?php 
		$term_obj_list = get_the_terms( $post->ID, 'publication_language' );
		$terms_string = join(', ', wp_list_pluck($term_obj_list, 'name')); 
		echo $terms_string;
	?>
	
	<?php if( get_field('publication_isbn') !== '' && get_field('publication_isbn') ) : ?>
		<div class="publication_isbn">isbn : <?php the_field('publication_isbn'); ?></div>
	<?php endif; ?>
</div>