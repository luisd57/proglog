import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  Patch,
  Post,
  Query,
} from '@nestjs/common';
import { ExercisesService } from './exercises.service';
import type { CreateExerciseInput } from './exercises.service';

@Controller('exercises')
export class ExercisesController {
  constructor(private readonly exercises: ExercisesService) {}

  @Get()
  list(
    @Query('search') search?: string,
    @Query('muscle') muscle?: string,
    @Query('equipment') equipment?: string,
  ) {
    return this.exercises.list({ search, muscle, equipment });
  }

  @Get(':id')
  get(@Param('id') id: string) {
    return this.exercises.get(id);
  }

  @Post()
  create(@Body() input: CreateExerciseInput) {
    return this.exercises.createCustom(input);
  }

  @Patch(':id')
  update(@Param('id') id: string, @Body() input: Partial<CreateExerciseInput>) {
    return this.exercises.updateCustom(id, input);
  }

  @Delete(':id')
  remove(@Param('id') id: string) {
    return this.exercises.removeCustom(id);
  }
}
