<?php
/**
 * Core exporter class for CSV Import and Exporter.
 *
 * @package CSV_Import_and_Exporter
 */

/**
 * Handles CSV export logic independently of HTTP or CLI context.
 */
class CSVIAE_Exporter {

	/**
	 * Post type object.
	 *
	 * @var WP_Post_Type|null
	 */
	protected $post_type_obj;

	/** @var array  wp_posts columns to include in SELECT */
	protected $posts_values;

	/** @var array  Post statuses to filter */
	protected $post_status;

	/** @var bool  Include thumbnail URL */
	protected $post_thumbnail;

	/** @var bool  Include post_parent */
	protected $post_parent;

	/** @var bool  Include menu_order */
	protected $menu_order;

	/** @var bool  Include post tags */
	protected $post_tags;

	/** @var array  Taxonomy slugs to include */
	protected $taxonomies;

	/** @var array  Custom field keys to include */
	protected $cf_fields;

	/** @var int  Max posts (0 = all) */
	protected $limit;

	/** @var int  Starting offset */
	protected $offset;

	/** @var string  'ASC' or 'DESC' */
	protected $order_by;

	/** @var string  Publish date range from (Y-m-d) */
	protected $post_date_from;

	/** @var string  Publish date range to (Y-m-d) */
	protected $post_date_to;

	/** @var string  Modified date range from (Y-m-d) */
	protected $post_modified_from;

	/** @var string  Modified date range to (Y-m-d) */
	protected $post_modified_to;

	/** @var string  Output encoding: 'UTF-8' or 'SJIS' */
	protected $string_code;

	/**
	 * All standard post fields available for export.
	 */
	const ALL_POSTS_VALUES = array(
		'post_name',
		'post_title',
		'post_content',
		'post_excerpt',
		'post_author',
		'post_date',
		'post_modified',
	);

	/**
	 * Constructor.
	 *
	 * @param array $args {
	 *   @type string     $post_type          Post type slug (required).
	 *   @type array      $posts_values       wp_posts columns to export. Default: all standard fields.
	 *   @type array      $post_status        Post statuses. Default: publish/pending/draft/future/private.
	 *   @type bool       $post_thumbnail     Include thumbnail URL. Default: false.
	 *   @type bool       $post_parent        Include post_parent. Default: false.
	 *   @type bool       $menu_order         Include menu_order. Default: false.
	 *   @type bool       $post_tags          Include tags. Default: false.
	 *   @type array|null $taxonomies         Taxonomy slugs, or null for auto-detect all. Default: null.
	 *   @type array|null $cf_fields          Custom field keys, or null for auto-detect all. Default: null.
	 *   @type int        $limit              Max posts (0 = all). Default: 0.
	 *   @type int        $offset             Starting offset. Default: 0.
	 *   @type string     $order_by           'ASC' or 'DESC'. Default: 'DESC'.
	 *   @type string     $post_date_from     Filter publish date from (Y-m-d). Default: ''.
	 *   @type string     $post_date_to       Filter publish date to (Y-m-d). Default: ''.
	 *   @type string     $post_modified_from Filter modified date from (Y-m-d). Default: ''.
	 *   @type string     $post_modified_to   Filter modified date to (Y-m-d). Default: ''.
	 *   @type string     $string_code        Output encoding. Default: 'UTF-8'.
	 * }
	 */
	public function __construct( array $args ) {
		$defaults = array(
			'post_type'          => '',
			'posts_values'       => self::ALL_POSTS_VALUES,
			'post_status'        => array( 'publish', 'pending', 'draft', 'future', 'private' ),
			'post_thumbnail'     => false,
			'post_parent'        => false,
			'menu_order'         => false,
			'post_tags'          => false,
			'taxonomies'         => null,
			'cf_fields'          => null,
			'limit'              => 0,
			'offset'             => 0,
			'order_by'           => 'DESC',
			'post_date_from'     => '',
			'post_date_to'       => '',
			'post_modified_from' => '',
			'post_modified_to'   => '',
			'string_code'        => 'UTF-8',
		);

		$parsed = wp_parse_args( $args, $defaults );

		$this->post_type_obj      = get_post_type_object( sanitize_key( $parsed['post_type'] ) );
		$this->posts_values       = array_map( 'sanitize_key', (array) $parsed['posts_values'] );
		$this->post_status        = array_map( 'sanitize_key', (array) $parsed['post_status'] );
		$this->post_thumbnail     = (bool) $parsed['post_thumbnail'];
		$this->post_parent        = (bool) $parsed['post_parent'];
		$this->menu_order         = (bool) $parsed['menu_order'];
		$this->post_tags          = (bool) $parsed['post_tags'];
		$this->limit              = absint( $parsed['limit'] );
		$this->offset             = absint( $parsed['offset'] );
		$this->order_by           = in_array( $parsed['order_by'], array( 'ASC', 'DESC' ), true ) ? $parsed['order_by'] : 'DESC';
		$this->post_date_from     = sanitize_text_field( $parsed['post_date_from'] );
		$this->post_date_to       = sanitize_text_field( $parsed['post_date_to'] );
		$this->post_modified_from = sanitize_text_field( $parsed['post_modified_from'] );
		$this->post_modified_to   = sanitize_text_field( $parsed['post_modified_to'] );
		$this->string_code        = sanitize_text_field( $parsed['string_code'] );

		// null = auto-detect all for the post type.
		$this->taxonomies = ( null === $parsed['taxonomies'] )
			? $this->detect_taxonomies()
			: array_map( 'sanitize_key', (array) $parsed['taxonomies'] );

		$this->cf_fields = ( null === $parsed['cf_fields'] )
			? $this->detect_cf_fields()
			: array_map( 'sanitize_text_field', (array) $parsed['cf_fields'] );
	}

	/**
	 * Returns true if the post type exists and export can proceed.
	 *
	 * @return bool
	 */
	public function is_valid() {
		return null !== $this->post_type_obj && ! empty( $this->post_status );
	}

	/**
	 * Export CSV rows to the given file handle.
	 *
	 * @param resource $fp File handle (e.g. STDOUT or fopen result).
	 * @return int|false Number of posts exported, or false on error.
	 */
	public function export( $fp ) {
		if ( ! $this->is_valid() ) {
			return false;
		}

		wp_raise_memory_limit( 'admin' );

		$results = $this->fetch_posts();
		if ( empty( $results ) ) {
			return 0;
		}

		foreach ( $results as $index => $result ) {
			$results[ $index ] = $this->enrich_row( $result );
			clean_post_cache( absint( $result['post_id'] ) );
		}

		// Header row + data rows.
		$list = array_merge( array( array_keys( $results[0] ) ), $results );

		foreach ( $list as $row ) {
			if ( 'UTF-8' !== $this->string_code && function_exists( 'mb_convert_variables' ) ) {
				mb_convert_variables( $this->string_code, 'UTF-8', $row );
			}
			fputcsv( $fp, $row );
		}

		return count( $results );
	}

	/**
	 * Fetch posts from the database.
	 *
	 * @return array
	 */
	protected function fetch_posts() {
		global $wpdb;

		$value_parameter = array();

		// SELECT.
		$query_select = 'ID as post_id, post_type, post_status';
		foreach ( $this->posts_values as $col ) {
			$query_select .= ', ' . $col;
		}
		$query = 'SELECT ' . $query_select . ' FROM ' . $wpdb->posts . ' ';

		// WHERE statuses.
		$placeholders = implode( ', ', array_fill( 0, count( $this->post_status ), "'%s'" ) );
		$query       .= 'WHERE post_status IN (' . $placeholders . ') ';
		foreach ( $this->post_status as $status ) {
			$value_parameter[] = $status;
		}

		// AND post_type.
		$query           .= "AND post_type LIKE '%s' ";
		$value_parameter[] = $this->post_type_obj->name;

		// Date range filters.
		if ( $this->post_date_from && $this->post_date_to ) {
			$query           .= "AND post_date BETWEEN '%s' AND '%s' ";
			$value_parameter[] = $this->post_date_from . ' 00:00:00';
			$value_parameter[] = $this->post_date_to . ' 23:59:59';
		}
		if ( $this->post_modified_from && $this->post_modified_to ) {
			$query           .= "AND post_modified BETWEEN '%s' AND '%s' ";
			$value_parameter[] = $this->post_modified_from . ' 00:00:00';
			$value_parameter[] = $this->post_modified_to . ' 23:59:59';
		}

		// ORDER.
		if ( 'ASC' === $this->order_by ) {
			$query .= 'ORDER BY post_date ASC, post_modified ASC ';
		} else {
			$query .= 'ORDER BY post_date DESC, post_modified DESC ';
		}

		// LIMIT / OFFSET.
		if ( $this->limit > 0 ) {
			$query           .= 'LIMIT %d ';
			$value_parameter[] = $this->limit;

			if ( $this->offset > 0 ) {
				$query           .= 'OFFSET %d ';
				$value_parameter[] = $this->offset;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $query, $value_parameter ), ARRAY_A );
	}

	/**
	 * Enrich a single post row with meta, taxonomies, and custom fields.
	 *
	 * @param array $result Raw DB row.
	 * @return array Enriched row.
	 */
	protected function enrich_row( array $result ) {
		$customs_array  = array();
		$result_post_id = absint( $result['post_id'] );

		if ( isset( $result['post_name'] ) ) {
			$customs_array['post_name'] = apply_filters( 'wp_csv_exporter_post_name', $result['post_name'], $result_post_id );
		}
		if ( isset( $result['post_title'] ) ) {
			$customs_array['post_title'] = apply_filters( 'wp_csv_exporter_post_title', $result['post_title'], $result_post_id );
		}
		if ( isset( $result['post_content'] ) ) {
			$customs_array['post_content'] = apply_filters( 'wp_csv_exporter_post_content', $result['post_content'], $result_post_id );
		}
		if ( isset( $result['post_excerpt'] ) ) {
			$customs_array['post_excerpt'] = apply_filters( 'wp_csv_exporter_post_excerpt', $result['post_excerpt'], $result_post_id );
		}
		if ( isset( $result['post_status'] ) ) {
			$customs_array['post_status'] = apply_filters( 'wp_csv_exporter_post_status', $result['post_status'], $result_post_id );
		}
		if ( isset( $result['post_date'] ) ) {
			$customs_array['post_date'] = apply_filters( 'wp_csv_exporter_post_date', $result['post_date'], $result_post_id );
		}
		if ( isset( $result['post_modified'] ) ) {
			$customs_array['post_modified'] = apply_filters( 'wp_csv_exporter_post_modified', $result['post_modified'], $result_post_id );
		}
		if ( isset( $result['post_author'] ) ) {
			$customs_array['post_author'] = apply_filters( 'wp_csv_exporter_post_author', $result['post_author'], $result_post_id );
		}

		// Thumbnail.
		if ( $this->post_thumbnail ) {
			$thumbnail_url_array  = wp_get_attachment_image_src( get_post_thumbnail_id( $result_post_id ), true );
			$customs_array['post_thumbnail'] = apply_filters(
				'wp_csv_exporter_thumbnail_url',
				isset( $thumbnail_url_array[0] ) ? $thumbnail_url_array[0] : '',
				$result_post_id
			);
		}

		// post_parent / menu_order.
		if ( $this->post_parent || $this->menu_order ) {
			$the_post = get_post( $result_post_id );
			if ( $this->post_parent ) {
				$customs_array['post_parent'] = apply_filters( 'wp_csv_exporter_post_parent', $the_post->post_parent, $result_post_id );
			}
			if ( $this->menu_order ) {
				$customs_array['menu_order'] = apply_filters( 'wp_csv_exporter_menu_order', $the_post->menu_order, $result_post_id );
			}
		}

		// Tags.
		if ( $this->post_tags ) {
			$tags = get_the_tags( $result_post_id );
			if ( is_array( $tags ) ) {
				$post_tags_val = wp_list_pluck( $tags, 'slug' );
				$post_tags_val = apply_filters( 'wp_csv_exporter_post_tags', $post_tags_val, $result_post_id );
				$customs_array['post_tags'] = urldecode( implode( ',', $post_tags_val ) );
			} else {
				$customs_array['post_tags'] = '';
			}
		}

		// Taxonomies.
		foreach ( $this->taxonomies as $taxonomy ) {
			$head_name   = ( 'category' === $taxonomy ) ? 'post_category' : 'tax_' . $taxonomy;
			$terms       = get_the_terms( $result_post_id, $taxonomy );

			if ( is_array( $terms ) ) {
				$term_values = wp_list_pluck( $terms, 'slug' );
				$term_values = apply_filters( 'wp_csv_exporter_' . $head_name, $term_values, $result_post_id );
				$customs_array[ $head_name ] = urldecode( implode( ',', $term_values ) );
			} else {
				$term_values = '';
				$term_values = apply_filters( 'wp_csv_exporter_' . $head_name, $term_values, $result_post_id );
				$customs_array[ $head_name ] = $term_values;
			}
		}

		// Custom fields.
		if ( ! empty( $this->cf_fields ) ) {
			$fields = get_post_custom( $result_post_id );
			foreach ( $this->cf_fields as $cf_key ) {
				if ( preg_match( '/^_/', $cf_key ) ) {
					continue;
				}
				$field       = isset( $fields[ $cf_key ] ) ? $fields[ $cf_key ] : null;
				$field_value = isset( $field[0] ) ? $field[0] : '';
				$field_value = apply_filters( 'wp_csv_exporter_' . $cf_key, $field_value, $result_post_id );
				$customs_array[ $cf_key ] = $field_value;
			}
		}

		return array_merge( $result, $customs_array );
	}

	/**
	 * Auto-detect all taxonomies registered for the post type.
	 *
	 * @return array
	 */
	protected function detect_taxonomies() {
		if ( ! $this->post_type_obj ) {
			return array();
		}
		return get_object_taxonomies( $this->post_type_obj->name );
	}

	/**
	 * Auto-detect all non-private custom fields for the post type.
	 *
	 * @return array
	 */
	protected function detect_cf_fields() {
		if ( ! $this->post_type_obj ) {
			return array();
		}
		global $wpdb;
		$query = <<<EOL
SELECT DISTINCT meta_key
FROM $wpdb->postmeta
INNER JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->postmeta.post_id
WHERE $wpdb->posts.post_type = '%s'
AND $wpdb->postmeta.meta_key NOT LIKE '\_%'
EOL;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $query, $this->post_type_obj->name ), ARRAY_A );
		return $rows ? array_column( $rows, 'meta_key' ) : array();
	}
}
