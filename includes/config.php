<?php
/**
 * Tobby's Suite - Global Configuration
 */

// 1. Load .env file (if it exists)
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (empty($line) || strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        @putenv("$name=$value");
    }
}
loadEnv(__DIR__ . '/../.env');

// 2. Database Constants
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'tobbys_suite');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// 3. AI Infrastructure (Google AI Studio Local Alternative)
//define('LOCAL_AI_ENDPOINT', $_ENV['LOCAL_AI_ENDPOINT'] ?? 'http://localhost:11434/api/generate'); // Ollama default
//define('AI_MODEL', $_ENV['AI_MODEL'] ?? 'gemma2');

// 4. Payment & Suite Details
define('PAYSTACK_PUBLIC_KEY', $_ENV['PAYSTACK_PUBLIC_KEY'] ?? 'pk_test_xxxx');
define('PAYSTACK_SECRET_KEY', $_ENV['PAYSTACK_SECRET_KEY'] ?? 'sk_test_xxxx');
define('CURRENCY_SYMBOL', '₦');
define('SUITE_NAME', "Tobby's Suite");

// Supabase Keys
define('SUPABASE_URL', $_ENV['SUPABASE_URL'] ?: 'https://your-project.supabase.co');
define('SUPABASE_ANON_KEY', $_ENV['SUPABASE_ANON_KEY'] ?: 'your-anon-key');

// 5. System Paths & Timezone
date_default_timezone_set('Africa/Lagos');
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/');

// 6. Error Reporting (Enable for development)
ini_set('display_errors', 1);
error_reporting(E_ALL);