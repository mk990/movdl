<?php
/**
 * Fixed Telegram MadelineProto Connection Script
 * This script demonstrates how to connect to Telegram using MadelineProto with proper proxy configuration
 */

require_once 'vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Connection;
use danog\MadelineProto\Stream\Proxy\HttpProxy;
use danog\MadelineProto\Stream\Proxy\SocksProxy;

class TelegramClient
{
    private $MadelineProto;
    private $session_file;

    public function __construct($session_file = 'session.madeline')
    {
        $this->session_file = $session_file;
    }

    /**
     * Initialize and connect to Telegram
     */
    public function connect($api_id, $api_hash)
    {
        try {
            $settings = new Settings();

            // Configure proxy if specified
            $proxy_url = env('SOCKS_PROXY');
            $proxy_port = env('SOCKS_PORT', '1080');

            if (!empty($proxy_url)) {
                echo "Configuring proxy: $proxy_url:$proxy_port\n";

                $connection = new Connection();

                // Parse proxy URL to determine type
                if (strpos($proxy_url, 'http://') === 0) {
                    // HTTP proxy
                    $proxy_host = str_replace('http://', '', $proxy_url);
                    $connection->addProxy(HttpProxy::class, [
                        'address' => $proxy_host,
                        'port'    => (int)$proxy_port,
                    ]);
                    echo "Using HTTP proxy: $proxy_host:$proxy_port\n";
                } elseif (strpos($proxy_url, 'socks://') === 0 || strpos($proxy_url, 'socks5://') === 0) {
                    // SOCKS proxy
                    $proxy_host = preg_replace('/^socks5?:\/\//', '', $proxy_url);
                    $connection->addProxy(SocksProxy::class, [
                        'address' => $proxy_host,
                        'port'    => (int)$proxy_port,
                    ]);
                    echo "Using SOCKS proxy: $proxy_host:$proxy_port\n";
                } else {
                    // Assume it's just the IP/hostname without protocol
                    $proxy_host = $proxy_url;
                    // Try HTTP proxy first (more common for port 8086)
                    $connection->addProxy(HttpProxy::class, [
                        'address' => $proxy_host,
                        'port'    => (int)$proxy_port,
                    ]);
                    echo "Using HTTP proxy (assumed): $proxy_host:$proxy_port\n";
                }

                $settings->setConnection($connection);
            } else {
                echo "No proxy configured\n";
            }

            // Logger settings - reduce verbosity to avoid spam
            $loggerSettings = $settings->getLogger()
                ->setLevel(Logger::LEVEL_WARNING) // Changed from VERBOSE to WARNING
                ->setType(Logger::FILE_LOGGER)
                ->setExtra('MadelineProto.log')
                ->setMaxSize(20 * 1024 * 1024); // 20 MB
            $settings->setLogger($loggerSettings);

            // App info
            $app = (new AppInfo())
                ->setApiId((int)$api_id)
                ->setApiHash($api_hash);
            $settings->setAppInfo($app);

            // Connection settings
            $connection_settings = $settings->getConnection();
            $connection_settings->setTimeout(30); // 30 seconds timeout

            // Disable IPv6 if causing issues
            $connection_settings->setIpv6(false);

            $settings->setConnection($connection_settings);

            // Create MadelineProto instance
            echo "Initializing MadelineProto...\n";
            $this->MadelineProto = new API($this->session_file, $settings);

            // Start the client
            echo "Starting client...\n";
            $this->MadelineProto->start();

            // Get self info to verify connection
            $me = $this->MadelineProto->getSelf();

            echo "Successfully connected to Telegram!\n";
            echo 'Logged in as: ' . $me['first_name'] . ' ' . ($me['last_name'] ?? '') . "\n";
            echo 'Username: @' . ($me['username'] ?? 'No username') . "\n";
            echo 'Phone: ' . ($me['phone'] ?? 'No phone') . "\n";
            echo 'User ID: ' . $me['id'] . "\n";

            return true;
        } catch (Exception $e) {
            echo 'Error connecting to Telegram: ' . $e->getMessage() . "\n";
            echo 'Error details: ' . $e->getTraceAsString() . "\n";
            return false;
        }
    }

    /**
     * Send a message to a chat
     */
    public function sendMessage($peer, $message)
    {
        try {
            echo "Sending message to $peer: $message\n";
            $result = $this->MadelineProto->messages->sendMessage([
                'peer'    => $peer,
                'message' => "$message"
            ]);

            echo "Message sent successfully!\n";
            return $result;
        } catch (Exception $e) {
            echo 'Error sending message: ' . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Send a message to a bot
     */
    public function sendBotMessage($bot_id, $message)
    {
        try {
            $result = $this->MadelineProto->messages->sendMessage([
                'peer'    => (int)$bot_id,
                'message' => (string)$message
            ]);

            echo "✅ Message sent successfully to bot!\n";
            return $result;
        } catch (Exception $e) {
            echo '❌ Error sending message to bot: ' . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Get chat history
     */
    public function getHistory($peer, $limit = 100)
    {
        try {
            $messages = $this->MadelineProto->messages->getHistory([
                'peer'  => $peer,
                'limit' => $limit
            ]);

            echo 'Retrieved ' . count($messages['messages']) . " messages\n";
            return $messages;
        } catch (Exception $e) {
            echo 'Error getting history: ' . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Get all dialogs (chats)
     */
    public function getDialogs()
    {
        try {
            $dialogs = $this->MadelineProto->messages->getDialogs([]);

            echo 'Found ' . count($dialogs['dialogs']) . " dialogs\n";

            foreach ($dialogs['dialogs'] as $dialog) {
                // Get chat info
                if (!isset($dialog['peer'])) {
                    continue; // Skip if no peer info
                }
                // want to check if start with - callback
                if (strpos($dialog['peer'], '-') === 0) {
                    // This is a channel or group
                    $peer = $dialog['peer'];
                    echo 'Channel/Group: ' . $dialog['peer'] . "\n";
                } else {
                    // This is a user
                    $peer = $dialog['peer'];
                    echo 'User/bot: ' . $dialog['peer'] . "\n";
                }

                // echo  $peer;
            }

            return $dialogs;
        } catch (Exception $e) {
            echo 'Error getting dialogs: ' . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Get peer information
     */
    public function getPeerInfo($peer)
    {
        try {
            $info = $this->MadelineProto->getInfo($peer);
            return $info;
        } catch (Exception $e) {
            echo 'Error getting peer info: ' . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Get MadelineProto instance
     */
    public function getAPI()
    {
        return $this->MadelineProto;
    }

    /**
     * Parse Telegram bot deep link
     */
    public function parseTelegramBotLink($url)
    {
        // Handle different formats:
        // http://t.me/botname?start=param
        // https://t.me/botname?start=param
        // https://telegram.me/botname?start=param

        $patterns = [
            '/(?:https?:\/\/)?(?:t\.me|telegram\.me)\/([a-zA-Z0-9_]+)\?start=([a-zA-Z0-9_]+)/',
            '/(?:https?:\/\/)?(?:t\.me|telegram\.me)\/([a-zA-Z0-9_]+)\?start=([a-zA-Z0-9_-]+)/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return [
                    'bot_username'    => $matches[1],
                    'start_parameter' => '/start ' . $matches[2]
                ];
            }
        }

        return false;
    }

    /**
      * Handle updates (incoming messages)
      */
    public function handleUpdates()
    {
        // TelegramEventHandler::startAndLoop('my_session.madeline', $setting);
        echo "Listening for updates...\n";
    }
}

/**
 * Load environment variables from .env file
 */
function loadEnvFile($file = '.env')
{
    if (!file_exists($file)) {
        return false;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue;
        } // Skip comments

        if (strpos($line, '=') === false) {
            continue;
        } // Skip lines without =

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'"); // Remove quotes and whitespace

        if (!empty($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    return true;
}

/**
 * Get environment variable
 */
function env($key, $default = null)
{
    $env = getenv($key);
    return $env !== false ? $env : $default;
}

/**
 * Test proxy connection
 */
function testProxy($proxy_host, $proxy_port, $proxy_type = 'http')
{
    echo "Testing $proxy_type proxy connection to $proxy_host:$proxy_port...\n";

    // Create a simple socket connection test
    $context = stream_context_create();

    if ($proxy_type === 'http') {
        // For HTTP proxy, try to connect directly first
        $fp = @fsockopen($proxy_host, $proxy_port, $errno, $errstr, 10);
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

/**
 * Create example .env file
 */
function createExampleEnvFile()
{
    $envContent = '# Telegram API Credentials
# Get these from https://my.telegram.org
TELEGRAM_API_ID=your_api_id_here
TELEGRAM_API_HASH=your_api_hash_here

# Proxy settings (choose one)
# For HTTP proxy:
SOCKS_PROXY=127.0.0.1
SOCKS_PORT=8086

# For SOCKS5 proxy:
# SOCKS_PROXY=socks5://127.0.0.1
# SOCKS_PORT=1080

# Leave empty to connect directly (no proxy)
# SOCKS_PROXY=
# SOCKS_PORT=
';

    file_put_contents('.env.example', $envContent);
    echo "Created .env.example file. Copy it to .env and add your credentials.\n";
}

// Example usage
function main()
{
    echo "=== Fixed Telegram MadelineProto Client ===\n\n";

    // Try to load .env file first
    if (loadEnvFile('.env')) {
        echo "✓ Loaded .env file\n";
    } else {
        echo "⚠ .env file not found\n";
        createExampleEnvFile();
    }

    // Get API credentials from environment variables
    $api_id = env('TELEGRAM_API_ID');
    $api_hash = env('TELEGRAM_API_HASH');

    if (empty($api_id) || empty($api_hash)) {
        echo "\n❌ API credentials not found!\n";
        echo "Please check your .env file or environment variables.\n";
        return;
    }
    echo "✓ API credentials found\n";

    // Test proxy if configured
    $proxy_host = env('SOCKS_PROXY');
    $proxy_port = env('SOCKS_PORT', '1080');

    if (!empty($proxy_host)) {
        // Clean up the proxy host
        $clean_proxy_host = str_replace(['http://', 'https://', 'socks://', 'socks5://'], '', $proxy_host);
        testProxy($clean_proxy_host, $proxy_port, 'http');
    }

    $client = new TelegramClient('my_session.madeline');

    // Connect to Telegram
    if ($client->connect($api_id, $api_hash)) {
        echo "\n=== Connection successful! ===\n\n";

        // Example operations

        // echo "Getting dialogs...\n";
        // $client->getDialogs();
        // return;

        echo "\nGetting history...\n";

        $chat_history = $client->getHistory(-1001209723598, 3);
        // want to save chat history to file
        foreach ($chat_history['messages'] as $item) {
            if (strpos($item['message'], 'سریال') !== false) {
                $bot_link = isset($item['entities'][0]['url']) ? $item['entities'][0]['url'] : 'No link';
                $client->sendBotMessage(1396990198, $client->parseTelegramBotLink($bot_link)['start_parameter']);
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
}

function openLinkBot($url)
{
    try {
        if (empty($url) || $url == 'No link' || strpos($url, 'http') === false) {
            echo "No link provided.\n";
            return;
        }
        // Create a new TelegramClient instance
        $client = new TelegramClient('my_session.madeline');
        // dynmic Id bot
        $client->sendMessage(1396990198, (string)$url);
    } catch (\Throwable $th) {
        echo 'Error: ' . $th->getMessage() . "\n";
    }
}

// Run the script
if (php_sapi_name() === 'cli') {
    main();
} else {
    echo "This script should be run from command line.\n";
}
