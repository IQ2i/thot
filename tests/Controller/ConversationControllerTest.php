<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ConversationControllerTest extends WebTestCase
{
    public function testConversationNewRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/conversations/new?project=1');

        $this->assertResponseRedirects('/security/login');
    }

    public function testConversationNewRequiresValidProject(): void
    {
        $client = static::createClient();
        $client->request('GET', '/conversations/new?project=999');

        $this->assertResponseRedirects('/security/login');
    }

    public function testConversationNewRequiresProjectParameter(): void
    {
        $client = static::createClient();
        $client->request('GET', '/conversations/new');

        $this->assertResponseRedirects('/security/login');
    }

    public function testConversationDetailRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/conversations/1');

        $this->assertResponseRedirects('/security/login');
    }

    public function testConversationDetailNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/conversations/999');

        $this->assertResponseRedirects('/security/login');
    }
}
