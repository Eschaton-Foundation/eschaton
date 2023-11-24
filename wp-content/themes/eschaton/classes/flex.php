<?php
/*
	Usage:
	--------------------------------------------------------------------------------
	<?= FLEX()->flexRows($post->ID); ?>

	// CUSTOM CLASSES
	<?= FLEX()->classes('add-base-class')->classes('another-base-class')->flexRows($post->ID); ?>

	// CUSTOM CLASSES (INNER)
	<?= FLEX()->classesInner('r-width')->flexRows($post->ID); ?>

	<?= FLEX()->baseWrap('div')->flexRows($post->ID); ?>
	<?= FLEX()->innerWrap('article')->flexRows($post->ID); ?>
	<?= FLEX()->baseWrap(null)->flexRows($post->ID); ?>
	<?= FLEX()->innerWrap(null)->flexRows($post->ID); ?>
	
	
*/


function FLEX() {
	return new flexRowsObject();
}    

class flexRowsObject {
	private $_attr;
	private $_classes;
	private $_finalOutput;
	private $_display;

	public function __construct() {
		$this->_reset();
	}

	private function _reset() {
		// $this->_baseWrap		= null;
		// $this->_innerWrap	= null;
		$this->_baseWrap		= 'section';
		$this->_innerWrap		= 'article';
		$this->_classesInner	= array();

		$this->_classes			= array();
		$this->_finalOutput		= '';
		$this->_isTax			= false;
		// $this->_shownAnchors	= false;
		// $this->_pageAnchors		= '';
		$this->_display			= '';
	}

	public function classes($manualClasses=false) {
	    if ($manualClasses) $this->_classes[] = $manualClasses;
	    return $this;
	}

	public function classesInner($manualClasses=false) {
		if ($manualClasses) $this->_classesInner[] = $manualClasses;
		return $this;
	}

	public function baseWrap($manualWrap=null) {
		$this->_baseWrap = $manualWrap;
		return $this;
	}

	public function innerWrap($manualWrap=null) {
		$this->_innerWrap = $manualWrap;
		return $this;
	}

	public function isTax($specifiedTax=false) {
		if ($specifiedTax) $this->_isTax = $specifiedTax;
		return $this;
	}

	public function setDisplay($newDisplay=false) {
		if ($newDisplay) $this->_display = $newDisplay;
		return $this;
	}

	public function flexRows($pID = null){
		$finalString = '';
		$wrapStyle = '';
		$sidepad = 0;
		$modalContents = '';
		if($pID !== null){
		ob_start(); 


		if($this->_isTax){
			$flexContents = get_field("page_contents",$this->_isTax . '_' . $pID);
		} else {
			$flexContents = get_field("page_contents",$pID);
		}
		if($flexContents && !empty($flexContents)){
			foreach($flexContents as $flexKey => $cLoop){
				$scopedWrap = $this->_baseWrap;
				$scopedWrapInner = $this->_innerWrap;
				$scopeClass = $this->_classes;
				$scopeClassInner = $this->_classesInner;

				if(file_exists(get_template_directory() . "/flex/" . $cLoop['acf_fc_layout'] . ".php")){
					include(get_template_directory() . "/flex/" . $cLoop['acf_fc_layout'] . ".php");
				} else {
					include(get_template_directory() . "/flex/_unsorted.php");
				}
			}
		}

		$finalString = ob_get_contents();
		ob_end_clean();
		}
		$this->_finalOutput = $finalString;
		return $this->output();
	}

	public function output(){
		$finalString = $this->_finalOutput;

		// Reset all the request settings.
		$this->_reset();
		return $finalString;
	}
}