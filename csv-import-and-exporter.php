<?php
/**
 * Plugin Name: CSV Import and Exporter
 * Description: You can import & export posts in CSV format for each post type. It is compatible with posts' custom fields and custom taxonomies. It is also possible to set the number or date range of posts to download.
 * Author: Nakashima Masahiro
 * Version: 1.0.2
 * Author URI: http://www.kigurumi.asia
 * License: GPLv2 or later
 * Text Domain: wp-csv-im-n-exporter
 * Domain Path: /languages/
 *
 * @package CSV_Import_and_Exporter
 */

if ( class_exists( 'CSV_Import_and_Exporter' ) ) {
	wp_die();
}

require __DIR__ . '/classes/csviae-base.php';

define( 'CSVIAE_PLUGIN_VERSION', '1.0.2' );
define( 'CSVIAE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CSVIAE_PLUGIN_NAME', trim( dirname( CSVIAE_PLUGIN_BASENAME ), '/' ) );
define( 'CSVIAE_PLUGIN_IMPORT_NAME', 'wp-csv-importer' );
define( 'CSVIAE_PLUGIN_DIR', untrailingslashit( __DIR__ ) );
define( 'CSVIAE_PLUGIN_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );

/**
 * Main plugin class for CSV Import and Exporter.
 */
class CSV_Import_and_Exporter extends CSV_Import_and_Exporter_Base {

	/**
	 * Text domain for translations.
	 *
	 * @var string
	 */
	protected $textdomain = 'csv-import-and-exporter';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();

		// 管理メニューに追加するフック
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );

		// CSS and JS.
		add_action( 'admin_print_styles', array( &$this, 'head_css' ) );
		add_action( 'admin_print_scripts', array( &$this, 'head_js' ) );

		// Ajax actions.
		add_action( 'wp_head', array( &$this, 'generate_js_params' ) );
		add_action( 'wp_ajax_download', array( &$this, 'ajax_download' ) );
		add_action( 'wp_ajax_nopriv_download', array( &$this, 'ajax_download' ) );

		// プラグインの有効・無効時
		register_activation_hook( __FILE__, array( $this, 'activationHook' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// 他言語化
		load_plugin_textdomain( $this->textdomain, false, basename( __DIR__ ) . '/languages/' );
	}

	/**
	 * メニューを表示
	 */
	public function admin_menu() {
		add_submenu_page( 'tools.php', $this->_( 'CSV Export', 'CSVエクスポート' ), $this->_( 'CSV Export', 'CSVエクスポート' ), 'manage_options', CSVIAE_PLUGIN_NAME, array( &$this, 'show_export_page' ) );
		add_submenu_page( 'tools.php', $this->_( 'CSV Import', 'CSVインポート' ), $this->_( 'CSV Import', 'CSVインポート' ), 'manage_options', CSVIAE_PLUGIN_IMPORT_NAME, array( &$this, 'show_import_page' ) );
	}

	/**
	 * プラグインのメインページ（エクスポート）
	 */
	public function show_export_page() {
		require_once CSVIAE_PLUGIN_DIR . '/admin/index.php';
	}

	/**
	 * インポートページ
	 */
	public function show_import_page() {
		require_once CSVIAE_PLUGIN_DIR . '/import/rs-csv-importer.php';
	}

	/**
	 * Get admin panel URL.
	 *
	 * @param string $view View slug.
	 * @return string
	 */
	public function setting_url( $view = '' ) {
		$query = array(
			'page' => CSVIAE_PLUGIN_NAME,
		);
		if ( $view ) {
			$query['view'] = $view;
		}
		return admin_url( 'tools.php?' . http_build_query( $query ) );
	}

	/**
	 * 管理画面CSS追加
	 */
	public function head_css() {
		if ( isset( $_REQUEST['page'] ) && CSVIAE_PLUGIN_NAME === $_REQUEST['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_enqueue_style( 'csviae_css', CSVIAE_PLUGIN_URL . '/css/style.css', array(), CSVIAE_PLUGIN_VERSION );
			wp_enqueue_style( 'jquery-ui-style', CSVIAE_PLUGIN_URL . '/css/jquery-ui.css', array(), CSVIAE_PLUGIN_VERSION );
		}
	}

	/**
	 * 管理画面JS追加
	 */
	public function head_js() {
		if ( isset( $_REQUEST['page'] ) && CSVIAE_PLUGIN_NAME === $_REQUEST['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'csviae_cookie_js', CSVIAE_PLUGIN_URL . '/js/jquery.cookie.js', array( 'jquery' ), CSVIAE_PLUGIN_VERSION, true );
			wp_enqueue_script( 'csviae_admin_js', CSVIAE_PLUGIN_URL . '/js/admin.js', array( 'jquery' ), CSVIAE_PLUGIN_VERSION, true );
		}
	}

	/**
	 * カスタムフィールドリストを取得
	 *
	 * @param string $type Post type slug.
	 * @return array
	 */
	public function get_custom_field_list( $type ) {
		global $wpdb;
		$value_parameter = $type;
		$pattern         = '\_%';
		$query           = <<<EOL
SELECT DISTINCT meta_key
FROM $wpdb->postmeta
INNER JOIN $wpdb->posts
        ON $wpdb->posts.ID = $wpdb->postmeta.post_id
WHERE $wpdb->posts.post_type = '%s'
AND $wpdb->postmeta.meta_key NOT LIKE '%s'
EOL;
		return $wpdb->get_results( $wpdb->prepare( $query, array( $value_parameter, $pattern ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * フロントエンドにAjax用URLを出力
	 */
	public function generate_js_params() {
		?>
		<script>
			var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		</script>
		<?php
	}

	/**
	 * Ajaxダウンロード処理
	 */
	public function ajax_download() {
		require_once CSVIAE_PLUGIN_DIR . '/admin/download.php';
	}

	/**
	 * プラグインが有効化されたときに実行
	 */
	public function activationHook() {
		// CSVを格納するDir
		$directory_path = CSVIAE_PLUGIN_DIR . '/download/';
		if ( ! file_exists( $directory_path ) ) {
			mkdir( $directory_path, 0770 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		chmod( $directory_path, 0770 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}

new CSV_Import_and_Exporter();
