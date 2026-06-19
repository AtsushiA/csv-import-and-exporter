<?php
/**
 * Integration tests for the CSVIAE_Exporter class.
 *
 * @package CSV_Import_and_Exporter
 */

/**
 * Tests the core CSV export logic.
 */
class ExporterTest extends WP_UnitTestCase {

	/**
	 * Export to an in-memory stream and return the parsed CSV rows.
	 *
	 * @param CSVIAE_Exporter $exporter Exporter instance.
	 * @return array{count:int|false, rows:array} Export result and parsed rows.
	 */
	private function export_to_rows( CSVIAE_Exporter $exporter ) {
		$fp    = fopen( 'php://temp', 'r+' );
		$count = $exporter->export( $fp );
		rewind( $fp );

		$rows = array();
		while ( false !== ( $row = fgetcsv( $fp ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			$rows[] = $row;
		}
		fclose( $fp );

		return array(
			'count' => $count,
			'rows'  => $rows,
		);
	}

	/**
	 * A non-existent post type is not valid for export.
	 */
	public function test_is_valid_returns_false_for_unknown_post_type() {
		$exporter = new CSVIAE_Exporter( array( 'post_type' => 'does_not_exist' ) );
		$this->assertFalse( $exporter->is_valid() );
	}

	/**
	 * A registered post type is valid for export.
	 */
	public function test_is_valid_returns_true_for_post() {
		$exporter = new CSVIAE_Exporter( array( 'post_type' => 'post' ) );
		$this->assertTrue( $exporter->is_valid() );
	}

	/**
	 * Exporting an invalid post type returns false without output.
	 */
	public function test_export_invalid_post_type_returns_false() {
		$exporter = new CSVIAE_Exporter( array( 'post_type' => 'does_not_exist' ) );
		$result   = $this->export_to_rows( $exporter );
		$this->assertFalse( $result['count'] );
		$this->assertSame( array(), $result['rows'] );
	}

	/**
	 * Exporting a post type with no matching posts returns zero rows.
	 */
	public function test_export_with_no_posts_returns_zero() {
		$exporter = new CSVIAE_Exporter(
			array(
				'post_type'   => 'post',
				'post_status' => array( 'publish' ),
			)
		);
		$result = $this->export_to_rows( $exporter );
		$this->assertSame( 0, $result['count'] );
	}

	/**
	 * Exported CSV contains a header row plus one data row per post.
	 */
	public function test_export_outputs_header_and_rows() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Hello CSV',
				'post_name'    => 'hello-csv',
				'post_status'  => 'publish',
				'post_content' => 'Body',
			)
		);

		$exporter = new CSVIAE_Exporter(
			array(
				'post_type'    => 'post',
				'post_status'  => array( 'publish' ),
				'posts_values' => array( 'post_name', 'post_title' ),
				'taxonomies'   => array(),
				'cf_fields'    => array(),
			)
		);
		$result = $this->export_to_rows( $exporter );

		$this->assertSame( 1, $result['count'] );
		$this->assertCount( 2, $result['rows'], 'Expected a header row and one data row.' );

		$header = $result['rows'][0];
		$this->assertContains( 'post_id', $header );
		$this->assertContains( 'post_title', $header );

		$data       = $result['rows'][1];
		$title_index = array_search( 'post_title', $header, true );
		$this->assertSame( 'Hello CSV', $data[ $title_index ] );

		$id_index = array_search( 'post_id', $header, true );
		$this->assertSame( (string) $post_id, $data[ $id_index ] );
	}

	/**
	 * The limit argument caps the number of exported posts.
	 */
	public function test_export_respects_limit() {
		self::factory()->post->create_many( 5, array( 'post_status' => 'publish' ) );

		$exporter = new CSVIAE_Exporter(
			array(
				'post_type'   => 'post',
				'post_status' => array( 'publish' ),
				'limit'       => 2,
				'taxonomies'  => array(),
				'cf_fields'   => array(),
			)
		);
		$result = $this->export_to_rows( $exporter );

		$this->assertSame( 2, $result['count'] );
	}

	/**
	 * Custom field values are included when requested.
	 */
	public function test_export_includes_custom_field() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $post_id, 'color', 'blue' );

		$exporter = new CSVIAE_Exporter(
			array(
				'post_type'   => 'post',
				'post_status' => array( 'publish' ),
				'taxonomies'  => array(),
				'cf_fields'   => array( 'color' ),
			)
		);
		$result = $this->export_to_rows( $exporter );

		$header      = $result['rows'][0];
		$this->assertContains( 'color', $header );
		$color_index = array_search( 'color', $header, true );
		$this->assertSame( 'blue', $result['rows'][1][ $color_index ] );
	}
}
