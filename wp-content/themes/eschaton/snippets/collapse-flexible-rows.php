<?php
// ---------------------------------------------------------------------------
// ACF : Flexible Content : Set a default title for when collapsed.
// https://www.advancedcustomfields.com/resources/acf-fields-flexible_content-layout_title/
function trimAdminDisplay($passedString)
{
	if (!is_array($passedString) && strlen($passedString) > 50) {
		return substr($passedString, 0, 50) . '&hellip;';
	} else {
		return $passedString;
	}
}

add_filter('acf/fields/flexible_content/layout_title', function ($title, $field, $layout, $i) {

	// Remove name of flexible content block.
	// $title = '';

	$sub_title = '';
	$imgPreview = '';
	if ($title === "Text") {
		$contents = strip_tags(get_sub_field("text"));
		if ($contents) {
			$sub_title .= trimAdminDisplay($contents);
		}
	} else if ($title === "Header") {
		$contents = strip_tags(get_sub_field("header"));
		if ($contents) {
			$sub_title .= trimAdminDisplay($contents);
		}
	} else if ($title === "Image") {
		$contentOptions = get_sub_field("row_options");
		$contents = get_sub_field("image");
		// error_log(print_r($contents,true));
		if (isset($contentOptions['headline']) && strlen($contentOptions['headline']) > 3) {
			$sub_title .= $contentOptions['headline'] . ' // ';
		}
		if ($contents && !is_array($contents)) {
			$imgOut = wp_get_attachment_image_src(trimAdminDisplay($contents), 'thumbnail');
			$imgPreview .= '<img src="' . $imgOut[0] . '" height="20" />';
		}
		// if(isset($contents) && !empty($contents)){
		// 	$imgPreview .= '<img src="' . $contents['sizes']['thumbnail'] . '" height="20" />';
		// }
	} else if ($title === "Image(s)") {
		$contents = get_sub_field("images");
		if ($contents && isset($contents) && !empty($contents)) {
			foreach ($contents as $imgSingle) {
				$imgOut = wp_get_attachment_image_src(trimAdminDisplay($imgSingle), 'thumbnail');
				$imgPreview .= '<img src="' . $imgOut[0] . '" height="20" />';
			}
		}
	} else if ($title === "Audio") {
		$contents = get_sub_field("audio");
		if ($contents && !empty($contents)) {
			$tempAdd = '';
			foreach ($contents as $audioSingle) {
				if ($tempAdd !== '') {
					$tempAdd .= ', ';
				}
				$tempAdd .= get_the_title($audioSingle);
			}
			$sub_title .= $tempAdd;
		}
	} else if (($title === "Callout row (paginated)") || ($title === 'Callout row (single page)') || ($title === 'Callouts (grid)')) {
		$contents = get_sub_field("callout_items");
		if ($contents && !empty($contents)) {
			$tempAdd = '';
			foreach ($contents as $itemSingle) {
				if ($tempAdd !== '') {
					$tempAdd .= ', ';
				}
				$tempAdd .= get_the_title($itemSingle);
			}
			$sub_title .= $tempAdd;
		}
	} else if ($title === "Feature") {
		$featureType = get_sub_field('ptype');
		$subClass = '';
		if ($featureType === 'page') {
			$tgt = get_sub_field('target_page_page');
		} else if ($featureType === 'event') {
			$subClass = 'h2';
			$tgt = get_sub_field('target_page_event');
		} else if ($featureType === 'program') {
			$tgt = get_sub_field('target_page_program');
		} else if ($featureType === 'programind') {
			$tgt = get_sub_field('target_page_page');
		} else {
			$tgt = get_sub_field('target_page');
		}
		$contents = strip_tags(get_the_title($tgt));
		if ($featureType === 'program') {
			$termLoad = get_term($tgt, 'program');
			$contents = $termLoad->name;
		}
		if ($contents) {
			$sub_title .= trimAdminDisplay($contents);
		}
	} else if ($title === "Announcement (text)") {
		$contents = strip_tags(get_sub_field("text"));
		if ($contents) {
			$sub_title .= trimAdminDisplay($contents);
		}
	} else if ($title === "Headline") {
		$contents = strip_tags(get_sub_field("headline"));
		if ($contents) {
			$sub_title .= trimAdminDisplay($contents);
		}
	} else if ($title === "Columned Text") {
		$cols = get_sub_field("column");
		$tempOut = '';
		if (!empty($cols)) {
			foreach ($cols as $colSingle) {
				if (isset($colSingle['text']) && $colSingle['text']) {
					$tempOut .= trimAdminDisplay(strip_tags($colSingle['text']));
				}
			}
		}
		$contents = $tempOut;
		if ($contents) {
			$sub_title .= $contents;
		}
	}

	if ($imgPreview !== '') {
		// $title .= '<span style="vertical-align: top; display: inline-block; margin-left: 6px;">—</span>';
		$title .= '<span style="vertical-align: top; display: inline-block; margin-left: 6px;"> ' . $imgPreview . '</span>';
	}
	if ($sub_title) {
		// $title .= '<span style="vertical-align: top; display: inline-block; margin-left: 6px;">—</span>';
		$title .= '<span style="vertical-align: top; opacity: 0.5; display: inline; margin-left: 6px; overflow: hidden;font-size:14px;">' . strip_tags($sub_title) . '</span>';
	}

	return $title;
}, 10, 4);



// ---------------------------------------------------------------------------
// ACF : Flexible Content : Always default to the collapsed state.
if (is_admin()) {
	add_action('acf/input/admin_head', function () {
?>
		<script type="text/javascript">
			jQuery(function($) {

				// Collapse any flexible content areas.
				$('.acf-flexible-content .layout').each(function() {
					if ($(this).parents('.clones').length == 0) {
						$(this).addClass('-collapsed');
					}
				});
				$('.acf-flexible-content .layout')
				// $('#acf-flexible-content-collapse').detach();
				// Make sure all the accordians are closed when the page loads.
				$('.acf-accordion').removeClass('-open');
				$('.acf-accordion-content').attr('style', 'display: none;');

			});
		</script>
<?php
	});
}
