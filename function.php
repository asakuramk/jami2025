<?php
if (!defined('ABSPATH')) exit;
add_editor_style();


// セッション開始
add_action('init', function () {
if (!session_id()) session_start();
});


// WPForms送信完了時の処理
add_action('wpforms_process_complete', 'wpforms_ai_detailed_scoring', 10, 4);


function wpforms_ai_detailed_scoring($fields, $entry, $form_data, $entry_id)
{
if (!defined('OPENAI_API_KEY')) {
error_log('OPENAI_API_KEY not defined.');
return;
}
$api_key = OPENAI_API_KEY;


// フィールドID
$FIELD_NAME = 1;
$FIELD_EMAIL = 2;
$FIELD_FEEDBACK = 4;


// データ取得
$name = sanitize_text_field($fields[$FIELD_NAME]['value']);
$email = sanitize_email($fields[$FIELD_EMAIL]['value']);
$feedback = sanitize_textarea_field($fields[$FIELD_FEEDBACK]['value']);


$post_id = $form_data['post_id'] ?? get_the_ID();
$title = get_the_title($post_id);


// プロンプト
$prompt = "あなたは教育評価の専門家です。\n\n"
. "以下の感想文を、次の5つの観点でそれぞれ20点満点で評価し、総合点を算出してください。\n"
. "各項目ごとにスコアと、簡潔なコメントを必ず記載してください。\n\n"
. "評価観点：\n"
. "1. タイトルとの関係性（Relevance）\n"
. "2. キーワードの出現頻度（Keyword）\n"
. "3. 論理的構成（Structure）\n"
. "4. 内容の具体性（Specificity）\n"
. "5. 表現力（Expression）\n\n"
. "形式:\n"
. "関連性: xx点（コメント）\n"
. "キーワード: xx点（コメント）\n"
. "論理性: xx点（コメント）\n"
. "具体性: xx点（コメント）\n"
. "表現力: xx点（コメント）\n"
. "合計: xx点\n\n"
. "応援メッセージ: （やる気を出す一言）\n\n"
. "タイトル：「{$title}」\n感想文:\n{$feedback}";


// OpenAI呼び出し
$response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
'headers' => [
'Authorization' => 'Bearer ' . $api_key,
'Content-Type' => 'application/json',
],
'body' => json_encode([
'model' => 'gpt-4o',
'messages' => [
['role' => 'system', 'content' => 'You are a grading assistant.'],
['role' => 'user', 'content' => $prompt]
],
'temperature' => 0.4,
'max_tokens' => 500,
]),
'timeout' => 30,
]);


// AIレスポンス処理
if (is_wp_error($response)) {
$result_text = "AI通信エラーが発生しました。";
} else {
$body = json_decode(wp_remote_retrieve_body($response), true);
$content = trim($body['choices'][0]['message']['content'] ?? '');
$result_text = $content ?: "AIからのレスポンスが不正でした。";
}


// セッションに保存
$_SESSION['ai_feedback_result'] = nl2br(esc_html($result_text));
$_SESSION['ai_title'] = $title;


// メール本文（管理者向け）
$admin_subject = '【新規提出】AI採点詳細';
$admin_body = <<<EOT
【新規提出】
名前: {$name}
メール: {$email}
タイトル: {$title}


=== 感想文 ===
{$feedback}


=== AI評価結果 ===
{$result_text}
EOT;


// メール本文（ユーザー向け）
$user_subject = '【提出完了】あなたのAI感想文評価';
$user_body = <<<EOT
{$name}様


ご提出ありがとうございました！
以下がAIによる詳細評価です。


タイトル：「{$title}」


{$result_text}


またのご利用をお待ちしております。
EOT;


// メール送信
wp_mail('asakuramk@gmail.com', $admin_subject, $admin_body);
wp_mail($email, $user_subject, $user_body);


// Google Apps Script Webhook URL（あなたのURLに差し替え）
$webhook_url = 'https://script.google.com/macros/s/AKfycby6Iz0EOMa6PrvLaZbqGVAqxqcKaHGC2ARg4w0VkHXJG8wqasClCchmivFCbptMkWy8wA/exec';


// タイトルとカテゴリのフォームID（例：ID #7 → title, ID #8 → category）
$FIELD_TITLE = 7;
$FIELD_CATEGORY = 8;


$video_title = sanitize_text_field($fields[$FIELD_TITLE]['value']);
$video_category = sanitize_text_field($fields[$FIELD_CATEGORY]['value']);


$webhook_payload = [
'name' => $name,
'email' => $email,
'title' => $video_title,
'category' => $video_category,
'feedback' => $feedback,
'ai_result' => $result_text,
];


wp_remote_post($webhook_url, [
'headers' => ['Content-Type' => 'application/json'],
'body' => json_encode($webhook_payload),
'timeout' => 15,
]);


}


// 結果表示ショートコード
add_shortcode('show_ai_score', function () {
if (!session_id()) session_start();
$title = $_SESSION['ai_title'] ?? 'タイトル取得エラー';
$result = $_SESSION['ai_feedback_result'] ?? '採点結果が見つかりませんでした。';
return "<div style='background:#f6faff;padding:20px;border-left:5px solid #3399ff;'>
<h3>🎓 評価結果</h3>
<strong>📌 タイトル：</strong>{$title}<br><br>
<pre style='white-space:pre-wrap;font-size:1.1em;'>{$result}</pre>
</div>";
});


// ショートコードを確認画面にも反映
add_filter('wpforms_frontend_confirmation_message', 'do_shortcode');
