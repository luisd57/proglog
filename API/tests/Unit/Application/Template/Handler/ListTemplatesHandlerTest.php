<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Template\Handler;

use App\Application\Template\DTO\Output\TemplateSummaryOutputDTO;
use App\Application\Template\Handler\ListTemplatesHandler;
use App\Domain\Template\Id\WorkoutTemplateId;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListTemplatesHandlerTest extends TestCase
{
    private WorkoutTemplateRepositoryInterface&MockObject $workoutTemplateRepository;
    private ListTemplatesHandler $handler;

    protected function setUp(): void
    {
        $this->workoutTemplateRepository = $this->createMock(WorkoutTemplateRepositoryInterface::class);
        $this->handler = new ListTemplatesHandler($this->workoutTemplateRepository);
    }

    public function testListReturnsSummariesWithExerciseCounts(): void
    {
        $bSplit = DomainTestHelper::createWorkoutTemplate(name: 'B Split', sortOrder: 0);
        $pushDay = DomainTestHelper::createWorkoutTemplate(name: 'Push Day', sortOrder: 1);

        $this->workoutTemplateRepository
            ->method('findAllActive')
            ->willReturn(new ArrayCollection([$bSplit, $pushDay]));

        $exerciseCounts = [
            $bSplit->getId()->getValue() => 1,
            $pushDay->getId()->getValue() => 2,
        ];
        $this->workoutTemplateRepository
            ->method('countExercisesByTemplateId')
            ->willReturnCallback(
                fn (WorkoutTemplateId $workoutTemplateId): int => $exerciseCounts[$workoutTemplateId->getValue()]
            );

        $result = $this->handler->__invoke();

        $this->assertCount(2, $result);
        $this->assertSame(
            ['B Split', 'Push Day'],
            $result->map(fn (TemplateSummaryOutputDTO $summary) => $summary->name)->toArray(),
        );
        $this->assertSame(1, $result->get(0)->exerciseCount);
        $this->assertSame(2, $result->get(1)->exerciseCount);
    }

    public function testListWithNoTemplatesReturnsEmptyCollection(): void
    {
        $this->workoutTemplateRepository->method('findAllActive')->willReturn(new ArrayCollection());

        $this->assertCount(0, $this->handler->__invoke());
    }
}
