import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  Patch,
  Post,
  Put,
} from '@nestjs/common';
import { SessionsService } from './sessions.service';
import type { SetInput } from './sessions.service';

@Controller('sessions')
export class SessionsController {
  constructor(private readonly sessions: SessionsService) {}

  @Post()
  start(@Body() body: { templateId?: string }) {
    return this.sessions.start(body?.templateId);
  }

  @Get()
  list() {
    return this.sessions.list();
  }

  @Get(':id')
  get(@Param('id') id: string) {
    return this.sessions.get(id);
  }

  @Put(':id/exercises/:seId/sets')
  async replaceSets(
    @Param('id') id: string,
    @Param('seId') seId: string,
    @Body() sets: SetInput[],
  ) {
    await this.sessions.replaceSets(id, seId, sets);
    return { ok: true };
  }

  @Post(':id/exercises')
  addExercise(@Param('id') id: string, @Body() body: { exerciseId: string }) {
    return this.sessions.addExercise(id, body.exerciseId);
  }

  @Delete(':id/exercises/:seId')
  removeExercise(@Param('id') id: string, @Param('seId') seId: string) {
    return this.sessions.removeExercise(id, seId);
  }

  @Patch(':id')
  async updateSessionNotes(
    @Param('id') id: string,
    @Body() body: { notes: string },
  ) {
    await this.sessions.updateNotes(id, null, body.notes ?? '');
    return { ok: true };
  }

  @Patch(':id/exercises/:seId')
  async updateExerciseNotes(
    @Param('id') id: string,
    @Param('seId') seId: string,
    @Body() body: { notes: string },
  ) {
    await this.sessions.updateNotes(id, seId, body.notes ?? '');
    return { ok: true };
  }

  @Post(':id/finish')
  finish(@Param('id') id: string) {
    return this.sessions.finish(id);
  }

  @Delete(':id')
  remove(@Param('id') id: string) {
    return this.sessions.remove(id);
  }
}
