<?php 
$scopedWrapInner = null;

include(get_template_directory() . "/flex/_flex-header.php"); ?>
<?php echo '<a name="' . sanitize_title($cLoop['header']) . '" class="page-anchor">'.$cLoop['header'].'</a>'; ?>

<h2><?php echo $cLoop['header']; ?></h2>

<?php include(get_template_directory() . "/flex/_flex-footer.php"); ?>