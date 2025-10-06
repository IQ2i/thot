<?php

namespace App\Factory;

use App\Entity\Project;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Project>
 */
final class ProjectFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Project::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->text(255),
        ];
    }
}
