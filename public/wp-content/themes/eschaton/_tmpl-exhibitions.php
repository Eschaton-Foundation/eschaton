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

		<div>
            <?php FILTERS('all dates', 'exhyear')->displayOutput(); ?>
            <?php FILTERS('all continent', 'exhcontinent')->displayOutput(); ?>
		</div>
		
		<div id="grid" data-posttype="exhibitions">
			<h2>Present</h2>
			<?php
			get_template_part('components/loops/loop', 'exhibitions', array('period' => 'present')); ?>

			<h2>Forthcoming</h2>
			<?php 
			get_template_part('components/loops/loop', 'exhibitions', array('period' => 'forthcoming')); ?>

			<h2>Passed</h2>
			<?php 
			get_template_part('components/loops/loop', 'exhibitions', array('period' => 'passed')); ?>
		</div>

	</section>

<?php endwhile;

echo "<script type='text/javascript'>const ajaxurl = '".admin_url('admin-ajax.php')."'</script>"; 

get_footer(); ?>

