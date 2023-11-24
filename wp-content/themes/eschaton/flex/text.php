<?php
$scopeClassInner[] = 'wyg';
// $scopeClassInner[] = $cLoop['display'];

include(get_template_directory() . "/flex/_flex-header.php"); ?>

<?php echo splitMore($cLoop['text']); ?>

<?php include(get_template_directory() . "/flex/_flex-footer.php"); ?>