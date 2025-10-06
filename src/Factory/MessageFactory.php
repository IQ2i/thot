<?php

namespace App\Factory;

use App\Entity\Message;
use App\Enum\MessageType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Message>
 */
final class MessageFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Message::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'type' => self::faker()->randomElement(MessageType::cases()),
            'content' => self::faker()->text(),
        ];
    }
}
