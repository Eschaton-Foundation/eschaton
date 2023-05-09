</main>
<div class="modal">
	<div class="modal-bg"></div>
	<button class="modal-close" aria-label="Close modal">
		<span>Close modal</span>
	</button>
	<div class="modal-content-scroll" id="zoomModal">

	</div>
</div>

<?php if( false && !is_page('privacy-policy')){ ?>
	<span class="consent-bg"></span>
	<div class="consent-wrap mid">
		<?php echo get_field("consent_text", "options"); ?>
		<button class="consent-optin-vis"><span><?php echo get_field("opt-in_text", "options"); ?></span></button>
	</div>
<?php } ?>
<footer class="footer">
	<?php
	wp_nav_menu(array(
		'menu'	=>	'Footer',
		'items_wrap'	=>	'<ul>%3$s</ul>',
		'container' => null
	));
	?>
</footer>
<?php wp_footer(); ?>
<?php
if (isset($_GET['trp-edit-translation']) && $_GET['trp-edit-translation'] == true) {
	echo '<script type="module">
		import { Editor } from \'https://cdn.skypack.dev/@tiptap/core?min\'
		import StarterKit from \'https://cdn.skypack.dev/@tiptap/starter-kit?min\'
		//const editor = new Editor({
		//element: document.querySelector(\'.trp-translation-input\'),
		//extensions: [
		//	StarterKit,
		//],
		//content: \'<p>Hello World!</p>\',
		//})
	</script>';
}


if( get_locale() == 'fr_FR' ) { ?>
	<script>
		tarteaucitronForceLanguage = 'fr'; /* supported: fr, en, de, es, it, pt, pl, ru */
	</script>
<?php }
elseif( get_locale() == 'de_DE'  ) {?>
	<script>
		tarteaucitronForceLanguage = 'de'; /* supported: fr, en, de, es, it, pt, pl, ru */
	</script>
<?php } ?>


</body>

</html>