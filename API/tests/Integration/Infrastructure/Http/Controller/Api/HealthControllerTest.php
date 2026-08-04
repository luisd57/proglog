<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testHealthReturnsOkStatus(): void
    {
        $this->client->request('GET', '/api/health');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('ok', $data['status']);
    }

    public function testHealthResponseContainsTimestamp(): void
    {
        $this->client->request('GET', '/api/health');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('timestamp', $data);
    }
}
