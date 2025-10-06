<?php

namespace App\Factory;

use App\Entity\GoogleDoc;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<GoogleDoc>
 */
final class GoogleDocFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return GoogleDoc::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'url' => self::faker()->text(255),
            'project' => ProjectFactory::new(),
        ];
    }
}
