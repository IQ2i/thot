<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserControllerTest extends WebTestCase
{
    public function testUserIndexRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/users');

        $this->assertResponseRedirects('/security/login');
    }

    public function testUserIndexShowsUsersList(): void
    {
        $client = static::createClient();
        $client->request('GET', '/users');

        $this->assertResponseRedirects('/security/login');
    }

    public function testUserNewRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/users/new');

        $this->assertResponseRedirects('/security/login');
    }

    public function testUserNewDisplaysForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/users/new');

        $this->assertResponseRedirects('/security/login');
    }

    public function testUserEditRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/users/1/edit');

        $this->assertResponseRedirects('/security/login');
    }

    public function testUserDeleteRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/users/delete/1');

        $this->assertResponseRedirects('/security/login');
    }

    public function testUserNotFoundRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/users/999/edit');

        $this->assertResponseRedirects('/security/login');
    }
}
