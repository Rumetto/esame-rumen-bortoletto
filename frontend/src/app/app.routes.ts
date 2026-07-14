import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './guards/auth.guard';
import { roleGuard } from './guards/role.guard';
import { AppLayout } from './layout/app-layout';

export const routes: Routes = [
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () => import('./pages/login/login').then((module) => module.LoginPage),
  },
  {
    path: '',
    component: AppLayout,
    canActivate: [authGuard],
    canActivateChild: [authGuard],
    children: [
      {
        path: 'dashboard',
        loadComponent: () => import('./pages/dashboard/dashboard').then((module) => module.DashboardPage),
      },
      {
        path: 'dipendente/corsi',
        canActivate: [roleGuard('DIPENDENTE')],
        loadComponent: () => import('./pages/employee-courses/employee-courses').then((module) => module.EmployeeCoursesPage),
      },
      {
        path: 'dipendente/scadenze',
        canActivate: [roleGuard('DIPENDENTE')],
        data: { deadlineOnly: true },
        loadComponent: () => import('./pages/employee-courses/employee-courses').then((module) => module.EmployeeCoursesPage),
      },
      {
        path: 'academy/corsi',
        canActivate: [roleGuard('REFERENTE_ACADEMY')],
        loadComponent: () => import('./pages/courses/courses').then((module) => module.CoursesPage),
      },
      {
        path: 'academy/assegnazioni',
        canActivate: [roleGuard('REFERENTE_ACADEMY')],
        loadComponent: () => import('./pages/assignments/assignments').then((module) => module.AssignmentsPage),
      },
      {
        path: 'academy/dipendenti',
        canActivate: [roleGuard('REFERENTE_ACADEMY')],
        loadComponent: () => import('./pages/employees/employees').then((module) => module.EmployeesPage),
      },
      {
        path: 'academy/statistiche',
        canActivate: [roleGuard('REFERENTE_ACADEMY')],
        loadComponent: () => import('./pages/statistics/statistics').then((module) => module.StatisticsPage),
      },
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
    ],
  },
  { path: '**', redirectTo: 'dashboard' },
];
