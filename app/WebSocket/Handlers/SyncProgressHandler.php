<?php

namespace App\WebSocket\Handlers;

use BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use BeyondCode\LaravelWebSockets\WebSockets\Exceptions\WebSocketException;
use BeyondCode\LaravelWebSockets\WebSockets\Messages\PusherMessage;
use BeyondCode\LaravelWebSockets\WebSockets\Messages\PusherMessageFactory;
use BeyondCode\LaravelWebSockets\WebSockets\WebSocketHandler;
use Ratchet\ConnectionInterface;
use Ratchet\RFC6455\Messaging\MessageInterface;

class SyncProgressHandler extends WebSocketHandler
{
    public function onMessage(ConnectionInterface $connection, MessageInterface $message)
    {
        $message = PusherMessageFactory::createForMessage($message, $connection, $this->channelManager);
        
        $this->channelManager->handleMessage($connection, $message);
    }

    public function onOpen(ConnectionInterface $connection)
    {
        $this->channelManager->handleConnection($connection);
    }

    public function onClose(ConnectionInterface $connection)
    {
        $this->channelManager->handleDisconnection($connection);
    }

    public function onError(ConnectionInterface $connection, \Exception $exception)
    {
        $this->channelManager->handleError($connection, $exception);
    }
}
