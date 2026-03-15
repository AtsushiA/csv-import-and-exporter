<?php
/*
Plugin Name: CSV Import and Exporter
Description: You can import & export posts in CSV format for each post type. It is compatible with posts' custom fields and custom taxonomies. It is also possible to set the number or date range of posts to download.
Author: Nakashima Masahiro
Version: 1.0.1
Author URI: http://www.kigurumi.asia
License: GPLv2 or later
Text Domain: wp-csv-im-n-exporter
Domain Path: /languages/
*/
if (class_exists('CSV_Import_and_Exporter')) {
    wp_die();
}

require('classes/csviae-base.php');

define('CSVIAE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('CSVIAE_PLUGIN_NAME', trim(dirname(CSVIAE_PLUGIN_BASENAME), '/'));
define('CSVIAE_PLUGIN_IMPORT_NAME', 'wp-csv-importer');
define('CSVIAE_PLUGIN_DIR', untrailingslashit(dirname(__FILE__)));
define('CSVIAE_PLUGIN_URL', untrailingslashit(plugins_url('', __FILE__)));

class CSV_Import_and_Exporter extends CSV_Import_and_Exporter_Base
{
    protected $textdomain = 'csv-import-and-exporter';

    public function __construct()
    {
        $this->init();

        // 管理メニューに追加するフック
        add_action('admin_menu', array(&$this, 'admin_menu', ));

        // css, js
        add_action('admin_print_styles', array(&$this, 'head_css', ));
        add_action('admin_print_scripts', array(&$this, "head_js", ));

        // Ajax
        add_action('wp_head', array(&$this, 'generate_js_params'));
        add_action('wp_ajax_download', array(&$this, 'ajax_download'));
        add_action('wp_ajax_nopriv_download', array(&$this, 'ajax_download'));

        // プラグインの有効・無効時
        register_activation_hook(__FILE__, array($this, 'activationHook'));
    }


    public function init()
    {
        //他言語化
        load_plugin_textdomain($this->textdomain, false, basename(dirname(__FILE__)) . '/languages/');
    }

    /**
     * メニューを表示
     */
    public function admin_menu()
    {
        add_submenu_page('tools.php', $this->_('CSV Export', 'CSVエクスポート'), $this->_('CSV Export', 'CSVエクスポート'), 'level_7', CSVIAE_PLUGIN_NAME, array(&$this, 'show_export_page', ));
        add_submenu_page('tools.php', $this->_('CSV Import', 'CSVインポート'), $this->_('CSV Import', 'CSVインポート'), 'level_7', CSVIAE_PLUGIN_IMPORT_NAME, array(&$this, 'show_import_page', ));
    }

    /**
     * プラグインのメインページ
     */
    public function show_export_page()
    {
        require_once CSVIAE_PLUGIN_DIR . '/admin/index.php';
    }

    public function show_import_page()
    {
        require_once CSVIAE_PLUGIN_DIR . '/import/rs-csv-importer.php';
    }

    /**
     * Get admin panel URL
     */
    public function setting_url($view = '')
    {
        $query = array(
            'page' => CSVIAE_PLUGIN_NAME,
        );
        if ($view) {
            $query['view'] = $view;
        }
        return admin_url('tools.php?' . http_build_query($query));
    }

    /**
     * 管理画面CSS追加
     */
    public function head_css()
    {
        if (isset($_REQUEST["page"]) && $_REQUEST["page"] == CSVIAE_PLUGIN_NAME) {
            wp_enqueue_style("csviae_css", CSVIAE_PLUGIN_URL . '/css/style.css');
            wp_enqueue_style('jquery-ui-style', CSVIAE_PLUGIN_URL . '/css/jquery-ui.css');
        }
    }

    /*
     * 管理画面JS追加
     */
    public function head_js()
    {
        if (isset($_REQUEST["page"]) && $_REQUEST["page"] == CSVIAE_PLUGIN_NAME) {
            wp_enqueue_script('jquery');
            wp_enqueue_script("jquery-ui-core");
            wp_enqueue_script("jquery-ui-datepicker");
            wp_enqueue_script("csviae_cookie_js", CSVIAE_PLUGIN_URL . '/js/jquery.cookie.js', array('jquery'), '', true);
            wp_enqueue_script("csviae_admin_js", CSVIAE_PLUGIN_URL . '/js/admin.js', array('jquery'), time(), true);
        }
    }

    /**
     * カスタムフィールドリストを取得
     */
    public function get_custom_field_list($type)
    {
        global $wpdb;
        $value_parameter = $type;
        $pattern = "\_%";
        $query = <<<EOL
SELECT DISTINCT meta_key
FROM $wpdb->postmeta
INNER JOIN $wpdb->posts
        ON $wpdb->posts.ID = $wpdb->postmeta.post_id
WHERE $wpdb->posts.post_type = '%s'
AND $wpdb->postmeta.meta_key NOT LIKE '%s'
EOL;
        return $wpdb->get_results($wpdb->prepare($query, array($value_parameter, $pattern)), ARRAY_A);
    }

    /**
     * Summary of generate_js_params
     * @return void
     */
    function generate_js_params()
    {
        ?>
                                        <script>
                                            var ajaxUrl = '<?php echo esc_html(admin_url('admin-ajax.php')); ?>';
                                        </script>
                                <?php
    }

    function ajax_download()
    {
        // ダウンロード処理
        require_once CSVIAE_PLUGIN_DIR . '/admin/download.php';
    }

    /**
     * プラグインが有効化されたときに実行
     */
    function activationHook()
    {
        // CSVを格納するDir
        $directory_path = CSVIAE_PLUGIN_DIR . '/download/';
        if (!file_exists($directory_path)) {
            mkdir($directory_path, 0770);
        }
        chmod($directory_path, 0770);
    }
}
new CSV_Import_and_Exporter();