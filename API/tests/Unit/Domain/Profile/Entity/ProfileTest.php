<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Profile\Entity;

use App\Domain\Profile\Entity\Profile;
use PHPUnit\Framework\TestCase;

final class ProfileTest extends TestCase
{
    public function testCreateDefaultStartsEmptyWithTheDefaultRestSeconds(): void
    {
        $profile = Profile::createDefault();

        $this->assertNull($profile->getSex());
        $this->assertNull($profile->getBirthDate());
        $this->assertSame(120, $profile->getDefaultRestSeconds());
        $this->assertSame(Profile::DEFAULT_REST_SECONDS, $profile->getDefaultRestSeconds());
        $this->assertNull($profile->getHeightCm());
    }

    public function testChangeSexAcceptsMaleAndFemale(): void
    {
        $profile = Profile::createDefault();

        $profile->changeSex('male');
        $this->assertSame('male', $profile->getSex());

        $profile->changeSex('female');
        $this->assertSame('female', $profile->getSex());
    }

    public function testChangeSexAcceptsNullToClearIt(): void
    {
        $profile = Profile::createDefault();
        $profile->changeSex('male');

        $profile->changeSex(null);

        $this->assertNull($profile->getSex());
    }

    public function testChangeSexWithAnUnknownValueThrowsInvalidArgumentException(): void
    {
        $profile = Profile::createDefault();

        $this->expectException(\InvalidArgumentException::class);

        $profile->changeSex('other');
    }

    public function testChangeBirthDateRoundTripsAndAcceptsNull(): void
    {
        $profile = Profile::createDefault();
        $birthDate = new \DateTimeImmutable('1995-04-12');

        $profile->changeBirthDate($birthDate);
        $this->assertEquals($birthDate, $profile->getBirthDate());

        $profile->changeBirthDate(null);
        $this->assertNull($profile->getBirthDate());
    }

    public function testChangeDefaultRestSecondsUpdatesTheValue(): void
    {
        $profile = Profile::createDefault();

        $profile->changeDefaultRestSeconds(90);

        $this->assertSame(90, $profile->getDefaultRestSeconds());
    }

    public function testChangeDefaultRestSecondsWithZeroThrowsInvalidArgumentException(): void
    {
        $profile = Profile::createDefault();

        $this->expectException(\InvalidArgumentException::class);

        $profile->changeDefaultRestSeconds(0);
    }

    public function testChangeDefaultRestSecondsWithANegativeValueThrowsInvalidArgumentException(): void
    {
        $profile = Profile::createDefault();

        $this->expectException(\InvalidArgumentException::class);

        $profile->changeDefaultRestSeconds(-30);
    }

    public function testChangeHeightCmRoundTripsAndAcceptsNull(): void
    {
        $profile = Profile::createDefault();

        $profile->changeHeightCm(178.5);
        $this->assertSame(178.5, $profile->getHeightCm());

        $profile->changeHeightCm(null);
        $this->assertNull($profile->getHeightCm());
    }

    public function testSexesListsExactlyMaleAndFemale(): void
    {
        $this->assertSame(['male', 'female'], Profile::SEXES);
    }
}
