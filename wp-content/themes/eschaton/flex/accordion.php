<?php
if (empty($cLoop['accordion_items'])) {
	return;
}
$scopedWrapInner = null;

include(get_template_directory() . "/flex/_flex-header.php");
?>

<?php if ($cLoop['accordion_items']) {
	foreach ($cLoop['accordion_items'] as $aKey => $aItem) { ?>
		<details class="accordion-single<?php if ($aKey === 0 && isset($cLoop['acc_open']) && $cLoop['acc_open'] !== 'none') {
											echo ' open';
										} else if ($aKey > 0 && isset($cLoop['acc_open']) && $cLoop['acc_open'] === 'all') {
											echo ' open';
										} ?>">
			<summary class="accordion-toggle">
				<?php echo $aItem['toggle_label']; ?>
				<svg data-name="Isolation Mode" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12.35 6.53">
					<path fill="none" stroke="#99998c" stroke-miterlimit="10" stroke-width="1" d="m12.18.18-6 6-6-6" />
				</svg>
			</summary>
			<div class="accordion-expand">
				<div class="text-col">
					<div class="wyg flow">
						<?php echo $aItem['text']; ?>
					</div>
				</div>
			</div>
		</details>
<?php }
} ?>

<?php
include(get_template_directory() . "/flex/_flex-footer.php"); ?>