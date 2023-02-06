
<div class="studio-single">

<?php if (has_post_thumbnail()) {
    echo '<div class="img-wrap">';
        echo wp_get_attachment_image(get_post_thumbnail_id($pID), 'thumbnail', false);
    echo '</div>';
} ?>


	<div class="txt-wrap wyg"><?php the_title(); ?></div>

    <?php the_content(); ?>

</div>