<?php 
/* 
Template name: FAQ
*/
get_header();
if (have_posts()) while (have_posts()) : the_post();
	if (get_field("display_title")) {
		echo '
<section class="section-displaytitle">
	<h2 class="display_title">' . get_field("display_title") . '</h2>';
		if (get_field("display_subtitle")) {
			echo '<span class="blok subtitle">' . get_field("display_subtitle") . '</span>';
		}
		echo '</section>
		';
	} else {
		if (get_field("pagetitle_tf")) { ?>
			<section class="section-manualtitle">
				<h2 class="tac">
					<?php the_title(); ?>
				</h2>
			</section>
		<?php } else if (get_field("title_tf")) { ?>
			<section class="section-manualtitle">
				<h2 class="tac">
					<?php the_field("title"); ?>
				</h2>
			</section>
		<?php }
	} ?>


    <div class="grid-faq">

        <?php if (strlen(get_the_content()) > 10) : ?>
            <section class="section-text wyg">
                <?php 
                $lang = get_locale();
                if(get_field("translate_tf") && strpos($lang,'fr_') !== false){
                    echo get_field("text_french");
                } else if(get_field("translate_tf") && strpos($lang,'de_') !== false){
                    echo get_field("text_german");
                } else {
                    the_content();
                }
                ?>
            </section>
        <?php endif; ?>


        <?php echo FLEX()->flexRows($post->ID); ?>

    </div>

<?php endwhile;
get_footer(); ?>