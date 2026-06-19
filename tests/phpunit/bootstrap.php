<?php
/**
 * PHPUnit bootstrap file for integration tests.
 *
 * @package CSV_Import_and_Exporter
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _csviae_manually_load_plugin() {
	require dirname( __DIR__, 2 ) . '/csv-import-and-exporter.php';
}
tests_add_filter( 'muplugins_loaded', '_csviae_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
