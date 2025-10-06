<?php

namespace App\Factory;

use App\Entity\Redmine;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Redmine>
 */
final class RedmineFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Redmine::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'projectUrl' => self::faker()->text(255),
            'accessToken' => self::faker()->text(255),
            'project' => ProjectFactory::new(),
        ];
    }
}
