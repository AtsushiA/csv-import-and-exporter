<?php
/**
 * エラー表示
 */
function display_messages( $_messages, $_state ) {
?>
    <div class="<?php echo wp_kses($_state); ?>">
        <ul>
            <?php foreach ( $_messages as $message ): ?>
                <li><?php echo wp_kses( $message ); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php
}