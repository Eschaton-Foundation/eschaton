<?php
/* 
Template Name: Exhibitions 
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-exhibition-intro content-intro section-w-filters">

		<h2 class="tac"><?php the_title(); ?></h2>
		
		<div class="wyg">
			<?php the_content(); ?>
		</div>

		<div class="listing_w_filters">
			<div class="page_filters">
				<?php FILTERS('All', '', 'all')->displayOutput(); ?>
				<?php FILTERS('', 'exhyear', 'medium', true)->displayOutput(); ?>
				<?php FILTERS('', 'exhpermanent')->displayOutput(); ?>
				<?php FILTERS('', 'exhcontinent', 'large', true )->displayOutput(); ?>
			</div>
			
			<div id="grid" data-posttype="exhibitions">
				<?php
				get_template_part('components/loops/loop', 'exhibitions'); ?>
			</div>
		</div>




	</section>

<?php endwhile;

echo "<script type='text/javascript'>const ajaxurl = '".admin_url('admin-ajax.php')."'</script>"; 

get_footer(); ?>

