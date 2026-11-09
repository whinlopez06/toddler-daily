import { Component, effect, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthStore } from '../auth/auth-store';
import { DatePipe } from '@angular/common';
import { errorMessage } from '../api/api-client';

@Component({
  selector: 'app-shell', imports: [RouterLink, RouterLinkActive, RouterOutlet, DatePipe],
  template: `
    <a class="skip-link" href="#main-content">Skip to content</a>
    <div class="app-frame">
      <div class="mobile-bar"><a routerLink="/" class="mobile-brand" (click)="menuOpen.set(false)"><img src="/toddler-mark.svg" width="32" height="32" alt="">WeeGrow</a><button id="menu-toggle" type="button" class="btn btn-outline-secondary" (click)="menuOpen.update(open => !open)" [attr.aria-expanded]="menuOpen()" aria-controls="app-navigation">{{ menuOpen() ? 'Close menu' : 'Menu' }}</button></div>
      <aside id="app-navigation" class="sidebar" [class.menu-open]="menuOpen()" (keydown.escape)="closeMenu()">
        <a class="brand" routerLink="/" (click)="menuOpen.set(false)"><img src="/toddler-mark.svg" width="32" height="32" alt=""><span>WeeGrow</span></a>
        <div class="workspace-name">{{ auth.user()?.family?.name }}</div>
        <nav aria-label="Main navigation">
          @for (item of navigation; track item.path) {
            <a [routerLink]="item.path" routerLinkActive="active" ariaCurrentWhenActive="page" [routerLinkActiveOptions]="{exact:item.path === '/'}" (click)="menuOpen.set(false)"><svg viewBox="0 0 24 24" aria-hidden="true"><path [attr.d]="item.icon" /></svg>{{ item.label }}</a>
          }
        </nav>
        <div class="sidebar-bottom"><span>{{ auth.user()?.name }}</span><button type="button" class="text-button" (click)="logout()">Sign out</button></div>
      </aside>

      <main id="main-content" class="app-main" tabindex="-1">
        @if (auth.user()?.is_demo) { <p class="notice" role="status"><strong>Private demo</strong> · Access ends {{ auth.user()?.demo_expires_at | date:'medium' }} (your local time). Use fictional records only. Other visitors cannot see your records. Reminders are disabled.</p> }
        @if (error()) { <p class="error-notice" role="alert">{{ error() }}</p> }
        <router-outlet />
      </main>
    </div>
  `
})
export class Shell {
  readonly auth = inject(AuthStore);
  private readonly router = inject(Router);
  readonly menuOpen = signal(false);
  readonly error = signal('');
  constructor() {
    effect(onCleanup => {
      const user = this.auth.user();
      if (!user?.is_demo || !user.demo_expires_at) return;
      const remaining = new Date(user.demo_expires_at).getTime() - Date.now();
      const timer = setTimeout(() => {
        this.auth.clear();
        void this.router.navigateByUrl('/login?demo-expired=1');
      }, Math.max(0, Math.min(remaining, 2147483647)));
      onCleanup(() => clearTimeout(timer));
    });
  }
  readonly navigation = [
    { path: '/', label: 'Family overview', icon: 'M3 11 12 3l9 8M5 10v11h5v-7h4v7h5V10' },
    { path: '/children', label: 'Children', icon: 'M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0M5 21v-3a7 7 0 0 1 14 0v3' },
    { path: '/timeline', label: 'Family timeline', icon: 'M6 3v18M3 6h6m-6 6h6m-6 6h6M13 6h8m-8 6h5m-5 6h8' },
    { path: '/appointments', label: 'Appointments', icon: 'M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2M7 3v4m10-4v4M3 11h18' },
    { path: '/notifications', label: 'Notifications', icon: 'M6 9a6 6 0 0 1 12 0v5l3 3H3l3-3ZM9 21h6' },
    { path: '/settings', label: 'Settings', icon: 'M3 6h7m4 0h7M3 12h13m4 0h1M3 18h3m4 0h11M10 3v6m6 0v6M6 15v6' },
  ];
  closeMenu(): void { this.menuOpen.set(false); document.getElementById('menu-toggle')?.focus(); }
  async logout(): Promise<void> {
    try { await this.auth.logout(); await this.router.navigateByUrl('/login'); }
    catch (error) { this.error.set(errorMessage(error)); }
  }
}
