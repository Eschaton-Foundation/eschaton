<!DOCTYPE html>
<html lang="en" class="no-js">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php

	$baseTitle = get_bloginfo('name');
	$pageTitleWP = wp_title('', false);
	if (strlen($pageTitleWP) > 3) {
		$soc_title = trim(wp_title('', false)) . ' | ';
	} else {
		$soc_title =  '';
	}
	$soc_description = get_field("meta_description", "options");

	// Post-type specific 
	global $post;
	$pImg = '';
	$pImgW = '';
	$pImgH = '';
	$excerpt = get_post_field('post_excerpt');
	if (strlen($excerpt) > 10) {
		$soc_description = strip_tags($excerpt);
	}

	$singlePageDesc = get_field("meta_description");
	if (strlen($singlePageDesc) >= 10) {
		$soc_description = $singlePageDesc;
	}
	$soc_url = "";
	$og_img = "";
	$og_imgW = "";
	$og_imgH = "";
	$tw_img = "";

	$featImg = get_the_post_thumbnail_url(null, 'medium_large');
	if (has_post_thumbnail() && $featImg) {
		$og_img = $featImg;
	}
	$imgOG = get_field("og_image");
	if ($imgOG === '' || empty($imgOG)) {
		if ($pImg !== '') {
			$og_img = $pImg;
			$og_imgW = $pImgW;
			$og_imgH = $pImgH;
		} else {
			$imgOG = get_field("og_image", "options");
		}
	}
	if (is_array($imgOG) && !empty($imgOG)) {
		$og_img = $imgOG['sizes']['medium_large'];
		$og_imgW = $imgOG['sizes']['medium_large-width'];
		$og_imgH = $imgOG['sizes']['medium_large-height'];
	}

	$imgTW = get_field("tw_image");
	if ($imgTW === '' || empty($imgTW)) {
		if ($pImg !== '') {
			$tw_img = $pImg;
		} else {
			$imgTW = get_field("tw_image", "options");
		}
	}
	if (is_array($imgTW) && !empty($imgTW)) {
		$tw_img = $imgTW['sizes']['medium_large'];
	} else if ($og_img !== '') {
		$tw_img = $og_img;
	}

	$soc_description = strip_tags($soc_description);
	$soc_description = strip_shortcodes($soc_description);
	$soc_description = str_replace(array("\n", "\r", "\t"), ' ', $soc_description);
	if (strlen($soc_description) > 500) {
		$soc_description = substr($soc_description, 0, 500);
	}

	?>
	<title><?php print $soc_title . $baseTitle; ?></title>
	<?php if ($soc_description !== "") { ?>
		<meta name="description" content="<?php print str_replace(array("\n", "\r"), '', $soc_description); ?>"><?php } ?>

	<meta property="og:title" content="<?php print $soc_title . $baseTitle; ?>" />
	<?php if ($soc_description !== "") { ?>
		<meta property="og:description" content="<?php print str_replace(array("\n", "\r"), '', $soc_description); ?>" /><?php } ?>

	<meta property="og:site_name" content="<?php echo $baseTitle; ?>" />
	<?php if ($og_img !== "") { ?>
		<meta property="og:image" content="<?php print $og_img; ?>" /><?php } ?>

	<?php if ($og_imgW !== "") { ?>
		<meta property="og:image:width" content="<?php echo $og_imgW; ?>" /><?php } ?>

	<?php if ($og_imgH !== "") { ?>
		<meta property="og:image:height" content="<?php echo $og_imgH; ?>" /><?php } ?>

	<meta name="twitter:card" content="summary_large_image" />
	<?php if ($soc_description !== "") { ?>
		<meta name="twitter:description" content="<?php print str_replace(array("\n", "\r"), '', $soc_description); ?>" /><?php } ?>

	<meta name="twitter:title" content="<?php print $soc_title . $baseTitle; ?>" />
	<?php /* <meta name="twitter:site" content="@">*/ ?>
	<?php if ($tw_img !== '') { ?>
		<meta name="twitter:image" content="<?php print $tw_img; ?>" /><?php } ?>
	<?php if ($post && $post->post_parent !== 0) {
		echo '<link rel="prefetch" href="' . get_permalink($post->post_parent) . '" as="document">';
	} ?>
	<?php if(is_page('booking-admin')){
		echo '
		<meta name="robots" content="noindex">';
	} ?>

	<script>
		document.documentElement.className = document.documentElement.className.replace("no-js", "js");
	</script>


	<?php /*
	<title>Eschaton—Anselm Kiefer Foundation</title>
	<meta name="description" content="A nonprofit organization established in 2017 advancing the artistic legacy of its founder, Anselm Kiefer, and maintaining his studio-estate, La Ribaute, for future generations.">
	<meta property="og:title" content="Eschaton—Anselm Kiefer Foundation">
	<meta property="og:description" content="A nonprofit organization established in 2017 advancing the artistic legacy of its founder, Anselm Kiefer, and maintaining his studio-estate, La Ribaute, for future generations.">
	<meta property="og:site_name" content="Eschaton—Anselm Kiefer Foundation">
	<meta property="og:type" content="website">
	<meta property="og:url" content="https://eschaton-foundation.com">
	<meta property="og:image" content="https://eschaton-foundation.com/assets/Amphitheater_2002_PC_Charles-Duprat_bw.jpg">
	*/ ?>
	<link rel="stylesheet" href="https://use.typekit.net/ywu7pvn.css">
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<meta name="msapplication-TileColor" content="#FFFFFF">
	<meta name="theme-color" content="#ffffff">
    <link href="https://kit-pro.fontawesome.com/releases/v5.15.4/css/pro.min.css" rel="stylesheet">
	
	<?php wp_head(); ?>
	<!-- Global site tag (gtag.js) - Google Analytics 
	<script async src="https://www.googletagmanager.com/gtag/js?id=G-D8K5WSFSGV"></script>
	<script>
		window.dataLayer = window.dataLayer || [];

		function gtag() {
			dataLayer.push(arguments);
		}
		gtag('js', new Date());

		gtag('config', 'G-D8K5WSFSGV');
	</script>-->
</head>

<body data-barba="wrapper" <?php
							$bodyClass = '';
							// if(is_singular('tours')){
							// 	$bodyClass .= ' lang-' . get_field("language");
							// }

							$current_language = get_locale();
							$bodyClass .= ' lang-' . $current_language;
							body_class($bodyClass);
							?>>



	<header class="<?php echo get_field('enable_banner', "options") ? 'banner-on' : ''?>">

		<?php if( get_field('enable_banner', "options") ) : ?>
			<div class="header_banner">
				<div class="marquee">
					<h3>
						<?php
							if( pll_current_language( ) === "en" ) {
								the_field('banner_content', "options"); 
							}
							else if (pll_current_language( ) === "fr") {
								the_field('banner_content_fr', "options"); 
							}
							else if (pll_current_language( ) === "de") {
								the_field('banner_content_de', "options"); 
							}
							?>
					</h3>
				</div>
			</div>

			<script>
				function Marquee(selector, speed) {
					const parentSelector = document.querySelector(selector);
					const clone = parentSelector.innerHTML;
					const firstElement = parentSelector.children[0];
					let i = 0;
					console.log(firstElement);
					parentSelector.insertAdjacentHTML('beforeend', clone);
					parentSelector.insertAdjacentHTML('beforeend', clone);

					setInterval(function () {
						firstElement.style.marginLeft = `-${i}px`;
						if (i > firstElement.clientWidth) {
						i = 0;
						}
						i = i + speed;
					}, 0);
					}

					//after window is completed load
					//1 class selector for marquee
					//2 marquee speed 0.2
				window.addEventListener('load', Marquee('.marquee', 0.4))

			</script>
		<?php endif; ?>

		<div class="header_nav">

			<div>
				<a class="site-name" href="<?php echo get_home_url(); ?>" rel="home" title="to homepage"><?php echo get_bloginfo('name'); ?></a>
				<button class="menu-toggle" aria-label="Toggle the primary menu">
					<span>Menu</span>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22.17 13">
						<line class="cls-1" y1="0.5" x2="22.17" y2="0.5" />
						<line class="cls-1" y1="6.5" x2="22.17" y2="6.5" />
						<line class="cls-1" y1="12.5" x2="22.17" y2="12.5" />
					</svg>
				</button>
			</div>

			<div class="lang_switcher">
				<ul id="lang_list" class="dropdown_panel">
					<?php pll_the_languages();?>
				</ul>
			</div>
		</div>
		
	</header>
	<?php
	wp_nav_menu(array(
		'theme_location' => 'primary',
		'items_wrap'	=>	'<ul>%3$s</ul>',
		'menu_class' => 'header-menu',
		'container' => 'nav',
		'container_class'	=> 'menu-primary-nav-container',

	));
	?>
	<main data-barba="container" data-barba-namespace="<?php
														if (is_home() || is_front_page()) {
															echo 'home';
														} else if (is_page()) {
															echo $post->post_name;
														}
														?>" <?php
															if (
																get_field("subnav_tf") ||
																is_singular('artists')
															) {
																echo ' class="has-subnav"';
															}
															?>>
		<?php
		if (get_field("subnav_tf")) {
			$tempContents = get_field("page_contents");
			if ($tempContents && !empty($tempContents)) {
				$subnavOut = '';
				foreach ($tempContents as $tempContent) {
					if ($tempContent['acf_fc_layout'] === 'header' && isset($tempContent['subnav_tf']) && $tempContent['subnav_tf']) {
						$labelOut = $tempContent['header'];
						if (isset($tempContent['nav_label_override']) && $tempContent['nav_label_override']) {
							$labelOut = $tempContent['nav_label_override'];
						}
						$subnavOut .= '<a class="anchor-link-single" href="#' . sanitize_title($tempContent['header']) . '">' . $labelOut . '</a> ';
					}
				}
				if ($subnavOut !== '') {
					echo '<nav class="subnav">' . $subnavOut . '</nav>';
				}
			}
		} else if (is_singular('artists')) {
			echo '<nav class="subnav"><a href="' . get_permalink(36) . '">' . get_the_title(36) . ' /</a></nav>';
		}
		?>