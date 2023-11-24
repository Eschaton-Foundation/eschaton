<?php
$scopedWrapInner = null;

$scopeClass[] = 'bleed';
// $scopeClassInner[] = $cLoop['display'];

include(get_template_directory() . "/flex/_flex-header.php"); ?>

<article class="col col-text wyg">
	<?php echo splitMore($cLoop['text_col_1']); ?>
</article>
<article class="col col-text wyg">
	<?php echo splitMore($cLoop['text_col_2']); ?>
</article>

<?php include(get_template_directory() . "/flex/_flex-footer.php"); ?>