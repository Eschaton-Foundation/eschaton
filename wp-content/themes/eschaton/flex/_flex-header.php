<?php 
// START OF BASE WRAP
if($scopedWrap){
	$scopedClass = trim($cLoop['acf_fc_layout'].' '.implode(' ', $scopeClass));
	echo '<' . $scopedWrap . ' class="section-'. $scopedClass . '">';
}
if($scopedWrapInner){
	echo '<' . $scopedWrapInner . ' class="'.implode(' ', $scopeClassInner) . '">';
}
// END OF BASE WRAP