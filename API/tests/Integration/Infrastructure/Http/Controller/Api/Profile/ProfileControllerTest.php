<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\Profile;

use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;

final class ProfileControllerTest extends ApiTestCase
{
    private ProfileRepositoryInterface $profileRepository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ProfileRepositoryInterface $profileRepository */
        $profileRepository = self::getContainer()->get(ProfileRepositoryInterface::class);
        $this->profileRepository = $profileRepository;
    }

    public function testShowCreatesTheDefaultRowOnFirstAccess(): void
    {
        $this->assertNull($this->profileRepository->find());

        $this->jsonRequest('GET', '/api/profile');

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame(
            [
                'sex' => null,
                'birth_date' => null,
                'default_rest_seconds' => 120,
                'height_cm' => null,
            ],
            $data['data']['profile'],
        );
        $this->assertNotNull($this->profileRepository->find());
    }

    public function testShowReturnsTheExistingRow(): void
    {
        $this->profileRepository->save(DomainTestHelper::createProfile(
            sex: 'male',
            birthDate: new \DateTimeImmutable('1995-04-12'),
            defaultRestSeconds: 90,
            heightCm: 178.5,
        ));

        $this->jsonRequest('GET', '/api/profile');

        $this->assertResponseStatusCodeSame(200);
        $profile = $this->getResponseData()['data']['profile'];
        $this->assertSame('male', $profile['sex']);
        $this->assertSame('1995-04-12', $profile['birth_date']);
        $this->assertSame(90, $profile['default_rest_seconds']);
        $this->assertSame(178.5, $profile['height_cm']);
    }

    public function testUpdateCreatesTheRowAndAppliesThePatch(): void
    {
        $this->jsonRequest('PATCH', '/api/profile', [
            'sex' => 'female',
            'birth_date' => '1995-04-12',
            'default_rest_seconds' => 150,
            'height_cm' => 165.5,
        ]);

        $this->assertResponseStatusCodeSame(200);
        $profile = $this->getResponseData()['data']['profile'];
        $this->assertSame('female', $profile['sex']);
        $this->assertSame('1995-04-12', $profile['birth_date']);
        $this->assertSame(150, $profile['default_rest_seconds']);
        $this->assertSame(165.5, $profile['height_cm']);

        $this->jsonRequest('GET', '/api/profile');
        $this->assertSame('female', $this->getResponseData()['data']['profile']['sex']);
    }

    public function testUpdateLeavesAbsentKeysUntouched(): void
    {
        $this->profileRepository->save(DomainTestHelper::createProfile(
            sex: 'male',
            birthDate: new \DateTimeImmutable('1995-04-12'),
            defaultRestSeconds: 90,
            heightCm: 178.5,
        ));

        $this->jsonRequest('PATCH', '/api/profile', ['default_rest_seconds' => 180]);

        $this->assertResponseStatusCodeSame(200);
        $profile = $this->getResponseData()['data']['profile'];
        $this->assertSame(180, $profile['default_rest_seconds']);
        $this->assertSame('male', $profile['sex']);
        $this->assertSame('1995-04-12', $profile['birth_date']);
        $this->assertSame(178.5, $profile['height_cm']);
    }

    public function testUpdateClearsNullableFieldsWithAnExplicitNull(): void
    {
        $this->profileRepository->save(DomainTestHelper::createProfile(
            sex: 'male',
            birthDate: new \DateTimeImmutable('1995-04-12'),
            defaultRestSeconds: 90,
            heightCm: 178.5,
        ));

        $this->jsonRequest('PATCH', '/api/profile', [
            'sex' => null,
            'birth_date' => null,
            'height_cm' => null,
        ]);

        $this->assertResponseStatusCodeSame(200);
        $profile = $this->getResponseData()['data']['profile'];
        $this->assertNull($profile['sex']);
        $this->assertNull($profile['birth_date']);
        $this->assertNull($profile['height_cm']);
        $this->assertSame(90, $profile['default_rest_seconds']);
    }

    public function testUpdateWithAnEmptyPatchReturnsTheUnchangedProfile(): void
    {
        $this->profileRepository->save(DomainTestHelper::createProfile(sex: 'male', defaultRestSeconds: 90));

        $this->jsonRequest('PATCH', '/api/profile', []);

        $this->assertResponseStatusCodeSame(200);
        $profile = $this->getResponseData()['data']['profile'];
        $this->assertSame('male', $profile['sex']);
        $this->assertSame(90, $profile['default_rest_seconds']);
    }

    public function testUpdateWithAnUnknownSexReturns422(): void
    {
        $this->jsonRequest('PATCH', '/api/profile', ['sex' => 'other']);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertSame('VALIDATION_ERROR', $data['error']['code']);
        $this->assertArrayHasKey('sex', $data['error']['details']);
    }

    public function testUpdateWithANonPositiveRestSecondsReturns422(): void
    {
        $this->jsonRequest('PATCH', '/api/profile', ['default_rest_seconds' => 0]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('default_rest_seconds', $this->getResponseData()['error']['details']);
    }

    public function testUpdateWithANonIntegerRestSecondsReturns422(): void
    {
        $this->jsonRequest('PATCH', '/api/profile', ['default_rest_seconds' => 'ninety']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('default_rest_seconds', $this->getResponseData()['error']['details']);
    }

    public function testUpdateWithANonNumericHeightReturns422(): void
    {
        $this->jsonRequest('PATCH', '/api/profile', ['height_cm' => 'tall']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('height_cm', $this->getResponseData()['error']['details']);
    }

    public function testUpdateWithAMalformedBirthDateReturns422(): void
    {
        $this->jsonRequest('PATCH', '/api/profile', ['birth_date' => 'not-a-date']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('VALIDATION_ERROR', $this->getResponseData()['error']['code']);
    }

    public function testUpdatedRestSecondsBecomesTheSessionFallback(): void
    {
        $this->jsonRequest('PATCH', '/api/profile', ['default_rest_seconds' => 200]);
        $this->assertResponseStatusCodeSame(200);

        $this->jsonRequest('GET', '/api/profile');
        $this->assertSame(200, $this->getResponseData()['data']['profile']['default_rest_seconds']);
    }
}
