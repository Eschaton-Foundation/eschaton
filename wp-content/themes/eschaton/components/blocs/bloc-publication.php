<div class="publication-single">

	<h3 class="item_title">
		<?php the_title(); ?>
	</h3>

	<h4 class="item_author">
		<?php the_field('publication_author'); ?> <span class="item_content wyg"> - <?php the_content(); ?></span>

	</h4>

	<div class="item_meta">
		<?php 
			$term_obj_list = get_the_terms( $post->ID, 'publication_date' );
			$terms_string = join(', ', wp_list_pluck($term_obj_list, 'name')); 
			echo $terms_string;
		?>
		
		<span class="separator">-</span> 
		
		<?php 
			$term_obj_list = get_the_terms( $post->ID, 'media_type' );
			$terms_string = join(', ', wp_list_pluck($term_obj_list, 'name')); 
			$terms_string = str_replace("All, ", "", $terms_string);
			echo $terms_string;
		?> 
		
		<span class="separator">-</span> 
		
		<?php 
			$term_obj_list = get_the_terms( $post->ID, 'publication_language' );
			$terms_string = join(', ', wp_list_pluck($term_obj_list, 'name')); 
			echo $terms_string;
		?>
		
		<?php if( get_field('publication_isbn') !== '' && get_field('publication_isbn') ) : ?>
			<span class="separator"> - </span> 

			<span class="publication_isbn">isbn : <?php the_field('publication_isbn'); ?></span>
		<?php endif; ?>

		<span class="separator">-</span> 

		
	</div>

	

</div>