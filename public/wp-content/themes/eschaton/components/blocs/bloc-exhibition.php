
<article class="exhibition-single">

    <?php if (has_post_thumbnail()) {
        echo '<div class="img-wrap">';
            echo wp_get_attachment_image(get_post_thumbnail_id(get_the_ID()), 'thumbnail', false);
            echo "<span class='exhibtion_caption'>";
                the_post_thumbnail_caption( );
            echo "</span>";
        echo '</div>';
    } ?>

    <div class="txt-wrap wyg">
        <a href="<?php the_field('link'); ?>" target="_blank">
            <h3 class="exhibition_title"><?php the_title(); ?></h3>
        </a>

        <div><?php the_field("exhibition_place"); ?></div>
        <div><?php the_content(); ?></div>

        <div><?php the_field("date_start"); ?> - <?php the_field("date_end"); ?></div>
        
    </div>
    

</article>