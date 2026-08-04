<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

/**
 * Base class for HTTP-level integration tests. No auth helpers: ProgLog is a
 * single-user LAN tool with no authentication (deliberate kit deviation).
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            $this->entityManager->close();
        } finally {
            parent::tearDown();
        }
    }

    protected function jsonRequest(string $method, string $uri, array $data = []): void
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        $this->client->request($method, $uri, [], [], $headers, json_encode($data));
    }

    protected function getResponseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true);
    }

    /**
     * Replace the container's ClockInterface with a frozen MockClock so date-dependent
     * tests can pin "now" to a fixed instant. Must be called before the test triggers
     * the request that resolves the clock-using handlers.
     */
    protected function freezeClock(string $now): MockClock
    {
        $clock = new MockClock(new \DateTimeImmutable($now));
        self::getContainer()->set(ClockInterface::class, $clock);

        return $clock;
    }
}
