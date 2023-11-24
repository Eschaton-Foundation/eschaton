<?php
/*
	Usage:
	--------------------------------------------------------------------------------
	<?= MARKUP()->HTML('main'); ?>

*/


function MARKUP() {
	return new htmlMarkupGen();
}    

class htmlMarkupGen {
	private $_attr;
	private $_classes;
	private $_finalOutput;

	public function __construct() {
		$this->_reset();
	}

	private function _reset() {
		$this->_classes     = array();
		$this->_finalOutput = '';
	}

	public function classes($manualClasses=false) {
	    if ($manualClasses) $this->_classes[] = $manualClasses;
	    return $this;
	}

	public function HTML($elType = 'div',$closingTag = false){
		$finalString = '';

		if(!$closingTag){
			$finalString .= '<' . $elType . ' class="' . implode(' ', $this->_classes) . '">';
		} else {
			$finalString .= '</' . $elType . '>';
		}
		
		$this->_finalOutput = $finalString;
		return $this->output();
	}

	public function MAIN($closingTag = false){
		$finalString = '';

		if(!$closingTag){
			$classAdd = '';
			if(!empty($this->_classes)){
				$classAdd = implode(' ', $this->_classes);
			}
			$finalString .= '<main class="' . $classAdd . '" id="content" role="main" tabindex="-1">';
		} else {
			$finalString .= '</main>';
		}
		
		$this->_finalOutput = $finalString;
		return $this->output();
	}

	public function MODAL($closingTag = false){
		$finalString = '';

		if(!$closingTag){
			$classAdd = '';
			if(!empty($this->_classes)){
				$classAdd = implode(' ', $this->_classes);
			}
			$finalString .= '<div class="page-modal-wrap ' . $classAdd . '" id="content_modal">';
		} else {
			$finalString .= '</div>';
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