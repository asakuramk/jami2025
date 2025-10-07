<?php
define('WP_CACHE', true);

/**------------------------------------------------
 * 環境変数の読み込み設定 (.env)
 *------------------------------------------------*/
$env_path = dirname(__DIR__) . '/.env';
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, "\"'");
        putenv("$name=$value");
    }
}

/** Database settings **/
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_HOST', getenv('DB_HOST'));
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

/** Authentication Keys and Salts **/
define('AUTH_KEY',          '************************************');
define('SECURE_AUTH_KEY',   '************************************');
define('LOGGED_IN_KEY',     '************************************');
define('NONCE_KEY',         '************************************');
define('AUTH_SALT',         '************************************');
define('SECURE_AUTH_SALT',  '************************************');
define('LOGGED_IN_SALT',    '************************************');
define('NONCE_SALT',        '************************************');
define('WP_CACHE_KEY_SALT', '************************************');

/** Table prefix **/
$table_prefix = 'wp20250618203430_';

/** Debug settings **/
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);
define('WP_DEBUG_LOG', true);

/** OpenAI API key **/
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY'));

/** Absolute path **/
if ( ! defined('ABSPATH') ) {
	define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files **/
require_once ABSPATH . 'wp-settings.php';
