<?php

namespace App\Factory;

use App\Entity\Gitlab;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Gitlab>
 */
final class GitlabFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Gitlab::class;
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
