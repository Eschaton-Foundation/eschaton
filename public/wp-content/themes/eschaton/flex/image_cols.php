<?php
if (empty($cLoop['images'])) {
	return;
}
$scopeClass[] = 'bleed';
$scopeClass[] = 'cnt-' . count($cLoop['images']);
$scopedWrapInner = null;

if (count($cLoop['images']) !== 2) {
	return;
}

$img1 = $cLoop['images'][0];
$img2 = $cLoop['images'][1];

$gutterW = 40;

$img1Dims = array($img1['width'], $img1['height']);
$img2Dims = array($img2['width'], $img2['height']);

if ($img2['height'] > $img1['height']) {
	$perc = $img2['height'] / $img1['height'];
	$img1Dims = array($img1Dims[0] * $perc, $img1Dims[1] * $perc);
} else {
	$perc = $img1['height'] / $img2['height'];
	$img2Dims = array($img2Dims[0] * $perc, $img2Dims[1] * $perc);
}

$combinedWidth = $img1Dims[0] + $img2Dims[0];;
$img1W = $img1Dims[0] / $combinedWidth;

$img1Perc = $img1W * 100;
$img2Perc = (1 - $img1W) * 100;

$scopeClass[] = 'size-contain';

// if ($img1['height'] >= $img1['width'] && $img2['height'] >= $img2['width']) {
// 	$img1Perc = 50;
// 	$img2Perc = 50;
// }

include(get_template_directory() . "/flex/_flex-header.php");

?>
<div class="img-cols-grid" style="grid-template-columns:<?php echo $img1Perc; ?>fr <?php echo $img2Perc; ?>fr;">
	<div style="grid-template-column:<?php echo $img1Perc; ?>fr;">
		<?php
		echo wp_get_attachment_image($img1['ID'], 'large', false);
		$attachment = get_post($img1['ID']);
		$caption = $attachment->post_excerpt;

		if ($caption) {
			echo '<figcaption class="img-caption wp-caption-text">' . $caption . '</figcaption>';
		}
		?>
	</div>
	<div style="grid-template-column:<?php echo $img2Perc; ?>fr;">
		<?php
		echo wp_get_attachment_image($img2['ID'], 'large', false);
		$attachment = get_post($img2['ID']);
		$caption = $attachment->post_excerpt;

		if ($caption) {
			echo '<figcaption class="img-caption wp-caption-text">' . $caption . '</figcaption>';
		}
		?>
	</div>
</div>
<?php

$img1AR = $img1['height'] / $img1['width'];
$img2AR = $img2['height'] / $img2['width'];

$simulateScreenWidth = 1000;


// foreach (range(1, 100) as $index) {
// 	echo $index . ' / ' . $index / $img1AR . ' / ' . (100 - $index) / $img2AR . "<br>";
// }
// print_r($img1);

/*
foreach ($cLoop['images'] as $imgKey => $imgLoop) {
	
	print_r($imgLoop);
	$fImg = wp_get_attachment_image_src($imgLoop, 'thumbnail');
	$orientClass = orientClass($fImg[2] / $fImg[1]);
?>

	<figure class="<?php echo $orientClass; ?>">
		<?php
		echo wp_get_attachment_image($imgLoop, 'large', false);

		$attachment = get_post($imgLoop);
		$alt = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
		$image_title = $attachment->post_title;
		$caption = $attachment->post_excerpt;

		if ($caption) {
			echo '<figcaption class="wp-caption-text">' . $caption . '</figcaption>';
		}
		?>
	</figure>

<?php 
}
*/
include(get_template_directory() . "/flex/_flex-footer.php");
