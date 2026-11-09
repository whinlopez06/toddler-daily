import { Routes } from '@angular/router';
import { signedInGuard } from './core/auth/auth-store';

export const routes: Routes = [
  {path:'demo',loadComponent:() => import('./features/auth/auth-page').then(m => m.AuthPage),data:{mode:'demo'}},
  {path:'login',loadComponent:() => import('./features/auth/auth-page').then(m => m.AuthPage),data:{mode:'login'}},
  {path:'register',loadComponent:() => import('./features/auth/auth-page').then(m => m.AuthPage),data:{mode:'register'}},
  {path:'forgot-password',loadComponent:() => import('./features/auth/auth-page').then(m => m.AuthPage),data:{mode:'forgot-password'}},
  {path:'reset-password',loadComponent:() => import('./features/auth/auth-page').then(m => m.AuthPage),data:{mode:'reset-password'}},
  {path:'verify-email',loadComponent:() => import('./features/auth/verify-page').then(m => m.VerifyPage)},
  {path:'privacy',loadComponent:() => import('./features/auth/privacy-page').then(m => m.PrivacyPage)},
  {path:'deletion-requested',loadComponent:() => import('./features/auth/deletion-page').then(m => m.DeletionPage)},
  {path:'',canActivate:[signedInGuard],loadComponent:() => import('./core/layout/shell').then(m => m.Shell),children:[
    {path:'',pathMatch:'full',loadComponent:() => import('./features/dashboard/dashboard-page').then(m => m.DashboardPage)},
    {path:'children',loadComponent:() => import('./features/children/children-page').then(m => m.ChildrenPage)},
    {path:'children/:child/medications/:medication/intakes',loadComponent:() => import('./features/medications/intakes-page').then(m => m.IntakesPage)},
    {path:'children/:child/:type',loadComponent:() => import('./features/records/records-page').then(m => m.RecordsPage)},
    {path:'timeline',loadComponent:() => import('./features/overview/activity-page').then(m => m.ActivityPage)},
    {path:'appointments',loadComponent:() => import('./features/overview/activity-page').then(m => m.ActivityPage),data:{appointments:true}},
    {path:'notifications',loadComponent:() => import('./features/notifications/notifications-page').then(m => m.NotificationsPage)},
    {path:'settings',loadComponent:() => import('./features/settings/settings-page').then(m => m.SettingsPage)},
  ]},
  {path:'**',redirectTo:''},
];
