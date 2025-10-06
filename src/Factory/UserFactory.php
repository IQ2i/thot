<?php

namespace App\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'username' => self::faker()->userName(),
            'email' => self::faker()->safeEmail(),
            'password' => '$2y$13$4UZnWm2.L9uoDT/UsoJOv.XP3sGcpyVgjCd4ZK0ji.e8XIrBy/hxu', // password
            'roles' => [],
        ];
    }
}
