<?php

/**
 * FIXME: fix bugs to this file
 * Docs: https://github.com/danog/MadelineProto/blob/v8/examples/bot.php
 * https://docs.madelineproto.xyz/docs/UPDATES.html
*/

require_once 'vendor/autoload.php';

use danog\MadelineProto\EventHandler\Attributes\Cron;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Plugin\RestartPlugin;
use danog\MadelineProto\SimpleEventHandler;
use danog\MadelineProto\ParseMode;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;

/**
 * Event handler for incoming messages
 */
class TelegramEventHandler extends SimpleEventHandler
{
    // !!! Change this to your username !!!
    public const ADMIN = '@EmmyDev';

    /**
     * @var array<int, bool>
     */
    private array $notifiedChats = [];

    /**
     * Returns a list of names for properties that will be automatically saved to the session database (MySQL/postgres/redis if configured, the session file otherwise).
     */
    public function __sleep(): array
    {
        return ['notifiedChats'];
    }

    /**
     * Get peer(s) where to report errors.
     *
     * @return int|string|array
     */
    public function getReportPeers()
    {
        return [self::ADMIN];
    }

    /**
     * Initialization logic.
     */
    public function onStart(): void
    {
        $this->logger('The bot was started!');
        $this->logger($this->getFullInfo('MadelineProto'));

        $this->sendMessageToAdmins('The bot was started!');
    }

    /**
     * Returns a set of plugins to activate.
     */
    public static function getPlugins(): array
    {
        return [
            // Offers a /restart command to admins that can be used to restart the bot, applying changes.
            // Make sure to run in a bash while loop when running via CLI to allow self-restarts.
            RestartPlugin::class,
        ];
    }

    /**
     * This cron function will be executed forever, every 60 seconds.
     */
    #[Cron(period: 60.0)]
    public function cron1(): void
    {
        $this->sendMessageToAdmins('The bot is online, current time ' . date(DATE_RFC850) . '!');
    }

    /**
     * Handle incoming updates from users, chats and channels.
     */
    #[Handler]
    public function handleMessage(Incoming&Message $message): void
    {
        // In this example code, send the "This userbot is powered by MadelineProto!" message only once per chat.
        // Ignore all further messages coming from this chat.
        if (!isset($this->notifiedChats[$message->chatId])) {
            $this->notifiedChats[$message->chatId] = true;

            $message->reply(
                message: 'This userbot is powered by [MadelineProto](https://t.me/MadelineProto)!',
                parseMode: ParseMode::MARKDOWN
            );
        }
    }
}
