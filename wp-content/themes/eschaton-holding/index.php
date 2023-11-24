<?php get_header(); ?>
<h1>
	<span><?php the_field("headline_en","options"); ?></span>
	<span><?php the_field("headline_fr","options"); ?></span>
	<span><?php the_field("headline_de","options"); ?></span>
</h1>
<figure>
	<?php $slides = get_field("img_car","options");
	if(!empty($slides)){
		foreach($slides as $slide){ ?>
			<div class="slide">
				<?php echo wp_get_attachment_image($slide['img_dt'],'large', "", ["class" => "orient-hor"]); ?>
				<?php echo wp_get_attachment_image($slide['img_mo'],'large', "", ["class" => "orient-vert"]); ?>
			</div>
		<?php }
	} ?>
</figure>
<section class="block">
	<?php the_field("hold_en","options"); ?>
</section>
<section class="block">
	<?php the_field("hold_fr","options"); ?>
</section>
<section class="block">
	<?php the_field("hold_de","options"); ?>
</section>
<?php get_footer(); ?>