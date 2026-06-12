import { Routes } from '@angular/router';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'workouts' },
  {
    path: 'workouts',
    loadComponent: () =>
      import('./pages/workouts/workouts-page').then((m) => m.WorkoutsPage),
  },
  {
    path: 'workouts/new',
    loadComponent: () =>
      import('./pages/workouts/template-editor-page').then(
        (m) => m.TemplateEditorPage,
      ),
  },
  {
    path: 'workouts/:id',
    loadComponent: () =>
      import('./pages/workouts/template-editor-page').then(
        (m) => m.TemplateEditorPage,
      ),
  },
  {
    path: 'log/:id',
    loadComponent: () =>
      import('./pages/log/log-page').then((m) => m.LogPage),
  },
  {
    path: 'history',
    loadComponent: () =>
      import('./pages/history/history-page').then((m) => m.HistoryPage),
  },
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
