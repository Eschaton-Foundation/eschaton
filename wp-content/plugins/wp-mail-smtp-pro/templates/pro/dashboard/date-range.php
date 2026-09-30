<?php
/**
 * Dashboard date-range control, governing the graph, Email Log and Email Sources widgets.
 *
 * @since 4.10.0
 *
 * @var array  $options Preset option labels, keyed by value: a day count, or `custom`.
 * @var string $active  The option value matching the range currently in effect.
 * @var string $custom  The active custom range as `"Y-m-d - Y-m-d"`, empty on a preset.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpms-dashboard-date-range">
	<span class="spinner wpms-dashboard-date-range__spinner"></span>
	<label class="screen-reader-text" for="wpms-dashboard-date-range-preset"><?php esc_html_e( 'Date range', 'wp-mail-smtp-pro' ); ?></label>
	<select id="wpms-dashboard-date-range-preset" class="wpms-dashboard-date-range__select">
		<?php foreach ( $options as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $active ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<input
		type="text"
		aria-label="<?php esc_attr_e( 'Custom date range', 'wp-mail-smtp-pro' ); ?>"
		class="wpms-dashboard-date-range__input<?php echo $custom === '' ? ' wp-mail-smtp-hide' : ''; ?>"
		value="<?php echo esc_attr( $custom ); ?>"
		placeholder="<?php esc_attr_e( 'Select a date range', 'wp-mail-smtp-pro' ); ?>"
	>
</div>
