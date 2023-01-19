<?php
function newsletter_shortcode($atts, $content = null)
{
	// if(isset($atts['text'])){
	// 	return '<a href="#">'.$atts['text'].'</a>';
	// }
	// $outText = '
	// 	<form class="mailinglist-cta">
	// 		<input type="text" placeholder="Enter your email address" />
	// 	</form>
	// ';
	$outText = do_shortcode('[gravityform id="1" title="false" description="false" ajax="true"]');
	return $outText;
}
add_shortcode('mailinglist-cta', 'newsletter_shortcode');
