<?php
if (empty($cLoop['image'])) {
	return;
}
$scopeClass[] = $cLoop['display'];
$scopedWrapInner = null;

include(get_template_directory() . "/flex/_flex-header.php");
$fImg = wp_get_attachment_image_src($cLoop['image'], 'thumbnail');
$orientClass = orientClass($fImg[2] / $fImg[1]);
?>

<figure class="<?php echo $orientClass; ?>">
	<?php
	echo wp_get_attachment_image($cLoop['image'], 'large', false);
	$attachment = get_post($cLoop['image']);
	$alt = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
	$image_title = $attachment->post_title;
	$caption = $attachment->post_excerpt;

	if ($caption) {
		echo '<figcaption class="wp-caption-text img-caption">' . $caption . '</figcaption>';
	}
	?>
</figure>

<?php
include(get_template_directory() . "/flex/_flex-footer.php"); ?>