<?php
/**
 * WP-CLI command for CSV Import and Exporter.
 *
 * @package CSV_Import_and_Exporter
 */

/**
 * Export WordPress posts to CSV via WP-CLI.
 */
class CSVIAE_CLI_Command extends WP_CLI_Command {

	/**
	 * Export posts to CSV.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post_type>]
	 * : Post type slug to export. Default: post
	 *
	 * [--status=<status>]
	 * : Comma-separated list of post statuses to include.
	 * Default: publish,pending,draft,future,private
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of post fields to include.
	 * Available: post_name,post_title,post_content,post_excerpt,post_author,post_date,post_modified
	 * Default: all fields
	 *
	 * [--taxonomies=<taxonomies>]
	 * : Comma-separated list of taxonomy slugs to include.
	 * Default: all taxonomies registered for the post type
	 *
	 * [--cf-fields=<cf_fields>]
	 * : Comma-separated list of custom field keys to include.
	 * Default: all non-private custom fields
	 *
	 * [--thumbnail]
	 * : Include thumbnail URL column.
	 *
	 * [--post-parent]
	 * : Include post_parent column.
	 *
	 * [--menu-order]
	 * : Include menu_order column.
	 *
	 * [--tags]
	 * : Include post tags column.
	 *
	 * [--limit=<limit>]
	 * : Maximum number of posts to export. 0 = all. Default: 0
	 *
	 * [--offset=<offset>]
	 * : Number of posts to skip. Default: 0
	 *
	 * [--order=<order>]
	 * : Sort order: ASC or DESC. Default: DESC
	 *
	 * [--date-from=<date>]
	 * : Filter by publish date range start (Y-m-d).
	 *
	 * [--date-to=<date>]
	 * : Filter by publish date range end (Y-m-d).
	 *
	 * [--modified-from=<date>]
	 * : Filter by modified date range start (Y-m-d).
	 *
	 * [--modified-to=<date>]
	 * : Filter by modified date range end (Y-m-d).
	 *
	 * [--encoding=<encoding>]
	 * : Output character encoding: UTF-8 or SJIS. Default: UTF-8
	 *
	 * [--output=<file>]
	 * : File path to write CSV. Default: stdout
	 *
	 * ## EXAMPLES
	 *
	 *     # Export all posts to stdout
	 *     wp csv-export export
	 *
	 *     # Export a custom post type to a file
	 *     wp csv-export export --post-type=record --output=records.csv
	 *
	 *     # Export only published posts with specific fields
	 *     wp csv-export export --post-type=post --status=publish --fields=post_title,post_name
	 *
	 *     # Export with taxonomy and date filter
	 *     wp csv-export export --post-type=record --taxonomies=genre,country --date-from=2024-01-01 --date-to=2024-12-31
	 *
	 * @subcommand export
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Named arguments.
	 */
	public function export( $args, $assoc_args ) {
		require_once CSVIAE_PLUGIN_DIR . '/classes/class-csviae-exporter.php';

		$post_type = \WP_CLI\Utils\get_flag_value( $assoc_args, 'post-type', 'post' );

		// --status: comma-separated → array, or null for default.
		$status_raw = \WP_CLI\Utils\get_flag_value( $assoc_args, 'status', null );
		$post_status = $status_raw
			? array_map( 'trim', explode( ',', $status_raw ) )
			: array( 'publish', 'pending', 'draft', 'future', 'private' );

		// --fields: comma-separated → array, or all fields.
		$fields_raw  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'fields', null );
		$posts_values = $fields_raw
			? array_map( 'trim', explode( ',', $fields_raw ) )
			: CSVIAE_Exporter::ALL_POSTS_VALUES;

		// --taxonomies: comma-separated → array, or null (auto-detect all).
		$taxonomies_raw = \WP_CLI\Utils\get_flag_value( $assoc_args, 'taxonomies', null );
		$taxonomies     = $taxonomies_raw
			? array_map( 'trim', explode( ',', $taxonomies_raw ) )
			: null;

		// --cf-fields: comma-separated → array, or null (auto-detect all).
		$cf_raw    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'cf-fields', null );
		$cf_fields = $cf_raw
			? array_map( 'trim', explode( ',', $cf_raw ) )
			: null;

		$exporter = new CSVIAE_Exporter(
			array(
				'post_type'          => $post_type,
				'posts_values'       => $posts_values,
				'post_status'        => $post_status,
				'post_thumbnail'     => isset( $assoc_args['thumbnail'] ),
				'post_parent'        => isset( $assoc_args['post-parent'] ),
				'menu_order'         => isset( $assoc_args['menu-order'] ),
				'post_tags'          => isset( $assoc_args['tags'] ),
				'taxonomies'         => $taxonomies,
				'cf_fields'          => $cf_fields,
				'limit'              => absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 0 ) ),
				'offset'             => absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'offset', 0 ) ),
				'order_by'           => \WP_CLI\Utils\get_flag_value( $assoc_args, 'order', 'DESC' ),
				'post_date_from'     => \WP_CLI\Utils\get_flag_value( $assoc_args, 'date-from', '' ),
				'post_date_to'       => \WP_CLI\Utils\get_flag_value( $assoc_args, 'date-to', '' ),
				'post_modified_from' => \WP_CLI\Utils\get_flag_value( $assoc_args, 'modified-from', '' ),
				'post_modified_to'   => \WP_CLI\Utils\get_flag_value( $assoc_args, 'modified-to', '' ),
				'string_code'        => \WP_CLI\Utils\get_flag_value( $assoc_args, 'encoding', 'UTF-8' ),
			)
		);

		if ( ! $exporter->is_valid() ) {
			WP_CLI::error( sprintf( 'Invalid post type: "%s"', $post_type ) );
			return;
		}

		$output_path = \WP_CLI\Utils\get_flag_value( $assoc_args, 'output', null );

		if ( $output_path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$fp = fopen( $output_path, 'w' );
			if ( ! $fp ) {
				WP_CLI::error( sprintf( 'Cannot open file for writing: %s', $output_path ) );
				return;
			}
		} else {
			$fp = STDOUT;
		}

		$count = $exporter->export( $fp );

		if ( $output_path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $fp );
		}

		if ( false === $count ) {
			WP_CLI::error( 'Export failed.' );
			return;
		}

		if ( 0 === $count ) {
			WP_CLI::warning( sprintf( 'No posts found for post type "%s".', $post_type ) );
			return;
		}

		if ( $output_path ) {
			WP_CLI::success( sprintf( 'Exported %d post(s) to %s', $count, $output_path ) );
		} else {
			WP_CLI::log( sprintf( '# Exported %d post(s).', $count ) );
		}
	}
}
