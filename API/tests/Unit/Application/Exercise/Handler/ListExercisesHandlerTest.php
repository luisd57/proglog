<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Exercise\Handler;

use App\Application\Exercise\DTO\Input\ListExercisesInputDTO;
use App\Application\Exercise\DTO\Output\ExerciseOutputDTO;
use App\Application\Exercise\Handler\ListExercisesHandler;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListExercisesHandlerTest extends TestCase
{
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private ListExercisesHandler $handler;

    protected function setUp(): void
    {
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->handler = new ListExercisesHandler($this->exerciseRepository);
    }

    public function testListWithoutFiltersReturnsRepositoryOrder(): void
    {
        $this->exerciseRepository
            ->expects($this->once())
            ->method('search')
            ->with([], null, null)
            ->willReturn(new ArrayCollection([
                DomainTestHelper::createBuiltInExercise(name: 'Barbell Bench Press'),
                DomainTestHelper::createBuiltInExercise(name: 'Dumbbell Curl'),
            ]));

        $result = $this->handler->__invoke(new ListExercisesInputDTO());

        $this->assertSame(
            ['Barbell Bench Press', 'Dumbbell Curl'],
            $result->map(fn (ExerciseOutputDTO $dto) => $dto->name)->toArray(),
        );
    }

    public function testSearchIsTokenizedTolerantOfPluralsAndHyphens(): void
    {
        // "chin ups" -> tokens ['chin', 'up']: punctuation stripped, simple plural trimmed
        $this->exerciseRepository
            ->expects($this->once())
            ->method('search')
            ->with(['chin', 'up'], null, null)
            ->willReturn(new ArrayCollection([
                DomainTestHelper::createBuiltInExercise(name: 'Chin-Up'),
            ]));

        $result = $this->handler->__invoke(new ListExercisesInputDTO(search: 'chin ups'));

        $this->assertSame(['Chin-Up'], $result->map(fn (ExerciseOutputDTO $dto) => $dto->name)->toArray());
    }

    public function testSearchTokensStripPunctuationAndShortPluralsAreKept(): void
    {
        $this->assertSame(['chin', 'up'], ListExercisesHandler::searchTokens('Chin-Ups!'));
        $this->assertSame(['front', 'raise'], ListExercisesHandler::searchTokens('front  raises'));
        // "as" is <= 2 chars after cleaning: trailing "s" is kept
        $this->assertSame(['as'], ListExercisesHandler::searchTokens('as'));
    }

    public function testFiltersArePassedToRepository(): void
    {
        $this->exerciseRepository
            ->expects($this->once())
            ->method('search')
            ->with(['cable', 'raise'], 'shoulders', 'cable')
            ->willReturn(new ArrayCollection([
                DomainTestHelper::createBuiltInExercise(name: 'Front Cable Raise', primaryMuscles: ['shoulders']),
            ]));

        $result = $this->handler->__invoke(new ListExercisesInputDTO(
            search: 'cable raise',
            muscle: 'shoulders',
            equipment: 'cable',
        ));

        $this->assertCount(1, $result);
    }

    public function testRanksThePlainestMatchAheadOfWordierVariants(): void
    {
        // Repository returns name-ASC order; ranking must float "Pushups" to the top
        $this->exerciseRepository
            ->method('search')
            ->willReturn(new ArrayCollection([
                DomainTestHelper::createBuiltInExercise(name: 'Decline Push-Up', primaryMuscles: ['chest']),
                DomainTestHelper::createBuiltInExercise(name: 'Incline Push-Up Reverse Grip', primaryMuscles: ['chest']),
                DomainTestHelper::createBuiltInExercise(name: 'Pushups', primaryMuscles: ['chest']),
            ]));

        $result = $this->handler->__invoke(new ListExercisesInputDTO(search: 'push up'));

        $this->assertSame('Pushups', $result->first()->name);
    }

    public function testRanksAnExactNameMatchFirst(): void
    {
        $this->exerciseRepository
            ->method('search')
            ->willReturn(new ArrayCollection([
                DomainTestHelper::createBuiltInExercise(name: 'Chin-Up', primaryMuscles: ['lats']),
                DomainTestHelper::createBuiltInExercise(name: 'One Arm Chin-Up', primaryMuscles: ['lats']),
                DomainTestHelper::createBuiltInExercise(name: 'Wide-Grip Chin-Up', primaryMuscles: ['lats']),
            ]));

        $result = $this->handler->__invoke(new ListExercisesInputDTO(search: 'chin up'));

        $this->assertSame('Chin-Up', $result->first()->name);
    }

    public function testPrefersWholeWordMatchesOverMidWordCoincidences(): void
    {
        // "chin" is a substring of "Machine", so both match the filter
        $this->exerciseRepository
            ->method('search')
            ->willReturn(new ArrayCollection([
                DomainTestHelper::createBuiltInExercise(name: 'Chin Raise', primaryMuscles: ['neck']),
                DomainTestHelper::createBuiltInExercise(name: 'Machine Row', primaryMuscles: ['middle back']),
            ]));

        $result = $this->handler->__invoke(new ListExercisesInputDTO(search: 'chin'));

        $this->assertSame(
            ['Chin Raise', 'Machine Row'],
            $result->map(fn (ExerciseOutputDTO $dto) => $dto->name)->toArray(),
        );
    }

    public function testOutputDtoUsesParsedMuscleArrays(): void
    {
        $this->exerciseRepository
            ->method('search')
            ->willReturn(new ArrayCollection([
                DomainTestHelper::createBuiltInExercise(
                    name: 'Barbell Bench Press',
                    primaryMuscles: ['chest'],
                    secondaryMuscles: ['shoulders', 'triceps'],
                ),
            ]));

        $result = $this->handler->__invoke(new ListExercisesInputDTO());

        $this->assertSame(['chest'], $result->first()->primaryMuscles);
        $this->assertSame(['shoulders', 'triceps'], $result->first()->secondaryMuscles);
    }
}
