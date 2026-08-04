<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Profile\Handler;

use App\Application\Profile\Handler\GetProfileHandler;
use App\Domain\Profile\Entity\Profile;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetProfileHandlerTest extends TestCase
{
    private ProfileRepositoryInterface&MockObject $profileRepository;
    private GetProfileHandler $handler;

    protected function setUp(): void
    {
        $this->profileRepository = $this->createMock(ProfileRepositoryInterface::class);
        $this->handler = new GetProfileHandler($this->profileRepository);
    }

    public function testGetReturnsTheExistingProfileWithoutSaving(): void
    {
        $this->profileRepository->method('find')->willReturn(DomainTestHelper::createProfile(
            sex: 'male',
            birthDate: new \DateTimeImmutable('1995-04-12'),
            defaultRestSeconds: 90,
            heightCm: 178.0,
        ));
        $this->profileRepository->expects($this->never())->method('save');

        $result = $this->handler->__invoke();

        $this->assertSame('male', $result->sex);
        $this->assertEquals(new \DateTimeImmutable('1995-04-12'), $result->birthDate);
        $this->assertSame(90, $result->defaultRestSeconds);
        $this->assertSame(178.0, $result->heightCm);
    }

    public function testGetCreatesTheDefaultRowOnFirstAccess(): void
    {
        $this->profileRepository->method('find')->willReturn(null);

        $saved = null;
        $this->profileRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Profile $profile) use (&$saved): void {
                $saved = $profile;
            });

        $result = $this->handler->__invoke();

        $this->assertInstanceOf(Profile::class, $saved);
        $this->assertNull($result->sex);
        $this->assertNull($result->birthDate);
        $this->assertSame(120, $result->defaultRestSeconds);
        $this->assertNull($result->heightCm);
    }

    public function testGetSerialisesBirthDateAsAPlainDayInTheOutputArray(): void
    {
        $this->profileRepository->method('find')->willReturn(DomainTestHelper::createProfile(
            sex: 'female',
            birthDate: new \DateTimeImmutable('1995-04-12'),
            defaultRestSeconds: 120,
            heightCm: null,
        ));

        $this->assertSame(
            [
                'sex' => 'female',
                'birth_date' => '1995-04-12',
                'default_rest_seconds' => 120,
                'height_cm' => null,
            ],
            $this->handler->__invoke()->toArray(),
        );
    }
}
