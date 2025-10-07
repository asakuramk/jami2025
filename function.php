<?php
// セッション開始
add_action('init', function () {
    if (!session_id()) {
        session_start();
    }
});

// WPForms送信完了時のAI評価処理
add_action('wpforms_process_complete', 'wpforms_ai_detailed_scoring', 10, 4);

function wpforms_ai_detailed_scoring($fields, $entry, $form_data, $entry_id)
{
    if (!defined('OPENAI_API_KEY')) return;
    $api_key = OPENAI_API_KEY;

    // フィールドID定義
    $FIELD_NAME     = 1;
    $FIELD_EMAIL    = 2;
    $FIELD_FEEDBACK = 4;
    $FIELD_TITLE    = 7;
    $FIELD_CATEGORY = 8;

    // 入力取得
    $name     = sanitize_text_field($fields[$FIELD_NAME]['value']);
    $email    = sanitize_email($fields[$FIELD_EMAIL]['value']);
    $feedback = sanitize_textarea_field($fields[$FIELD_FEEDBACK]['value']);
    $title    = sanitize_text_field($fields[$FIELD_TITLE]['value']);
    $category = sanitize_text_field($fields[$FIELD_CATEGORY]['value']);

    // ChatGPTプロンプト
    $prompt = "あなたは教育評価の専門家です。\n\n"
        . "以下の感想文を、次の5つの観点でそれぞれ20点満点で評価し、総合点を算出してください。\n"
        . "最後に「合計: xx点」の形式で出力してください。\n"
        . "{$name}さんに語りかけるように、前向きで励ましのコメントを添えてください。\n\n"
        . "評価観点：\n"
        . "1. タイトルとの関係性\n"
        . "2. キーワードの出現頻度\n"
        . "3. 論理的構成\n"
        . "4. 内容の具体性\n"
        . "5. 表現力\n\n"
        . "タイトル：「{$title}」\n感想文:\n{$feedback}";

    // ChatGPT API呼び出し
    $response = wp_remote_post(
        'https://api.openai.com/v1/chat/completions',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages'  => [
                    ['role' => 'system', 'content' => 'You are a grading assistant.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.4,
                'max_tokens'  => 500,
            ]),
            'timeout' => 30,
        ]
    );

    // レスポンス処理
    if (is_wp_error($response)) {
        $result_text = "AI通信エラーが発生しました。";
    } else {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $result_text = trim($body['choices'][0]['message']['content'] ?? "AIからのレスポンスが不正でした。");
    }

    // 点数抽出（xx点 or xx/100点 に対応）
    if (preg_match('/合計[:：]\s*([0-9]{1,3})(?:\/100)?点/u', $result_text, $m)) {
        $total_score = $m[1];
    } else {
        $total_score = '';
    }

    // セッション保存（nl2brは出力時に処理）
    $_SESSION['ai_feedback_result'] = esc_html($result_text);
    $_SESSION['ai_title']           = $title;

    // ユーザー宛メール送信
    $user_subject = '【提出完了】あなたのAI感想文評価';
    $user_body    = "{$name}様\n\n"
                 . "ご提出ありがとうございました！以下がAIによるフィードバックです。\n\n"
                 . "📌 タイトル：「{$title}」\n"
                 . "📊 評価結果：\n\n"
                 . "{$result_text}\n\n"
                 . "またのご利用をお待ちしております。";
    wp_mail($email, $user_subject, $user_body);

    // Googleスプレッドシート（GAS）連携
    $webhook_url = 'https://script.google.com/macros/s/AKfycbwvZv6l1mv2iY9BJHG-xhCanQL2Lm-RaSjTc4pf4zZ6OCdhqMM9fIvhklZ7LlUPEx9p7g/exec';
    $payload = [
        'name'      => $name,
        'email'     => $email,
        'category'  => $category,
        'title'     => $title,
        'feedback'  => $feedback,
        'ai_result' => $total_score,
    ];
    wp_remote_post($webhook_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode($payload),
        'timeout' => 15,
    ]);
}

// ショートコード：評価結果表示
add_shortcode('show_ai_score', function () {
    if (!session_id()) session_start();
    $title  = $_SESSION['ai_title'] ?? 'タイトル取得エラー';
    $result_raw = $_SESSION['ai_feedback_result'] ?? '採点結果が見つかりませんでした。';
    $result = nl2br($result_raw);  // 改行処理

    return "<div style='background:#f6faff;padding:20px;border-left:5px solid #3399ff;'>
        <h3>🎓 評価結果</h3>
        <strong>📌 タイトル：</strong>" . esc_html($title) . "<br><br>
        <div style='font-size:1.1em; line-height:1.6;'>{$result}</div>
    </div>";
});

// WPForms 確認画面にもショートコードを反映
add_filter('wpforms_frontend_confirmation_message', 'do_shortcode');

// 管理者メール通知を抑止
add_action('wpforms_process', function($fields, $entry, $form_data) {
    add_filter('wpforms_email_send', function($email, $email_obj) {
        if ($email['recipient'] === get_option('admin_email')) {
            return false;
        }
        return $email;
    }, 10, 2);
}, 10, 3);
