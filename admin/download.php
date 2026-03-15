<?php
/**
 * CSV download handler for CSV Import and Exporter plugin.
 *
 * @package CSV_Import_and_Exporter
 */

wp_raise_memory_limit( 'admin' );

$errors = array();
if (
    isset($_POST['type']) &&
    is_user_logged_in() &&
    isset($_POST['_wpnonce']) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'csv_exporter' ) &&
    (current_user_can('administrator') || current_user_can('editor'))
) {
    check_admin_referer('csv_exporter');

    // Validation
    if (!isset($_POST['type'])) {
        wp_die();
    }

    global $wpdb;
    $post_type = get_post_type_object( sanitize_text_field( wp_unslash( $_POST['type'] ) ) );
    $posts_values = array();
    if (isset($_POST['posts_values']) && !empty($_POST['posts_values']) && is_array($_POST['posts_values'])) {
        foreach ($_POST['posts_values'] as $posts_value) {
            $posts_values[] = sanitize_text_field( wp_unslash( $posts_value ) );
        }
    }
    $post_status = array();
    if (isset($_POST['post_status']) && !empty($_POST['post_status']) && is_array($_POST['post_status'])) {
        foreach ($_POST['post_status'] as $post_status_value) {
            $post_status[] = sanitize_text_field( wp_unslash( $post_status_value ) );
        }
    }
    $limit = isset( $_POST['limit'] )
        ? absint( sanitize_text_field( wp_unslash( $_POST['limit'] ) ) )
        : 0;
    $offset = isset( $_POST['offset'] )
        ? absint( sanitize_text_field( wp_unslash( $_POST['offset'] ) ) )
        : 0;
    $order_by = isset( $_POST['order_by'] )
        ? sanitize_text_field( wp_unslash( $_POST['order_by'] ) )
        : 'DESC';
    $post_date_from = isset( $_POST['post_date_from'] )
        ? sanitize_text_field( wp_unslash( $_POST['post_date_from'] ) )
        : '';
    $post_date_to = isset( $_POST['post_date_to'] )
        ? sanitize_text_field( wp_unslash( $_POST['post_date_to'] ) )
        : '';
    $post_modified_from = isset( $_POST['post_modified_from'] )
        ? sanitize_text_field( wp_unslash( $_POST['post_modified_from'] ) )
        : '';
    $post_modified_to = isset( $_POST['post_modified_to'] )
        ? sanitize_text_field( wp_unslash( $_POST['post_modified_to'] ) )
        : '';
    $string_code = isset( $_POST['string_code'] )
        ? sanitize_text_field( wp_unslash( $_POST['string_code'] ) )
        : 'UTF-8';

    // SQL文作成
    $query = "";
    //プレースホルダーに代入する値
    $value_parameter = array();

    // wp_postsテーブルから指定したpost_typeの公開記事を取得
    $query_select = 'ID as post_id, post_type, post_status';
    if (!empty($posts_values)) {
        foreach ($posts_values as $key => $value) {
            $query_select .= ', ' . sanitize_key($value);
        }
    }
    $query .= "SELECT " . $query_select . " ";

    // FROM
    $query .= " FROM " . $wpdb->posts . " ";

    //ステータスのSQL
    $query_where = '';
    foreach ($post_status as $key => $status) {
        $query_where .= "'%s'";
        $value_parameter[] = $status;
        if ($status != end($post_status)) {
            $query_where .= ', ';
        }
    }
    $query .= "WHERE post_status IN (" . $query_where . ") ";

    //AND
    $query .= "AND post_type LIKE '%s' ";
    $value_parameter[] = $post_type->name;

    //期間指定-公開日
    if (!empty($post_date_from) && !empty($post_date_to)) {
        $query .= "AND post_date BETWEEN '%s' AND '%s' ";
        $value_parameter[] = $post_date_from . ' 00:00:00';
        $value_parameter[] = $post_date_to . ' 23:59:59';
    }
    //期間指定-更新日
    if (!empty($post_modified_from) && !empty($post_modified_to)) {
        $query .= "AND post_modified BETWEEN '%s' AND '%s' ";
        $value_parameter[] = $post_modified_from . ' 00:00:00';
        $value_parameter[] = $post_modified_to . ' 23:59:59';
    }
    //ソート順
    if ($order_by == 'DESC') {
        $query .= "ORDER BY post_date DESC, post_modified DESC ";
    } elseif ($order_by == 'ASC') {
        $query .= "ORDER BY post_date ASC, post_modified ASC ";
    }
    //記事数が指定されている時
    if (!empty($limit)) {
        $query .= "LIMIT %d ";
        $value_parameter[] = $limit;
    }

    //開始位置
    if (!empty($limit) && !empty($offset)) {
        $query .= "OFFSET %d ";
        $value_parameter[] = $offset;
    }

    //DBから取得
    $prepare = $wpdb->prepare($query, $value_parameter);
    $results = $wpdb->get_results($prepare, ARRAY_A);

    // カテゴリとタグのslugを追加
    foreach ( $results as $index => $result ) {
        $customs_array  = array();
        $result_post_id = absint( $result['post_id'] );

        //スラッグ
        if (isset($result['post_name'])) {
            $post_name = apply_filters('wp_csv_exporter_post_name', $result['post_name'], $result_post_id);
            $customs_array += array('post_name' => $post_name);
        }
        //タイトル
        if (isset($result['post_title'])) {
            $post_title = apply_filters('wp_csv_exporter_post_title', $result['post_title'], $result_post_id);
            $customs_array += array('post_title' => $post_title);
        }
        //本文
        if (isset($result['post_content'])) {
            $post_content = apply_filters('wp_csv_exporter_post_content', $result['post_content'], $result_post_id);
            $customs_array += array('post_content' => $post_content);
        }
        //抜粋
        if (isset($result['post_excerpt'])) {
            $post_excerpt = apply_filters('wp_csv_exporter_post_excerpt', $result['post_excerpt'], $result_post_id);
            $customs_array += array('post_excerpt' => $post_excerpt);
        }
        //ステータス
        if (isset($result['post_status'])) {
            $post_status_val = apply_filters('wp_csv_exporter_post_status', $result['post_status'], $result_post_id);
            $customs_array  += array('post_status' => $post_status_val);
        }
        //公開日時
        if (isset($result['post_date'])) {
            $post_date = apply_filters('wp_csv_exporter_post_date', $result['post_date'], $result_post_id);
            $customs_array += array('post_date' => $post_date);
        }
        //変更日時
        if (isset($result['post_modified'])) {
            $post_modified = apply_filters('wp_csv_exporter_post_modified', $result['post_modified'], $result_post_id);
            $customs_array += array('post_modified' => $post_modified);
        }
        //投稿者
        if (isset($result['post_author'])) {
            $post_author = apply_filters('wp_csv_exporter_post_author', $result['post_author'], $result_post_id);
            $customs_array += array('post_author' => $post_author);
        }
        //サムネイル
        if (!empty($_POST['post_thumbnail']) && $_POST['post_thumbnail'] == true) { // phpcs:ignore WordPress.Security.NonceVerification
            $thumbnail_id        = get_post_thumbnail_id($result_post_id);
            $thumbnail_url_array = wp_get_attachment_image_src($thumbnail_id, true);
            $thumbnail_url       = apply_filters('wp_csv_exporter_thumbnail_url', $thumbnail_url_array[0], $result_post_id);
            $customs_array      += array($_POST['post_thumbnail'] => $thumbnail_url); // phpcs:ignore WordPress.Security.NonceVerification
        }

        // $post系
        $the_post = get_post($result_post_id);
        if (!empty($_POST['post_parent']) && $_POST['post_parent'] == true) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_parent   = apply_filters('wp_csv_exporter_post_parent', $the_post->post_parent, $result_post_id);
            $customs_array += array('post_parent' => $post_parent);
        }
        if (!empty($_POST['menu_order']) && $_POST['menu_order'] == true) { // phpcs:ignore WordPress.Security.NonceVerification
            $menu_order    = apply_filters('wp_csv_exporter_menu_order', $the_post->menu_order, $result_post_id);
            $customs_array += array('menu_order' => $menu_order);
        }

        //タグ
        if ( ! empty( $_POST['post_tags'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $tags = get_the_tags($result_post_id);
            if (is_array($tags)) {
                $post_tags = wp_list_pluck( $tags, 'slug' );
                $post_tags = apply_filters('wp_csv_exporter_post_tags', $post_tags, $result_post_id);
                $post_tags = urldecode(implode(',', $post_tags));
                $customs_array += array($_POST['post_tags'] => $post_tags); // phpcs:ignore WordPress.Security.NonceVerification
            } else {
                $customs_array += array($_POST['post_tags'] => ''); // phpcs:ignore WordPress.Security.NonceVerification
            }
        }

        //カスタムタクソノミー
        if (!empty($_POST['taxonomies']) && is_array($_POST['taxonomies'])) { // phpcs:ignore WordPress.Security.NonceVerification
            foreach ($_POST['taxonomies'] as $taxonomy) { // phpcs:ignore WordPress.Security.NonceVerification
                $terms = get_the_terms($result_post_id, sanitize_key($taxonomy));

                if ($taxonomy == 'category') {
                    $head_name = 'post_category';
                } else {
                    $head_name = 'tax_' . $taxonomy;
                }

                if (is_array($terms)) {
                    $term_values = wp_list_pluck( $terms, 'slug' );
                    $term_values = apply_filters('wp_csv_exporter_' . $head_name, $term_values, $result_post_id);
                    $term_values = urldecode(implode(',', $term_values));
                } else {
                    $term_values = '';
                    $term_values = apply_filters('wp_csv_exporter_' . $head_name, $term_values, $result_post_id);
                }
                $customs_array += array($head_name => $term_values);
            }
        }

        // カスタムフィールドを取得
        $fields = get_post_custom($result_post_id);
        if (!empty($fields) && !empty($_POST['cf_fields'])) { // phpcs:ignore WordPress.Security.NonceVerification
            foreach ($_POST['cf_fields'] as $cf_key) { // phpcs:ignore WordPress.Security.NonceVerification
                //チェックしたフィールドだけを取得
                $field = isset( $fields[ $cf_key ] ) ? $fields[ $cf_key ] : null;
                //アンダーバーから始まるのは削除
                if (!preg_match('/^_.*/', $cf_key)) {
                    $field_value    = isset( $field[0] ) ? $field[0] : '';
                    $field_value    = apply_filters( 'wp_csv_exporter_' . $cf_key, $field_value, $result_post_id );
                    $customs_array += array( $cf_key => $field_value );
                }
            }
        }

        $results[ $index ] = array_merge( $result, $customs_array );

        // 1投稿処理するごとにキャッシュを解放してメモリ使用量を抑制する
        clean_post_cache( $result_post_id );
    }
    //結果があれば
    if (!empty($results)) {
        // 項目名を取得
        $head[] = array_keys($results[0]);

        // 先頭に項目名を追加
        $list = array_merge($head, $results);

        // ファイルの保存場所を設定
        $filename = 'export-' . $post_type->name . '-' . date_i18n("Y-m-d_H-i-s") . '.csv';
        $filepath = CSVIAE_PLUGIN_DIR . '/download/' . $filename;
        $fp = fopen($filepath, 'w');

        // 配列をカンマ区切りにしてファイルに書き込み
        foreach ($list as $fields) {
            //文字コード変換
            if (function_exists("mb_convert_variables")) {
                mb_convert_variables($string_code, 'UTF-8', $fields);
            }
            fputcsv($fp, $fields);
        }
        fclose($fp);

        //ダウンロードの指示
        header('Content-Type:application/octet-stream');
        header('Content-Disposition:filename=' . $filename); //ダウンロードするファイル名
        header('Content-Length:' . filesize($filepath)); //ファイルサイズを指定
        readfile($filepath); //ダウンロード
        unlink($filepath);
        exit;

    } else {
        //結果がない場合
        $errors[] = '"' . $post_type->name . '" post type has no posts.';
    }

} else {
    $errors[] = 'エラーが起きました。';
}

//エラー表示
if (!empty($errors)) {
    foreach ($errors as $key => $value) {
        echo esc_html($value) . PHP_EOL;
    }
    return;

}
