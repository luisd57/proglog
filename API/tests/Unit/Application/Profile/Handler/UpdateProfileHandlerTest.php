<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Profile\Handler;

use App\Application\Profile\DTO\Input\UpdateProfileInputDTO;
use App\Application\Profile\Handler\UpdateProfileHandler;
use App\Domain\Profile\Entity\Profile;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateProfileHandlerTest extends TestCase
{
    private ProfileRepositoryInterface&MockObject $profileRepository;
    private UpdateProfileHandler $handler;
    private ?Profile $savedProfile = null;

    protected function setUp(): void
    {
        $this->profileRepository = $this->createMock(ProfileRepositoryInterface::class);
        $this->handler = new UpdateProfileHandler($this->profileRepository);

        $this->profileRepository
            ->method('save')
            ->willReturnCallback(function (Profile $profile): void {
                $this->savedProfile = $profile;
            });
    }

    private static function patch(
        bool $sexProvided = false,
        ?string $sex = null,
        bool $birthDateProvided = false,
        ?string $birthDate = null,
        bool $defaultRestSecondsProvided = false,
        ?int $defaultRestSeconds = null,
        bool $heightCmProvided = false,
        ?float $heightCm = null,
    ): UpdateProfileInputDTO {
        return new UpdateProfileInputDTO(
            sexProvided: $sexProvided,
            sex: $sex,
            birthDateProvided: $birthDateProvided,
            birthDate: $birthDate,
            defaultRestSecondsProvided: $defaultRestSecondsProvided,
            defaultRestSeconds: $defaultRestSeconds,
            heightCmProvided: $heightCmProvided,
            heightCm: $heightCm,
        );
    }

    public function testUpdateAppliesOnlyTheProvidedKeys(): void
    {
        $this->profileRepository->method('find')->willReturn(DomainTestHelper::createProfile(
            sex: 'male',
            birthDate: new \DateTimeImmutable('1995-04-12'),
            defaultRestSeconds: 120,
            heightCm: 178.0,
        ));

        $result = $this->handler->__invoke(self::patch(
            defaultRestSecondsProvided: true,
            defaultRestSeconds: 90,
        ));

        $this->assertSame(90, $result->defaultRestSeconds);
        // untouched keys keep their values
        $this->assertSame('male', $result->sex);
        $this->assertEquals(new \DateTimeImmutable('1995-04-12'), $result->birthDate);
        $this->assertSame(178.0, $result->heightCm);
        $this->assertNotNull($this->savedProfile);
        $this->assertSame(90, $this->savedProfile->getDefaultRestSeconds());
    }

    public function testUpdateSetsEveryProvidedKey(): void
    {
        $this->profileRepository->method('find')->willReturn(DomainTestHelper::createProfile());

        $result = $this->handler->__invoke(self::patch(
            sexProvided: true,
            sex: 'female',
            birthDateProvided: true,
            birthDate: '1995-04-12',
            defaultRestSecondsProvided: true,
            defaultRestSeconds: 150,
            heightCmProvided: true,
            heightCm: 165.5,
        ));

        $this->assertSame('female', $result->sex);
        $this->assertEquals(new \DateTimeImmutable('1995-04-12'), $result->birthDate);
        $this->assertSame(150, $result->defaultRestSeconds);
        $this->assertSame(165.5, $result->heightCm);
    }

    public function testUpdateClearsNullableFieldsWithAnExplicitNull(): void
    {
        $this->profileRepository->method('find')->willReturn(DomainTestHelper::createProfile(
            sex: 'male',
            birthDate: new \DateTimeImmutable('1995-04-12'),
            defaultRestSeconds: 90,
            heightCm: 178.0,
        ));

        $result = $this->handler->__invoke(self::patch(
            sexProvided: true,
            sex: null,
            birthDateProvided: true,
            birthDate: null,
            heightCmProvided: true,
            heightCm: null,
        ));

        $this->assertNull($result->sex);
        $this->assertNull($result->birthDate);
        $this->assertNull($result->heightCm);
        // rest seconds is not nullable and was not provided
        $this->assertSame(90, $result->defaultRestSeconds);
    }

    public function testUpdateIgnoresAProvidedNullDefaultRestSeconds(): void
    {
        $this->profileRepository->method('find')->willReturn(
            DomainTestHelper::createProfile(defaultRestSeconds: 90),
        );

        $result = $this->handler->__invoke(self::patch(
            defaultRestSecondsProvided: true,
            defaultRestSeconds: null,
        ));

        $this->assertSame(90, $result->defaultRestSeconds);
    }

    public function testUpdateCreatesTheDefaultRowWhenTheProfileDoesNotExistYet(): void
    {
        $this->profileRepository->method('find')->willReturn(null);

        $result = $this->handler->__invoke(self::patch(sexProvided: true, sex: 'male'));

        $this->assertSame('male', $result->sex);
        $this->assertSame(120, $result->defaultRestSeconds);
        $this->assertNotNull($this->savedProfile);
    }

    public function testUpdateWithAnUnknownSexThrowsInvalidArgumentException(): void
    {
        $this->profileRepository->method('find')->willReturn(DomainTestHelper::createProfile());
        $this->profileRepository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke(self::patch(sexProvided: true, sex: 'other'));
    }

    public function testUpdateWithANonPositiveRestSecondsThrowsInvalidArgumentException(): void
    {
        $this->profileRepository->method('find')->willReturn(DomainTestHelper::createProfile());
        $this->profileRepository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke(self::patch(defaultRestSecondsProvided: true, defaultRestSeconds: 0));
    }

    public function testUpdateWithAMalformedBirthDateThrowsInvalidArgumentException(): void
    {
        $this->profileRepository->method('find')->willReturn(DomainTestHelper::createProfile());
        $this->profileRepository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke(self::patch(birthDateProvided: true, birthDate: 'not-a-date'));
    }

    public function testUpdateWithAnEmptyPatchStillPersistsTheProfileUnchanged(): void
    {
        $this->profileRepository->method('find')->willReturn(
            DomainTestHelper::createProfile(sex: 'male', defaultRestSeconds: 90),
        );

        $result = $this->handler->__invoke(self::patch());

        $this->assertSame('male', $result->sex);
        $this->assertSame(90, $result->defaultRestSeconds);
        $this->assertNotNull($this->savedProfile);
    }
}
