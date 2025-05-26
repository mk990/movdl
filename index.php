<?php

define('ok', 'ok');
require_once 'vendor/autoload.php';
Dotenv\Dotenv::createImmutable(getcwd())->load();

use App\TelegramClient;

$options = getopt('hV', ['help', 'version']);

if (isset($options['h']) || isset($options['help'])) {
    echo "Usage: php script.php [--help] [--version]\n";
    exit;
}

if (isset($options['V']) || isset($options['version'])) {
    echo "Version 0.0.1\n";
    exit;
}

// Get API credentials from environment variables
$apiId = env('TELEGRAM_API_ID');
$apiHash = env('TELEGRAM_API_HASH');

if (empty($apiId) || empty($apiHash)) {
    echo "\n❌ API credentials not found!\n";
    echo "Please check your .env file or environment variables.\n";
    return;
}
echo "✓ API credentials found\n";

// Test proxy if configured
$proxyHost = env('SOCKS_PROXY');
$proxyPort = env('SOCKS_PORT', '1080');

if (!empty($proxyHost)) {
    // Clean up the proxy host
    $cleanProxyHost = str_replace(['http://', 'https://', 'socks://', 'socks5://'], '', $proxyHost);
    testProxy($cleanProxyHost, $proxyPort, 'http');
}

$client = new TelegramClient('my_session.madeline' . env('SESSION_PATH'));

// Connect to Telegram
if ($client->connect($apiId, $apiHash)) {
    echo "\n=== Connection successful! ===\n\n";

    // Example operations

    // echo "Getting dialogs...\n";
    $client->getDialogs(false);

    echo "\nGetting history...\n";

    $chatHistory = $client->getHistory(-1001209723598, 3);
    // want to save chat history to file
    foreach ($chatHistory['messages'] as $item) {
        if (strpos($item['message'], 'سریال') !== false) {
            $botLink = isset($item['entities'][0]['url']) ? $item['entities'][0]['url'] : 'No link';
            $client->sendBotMessage(1396990198, $client->parseTelegramBotLink($botLink)['start_parameter']);
        }
    }
} else {
    echo "❌ Failed to connect to Telegram.\n";
    echo "\nTroubleshooting tips:\n";
    echo "1. Check if your proxy is running and accessible\n";
    echo "2. Try without proxy (comment out SOCKS_PROXY in .env)\n";
    echo "3. Verify your API credentials\n";
    echo "4. Check firewall/network restrictions\n";
}

/**
 * Get environment variable
 */
function env($key, $default = null)
{
    $env = $_ENV[$key];
    return $env !== false ? $env : $default;
}

/**
 * Test proxy connection
 */
function testProxy($proxyHost, $proxyPort, $proxyType = 'http')
{
    echo "Testing $proxyType proxy connection to $proxyHost:$proxyPort...\n";

    // Create a simple socket connection test
    $context = stream_context_create();

    if ($proxyType === 'http') {
        // For HTTP proxy, try to connect directly first
        $fp = @fsockopen($proxyHost, $proxyPort, $errno, $errstr, 10);
        if ($fp) {
            fclose($fp);
            echo "✓ HTTP proxy connection successful\n";
            return true;
        } else {
            echo "✗ HTTP proxy connection failed: $errstr ($errno)\n";
            return false;
        }
    }

    return false;
}
