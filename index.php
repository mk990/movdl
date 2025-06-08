<?php

define('ok', 'ok');
require_once 'vendor/autoload.php';
Dotenv\Dotenv::createImmutable(getcwd())->load();

use App\TelegramClient;

$options = getopt('s:hVx:', ['search:', 'help', 'version', 'proxy:']);

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

if (isset($options['x']) || isset($options['proxy'])) {
    $proxy = isset($options['x']) ? $options['x'] : $options['proxy'];
    $proxyHost = explode(':', $proxy)['1'];
    $proxyPort = explode(':', $proxy)['2'];
    // Clean up the proxy host
    $cleanProxyHost = str_replace(['//', 'http://', 'https://', 'socks://', 'socks5://'], '', $proxyHost);
    testProxy($cleanProxyHost, $proxyPort, 'http');
}

$client = new TelegramClient('my_session.madeline' . env('SESSION_PATH'));

// Connect to Telegram
if (!$client->connect($apiId, $apiHash)) {
    echo "❌ Failed to connect to Telegram.\n";
    echo "\nTroubleshooting tips:\n";
    echo "1. Check if your proxy is running and accessible\n";
    echo "2. Try without proxy (comment out SOCKS_PROXY in .env)\n";
    echo "3. Verify your API credentials\n";
    echo "4. Check firewall/network restrictions\n";
}
echo "\n=== Connection successful! ===\n\n";
// echo "Getting dialogs...\n";
$client->getDialogs(false);

echo "\nGetting history...\n";

if (isset($options['s']) || isset($options['search'])) {
    $search = isset($options['s']) ? $options['s'] : $options['search'];
    echo "Search For: $search\n";
    $results = $client->sendBotInlineQuery(1396990198, '@alphadlbot', $search);
    $options = [];
    foreach ($results['results'] as $i => $item) {
        $options[$i] = $item['title'] ?? ($item['description'] ?? $item['id']);
        $rows = $item['send_message']['reply_markup']['rows'] ?? [];
        $url = ($rows[0]['buttons'][0]['url']);
        $client->sendBotMessage(1396990198, $client->parseTelegramBotLink($url)['start_parameter']);

        sleep(2);
        $chatHistory = $client->getHistory(1396990198, 1);
        $messageId = $chatHistory['messages'][0]['id'];

        echo 'Message Id: ' . $messageId . PHP_EOL;
        $dataBytes = $chatHistory['messages'][0]['reply_markup']['rows'][5]['buttons'][0];
        $dataBytes->click();
        sleep(2);
        $chatHistory = $client->getHistory(1396990198, 1);
        $dataBytes = $chatHistory['messages'][0]['reply_markup']['rows'][0]['buttons'][0];
        $dataBytes->click();
        sleep(2);
        $chatHistory = $client->getHistory(1396990198, 1);
        $dataBytes = $chatHistory['messages'][0]['reply_markup']['rows'][2]['buttons'][0];
        $dataBytes->click();
        sleep(2);
        $chatHistory = $client->getHistory(8179612995, 1);
        var_export($chatHistory['messages'][0]['media']['document']);
        // Then try direct access
        // var_export($dataBytes->getId());
        exit;
    }
    var_export($results);
    exit;
}

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
