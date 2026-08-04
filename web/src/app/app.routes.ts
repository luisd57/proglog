import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    loadComponent: () =>
      import('./dashboard/feature/dashboard.page').then((m) => m.DashboardPage),
  },
  {
    path: 'workouts',
    loadChildren: () =>
      import('./workouts/feature/workouts-shell.routes').then(
        (m) => m.workoutsRoutes,
      ),
  },
  {
    path: 'log/:id',
    loadComponent: () => import('./log/feature/log.page').then((m) => m.LogPage),
  },
  {
    path: 'history',
    loadComponent: () =>
      import('./history/feature/history.page').then((m) => m.HistoryPage),
  },
  {
    path: 'strength',
    loadComponent: () =>
      import('./strength/feature/strength.page').then((m) => m.StrengthPage),
  },
  {
    path: 'measurements',
    loadComponent: () =>
      import('./measurements/feature/measurements.page').then(
        (m) => m.MeasurementsPage,
      ),
  },
  {
    path: 'settings',
    loadComponent: () =>
      import('./settings/feature/settings.page').then((m) => m.SettingsPage),
  },
  {
    path: 'exercises',
    loadChildren: () =>
      import('./exercises/feature/exercises-shell.routes').then(
        (m) => m.exercisesRoutes,
      ),
  },
];
