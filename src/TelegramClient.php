<?php

namespace App;

/**
 * Fixed Telegram MadelineProto Connection Script
 * This script demonstrates how to connect to Telegram using MadelineProto with proper proxy configuration
 */

use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Connection;
use danog\MadelineProto\Stream\Proxy\HttpProxy;
use danog\MadelineProto\Stream\Proxy\SocksProxy;
use Exception;

class TelegramClient
{
    private $MadelineProto;
    private $sessionFile;

    public function __construct($sessionFile = 'session.madeline')
    {
        $this->sessionFile = $sessionFile;
    }

    /**
     * Initialize and connect to Telegram
     */
    public function connect($apiId, $apiHash)
    {
        try {
            $settings = new Settings();

            // Configure proxy if specified
            $proxyUrl = env('SOCKS_PROXY');
            $proxyPort = env('SOCKS_PORT', '1080');

            if (!empty($proxyUrl)) {
                echo "Configuring proxy: $proxyUrl:$proxyPort\n";

                $connection = new Connection();

                // Parse proxy URL to determine type
                if (strpos($proxyUrl, 'http://') === 0) {
                    // HTTP proxy
                    $proxyHost = str_replace('http://', '', $proxyUrl);
                    $connection->addProxy(HttpProxy::class, [
                        'address' => $proxyHost,
                        'port'    => (int)$proxyPort,
                    ]);
                    echo "Using HTTP proxy: $proxyHost:$proxyPort\n";
                } elseif (strpos($proxyUrl, 'socks://') === 0 || strpos($proxyUrl, 'socks5://') === 0) {
                    // SOCKS proxy
                    $proxyHost = preg_replace('/^socks5?:\/\//', '', $proxyUrl);
                    $connection->addProxy(SocksProxy::class, [
                        'address' => $proxyHost,
                        'port'    => (int)$proxyPort,
                    ]);
                    echo "Using SOCKS proxy: $proxyHost:$proxyPort\n";
                } else {
                    // Assume it's just the IP/hostname without protocol
                    $proxyHost = $proxyUrl;
                    // Try HTTP proxy first (more common for port 8086)
                    $connection->addProxy(HttpProxy::class, [
                        'address' => $proxyHost,
                        'port'    => (int)$proxyPort,
                    ]);
                    echo "Using HTTP proxy (assumed): $proxyHost:$proxyPort\n";
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
                ->setApiId((int)$apiId)
                ->setApiHash($apiHash);
            $settings->setAppInfo($app);

            // Connection settings
            $connectionSettings = $settings->getConnection();
            $connectionSettings->setTimeout(30); // 30 seconds timeout

            // Disable IPv6 if causing issues
            $connectionSettings->setIpv6(false);

            $settings->setConnection($connectionSettings);

            // Create MadelineProto instance
            echo "Initializing MadelineProto...\n";
            $this->MadelineProto = new API($this->sessionFile, $settings);

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
    public function sendBotMessage($botId, $message)
    {
        try {
            $result = $this->MadelineProto->messages->sendMessage([
                'peer'    => (int)$botId,
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
    public function getDialogs($showMessage = true)
    {
        try {
            $dialogs = $this->MadelineProto->messages->getDialogs([]);

            if ($showMessage) {
                echo 'Found ' . count($dialogs['dialogs']) . " dialogs\n";
            }

            foreach ($dialogs['dialogs'] as $dialog) {
                // Get chat info
                if (!isset($dialog['peer'])) {
                    continue; // Skip if no peer info
                }
                // want to check if start with - callback
                if (strpos($dialog['peer'], '-') === 0) {
                    // This is a channel or group
                    $peer = $dialog['peer'];
                    if ($showMessage) {
                        echo 'Channel/Group: ' . $dialog['peer'] . "\n";
                    }
                } else {
                    // This is a user
                    $peer = $dialog['peer'];
                    if ($showMessage) {
                        echo 'User/bot: ' . $dialog['peer'] . "\n";
                    }
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
