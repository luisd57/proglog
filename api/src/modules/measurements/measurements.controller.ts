import { Body, Controller, Delete, Get, Param, Post, Query } from '@nestjs/common';
import { MeasurementsService } from './measurements.service';
import type { MeasurementInput } from './measurements.service';

@Controller('measurements')
export class MeasurementsController {
  constructor(private readonly measurements: MeasurementsService) {}

  @Get()
  series(@Query('type') type: string) {
    return this.measurements.series(type);
  }

  @Post()
  add(@Body() input: MeasurementInput) {
    return this.measurements.add(input);
  }

  @Delete(':id')
  remove(@Param('id') id: string) {
    return this.measurements.remove(id);
  }
}
