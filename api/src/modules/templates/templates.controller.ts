import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  Post,
  Put,
} from '@nestjs/common';
import { TemplatesService } from './templates.service';
import type { TemplateInput } from './templates.service';

@Controller('templates')
export class TemplatesController {
  constructor(private readonly templates: TemplatesService) {}

  @Get()
  list() {
    return this.templates.list();
  }

  @Get(':id')
  get(@Param('id') id: string) {
    return this.templates.get(id);
  }

  @Get(':id/muscles')
  muscles(@Param('id') id: string) {
    return this.templates.muscles(id);
  }

  @Post()
  create(@Body() input: TemplateInput) {
    return this.templates.create(input);
  }

  @Put(':id')
  update(@Param('id') id: string, @Body() input: TemplateInput) {
    return this.templates.update(id, input);
  }

  @Delete(':id')
  remove(@Param('id') id: string) {
    return this.templates.remove(id);
  }
}
