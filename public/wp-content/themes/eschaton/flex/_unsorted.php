<?php include(get_template_directory() . "/flex/_flex-header.php"); ?>


<?php
if ($cLoop['acf_fc_layout'] === 'text_landing') { ?>
	<section class="padinLR">
		<article class="wyg text-long col-max">
			<?php echo $cLoop['text']; ?>
		</article>
	</section>
<?php } else if ($cLoop['acf_fc_layout'] === 'single-item') {
	$linkTarget = $cLoop['target'];
	if (is_array($linkTarget)) {
		$linkTarget = $linkTarget[0];
	}
	$cardSize = '1';
	if ($cLoop['display'] === 'large') {
		$cardSize = '0';
	}
?>
	<section class="padinLR cards-group iv cards-size-<?php echo $cardSize; ?>-wrap">
		<div class="cards-wrap col-max">
			<article class="cards cards-size-<?php echo $cardSize; ?> b-lr">
				<div class="card<?php if (isset($cLoop['layout']) && $cLoop['layout']) {
									echo ' ' . $cLoop['layout'];
								} ?>">
					<span class="blok type-label"><?php echo pageTypeOut(get_post_type($linkTarget));
													if (get_field("activeTF", $linkTarget)) {
														echo ' &bull; Active';
													}
													?></span>
					<div class="img-wrap"><a href="<?php echo get_permalink($linkTarget); ?>"><?php
																								if (has_post_thumbnail($linkTarget)) {
																									echo wp_get_attachment_image(get_post_thumbnail_id($linkTarget), 'medium', false);
																								} else {
																									echo '<div class="img-placeholder full-w"></div>';
																								}
																								?></a></div>
					<div class="txt-wrap">
						<h3><a href="<?php echo get_permalink($linkTarget); ?>"><?php echo get_the_title($linkTarget); ?></a></h3>
						<div class="wyg text-excerpt">
							<?php echo get_the_excerpt($linkTarget); ?>
						</div>
					</div>
				</div>
			</article>
		</div>
	</section>
<?php } else if ($cLoop['acf_fc_layout'] === 'two-up') { ?>
	<section class="padinLR cards-group">
		<div class="cards-wrap col-max">
			<article class="cards cards-size-2 b-lr">
				<?php
				$linkTargets = $cLoop['target'];
				if (is_array($linkTargets) && !empty($linkTargets) && $linkTargets[0] !== 1) {
					foreach ($linkTargets as $linkTarget) { ?>
						<div class="card">
							<span class="blok type-label"><?php echo pageTypeOut(get_post_type($linkTarget));
															if (get_field("activeTF", $linkTarget)) {
																echo ' &bull; Active';
															}
															?></span>
							<div class="img-wrap"><a href="<?php echo get_permalink($linkTarget); ?>"><?php
																										if (has_post_thumbnail($linkTarget)) {
																											echo wp_get_attachment_image(get_post_thumbnail_id($linkTarget), 'medium', false);
																										} else {
																											echo '<div class="img-placeholder"></div>';
																										}
																										?></a></div>
							<div class="txt-wrap">
								<h3><a href="<?php echo get_permalink($linkTarget); ?>"><?php echo get_the_title($linkTarget); ?></a></h3>
								<div class="wyg text-excerpt">
									<?php echo get_the_excerpt($linkTarget); ?>
								</div>
							</div>
						</div>
					<?php }
				} else { ?>

					<div class="card">
						<span class="blok type-label">Grants</span>
						<div class="img-wrap"><?php
												echo wp_get_attachment_image(67, 'medium', false);
												?></div>
						<div class="txt-wrap">
							<h3><a href="#">Grant awarded to SIXTY Inches from Center</a></h3>
							<div class="wyg text-excerpt">
								<p>As part of our new Research Grant Program we awarded $25,000 to SIXTY Inches from Center / Chicago Archives + Artists Project to support their resident artists and the production of their book <em>Archives + Artists: Case Studies in Collaboration</em>, a guide that will share their experiences over several years of collaborations between artists' and archivists' communities.</p>
							</div>
						</div>
					</div>
					<div class="card">
						<span class="blok type-label">Events</span>
						<div class="img-wrap"><?php
												echo wp_get_attachment_image(68, 'medium', false);
												?></div>
						<div class="txt-wrap">
							<h3><a href="#">Artist-Centered Archiving</a></h3>
							<small class="blok">JULY 24 2021 • 7PM</small>
							<div class="wyg text-excerpt">
								<p>The archives field is increasingly shifting its focus from institutional collecting to supporting and empowering communities to archive themselves. Join us for a conversation with the innovative game-changers at ART360 Foundation, SIXTY Inches From Center, and Voices of Contemporary art as they discuss programs connecting communities and artists with tools to preserve their own legacies. </p>
							</div>
						</div>
					</div>
				<?php } ?>
			</article>
		</div>
	</section>
<?php } else if ($cLoop['acf_fc_layout'] === 'headline') {
	$rowClass .= ' ' . $cLoop['size'];

	if (isset($cLoop['header_exclude_tf']) && $cLoop['header_exclude_tf']) {
		$rowClass .= ' headline_hidden';
	}
?>
	<section class="container-fluid flow section-<?php echo $cLoop['acf_fc_layout']; ?><?php echo $rowClass; ?>">
		<?php
		if (isset($cLoop['page_anchor_tf']) && $cLoop['page_anchor_tf']) {
			echo '<a name="' . sanitize_title($cLoop['headline']) . '" class="page-anchor">' . $cLoop['headline'] . '</a>';
		}
		if ($cLoop['rule_tf']) {
			echo '<hr />';
		}
		?>
		<div class="header-col wyg">
			<?php echo splitMore($cLoop['headline']); ?>
		</div>
	</section>
<?php
} else if ($cLoop['acf_fc_layout'] === 'image') {
	if (isset($cLoop['image']) && $cLoop['image'] !== '') {
		$captionOut = '';
		$figOut = '';
		if (get_field("fig_tf", $cLoop['image']) && get_field("figure_text", $cLoop['image'])) {
			$figOut = '<span class="fig-text-caption">' . get_field("figure_text", $cLoop['image']) . '</span> ';
		}
		if (get_field("media_caption", $cLoop['image'])) {
			$imgCaption = get_field("media_caption", $cLoop['image']);
			$captionOut = '<div class="img-caption caption">' . $figOut . $imgCaption . '</div>';
		} else {
			$imgCaption = wp_get_attachment_caption($cLoop['image']);
			if ($imgCaption && !empty($imgCaption)) {
				$captionOut = '<div class="img-caption caption">' . $figOut . apply_filters("the_content", $imgCaption) . '</div>';
			}
		}

		if (!isset($cLoop['caption_tf']) || !$cLoop['caption_tf']) {
			$captionOut = '';
		}
		$rowClass .= ' img-layout-' . $cLoop['display'];
		if ($cLoop['display'] === 'std' || $cLoop['display'] === 'inset') {
			$rowClass .= ' col-max padinConLR';
		}
		if ($cLoop['display'] === 'bleedshape') {
			$rowClass .= ' ' . $cLoop['shape'];
		}
		$fImg = wp_get_attachment_image_src($cLoop['image'], 'thumbnail');
		$extraClass = orientClass($fImg[2] / $fImg[1]);
		echo '
		<section class="flow section-' . $cLoop['acf_fc_layout'] . $rowClass . '">
			<div class="img-container">
				<div class="img-wrap ' . $extraClass . '">' . wp_get_attachment_image($cLoop['image'], 'large') . '</div>
				' . $captionOut . '
			</div>
		</section>
		';
	}
} else if ($cLoop['acf_fc_layout'] === 'cta') {

?>
	<article class="cta-button-wrap <?php echo $cLoop['display']; ?>">
		<div class="cta-button-inline"><a href="<?php
												if ($cLoop['link_type'] === 'ref') {
													echo get_permalink($cLoop['link_target']);
												} else {
													echo $cLoop['url'];
												}
												?>"><?php echo $cLoop['text']; ?></a></div>
	</article>
	<?php
} else if ($cLoop['acf_fc_layout'] === 'gallery') {
	if (isset($cLoop['gallery']) && !empty($cLoop['gallery']) && count($cLoop['gallery']) > 1) {
		$sliderGalOut = '';
		$gridGalOut = '';
		$captionOut = '';

		foreach ($cLoop['gallery'] as $galKey => $galImg) {
			$fImg = wp_get_attachment_image_src($galImg, 'thumbnail');
			$postImg = $fImg[0];
			$extraClass = orientClass($fImg[2] / $fImg[1]);

			$sliderGalOut .= '
			<div class="gallery-slide">' . wp_get_attachment_image($galImg, 'large', false) . '</div>
			';

			$gridGalOut .= '
			<div class="grid-item grid-item-exh-img">
				<div class="img-wrap ' . $extraClass . '">' . wp_get_attachment_image($galImg, 'thumbnail') . '</div>
				<div class="txt-wrap">' . getImgCaption($galImg) . '</div>
			</div>
			';
			if ($galKey === 0) {
				$captionOut .= '<div class="gallery-caption caption-' . $galKey . '">' . getImgCaption($galImg) . '</div>';
			} else {
				$captionOut .= '<div class="gallery-caption active caption-' . $galKey . '">' . getImgCaption($galImg) . '</div>';
			}
			// echo '<div class="img-single">';
			// echo remoteAssets(wp_get_attachment_image($galImg,'thumbnail'));
			// echo '</div>';
		}
		// echo '</div>';

		if (isset($cLoop['images_flex_img']) && isset($cLoop['images_flex_img']['img_w'])) {
			$rowClass .= ' size-' . $cLoop['images_flex_img']['img_w'];
		}

	?>
		<section class="gallery-wrap keen-gallery section-<?php echo $cLoop['acf_fc_layout']; ?><?php echo $rowClass; ?>">
			<div class="container-fluid">
				<div class="cycle-header flex rule-above">
					<?php if (isset($cLoop['header']) && $cLoop['header'] !== '') { ?><h2><?php echo $cLoop['header']; ?></h2><?php } ?>
					<?php if (count($cLoop['gallery']) > 1) { ?>
						<div class="cycle-counts">
							<span class="cycle-curr h2">1</span><span class="cycle-sep h2">/</span><span class="cycle-total h2"><?php echo count($cLoop['gallery']) ?></span>
							<div class="cycle-navigation">
								<button class="cycle-previous" aria-label="Previous page of results"><span></span></button>
								<button class="cycle-next" aria-label="Next page of results"><span></span></button>
							</div>
						</div>
					<?php } ?>
				</div>
			</div>

			<div class="gallery-parent">
				<div class="gallery-sizer"></div>
				<div class="gallery-slides">
					<?php echo $sliderGalOut; ?>
				</div>
			</div>
			<div class="container-fluid cycle-below">
				<div class="gallery-captions"><?php echo $captionOut; ?></div>
			</div>
		</section>
		<?php
		// if(!$rowOptions['disable_section_border']){
		// 	echo '<div class="gallery-after"><hr /></div>';
		// }
	}
	/*if(isset($cLoop['image']['ID']) && $cLoop['image']['ID'] !== ''){
		$captionOut = '';
		$figOut = '';
		// if(get_field("fig_tf",$cLoop['image']['ID']) && get_field("figure_text",$cLoop['image']['ID'])){
		// 	$figOut = '<span class="fig-text-caption">' . get_field("figure_text",$cLoop['image']['ID']) . '</span> ';
		// }
		if(get_field("caption",$cLoop['image']['ID'])){
			$imgCaption = get_field("caption",$cLoop['image']['ID']);
			$captionOut = '<div class="img-caption caption">' . $figOut . $imgCaption . '</div>';
		} else {
			$imgCaption = wp_get_attachment_caption($cLoop['image']['ID']);
			if($imgCaption && !empty($imgCaption)){
				$captionOut = '<div class="img-caption caption">' . $figOut . apply_filters("the_content",$imgCaption) . '</div>';
			}
		}
		$colContentClass = 'col-content';
		if($cLoop["img_extend_tf"]){
			$colContentClass = 'col-content-full';
		}
		echo '
			<div class="'.$colContentClass.'">
				'.$rowHeadline.'
				<div class="img-container">
					<div class="img-wrap">'.wp_get_attachment_image( $cLoop['image']['ID'], 'large').'</div>
					'.$captionOut.'
				</div>
			</div>
		';
	}*/
} else if ($cLoop['acf_fc_layout'] === 'coltext') {
	$cols = $cLoop['column'];
	$colCnt = 2;
	$colsSize = 'inline';
	if (isset($cLoop['dt_width'])) {
		$colsSize = $cLoop['dt_width'];
	}
	if ($cLoop['rule_tf']) {
		echo '<div class="container-fluid"><hr /></div>';
	}
	if (!empty($cols)) {
		$colCnt = count($cols);
	}

	echo '
	<section class="container-fluid col-size-' . $colsSize . ' flow section-' . $cLoop['acf_fc_layout'] . $rowClass . ' col-cnt-' . $colCnt . '">';
	if ($colCnt <= 2) {
		echo '<div class="text-col">';
	}
	if (!empty($cols)) {
		foreach ($cols as $colSingle) {
			if (isset($colSingle['coltype'])) {
				$colType = $colSingle['coltype'];
			} else {
				$colType = 'text';
			}
			if ($colType === 'img') {
				if (get_field("media_caption", $colSingle['image'])) {
					$imgCaption = get_field("media_caption", $colSingle['image']);
					$captionOut = '<div class="img-caption caption">' . $imgCaption . '</div>';
				} else {
					$imgCaption = wp_get_attachment_caption($colSingle['image']);
					if ($imgCaption && !empty($imgCaption)) {
						$captionOut = '<div class="img-caption caption">' . $figOut . apply_filters("the_content", $imgCaption) . '</div>';
					}
				}
				$fImg = wp_get_attachment_image_src($colSingle['image'], 'thumbnail');
				$extraClass = orientClass($fImg[2] / $fImg[1]);
		?>
				<div class="flex-col col-w-image">
					<div class="img-container">
						<div class="img-wrap"><?php echo wp_get_attachment_image($colSingle['image'], 'large'); ?></div>
						<?php echo $captionOut; ?>
					</div>
				</div>
			<?php } else { ?>
				<div class="flex-col col-w-text wyg list-of-names"><?php echo $colSingle['text']; ?></div>
	<?php }
		}
	}
	if ($colCnt <= 2) {
		echo '</div>';
	}
	echo '
	</section>';
} else if ($cLoop['acf_fc_layout'] === 'popup_grid') {
	?>
	<section class="container-fluid section-<?php echo $cLoop['acf_fc_layout']; ?><?php echo $rowClass; ?>">
		<div class="enlarge-repeater">
			<?php if (!empty($cLoop['grid_items'])) {
				foreach ($cLoop['grid_items'] as $gItem) {
					$imgOut = '';
					$imgOutLg = '';
					if ($gItem['image'] && !empty($gItem['image'])) {
						$imgOut = wp_get_attachment_image($gItem['image'], 'thumbnail', false);
						$imgOutLg = wp_get_attachment_image($gItem['image'], 'large', false);
					}
			?>
					<div class="enlarge-grid-single" data-id="<?php echo $gItem['image']; ?>">
						<div class="img-container iv">
							<?php echo $imgOut; ?>
						</div>
						<div class="txt-container<?php

													?>">
							<div class="callout-text flow wyg">
								<?php echo textRewrite($gItem['grid_text']); ?>
							</div>
						</div>
					</div>
			<?php
					$modalContents .= '<div class="enlargement-grid-single modal-item-' . $gItem['image'] . '">
						<button class="modal-close">Close</button>
						<div class="img-container iv">' . $imgOutLg . '</div>
						<div class="txt-container">
							<div class="callout-text flow wyg">' . textRewrite($gItem["popup_text"]) . '</div>
						</div>
					</div>';
				}
			} ?>

		</div>
	</section>

	<?php
} else if ($cLoop['acf_fc_layout'] === 'callout_grid') {
	if ($cLoop['callout_rep'] && !empty($cLoop['callout_rep'])) {
		$gridSize = $cLoop['grid_size'];
		echo '<section class="container-fluid grid-size-' . $gridSize . ' section-' . $cLoop['acf_fc_layout'] . $rowClass . '" data-cnt="' . count($cLoop['callout_rep']) . '">';
		foreach ($cLoop['callout_rep'] as $calloutSingle) {

			$featureType = $calloutSingle['ptype'];
			$subClass = '';
			if ($featureType === 'page') {
				$tgt = $calloutSingle['target_page_page'];
			} else if ($featureType === 'event') {
				$subClass = 'h2';
				$tgt = $calloutSingle['target_page_event'];
			} else if ($featureType === 'program') {
				$tgt = $calloutSingle['target_page_program'];
			} else if ($featureType === 'programind') {
				$tgt = $calloutSingle['target_page_page'];
			} else {
				$tgt = $calloutSingle['target_page'];
			}
			if ($tgt) {
				$tgtType = get_post_type($tgt);
			}

			$imgOut = '';
			if (has_post_thumbnail($tgt)) {
				if ($cLoop['images_tf']) {
					$imgOut = wp_get_attachment_image(get_post_thumbnail_id($tgt), 'large', false);
				}
			}

			$titleOut = get_the_title($tgt);
			$linkOut = get_the_permalink($tgt);
			$subOut = get_field("display_subtitle", $tgt);
			$tertOut = get_field("display_tertiary", $tgt);

			if ($featureType === 'programind' && get_post_type($tgt) === 'cwos') {
				// print_r($cLoop);
				$titleOut = get_field("display_title", $tgt);
				$subOut = get_field("date_display", $tgt);
				$tertOut = null;
				if (has_excerpt($tgt)) {
					$tertOut = get_the_excerpt($tgt);
				}
			}

			$tertClass = 'h2';
			if ($featureType === 'programind') {
				if (has_excerpt($tgt)) {
					$tertOut = get_the_excerpt($tgt);
				}
				$tertClass = '';
			}

			if ($featureType === 'program') {
				$termLoad = get_term($tgt, 'program');
				$titleOut = $termLoad->name;
				$tertOut = $termLoad->description;
				$linkOut = get_term_link($tgt);
				$tertClass = '';
				$tempImg = get_field("featured_image", 'program_' . $tgt);
				if ($tempImg) {
					$imgOut = wp_get_attachment_image($tempImg, 'large', false);
				}
				// print_r($termLoad);
			}
	?>
			<div class="card-row-single hovParent flow<?php if ($imgOut !== '') {
															echo ' has-img';
														} ?>">
				<?php if ($imgOut !== '') {
					echo '<div class="card-img"><a href="' . $linkOut . '">' . $imgOut . '</a></div>';
				} ?>
				<?php if ($tgtType === 'programs') {
					$pretitle = '';
					if ($this->_display !== 'program-archive') {
						$pTerms = get_the_terms($tgt, 'program');
						if (!empty($pTerms)) {
							foreach ($pTerms as $pKey => $pTerm) {
								$term_link = get_term_link($pTerm, array('program'));
								if ($pKey > 0) {
									$pretitle .= ', ';
								}
								$pretitle .= '<a href="' . $term_link . '">' . $pTerm->name . '</a>';
							}
						}
					}
					if (get_field("pretitle", $tgt)) {
						if ($pretitle !== '') {
							$pretitle .= ' / ';
						}
						$pretitle .= get_field("pretitle", $tgt);
					}

					if ($pretitle !== '') { ?><span class="blok breadcrumb-row hovlink h2"><?php echo $pretitle; ?></span><?php } ?>

				<?php } ?>
				<p class="h2"><?php
								if (isset($pType) && $pType === 'post') {
									echo get_the_date('F j, Y', $tgt);
								} else {
									if (get_field("date_start", $tgt)) {
										echo dateOut(get_field("date_start", $tgt), get_field("date_end", $tgt));
									}
								}
								?></p>
				<h2 class="h2std hovlink"><a href="<?php echo $linkOut; ?>"><?php echo $titleOut; ?></a></h2>
				<?php if ($subOut) { ?><p class="h2"><?php echo $subOut; ?></p><?php } ?>
				<?php if ($tertOut) { ?><span class="blok <?php echo $tertClass; ?>"><?php echo $tertOut; ?></span><?php } ?>
				<?php if (isset($pType) && $pType === 'post') {
					$categories = get_the_category($tgt);
					if (!empty($categories)) {
						echo '<span class="blok h2 subdue-bg">' . esc_html($categories[0]->name) . '</span>';
					}
				} ?>

			</div>
	<?php

		}

		echo '</section>';
	}
} else if ($cLoop['acf_fc_layout'] === 'accordion') { ?>

	<?php
} else if ($cLoop['acf_fc_layout'] === 'callout_row') {
	echo '<div class="col-content callout-row">';
	if ($cLoop['callout'] && !empty($cLoop['callout'])) {
		foreach ($cLoop['callout'] as $calloutSingle) {
			if ($calloutSingle && !empty($calloutSingle) && !empty($calloutSingle['target_page'])) {
				$tgt = $calloutSingle['target_page'];
				echo '<div class="flex-col callout-col top-border padTop1">
						<h4 class="link-arrow-subdue"><a href="' . get_permalink($tgt) . '">' . get_the_title($tgt) . '</a></h4>
						<div class="serif below-head">' . get_field("wyg_excerpt", $tgt) . '</div>
					</div>';
			}
		}
	}

	echo '</div>';
} else if ($cLoop['acf_fc_layout'] === 'exhibitions_events') {
	$now = new DateTime('now');
	$today = $now->format("Y-m-d");
	$lastEvent = null;

	$relID = get_the_id();
	$args = array(
		'post_type' => 'events',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'meta_key' => 'date_start',
		'orderby' => 'meta_value',
		'order' => 'ASC',
		'meta_query' => array(
			'relation' => 'AND',
			// array(
			// 	'key'		=> 'date_start',
			// 	'compare'	=> '>=',
			// 	'value'		=> $today,
			// ),
			array(
				'key' => 'related_exhibition',
				'value' => '' . $relID . '',
				'compare' => 'LIKE'
			)
		),


	);
	query_posts($args);
	// print_r($args);
	$outEvents = array();
	if (have_posts()) {
		while (have_posts()) : the_post();
			$tgt = get_the_id();
			$outEvents[] = $tgt;
		endwhile;
	?>

		<section class="cycle-row section-flex-related-events">
			<div class="container-fluid">
				<div class="cycle-header flex rule-above">
					<h2>Related Events</h2>
					<?php if (count($outEvents) > 3) { ?>
						<div class="cycle-counts">
							<span class="cycle-curr h2">1</span><span class="cycle-sep h2">/</span><span class="cycle-total h2"><?php echo count($outEvents) ?></span>
							<button class="cycle-next" aria-label="Next page of results"><span></span></button>
						</div>
					<?php } ?>
				</div>
			</div>
			<div class="cycle-wrap">
				<div class="keen-slider">
					<?php if ($outEvents && !empty($outEvents)) {
						foreach ($outEvents as $cItem) {
							$imgOut = '';
							$pType = get_post_type($cItem);
							if (has_post_thumbnail($cItem)) {
								$imgOut = wp_get_attachment_image(get_post_thumbnail_id($cItem), 'large', false);
							}
							// if($cItem['img_override'] && !empty($cItem['img_override'])){
							// 	$imgOut = wp_get_attachment_image ( $cItem['img_override'], 'large', false );	
							// }

					?>
							<div class="keen-slider__slide">
								<div class="card-row-single hovParent flow">
									<?php if ($imgOut !== '') {
										echo '<div class="card-img"><a href="' . get_the_permalink($cItem) . '" aria-label="<?php echo $titleOut; ?>" tabindex="-1">' . $imgOut . '</a></div>';
									} ?>
									<p class="h2"><?php
													if ($pType === 'post') {
														echo get_the_date('F j, Y', $cItem);
													} else {
														echo dateOut(get_field("date_start", $cItem), get_field("date_end", $cItem));
													}
													?></p>
									<h2 class="h2std hovlink"><a href="<?php echo get_the_permalink($cItem); ?>"><?php echo get_the_title($cItem); ?></a></h2>
									<?php if (get_field("display_subtitle", $cItem)) { ?><p class="h2"><?php the_field("display_subtitle", $cItem); ?></p><?php } ?>
									<?php if (get_field("display_tertiary", $cItem)) { ?><span class="blok h2"><?php the_field("display_tertiary", $cItem); ?></span><?php } ?>
									<?php  // flag add location? 
									?>
									<?php if (isset($pType) && $pType === 'post') {
										$categories = get_the_category($cItem);
										if (!empty($categories)) {
											echo '<span class="blok h2 subdue-bg">' . esc_html($categories[0]->name) . '</span>';
										}
									} ?>
								</div>
							</div>
					<?php
						}
					} ?>
				</div>
			</div>
		</section>


	<?php
	}
	wp_reset_query();
	echo '</div>';
} else if ($cLoop['acf_fc_layout'] === 'callout_single') { ?>
	<section class="cycle-row-single container-fluid section-<?php echo $cLoop['acf_fc_layout'] . $rowClass; ?>">
		<div class="cycle-header flex">
			<h2><?php echo $cLoop['header']; ?></h2>
			<div class="more-wrap h2">
				<?php if ($cLoop['see_all_link']) { ?><a href="<?php echo $cLoop['see_all_link']; ?>"><?php echo $cLoop['see_all_text']; ?></a><?php } ?>
			</div>
		</div>
		<div class="callout-single-grid">
			<?php if ($cLoop['callout_items'] && !empty($cLoop['callout_items'])) {
				foreach ($cLoop['callout_items'] as $cItem) {
					$imgOut = '';
					if (has_post_thumbnail($cItem->ID)) {
						if ($cLoop['images_tf']) {
							$imgOut = wp_get_attachment_image(get_post_thumbnail_id($cItem->ID), 'large', false);
						}
					}
			?>
					<div class="card-row-single hovParent flow<?php if ($imgOut !== '') {
																	echo ' has-img';
																} ?>">
						<?php if ($imgOut !== '') {
							echo '<div class="card-img"><a href="' . get_the_permalink($cItem) . '">' . $imgOut . '</a></div>';
						} ?>
						<p class="h2"><?php
										if (isset($pType) && $pType === 'post') {
											echo get_the_date('F j, Y', $cItem->ID);
										} else {
											if (get_field("date_start", $cItem)) {
												echo dateOut(get_field("date_start", $cItem), get_field("date_end", $cItem));
											}
										}
										?></p>
						<h2 class="h2std"><a href="<?php echo get_the_permalink($cItem); ?>"><?php echo get_the_title($cItem); ?></a></h2>
						<?php if (get_field("display_subtitle", $cItem)) { ?><p class="h2"><?php the_field("display_subtitle", $cItem); ?></p><?php } ?>
						<?php if (get_field("display_tertiary", $cItem)) { ?><span class="blok h2"><?php the_field("display_tertiary", $cItem); ?></span><?php } ?>

					</div>
			<?php
				}
			} ?>
		</div>
	</section>

<?php
} else if ($cLoop['acf_fc_layout'] === 'callout_paginated') {
	$callouts = array();
	$appendList = array();
	if ($cLoop['callout_items'] && !empty($cLoop['callout_items'])) {
		foreach ($cLoop['callout_items'] as $cItem) {
			$cItem->addClass = '';
			$outTF = false;
			if (isset($cLoop['past_event_action'])) {
				if ($cItem->post_type === 'events' && strtotime(get_field("date_end", $cItem)) < strtotime('today')) {
					if ($cLoop['past_event_action'] === 'remove') {
						// echo $cLoop['past_event_action'];
						$outTF = true;
					} else {
						$cItem->addClass = 'past-event';
						$outTF = true;
						$appendList[] = $cItem;
					}
				}
			}

			if (!$outTF) {
				$callouts[] = $cItem;
			}
		}

		if (!empty($appendList)) {
			foreach ($appendList as $appendItem) {
				$callouts[] = $appendItem;
			}
		}
	}
?>
	<section class="cycle-row section-<?php echo $cLoop['acf_fc_layout'] . $rowClass; ?>">
		<div class="container-fluid">
			<div class="cycle-header flex rule-above">
				<h2><?php echo $cLoop['header']; ?></h2>
				<?php if (count($callouts) > 3) { ?>
					<div class="cycle-counts">
						<span class="cycle-curr h2">1</span><span class="cycle-sep h2">/</span><span class="cycle-total h2"><?php echo count($callouts) ?></span>
						<button class="cycle-next" aria-label="Next page of results"><span></span></button>
					</div>
				<?php } ?>
			</div>
		</div>
		<div class="cycle-wrap">
			<div class="keen-slider">
				<?php if ($callouts && !empty($callouts)) {
					foreach ($callouts as $cItem) {
						$imgOut = '';
						$pType = get_post_type($cItem);
						if (has_post_thumbnail($cItem->ID)) {
							if ($cLoop['images_tf']) {
								$imgOut = wp_get_attachment_image(get_post_thumbnail_id($cItem->ID), 'large', false);
							}
						}
						// if($cItem['img_override'] && !empty($cItem['img_override'])){
						// 	$imgOut = wp_get_attachment_image ( $cItem['img_override'], 'large', false );	
						// }

				?>
						<div class="keen-slider__slide">
							<div class="card-row-single hovParent flow<?php if (isset($cItem->addClass)) {
																			echo ' ' . $cItem->addClass;
																		} ?>">
								<?php if ($imgOut !== '') {
									echo '<div class="card-img"><a href="' . get_the_permalink($cItem) . '">' . $imgOut . '</a></div>';
								} ?>
								<p class="h2"><?php
												if ($pType === 'post') {
													echo get_the_date('F j, Y', $cItem->ID);
												} else {
													echo dateOut(get_field("date_start", $cItem), get_field("date_end", $cItem));
												}
												?></p>
								<h2 class="h2std hovlink"><a href="<?php echo get_the_permalink($cItem); ?>"><?php echo get_the_title($cItem); ?></a></h2>
								<?php if (get_field("display_subtitle", $cItem)) { ?><p class="h2"><?php the_field("display_subtitle", $cItem); ?></p><?php } ?>
								<?php if (get_field("display_tertiary", $cItem)) { ?><span class="blok h2"><?php the_field("display_tertiary", $cItem); ?></span><?php } ?>
								<?php  // flag add location? 
								?>
								<?php if (isset($pType) && $pType === 'post') {
									$categories = get_the_category($cItem);
									if (!empty($categories)) {
										echo '<span class="blok h2 subdue-bg">' . esc_html($categories[0]->name) . '</span>';
									}
								} ?>
							</div>
						</div>
				<?php
					}
				} ?>
			</div>
		</div>
	</section>

	<?php
} else if ($cLoop['acf_fc_layout'] === 'feature') {
	$featureType = $cLoop['ptype'];
	$subClass = '';
	if ($featureType === 'page') {
		$targetPage = $cLoop['target_page_page'];
	} else if ($featureType === 'event') {
		$subClass = 'h2';
		$targetPage = $cLoop['target_page_event'];
	} else if ($featureType === 'exhibition') {
		$subClass = 'h2';
		$targetPage = $cLoop['target_page'];
	} else if ($featureType === 'program') {
		$targetPage = $cLoop['target_page_program'];
	} else if ($featureType === 'programind') {
		$targetPage = $cLoop['target_page_page'];
	} else {
		$targetPage = $cLoop['target_page'];
	}
	if ($targetPage) {
		$pType = get_post_type($targetPage);
		$imgLayout = $cLoop['img_layout'];
		if (isset($cLoop['img_w'])) {
			$imgW = 'imgw-' . $cLoop['img_w'];
		}
		$inlineStyle = '';

		$titleOut = get_the_title($targetPage);
		$imgOut = '';
		if (has_post_thumbnail($targetPage)) {
			$imgOut = wp_get_attachment_image(get_post_thumbnail_id($targetPage), 'large', false);
		}
		if ($cLoop['img_override'] && !empty($cLoop['img_override'])) {
			$imgOut = wp_get_attachment_image($cLoop['img_override'], 'large', false);
		}

		$preTitleOut = '';
		if (get_post_type($targetPage) === 'exhibitions') {
			$locTaxes = get_the_terms($targetPage, 'location');
			if (!empty($locTaxes)) {
				foreach ($locTaxes as $locTax) {
					$preTitleOut = __($locTax->name);
				}
			}
		} else if (get_post_type($targetPage) === 'events') {
			$locTaxes = get_the_terms($targetPage, 'event_location');
			if (!empty($locTaxes)) {
				foreach ($locTaxes as $locTax) {
					$preTitleOut = __($locTax->name);
				}
			}
		}
		$subtitleOut = get_field("display_subtitle", $targetPage);
		$terttitleOut = get_field("display_tertiary", $targetPage);

		if ($pType === 'programs') {
			$subtitleOut = get_field("pretitle", $targetPage);
			$pretitle = '';
			$pTerms = get_the_terms($targetPage, 'program');
			if (!empty($pTerms)) {
				foreach ($pTerms as $pKey => $pTerm) {
					$term_link = get_term_link($pTerm, array('program'));
					if ($pKey > 0) {
						$pretitle .= ', ';
					}
					$pretitle .= '' . $pTerm->name . '';
				}
			}
			if (get_field("pretitle")) {
				$pretitle .= ' / ' . get_field("pretitle");
			}
			$preTitleOut = $pretitle;
		}

		if (has_excerpt($targetPage)) {
			$terttitleOut = get_the_excerpt($targetPage);
		}

		$linkOut = get_permalink($targetPage);


		if ($featureType === 'program') {
			$termLoad = get_term($targetPage, 'program');
			$titleOut = $termLoad->name;
			$terttitleOut = $termLoad->description;
			$linkOut = get_term_link($targetPage);
			$tertClass = '';
			$tempImg = get_field("featured_image", 'program_' . $targetPage);
			if ($tempImg) {
				$imgOut = wp_get_attachment_image($tempImg, 'large', false);
			}
			// print_r($termLoad);
		}


		if ($cLoop['pre_override'] && !empty($cLoop['pre_override'])) {
			$preTitleOut = $cLoop['pre_override'];
		}
		if ($cLoop['title_override'] && !empty($cLoop['title_override'])) {
			$titleOut = $cLoop['title_override'];
		}
		// if($cLoop['sub_override'] && !empty($cLoop['sub_override'])){
		// 	$subtitleOut = $cLoop['sub_override'];
		// }

		if ($featureType === 'page' && $cLoop['excerpt_override'] && !empty($cLoop['excerpt_override'])) {

			$terttitleOut = $cLoop['excerpt_override'];
		}



		// flag this was incorrectly removing excerpt from big features
		// if($featureType === 'programind'){
		// 	$terttitleOut = '';
		// 	$subtitleOut = '';
		// }


		if ($pType === 'events') { ?>
			<section class="container-fluid section-<?php echo $cLoop['acf_fc_layout']; ?><?php echo $rowClass; ?>" <?php
																													if ($cLoop['color_override']) {
																														echo ' style="--colorHighlight:' . $cLoop['color_override'] . '"';
																													} else if (get_field("pri_color", $targetPage)) {
																														echo ' style="--colorHighlight:' . get_field("pri_color", $targetPage) . ';"';
																													} ?>>
				<div class="callout-row hovParent std-event layout-<?php echo $imgLayout; ?> <?php echo $imgW; ?> bg-highlight">
					<div class="img-container iv hovTarget">
						<div class="feat-img-wrap">
							<div class="img-size sq"></div>
							<a href="<?php echo $linkOut; ?>" aria-label="<?php echo $titleOut; ?>" tabindex="-1"><?php
																													echo $imgOut;
																													?></a>
						</div>
					</div>
					<div class="txt-container<?php
												if ($cLoop['invert_tf']) {
													echo ' ui-invert';
												}
												?>">
						<div class="callout-text flow">
							<?php
							$locTaxes = get_the_terms($targetPage, 'event_location');
							if (!empty($locTaxes)) {
								foreach ($locTaxes as $locTax) {
									echo '<span class="blok h2">' . __($locTax->name) . '</span>';
								}
							}
							?>
							<h1 class="h1"><a href="<?php echo $linkOut; ?>"><?php
																				echo $titleOut;
																				?></a></h1>

							<?php
							$dateOut = '';
							$start = get_field("date_start", $targetPage);
							$end = get_field("date_end", $targetPage);
							if ($start && $end) {
								$dateOut = date('F j, Y', strtotime($start));
							}
							if ($dateOut !== '') { ?><span class="blok h2"><?php echo $dateOut; ?></span><?php }
																											?>

						</div>
					</div>
				</div>
			</section>
		<?php } else if ($pType === 'page' || $pType === 'programs' || $pType === 'programind') { ?>
			<section class="container-fluid section-<?php echo $cLoop['acf_fc_layout']; ?><?php echo $rowClass; ?>" <?php
																													if ($cLoop['color_override']) {
																														echo ' style="--colorHighlight:' . $cLoop['color_override'] . '"';
																													} else if (get_field("pri_color", $targetPage)) {
																														echo ' style="--colorHighlight:' . get_field("pri_color", $targetPage) . ';"';
																													} ?>>
				<div class="callout-row hovParent std layout-<?php echo $imgLayout; ?> <?php echo $imgW; ?> bg-highlight">
					<div class="img-container iv hovTarget">
						<div class="img-size sq"></div>
						<a href="<?php echo $linkOut; ?>" aria-label="<?php echo $titleOut; ?>" tabindex="-1"><?php
																												echo $imgOut;
																												?></a>
					</div>
					<div class="txt-container<?php
												if ($cLoop['invert_tf']) {
													echo ' ui-invert';
												}
												?>">
						<div class="callout-text flow">
							<?php
							if ($preTitleOut && $preTitleOut !== '') {
								echo '<span class="blok h2">' . $preTitleOut . '</span>';
							}
							?>
							<h2 class="cap h2std"><a href="<?php echo $linkOut; ?>"><?php
																					echo $titleOut;
																					?></a></h2>
							<?php if ($subtitleOut && $subtitleOut !== '' && ($pType !== 'programs')) { ?><p class="<?php echo $subClass; ?>"><?php echo $subtitleOut; ?></p><?php } ?>
							<?php if ($terttitleOut && $terttitleOut !== '') { ?><p class="<?php echo $subClass; ?>"><?php echo $terttitleOut; ?></p><?php } ?>
							<?php if (get_post_type($targetPage) === 'exhibitions') { ?><p class="<?php echo $subClass; ?>"><?php echo dateOut(get_field("date_start", $targetPage), get_field("date_end", $targetPage)); ?></p><?php } ?>
						</div>
					</div>
				</div>
			</section>
		<?php } else {
		?>
			<section class="container-fluid section-<?php echo $cLoop['acf_fc_layout']; ?><?php echo $rowClass; ?>" <?php
																													if ($cLoop['color_override']) {
																														echo ' style="--colorHighlight:' . $cLoop['color_override'] . '"';
																													} else if (get_field("pri_color", $targetPage)) {
																														echo ' style="--colorHighlight:' . get_field("pri_color", $targetPage) . ';"';
																													} ?>>
				<div class="callout-row hovParent std layout-<?php echo $imgLayout; ?> <?php echo $imgW; ?> bg-highlight">
					<div class="img-container iv hovTarget">
						<div class="img-size sq"></div>
						<a href="<?php echo $linkOut; ?>" aria-label="<?php echo $titleOut; ?>" tabindex="-1"><?php
																												echo $imgOut;
																												?></a>
					</div>
					<div class="txt-container<?php
												if ($cLoop['invert_tf']) {
													echo ' ui-invert';
												}
												?>">
						<div class="callout-text flow">
							<?php
							if ($preTitleOut && $preTitleOut !== '') {
								echo '<span class="blok h2">' . $preTitleOut . '</span>';
							}
							$titleClass = getTitleClass($titleOut);
							?>
							<h2 class="h1 cap<?php echo $titleClass; ?>"><a href="<?php echo $linkOut; ?>"><?php
																											echo $titleOut;
																											?></a></h2>
							<?php if ($subtitleOut && $subtitleOut !== '') { ?><p class="h2"><?php echo $subtitleOut; ?></p><?php } ?>
							<?php if ($terttitleOut && $terttitleOut !== '') { ?><p class="<?php echo $subClass; ?>"><?php echo $terttitleOut; ?></p><?php } ?>
							<?php if (get_post_type($targetPage) === 'exhibitions') { ?><p class="h2"><?php echo dateOut(get_field("date_start", $targetPage), get_field("date_end", $targetPage)); ?></p><?php } ?>
						</div>
					</div>
				</div>
			</section>
		<?php
		}
	}
} else if ($cLoop['acf_fc_layout'] === 'announcement_text') {
	echo '
	<section class="container-fluid flow section-' . $cLoop['acf_fc_layout'] . $rowClass . '">
		<div class="text-col">
			<div class="announcement_wrap wyg">' . $cLoop['text'] . '</div>
		</div>
	</section>';
} else if ($cLoop['acf_fc_layout'] === 'video') {
	$coverImg = $cLoop['cover_image'];
	$ar = '';
	if (isset($cLoop['video_aspect_ratio'])) {
		$ar = $cLoop['video_aspect_ratio'];
	}
	if (!empty($coverImg)) {
		echo '<div class="flex-content-area flex-video has-cover ' . $ar . '">';
		$coverImg = wp_get_attachment_image($coverImg['ID'], 'large', "", array("class" => "vid-poster"));
		$video = $cLoop['video'];
		if (preg_match('/src="(.+?)"/', $video, $matches)) {
			$src = $matches[1];
			$params = array(
				'controls'    => 1,
				'hd'        => 1,
				'autoplay' => 1
			);
			$new_src = add_query_arg($params, $src);
			$video = str_replace($src, $new_src, $video);
			$attributes = 'frameborder="0"';
			$video = str_replace('></iframe>', ' ' . $attributes . '></iframe>', $video);
			$video = str_replace(' src=', ' data-src=', $video);
			echo '<div class="vid-wrap">' . $video . $coverImg . '</div>';
		}
	} else {
		echo '<div class="flex-content-area flex-video ' . $ar . '">';
		echo '<div class="vid-wrap">';
		echo $cLoop['video'];
		echo '</div>';
	}


	if ($cLoop['caption'] && strlen($cLoop['caption']) > 5) {
		echo '<div class="flex-vid-caption caption">' . $cLoop['caption'] . '</div>';
	}
	echo '</div>';
} else if ($cLoop['acf_fc_layout'] === 'cards') {
	if ($cLoop['cards'] && !empty($cLoop['cards'])) {
		echo '<section data-cnt="' . count($cLoop['cards']) . '" class="spc-std layout-cards card-layout-' . $cLoop['card_layout'] . ' card-size-' . $cLoop['card_size'] . '">';

		if ($cLoop['card_layout'] === 'paged') { ?>
			<div class="container-fluid">
				<div class="cycle-header flex rule-above">
					<?php if (isset($cLoop['header']) && $cLoop['header'] !== '') { ?><h2><?php echo $cLoop['header']; ?></h2><?php } ?>
					<?php if (count($cLoop['cards']) > 3) { ?>
						<div class="cycle-counts">
							<span class="cycle-curr h2">1</span><span class="cycle-sep h2">/</span><span class="cycle-total h2"><?php echo count($cLoop['cards']) ?></span>
							<div class="cycle-navigation">
								<button class="cycle-previous" aria-label="Previous page of results"><span></span></button>
								<button class="cycle-next" aria-label="Next page of results"><span></span></button>
							</div>
						</div>
					<?php } ?>
				</div>
			</div>
			<?php
		}

		echo '<div class="card-wrap">';
		foreach ($cLoop['cards'] as $cardSingle) {
			if ($cardSingle && !empty($cardSingle)) { ?>
				<div class="flex-col card-col padTop1 flow">
					<?php if (!empty($cardSingle['image'])) { ?>
						<div class="img-container iv hovTarget">
							<?php echo wp_get_attachment_image($cardSingle['image'], 'large'); ?>
						</div>
					<?php } ?>
					<div class="txt-wrap flow">
						<h4 class="card-title"><?php echo $cardSingle['header']; ?></h4>
						<div class="serif<?php if (count($cLoop['cards']) > 1) {
												echo ' small';
											} ?> below-head"><?php echo $cardSingle['card_text']; ?></div>
					</div>
				</div>

<?php }
		}
	}
	echo '</div>
	</section>';
} else {
}

?>


<?php include(get_template_directory() . "/flex/_flex-footer.php"); ?>