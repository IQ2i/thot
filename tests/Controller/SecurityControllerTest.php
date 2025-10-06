<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/security/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorTextContains('h2', 'Sign in to your account');
    }

    public function testLoginFormDisplaysCorrectFields(): void
    {
        $client = static::createClient();
        $client->request('GET', '/security/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
        $this->assertSelectorExists('input[name="_csrf_token"]');
    }

    public function testLogoutRedirectsToHomepage(): void
    {
        $client = static::createClient();

        $client->request('GET', '/security/logout');

        $this->assertResponseRedirects('/');
    }
}
