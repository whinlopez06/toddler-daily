import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { ApiClient } from '../api/api-client';
import { Envelope, User } from '../api/models';

@Injectable({ providedIn: 'root' })
export class AuthStore {
  private readonly http = inject(HttpClient);
  private readonly api = inject(ApiClient);
  readonly user = signal<User | null>(null);
  readonly demoOptions = signal<{ demo_mode: boolean; available: boolean } | null>(null);
  private optionsPending: Promise<void> | null = null;
  async loadOptions(): Promise<void> {
    if (this.demoOptions()) return;
    if (!this.optionsPending) this.optionsPending = firstValueFrom(this.http.get<Envelope<{ demo_mode: boolean; available: boolean }>>('/auth/demo-options'))
      .then(response => { this.demoOptions.set(response.data); }).finally(() => { this.optionsPending = null; });
    return this.optionsPending;
  }
  private loaded = false;
  private pending: Promise<User | null> | null = null;
  async load(force = false): Promise<User | null> {
    if (this.pending) return this.pending;
    if (this.loaded && !force) return this.user();
    this.pending = this.api.get<Envelope<User>>('me').then(response => {
      this.loaded = true; this.user.set(response.data); return response.data;
    }).catch(error => {
      if (error instanceof HttpErrorResponse && error.status === 401) { this.loaded = true; this.user.set(null); return null; }
      throw error;
    }).finally(() => this.pending = null);
    return this.pending;
  }
  async submit(endpoint: string, body: unknown): Promise<void> {
    // Fortify account-entry routes require guests. This is also necessary when
    // another tab has retained an authenticated signup or login session.
    if (['login', 'register', 'demo', 'forgot-password', 'reset-password'].includes(endpoint)) await this.logout();
    await firstValueFrom(this.http.get('/sanctum/csrf-cookie'));
    const response = await firstValueFrom(this.http.post<{ password_reset?: boolean } | null>('/auth/' + endpoint, body));
    if (endpoint === 'reset-password' && response?.password_reset !== true) {
      throw new HttpErrorResponse({ status: 409, error: { message: 'Your password change was not confirmed. Request a new reset link and try again.' } });
    }
  }
  async logout(): Promise<void> {
    try {
      await this.submit('logout', {});
    } catch (error) {
      // Fortify protects logout with auth middleware. A 401 means the desired
      // signed-out state already exists; other failures must stop the flow.
      if (!(error instanceof HttpErrorResponse) || error.status !== 401) throw error;
    }
    this.user.set(null); this.loaded = true;
  }
  clear(): void { this.user.set(null); this.loaded = true; }
}
export const signedInGuard: CanActivateFn = async () => {
  const auth = inject(AuthStore); const router = inject(Router);
  try {
    const user = await auth.load();
    if (!user) return router.createUrlTree(['/login']);
    if (!user.email_verified && !user.is_demo) return router.createUrlTree(['/verify-email']);
    if (user.family?.deletion_pending) return router.createUrlTree(['/deletion-requested']);
    return true;
  } catch { return router.createUrlTree(['/login'], { queryParams: { unavailable: 'true' } }); }
};
