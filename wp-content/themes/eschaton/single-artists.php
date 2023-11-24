<?php get_header();
if ( have_posts() ) while ( have_posts() ) : the_post(); ?>

	<?php if(get_field("additional_headshots_tf")){ ?>
		<section class="section-artists-headshots content-intro bleed">
			<div class="headshots">
				<figure class="headshot-single"><?php echo wp_get_attachment_image ( get_field("headshot"), 'thumbnail', false ); ?></figure>
				<?php foreach(get_field("additional_headshots") as $headshotSingle){ ?>
					<figure class="headshot-single"><?php echo wp_get_attachment_image ( $headshotSingle['ID'], 'thumbnail', false ); ?></figure>
				<?php } ?>
			</div>
		</section>
	<?php } else { ?>
		<section class="section-artists-headshot content-intro">
			<?php if(get_field("headshot")){
				echo '<div class="img-wrap artist-headshot">';
				echo wp_get_attachment_image ( get_field("headshot"), 'thumbnail', false );
				echo '</div>';
			} else if(has_post_thumbnail()){
				echo '<div class="img-wrap">';
				echo wp_get_attachment_image ( get_post_thumbnail_id(), 'thumbnail', false );
				echo '</div>';
			} ?>
		</section>
	<?php } ?>
	<section class="section-artists-intro content-intro">
		<h2 class="tac"><?php the_title(); ?></h2>
		<article class="wyg">
			<div class="quote artist-quote"><?php the_field("quote"); ?></div>
			<?php the_field("intro"); ?>
		</article>
	</section>

	<?= FLEX()->flexRows($post->ID); ?>
<?php endwhile;
get_footer(); ?>