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

            <section class="section-text wyg">
                <?php if ( is_page() ) :

					if( $post->post_parent ) :
						$children = get_pages( 'title_li=&child_of='.$post->post_parent.'&echo=0' );
					else:
						$children = get_pages( 'title_li=&child_of='.$post->ID.'&echo=0' );
					endif;

					if ($children) : ?>
					<ul class="faq-nav">
					<?php foreach ( $children as $child ) : ?>

						<li class="faq-nav-item">
							<a href="<?php echo get_page_link( $child->ID ); ?>" class="<?php if (is_page($child->post_title)) echo 'active'; ?>">
								<?php echo $child->post_title; ?>
								<svg viewBox="0 0 20 20" width="1em" height="1em" focusable="false" aria-hidden="true" class="SvgRWrapper"><path d="M13 9.63 8.62 5.25l-.87.88 3.5 3.5-3.5 3.5.88.87z"></path></svg>
							</a>
						</li>
					
					<?php endforeach; endif; ?>
					</ul>

				<?php endif; ?>


            </section>


        <?php echo FLEX()->flexRows($post->ID); ?>

    </div>

<?php endwhile;
get_footer(); ?>