<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\Stats;

use App\Application\Stats\DTO\Input\GetExerciseBestInputDTO;
use App\Application\Stats\Handler\GetExerciseBestHandler;
use App\Application\Stats\Handler\GetExerciseSeriesHandler;
use App\Application\Stats\Handler\GetOverviewHandler;
use App\Application\Stats\Handler\GetStrengthLevelsHandler;
use App\Application\Stats\Handler\GetWeeklyMusclesHandler;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ValidatesRequestTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/stats')]
final class StatsController extends AbstractController
{
    use ApiResponseTrait;
    use ValidatesRequestTrait;

    #[Route('/exercise/{id}/best', name: 'api_stats_exercise_best', methods: ['GET'])]
    public function exerciseBest(string $id, Request $request, GetExerciseBestHandler $handler): JsonResponse
    {
        $excludeSession = $request->query->get('exclude_session');

        try {
            $best = $handler->__invoke(new GetExerciseBestInputDTO(
                exerciseId: $id,
                excludeSessionId: ($excludeSession !== null && $excludeSession !== '') ? $excludeSession : null,
            ));

            return $this->success($best->toArray());
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['id' => $exception->getMessage()]);
        }
    }

    #[Route('/exercise/{id}/series', name: 'api_stats_exercise_series', methods: ['GET'])]
    public function exerciseSeries(string $id, GetExerciseSeriesHandler $handler): JsonResponse
    {
        try {
            $series = $handler->__invoke($id);

            return $this->success($series->toArray());
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['id' => $exception->getMessage()]);
        }
    }

    #[Route('/strength-levels', name: 'api_stats_strength_levels', methods: ['GET'])]
    public function strengthLevels(GetStrengthLevelsHandler $handler): JsonResponse
    {
        return $this->success($handler->__invoke()->toArray());
    }

    #[Route('/weekly-muscles', name: 'api_stats_weekly_muscles', methods: ['GET'])]
    public function weeklyMuscles(GetWeeklyMusclesHandler $handler): JsonResponse
    {
        return $this->success($handler->__invoke()->toArray());
    }

    #[Route('/overview', name: 'api_stats_overview', methods: ['GET'])]
    public function overview(Request $request, GetOverviewHandler $handler): JsonResponse
    {
        $period = $request->query->get('period');

        return $this->success(
            $handler->__invoke(is_string($period) && $period !== '' ? $period : '7d')->toArray(),
        );
    }
}
