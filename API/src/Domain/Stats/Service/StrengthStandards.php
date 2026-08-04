<?php

declare(strict_types=1);

namespace App\Domain\Stats\Service;

use App\Domain\Stats\ValueObject\LiftStandard;
use App\Domain\Stats\ValueObject\StandardRow;

/**
 * Strength standards (1RM in kg by bodyweight, sex) for the main barbell
 * lifts. Levels: beginner / novice / intermediate / advanced / elite.
 * Values are approximations of widely circulated community standards
 * (strengthlevel.com-style) - good enough for personal tracking.
 * Faithful port of the STRENGTH_STANDARDS data in strength-standards.ts.
 */
final class StrengthStandards
{
    private function __construct()
    {
    }

    /**
     * @return array<int, LiftStandard>
     */
    public static function all(): array
    {
        return [
            new LiftStandard(
                lift: 'squat',
                label: 'Squat',
                exerciseNames: ['Barbell Squat', 'Barbell Full Squat'],
                male: [
                    self::row(60, 46, 64, 87, 113, 142),
                    self::row(70, 56, 77, 102, 130, 161),
                    self::row(80, 66, 89, 115, 145, 177),
                    self::row(90, 75, 99, 127, 158, 192),
                    self::row(100, 83, 109, 138, 170, 205),
                    self::row(110, 91, 117, 148, 181, 217),
                    self::row(120, 98, 125, 157, 191, 227),
                    self::row(140, 111, 140, 173, 208, 246),
                ],
                female: [
                    self::row(50, 26, 41, 59, 80, 104),
                    self::row(60, 31, 47, 66, 89, 113),
                    self::row(70, 36, 52, 73, 96, 121),
                    self::row(80, 40, 57, 78, 102, 128),
                    self::row(90, 44, 61, 83, 108, 134),
                    self::row(100, 47, 65, 87, 112, 139),
                ],
            ),
            new LiftStandard(
                lift: 'bench',
                label: 'Bench Press',
                exerciseNames: ['Barbell Bench Press - Medium Grip'],
                male: [
                    self::row(60, 32, 48, 68, 91, 117),
                    self::row(70, 41, 58, 80, 105, 133),
                    self::row(80, 49, 68, 91, 118, 147),
                    self::row(90, 57, 77, 102, 130, 160),
                    self::row(100, 64, 85, 111, 140, 171),
                    self::row(110, 71, 92, 119, 150, 182),
                    self::row(120, 77, 99, 127, 158, 191),
                    self::row(140, 88, 111, 140, 173, 207),
                ],
                female: [
                    self::row(50, 13, 22, 33, 48, 64),
                    self::row(60, 17, 26, 39, 54, 71),
                    self::row(70, 20, 30, 43, 59, 77),
                    self::row(80, 23, 33, 47, 64, 82),
                    self::row(90, 25, 36, 50, 68, 86),
                    self::row(100, 27, 38, 53, 71, 90),
                ],
            ),
            new LiftStandard(
                lift: 'deadlift',
                label: 'Deadlift',
                exerciseNames: ['Barbell Deadlift'],
                male: [
                    self::row(60, 57, 78, 103, 132, 163),
                    self::row(70, 68, 91, 119, 149, 182),
                    self::row(80, 79, 103, 132, 164, 199),
                    self::row(90, 88, 114, 145, 178, 214),
                    self::row(100, 97, 124, 156, 190, 227),
                    self::row(110, 105, 133, 166, 201, 239),
                    self::row(120, 113, 141, 175, 211, 250),
                    self::row(140, 126, 156, 191, 229, 269),
                ],
                female: [
                    self::row(50, 33, 49, 69, 92, 117),
                    self::row(60, 39, 56, 77, 101, 127),
                    self::row(70, 44, 62, 84, 109, 136),
                    self::row(80, 49, 67, 90, 116, 143),
                    self::row(90, 53, 72, 95, 121, 149),
                    self::row(100, 57, 76, 100, 126, 155),
                ],
            ),
            new LiftStandard(
                lift: 'ohp',
                label: 'Overhead Press',
                exerciseNames: ['Standing Military Press', 'Barbell Shoulder Press'],
                male: [
                    self::row(60, 20, 31, 44, 60, 77),
                    self::row(70, 25, 36, 51, 67, 86),
                    self::row(80, 29, 41, 56, 74, 93),
                    self::row(90, 33, 45, 61, 80, 100),
                    self::row(100, 37, 49, 66, 85, 105),
                    self::row(110, 40, 53, 70, 89, 110),
                    self::row(120, 43, 56, 73, 93, 114),
                    self::row(140, 48, 61, 79, 100, 122),
                ],
                female: [
                    self::row(50, 9, 15, 23, 33, 44),
                    self::row(60, 11, 17, 26, 36, 48),
                    self::row(70, 13, 19, 28, 39, 51),
                    self::row(80, 14, 21, 30, 41, 54),
                    self::row(90, 16, 23, 32, 44, 56),
                    self::row(100, 17, 24, 34, 45, 58),
                ],
            ),
            new LiftStandard(
                lift: 'row',
                label: 'Barbell Row',
                exerciseNames: ['Bent Over Barbell Row'],
                male: [
                    self::row(60, 28, 43, 61, 82, 105),
                    self::row(70, 34, 50, 69, 91, 115),
                    self::row(80, 39, 56, 76, 99, 124),
                    self::row(90, 44, 61, 82, 106, 131),
                    self::row(100, 49, 66, 88, 112, 138),
                    self::row(110, 53, 70, 92, 117, 144),
                    self::row(120, 56, 74, 97, 122, 149),
                    self::row(140, 63, 81, 104, 130, 158),
                ],
                female: [
                    self::row(50, 15, 24, 35, 48, 62),
                    self::row(60, 17, 27, 38, 52, 67),
                    self::row(70, 20, 30, 42, 56, 71),
                    self::row(80, 22, 32, 44, 59, 74),
                    self::row(90, 24, 34, 47, 61, 77),
                    self::row(100, 25, 36, 49, 64, 80),
                ],
            ),
        ];
    }

    private static function row(
        float $bodyweightKg,
        float $beginner,
        float $novice,
        float $intermediate,
        float $advanced,
        float $elite,
    ): StandardRow {
        return new StandardRow($bodyweightKg, [$beginner, $novice, $intermediate, $advanced, $elite]);
    }
}
