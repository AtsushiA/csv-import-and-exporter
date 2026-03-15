<?php
/**
 * Base class for CSV Import and Exporter.
 *
 * @package CSV_Import_and_Exporter
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName, WordPress.NamingConventions.ValidClassName

/**
 * Abstract base class providing shared utilities.
 */
abstract class CSV_Import_and_Exporter_Base {

	/**
	 * Array-compatible wrapper for esc_html.
	 *
	 * @param mixed $str String or array to escape.
	 * @return mixed
	 */
	public function esc_htmls( $str ) {
		if ( is_array( $str ) ) {
			return array_map( 'esc_html', $str );
		} else {
			return esc_html( $str );
		}
	}

	/**
	 * Load template file.
	 *
	 * @param string $name Template name (relative to plugin root).
	 */
	public function get_template( $name ) {
		$path = CSVIAE_PLUGIN_DIR . "{$name}.php";
		if ( file_exists( $path ) ) {
			include $path; // phpcs:ignore PEAR.Files.IncludingFile
		}
	}

	/**
	 * Return raw $_REQUEST value by key. Caller is responsible for sanitization.
	 *
	 * @param string $key Request key.
	 * @return mixed|null
	 */
	public function request( $key ) {
		if ( isset( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $_REQUEST[ $key ]; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		} else {
			return null;
		}
	}

	/**
	 * Translate and echo a string.
	 *
	 * @param string $text Text to translate.
	 * @param mixed  $ja   Japanese fallback (unused; kept for API compatibility).
	 */
	public function e( $text, $ja = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		esc_html_e( $text, $this->textdomain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
	}

	/**
	 * Translate and return a string.
	 *
	 * @param string $text Text to translate.
	 * @param mixed  $ja   Japanese fallback (unused; kept for API compatibility).
	 * @return string
	 */
	public function _( $text, $ja = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return __( $text, $this->textdomain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
	}
}
