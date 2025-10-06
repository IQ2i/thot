<?php

namespace App\Factory;

use App\Entity\Conversation;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Conversation>
 */
final class ConversationFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Conversation::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'project' => ProjectFactory::new(),
        ];
    }
}
