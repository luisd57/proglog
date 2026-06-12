import { Controller, Get, Param, Query } from '@nestjs/common';
import { StatsService } from './stats.service';

@Controller('stats')
export class StatsController {
  constructor(private readonly stats: StatsService) {}

  @Get('exercise/:id/best')
  exerciseBest(
    @Param('id') id: string,
    @Query('excludeSession') excludeSession?: string,
  ) {
    return this.stats.exerciseBest(id, excludeSession);
  }

  @Get('exercise/:id/series')
  exerciseSeries(@Param('id') id: string) {
    return this.stats.exerciseSeries(id);
  }

  @Get('strength-levels')
  strengthLevels() {
    return this.stats.strengthLevels();
  }

  @Get('weekly-muscles')
  weeklyMuscles() {
    return this.stats.weeklyMuscles();
  }
}
