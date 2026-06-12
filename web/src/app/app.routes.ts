import { Routes } from '@angular/router';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'exercises' },
  {
    path: 'exercises',
    loadComponent: () =>
      import('./pages/exercises/exercises-page').then((m) => m.ExercisesPage),
  },
  {
    path: 'exercises/new',
    loadComponent: () =>
      import('./pages/exercises/exercise-new-page').then(
        (m) => m.ExerciseNewPage,
      ),
  },
  {
    path: 'exercises/:id',
    loadComponent: () =>
      import('./pages/exercises/exercise-detail-page').then(
        (m) => m.ExerciseDetailPage,
      ),
  },
];
