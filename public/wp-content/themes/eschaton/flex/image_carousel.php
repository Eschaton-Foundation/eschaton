<?php
if (empty($cLoop['images'])) {
	return;
}
$scopedWrap = 'figure';
$scopeClass[] = 'bleed';
$scopeClass[] = 'slideshow';
$scopeClass[] = 'cnt-' . count($cLoop['images']);
$scopedWrapInner = null;

include(get_template_directory() . "/flex/_flex-header.php");
$moImgs = array();
if ($cLoop['mobile_diff_tf'] && !empty($cLoop['images_mobile'])) {
	$moImgs = $cLoop['images_mobile'];
}
foreach ($cLoop['images'] as $imgKey => $imgLoop) {
	$fImg = wp_get_attachment_image_src($imgLoop, 'thumbnail');
	$orientClass = orientClass($fImg[2] / $fImg[1]);
?>
	<div class="slide<?php if ($imgKey === 0) {
							echo ' active';
						} ?>">
		<?php echo wp_get_attachment_image($imgLoop, 'large', "", ["class" => "orient-hor"]);
		if (!empty($moImgs) && isset($moImgs[$imgKey])) {
			echo wp_get_attachment_image($moImgs[$imgKey], 'large', "", ["class" => "orient-vert"]);
		}

		$attachment = get_post($imgLoop);
		$caption = $attachment->post_excerpt;

		if ($caption) {
			echo '<figcaption class="img-caption wp-caption-text img-caption">' . $caption . '</figcaption>';
		}
		?>
	</div>

<?php }

include(get_template_directory() . "/flex/_flex-footer.php"); ?>