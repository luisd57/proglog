<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api;

use App\Tests\Helper\ApiTestCase;

final class HealthControllerTest extends ApiTestCase
{
    public function testHealthReturnsOkStatus(): void
    {
        $this->jsonRequest('GET', '/api/health');

        $this->assertResponseIsSuccessful();
        $this->assertSame('ok', $this->getResponseData()['status']);
    }

    public function testHealthResponseContainsTimestamp(): void
    {
        $this->jsonRequest('GET', '/api/health');

        $this->assertArrayHasKey('timestamp', $this->getResponseData());
    }
}
