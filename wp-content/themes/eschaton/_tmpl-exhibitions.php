<?php
/* 
Template Name: Exhibitions 
*/
get_header();

$step = 24;


if (have_posts()) while (have_posts()) : the_post(); ?>
	<section class="section-exhibition-intro content-intro section-w-filters">

		<h2 class="tac"><?php the_title(); ?></h2>
		
		<div class="wyg">
			<?php the_content(); ?>
		</div>

		<div class="listing_w_filters">
			<div class="page_filters">
				<?php FILTERS(pll__( 'All' ), '', 'all')->displayOutput(); ?>
				<?php FILTERS('', 'exhyear', 'medium', true)->displayOutput(); ?>
				<?php FILTERS('', 'exhpermanent')->displayOutput(); ?>
				<?php FILTERS('', 'exhcontinent', 'large', true )->displayOutput(); ?>
			</div>
			
			<div class="outer_grid">
				<div id="grid" data-posttype="exhibitions" data-step="<?php echo $step; ?>">
					<?php
					get_template_part('components/loops/outerloop', 'exhibitions', array(
						'step'  => $step
					)); ?>
				</div>

				<div class="posts_navigation">
					<button id="loadMore" class="mainBtn">Load more</button>
				</div>
			</div>


		</div>

	</section>

<?php endwhile;

echo "<script type='text/javascript'>const ajaxurl = '".admin_url('admin-ajax.php')."'</script>"; 

get_footer(); ?>

