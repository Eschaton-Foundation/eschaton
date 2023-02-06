<div class="bibliography-single">

	<div class="txt-wrap wyg"><?php the_title(); ?></div>

	<?php the_field('media_date'); ?>
	<?php the_field('media_name'); ?>

	<?php if (get_field("media_link")) {
		echo '<a href="' . get_field("media_link") . '" target="_blank">' . "Read more" . '</a>';
	} ?>

</div>