import { Module } from '@nestjs/common';
import { ServeStaticModule } from '@nestjs/serve-static';
import { existsSync } from 'fs';
import { join } from 'path';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { PrismaModule } from './prisma/prisma.module';

// In the prod image the Angular build is copied to /app/public; in dev the
// Angular dev server runs separately, so the folder does not exist.
const clientDist = join(__dirname, '..', 'public');

@Module({
  imports: [
    PrismaModule,
    ...(existsSync(clientDist)
      ? [
          ServeStaticModule.forRoot({
            rootPath: clientDist,
            exclude: ['/api/{*splat}'],
          }),
        ]
      : []),
  ],
  controllers: [AppController],
  providers: [AppService],
})
export class AppModule {}
