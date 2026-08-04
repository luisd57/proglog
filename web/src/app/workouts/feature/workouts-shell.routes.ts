import { Routes } from '@angular/router';

export const workoutsRoutes: Routes = [
  {
    path: '',
    loadComponent: () => import('./workouts.page').then((m) => m.WorkoutsPage),
  },
  {
    path: 'new',
    loadComponent: () =>
      import('./template-editor.page').then((m) => m.TemplateEditorPage),
  },
  {
    path: ':id',
    loadComponent: () =>
      import('./template-editor.page').then((m) => m.TemplateEditorPage),
  },
];
