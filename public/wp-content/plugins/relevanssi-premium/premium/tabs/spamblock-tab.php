<?php
/**
 * /premium/tabs/spamblock-tab.php
 *
 * Prints out the Premium Spam Block tab in Relevanssi settings.
 *
 * @package Relevanssi_Premium
 * @author  Mikko Saari
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 * @see     https://www.relevanssi.com/
 */

/**
 * Prints out the Premium Spam Block tab in Relevanssi settings.
 */
function relevanssi_spamblock_tab() {
	$spamblock = get_option( 'relevanssi_spamblock' );
	if ( ! isset( $spamblock['keywords'] ) ) {
		$spamblock['keywords'] = '';
	}
	if ( ! isset( $spamblock['regex'] ) ) {
		$spamblock['regex'] = '';
	}
	if ( ! isset( $spamblock['chinese'] ) ) {
		$spamblock['chinese'] = '';
	}
	if ( ! isset( $spamblock['cyrillic'] ) ) {
		$spamblock['cyrillic'] = '';
	}
	if ( ! isset( $spamblock['emoji'] ) ) {
		$spamblock['emoji'] = '';
	}
	$chinese  = relevanssi_check( $spamblock['chinese'] );
	$cyrillic = relevanssi_check( $spamblock['cyrillic'] );
	$emoji    = relevanssi_check( $spamblock['emoji'] );

	?>
<h2 id="options"><?php esc_html_e( 'Spam Blocking', 'relevanssi' ); ?></h2>

<p><?php esc_html_e( "These tools can be used to block spam searches on your site. It's best if the spam searches can be blocked earlier on server level before WordPress starts at all, but if that's not possible, this is a fine option.", 'relevanssi' ); ?></p>

<p><?php esc_html_e( "You can figure out the suitable keywords from your User searches page. Look for common terms. Often spam queries contain URLs, and the top level domain names are good keywords, things like '.shop', '.online', '.com' – those appear rarely in legitimate searches.", 'relevanssi' ); ?></p>

<table class="form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row"><label for="relevanssi_spamblock_keywords"><?php esc_html_e( 'Keyword spam blocking', 'relevanssi' ); ?></label></th>
			<td><textarea name="relevanssi_spamblock_keywords" id="relevanssi_spamblock_keywords" rows="9" cols="60"><?php echo esc_textarea( $spamblock['keywords'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Enter keywords, one per line. If these keywords appear anywhere in the search string, the search will be stopped. Use as short keywords as possible, but be careful to avoid blocking legitimate searches. The keywords are case insensitive.', 'relevanssi' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><label for="relevanssi_spamblock_regex"><?php esc_html_e( 'Regex keywords', 'relevanssi' ); ?></label></th>
			<td><textarea name="relevanssi_spamblock_regex" id="relevanssi_spamblock_regex" rows="9" cols="60"><?php echo esc_textarea( $spamblock['regex'] ); ?></textarea>
			<?php // Translators: %1$s is <code>/.../iu</code>. ?>
			<p class="description"><?php printf( esc_html__( 'These keywords support the use of regular expressions with preg_match(). The keywords will be wrapped with %1$s.', 'relevanssi' ), '<code>/.../iu</code>' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><label for="relevanssi_spamblock_chinese"><?php esc_html_e( 'Block Chinese queries', 'relevanssi' ); ?></label></th>
			<td><input type='checkbox' name='relevanssi_spamblock_chinese' id='relevanssi_spamblock_chinese' <?php echo esc_attr( $chinese ); ?> />
			<?php esc_html_e( 'Block queries that contain Chinese characters.', 'relevanssi' ); ?>
		</tr>
		<tr>
			<th scope="row"><label for="relevanssi_spamblock_cyrillic"><?php esc_html_e( 'Block Cyrillic queries', 'relevanssi' ); ?></label></th>
			<td><input type='checkbox' name='relevanssi_spamblock_cyrillic' id='relevanssi_spamblock_cyrillic' <?php echo esc_attr( $cyrillic ); ?> />
			<?php esc_html_e( 'Block queries that contain Cyrillic characters.', 'relevanssi' ); ?>
		</tr>
		<tr>
			<th scope="row"><label for="relevanssi_spamblock_emoji"><?php esc_html_e( 'Block emoji queries', 'relevanssi' ); ?></label></th>
			<td><input type='checkbox' name='relevanssi_spamblock_emoji' id='relevanssi_spamblock_emoji' <?php echo esc_attr( $emoji ); ?> />
			<?php esc_html_e( 'Block queries that contain emoji characters.', 'relevanssi' ); ?>
		</tr>
	</tbody>
</table>

	<?php
}
