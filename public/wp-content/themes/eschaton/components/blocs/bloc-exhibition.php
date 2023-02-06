
<article class="exhibition-single">

    <?php if (has_post_thumbnail()) {
        echo '<div class="img-wrap">';
            echo wp_get_attachment_image(get_post_thumbnail_id(get_the_ID()), 'thumbnail', false);
        echo '</div>';
    } ?>

    <div class="txt-wrap wyg">
        <?php the_content(); ?>
    </div>
    
    <?php if (get_field("link_text")) {
        echo '<a href="' . get_field("link") . '" target="_blank">' . get_field("link_text") . '</a>';
    } ?>
</article>