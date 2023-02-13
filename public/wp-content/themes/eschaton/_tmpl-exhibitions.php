<?php
/* 
Template Name: Exhibitions 
*/
get_header();
if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-exhibition-intro content-intro">

		<h2 class="tac"><?php the_title(); ?></h2>
		
		<div class="wyg">
			<?php the_content(); ?>
		</div>

		<div class="page_filters">
            <?php FILTERS('All dates', 'exhyear')->displayOutput(); ?>
            <?php FILTERS('All types', 'exhpermanent')->displayOutput(); ?>
            <?php FILTERS('All continent', 'exhcontinent', true )->displayOutput(); ?>
		</div>
		
		<div id="grid" data-posttype="exhibitions">
			<?php
			get_template_part('components/loops/loop', 'exhibitions'); ?>
		</div>

	</section>

<?php endwhile;

echo "<script type='text/javascript'>const ajaxurl = '".admin_url('admin-ajax.php')."'</script>"; 

get_footer(); ?>

