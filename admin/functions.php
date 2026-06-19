<?php
/**
 * Admin notice rendering helper.
 *
 * @package CSV_Import_and_Exporter
 */

/**
 * Render a list of admin messages.
 *
 * @param array  $_messages Messages to display.
 * @param string $_state    CSS class for the wrapper (e.g. 'error', 'updated').
 */
function display_messages( $_messages, $_state ) {
	?>
	<div class="<?php echo wp_kses( $_state ); ?>">
		<ul>
			<?php foreach ( $_messages as $message ) : ?>
				<li><?php echo wp_kses( $message ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}