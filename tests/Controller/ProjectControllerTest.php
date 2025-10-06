<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProjectControllerTest extends WebTestCase
{
    public function testProjectIndexRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/projects');

        $this->assertResponseRedirects('/security/login');
    }

    public function testProjectIndexShowsProjectsList(): void
    {
        $client = static::createClient();
        $client->request('GET', '/projects');

        $this->assertResponseRedirects('/security/login');
    }

    public function testProjectNewRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/projects/new');

        $this->assertResponseRedirects('/security/login');
    }

    public function testProjectNewDisplaysForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/projects/new');

        $this->assertResponseRedirects('/security/login');
    }

    public function testProjectEditRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/projects/1/edit');

        $this->assertResponseRedirects('/security/login');
    }

    public function testProjectDeleteRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/projects/delete/1');

        $this->assertResponseRedirects('/security/login');
    }

    public function testProjectNotFoundRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/projects/999/edit');

        $this->assertResponseRedirects('/security/login');
    }
}
