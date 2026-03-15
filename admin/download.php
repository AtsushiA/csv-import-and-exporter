<?php
/**
 * CSV download handler for CSV Import and Exporter plugin.
 *
 * @package CSV_Import_and_Exporter
 */

if (
	! isset( $_POST['type'] ) ||
	! is_user_logged_in() ||
	! isset( $_POST['_wpnonce'] ) ||
	! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'csv_exporter' ) ||
	! current_user_can( 'manage_options' )
) {
	echo esc_html( 'エラーが起きました。' );
	return;
}

check_admin_referer( 'csv_exporter' );

require_once CSVIAE_PLUGIN_DIR . '/classes/class-csviae-exporter.php';

// Parse request parameters.
// Nonce verified above via check_admin_referer(); safe to access $_POST directly here.
// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
$posts_values = ( isset( $_POST['posts_values'] ) && is_array( $_POST['posts_values'] ) )
	? array_map( 'sanitize_key', wp_unslash( $_POST['posts_values'] ) )
	: array();

$post_status = ( isset( $_POST['post_status'] ) && is_array( $_POST['post_status'] ) )
	? array_map( 'sanitize_key', wp_unslash( $_POST['post_status'] ) )
	: array();

$taxonomies = ( isset( $_POST['taxonomies'] ) && is_array( $_POST['taxonomies'] ) )
	? array_map( 'sanitize_key', wp_unslash( $_POST['taxonomies'] ) )
	: array();

$cf_fields = ( isset( $_POST['cf_fields'] ) && is_array( $_POST['cf_fields'] ) )
	? array_map( 'sanitize_text_field', wp_unslash( $_POST['cf_fields'] ) )
	: array();
// phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput

$exporter = new CSVIAE_Exporter(
	array(
		'post_type'          => sanitize_key( wp_unslash( $_POST['type'] ) ),
		'posts_values'       => ! empty( $posts_values ) ? $posts_values : CSVIAE_Exporter::ALL_POSTS_VALUES,
		'post_status'        => $post_status,
		'post_thumbnail'     => ! empty( $_POST['post_thumbnail'] ), // phpcs:ignore WordPress.Security.NonceVerification
		'post_parent'        => ! empty( $_POST['post_parent'] ), // phpcs:ignore WordPress.Security.NonceVerification
		'menu_order'         => ! empty( $_POST['menu_order'] ), // phpcs:ignore WordPress.Security.NonceVerification
		'post_tags'          => ! empty( $_POST['post_tags'] ), // phpcs:ignore WordPress.Security.NonceVerification
		'taxonomies'         => $taxonomies,
		'cf_fields'          => $cf_fields,
		'limit'              => isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification
		'offset'             => isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification
		'order_by'           => isset( $_POST['order_by'] ) ? sanitize_text_field( wp_unslash( $_POST['order_by'] ) ) : 'DESC', // phpcs:ignore WordPress.Security.NonceVerification
		'post_date_from'     => isset( $_POST['post_date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['post_date_from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		'post_date_to'       => isset( $_POST['post_date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['post_date_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		'post_modified_from' => isset( $_POST['post_modified_from'] ) ? sanitize_text_field( wp_unslash( $_POST['post_modified_from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		'post_modified_to'   => isset( $_POST['post_modified_to'] ) ? sanitize_text_field( wp_unslash( $_POST['post_modified_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		'string_code'        => isset( $_POST['string_code'] ) ? sanitize_text_field( wp_unslash( $_POST['string_code'] ) ) : 'UTF-8', // phpcs:ignore WordPress.Security.NonceVerification
	)
);

if ( ! $exporter->is_valid() ) {
	echo esc_html( 'Invalid post type.' );
	return;
}

// Write to a temporary file, then stream to browser.
$post_type_name = sanitize_key( wp_unslash( $_POST['type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
$filename       = 'export-' . $post_type_name . '-' . date_i18n( 'Y-m-d_H-i-s' ) . '.csv';
$filepath       = CSVIAE_PLUGIN_DIR . '/download/' . $filename;

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
$fp = fopen( $filepath, 'w' );
if ( ! $fp ) {
	echo esc_html( 'ファイルの書き込みに失敗しました。' );
	return;
}

$count = $exporter->export( $fp );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
fclose( $fp );

if ( false === $count || 0 === $count ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	unlink( $filepath );
	echo esc_html( '"' . $post_type_name . '" post type has no posts.' );
	return;
}

header( 'Content-Type:application/octet-stream' );
header( 'Content-Disposition:filename=' . $filename );
header( 'Content-Length:' . filesize( $filepath ) );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
readfile( $filepath );
// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
unlink( $filepath );
exit;
