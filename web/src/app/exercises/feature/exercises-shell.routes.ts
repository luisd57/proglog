import { Routes } from '@angular/router';

export const exercisesRoutes: Routes = [
  {
    path: '',
    loadComponent: () => import('./exercises.page').then((m) => m.ExercisesPage),
  },
  {
    path: 'new',
    loadComponent: () => import('./exercise-new.page').then((m) => m.ExerciseNewPage),
  },
  {
    path: ':id',
    loadComponent: () =>
      import('./exercise-detail.page').then((m) => m.ExerciseDetailPage),
  },
];
