<?php
/*
	Usage:
	--------------------------------------------------------------------------------
	<?= IMAGE()->classes('my-class')->img($post->ID); ?>

*/


function IMAGE() {
	return new imgObject();
}    

class imgObject {
	private $_attr;
	private $_classes;
	private $_finalOutput;

	public function __construct() {
		$this->_reset();
	}

	private function _reset() {
		$this->_classes     = array();
		$this->_wrap        = false;
		$this->_finalOutput = '';
	}

	public function classes($manualClasses=false) {
	    if ($manualClasses) $this->_classes[] = $manualClasses;
	    return $this;
	}

	public function img($img,$size = 'large',$captionTF = true,$padTF = false,$link = null,$linkClass = '',$captionOut = ''){
		$finalString = '';
		$wrapStyle = '';
		$sidepad = 0;

		$linkOutput = array('','');
		if($link !== null){
			$linkOutput = array('<a href="' . $link . '" class="' . $linkClass . '" title="' . altFromImg($img) . '">','</a>');
		}

		$finalCaption = '';
		if($captionTF !== false){
			$finalCaption = $captionOut;
			if($finalCaption === ''){
				if(!empty($img) && isset($img['height'])){
					$finalCaption = getImgCaption($img['ID']);
				} else if(is_numeric($img)) {
					$finalCaption = getImgCaption($img);
				}
			}
		}

		if(!empty($img) && isset($img['height'])){
			$wrapStyle .= 'max-width:' . $img['width'] . 'px;';
			if(($img['height'] / $img['width'] > 1) && $padTF === true){
				$sidepad = ( ($img['height'] / $img['width']) - 1) * 25;
				$wrapStyle .= ' padding:0 ' . $sidepad . '%;';
			}
			$srcset = wp_get_attachment_image_srcset( $img['ID'], 'medium' );
			$finalString .= '
			<div class="img-wrap" style="' . $wrapStyle . '">
				<div class="img-pad" style="padding-bottom:' . ($img['height'] / $img['width'])*100 . '%">
					'.$linkOutput[0].'
					<img src="' . $img['sizes'][$size] . '" srcset="'.$srcset.'" width="' . $img['sizes'][$size.'-width'] . '" height="' . $img['sizes'][$size.'-height'] . '" border="0" alt="' . altFromImg($img) . '" />
					'.$linkOutput[1].'
				</div>
				' . $finalCaption . '
			</div>';
		} else if(!empty($img) && isset($img[0]) && !is_numeric($img)){
			$wrapStyle .= 'max-width:' . $img['1'] . 'px;';
			if(($img['2'] / $img['1'] > 1) && $padTF === true){
				$sidepad = ( ($img['2'] / $img['1']) - 1) * 25;
				$wrapStyle .= ' padding:0 ' . $sidepad . '%;';
			}
			$finalString .= '
			<div class="img-wrap" style="' . $wrapStyle . '">
				<div class="img-pad" style="padding-bottom:' . ($img['2'] / $img['1'])*100 . '%">
					'.$linkOutput[0].'
					<img src="' . $img[0] . '" border="0" alt="' . altFromImg($img) . '" />
					'.$linkOutput[1].'
				</div>
				'.$finalCaption.'
			</div>';
		} else if(is_numeric($img)) {
			$loadImg = wp_get_attachment_image_src( $img, 'large');
			$wrapStyle .= 'max-width:' . $loadImg['1'] . 'px;';
			if(($loadImg['2'] / $loadImg['1'] > 1) && $padTF === true){
				$sidepad = ( ($loadImg['2'] / $loadImg['1']) - 1) * 25;
				$wrapStyle .= ' padding:0 ' . $sidepad . '%;';
			}
			$finalString .= '
			<div class="img-wrap" style="' . $wrapStyle . '">
				<div class="img-pad" style="padding-bottom:' . ($loadImg['2'] / $loadImg['1'])*100 . '%">
					'.$linkOutput[0].'
					<img src="' . $loadImg[0] . '" border="0" alt="' . altFromImg($loadImg) . '" />
					'.$linkOutput[1].'
				</div>
				'.$finalCaption.'
			</div>';
		}
		
		$this->_finalOutput = $finalString;
		return $this->output();
	}

	public function output(){
		$finalString = $this->_finalOutput;
		if($this->_wrap){
			$finalString = '<div class="wrap">' . $finalString . '</div>';
		}

		// Reset all the request settings.
		$this->_reset();
		return $finalString;
	}
	function processImg($imgID,$size='large',$captionTF = true){
		$outputImg = wp_get_attachment_image($imgID,$size);
		$finalCaption = '';
		if($captionTF !== false){
			if($finalCaption === ''){
				if(!empty($outputImg) && isset($outputImg[2])){
					$finalCaption = getImgCaption($imgID);
				}
			}
		}

		return '<div class="img-content">'.$outputImg.$finalCaption.'</div>';
	}
	
}