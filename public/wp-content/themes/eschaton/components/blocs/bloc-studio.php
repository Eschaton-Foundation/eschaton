
<div id="about-<?php the_field('date_start'); ?>" class="studio_single" data-start="<?php the_field('date_start'); ?>" data-end="<?php the_field('date_end'); ?>">


    <div class="studio_intro">
        <div class="intro_text">
            <h2 class="studio_dates">
                <?php the_field('date_start'); ?><br>
                <?php the_field('date_end'); ?>
            </h2>

            <h3 class="studio_title">
                <?php the_title(); ?>
            </h3>

            <div class="txt-wrap wyg">
                <?php the_field('studio_introduction'); ?>
            </div>
        </div>

        <div class="intro_image">
            <?php if (has_post_thumbnail()) {
                echo '<div class="img-wrap">';
                    echo wp_get_attachment_image(get_post_thumbnail_id($pID), 'thumbnail', false);
                echo '</div>';
            } ?>
        </div>
    </div>

    <div class="studio_content">
        <?php the_content(); ?>
    </div>

    <?php 
        // $images = get_field('studio_medias');
        // $size = 'full'; // (thumbnail, medium, large, full or custom size)
        if( $images ): ?>
            <div id="slider" class="flexslider">
                <ul class="studio_medias">
                    <?php foreach( $images as $image ): ?>
                        <li>
                            <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" />
                            <span><?php echo esc_html($image['caption']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

</div>