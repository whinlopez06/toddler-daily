import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter, Router } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { describe, expect, it } from 'vitest';
import { AuthPage } from './auth-page';

describe('recovery routes with the real account store', () => {
  it('submits the reset token and new password before showing login success', async () => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([
      { path: 'forgot-password', component: AuthPage, data: { mode: 'forgot-password' } },
      { path: 'reset-password', component: AuthPage, data: { mode: 'reset-password' } },
      { path: 'login', component: AuthPage, data: { mode: 'login' } },
      { path: 'verify-email', component: AuthPage, data: { mode: 'login' } },
    ])] });
    const harness = await RouterTestingHarness.create('/forgot-password');
    TestBed.inject(HttpTestingController).expectOne('/auth/demo-options').flush({ data: { demo_mode: false, available: false } });
    const page = await harness.navigateByUrl('/reset-password?token=synthetic-token&email=sample%40example.test', AuthPage);
    expect(page.mode).toBe('reset-password');
    page.form.patchValue({ password: 'new-synthetic-password', password_confirmation: 'new-synthetic-password' });
    const pending = page.submit();
    const http = TestBed.inject(HttpTestingController);
    http.expectOne('/sanctum/csrf-cookie').flush(null); await Promise.resolve();
    http.expectOne('/auth/logout').flush({ message: 'Unauthenticated.' }, { status: 401, statusText: 'Unauthorized' });
    await Promise.resolve(); await Promise.resolve(); await Promise.resolve();
    expect(page.success()).toBe('');
    expect(TestBed.inject(Router).url).toContain('/reset-password');
    http.expectOne('/sanctum/csrf-cookie').flush(null); await Promise.resolve();
    const reset = http.expectOne('/auth/reset-password');
    expect(reset.request.body).toMatchObject({ token: 'synthetic-token', email: 'sample@example.test', password: 'new-synthetic-password', password_confirmation: 'new-synthetic-password' });
    reset.flush({ password_reset: true });
    await pending;
    harness.detectChanges();
    expect(TestBed.inject(Router).url).toBe('/login?password-reset=1');
    const login = harness.routeDebugElement!.componentInstance as AuthPage;
    expect(login.mode).toBe('login');
    expect(login.form.controls.password.value).toBe('');
    expect(login.success()).toContain('Your password was updated');
    login.form.patchValue({ email: 'sample@example.test', password: 'new-synthetic-password' });
    const signIn = login.submit();
    http.expectOne('/sanctum/csrf-cookie').flush(null); await Promise.resolve();
    http.expectOne('/auth/logout').flush({ message: 'Unauthenticated.' }, { status: 401, statusText: 'Unauthorized' });
    await Promise.resolve(); await Promise.resolve(); await Promise.resolve();
    http.expectOne('/sanctum/csrf-cookie').flush(null); await Promise.resolve();
    const credentials = http.expectOne('/auth/login');
    expect(credentials.request.body.password).toBe('new-synthetic-password');
    credentials.flush({ two_factor: false });
    await Promise.resolve(); await Promise.resolve();
    http.expectOne('/api/v1/me').flush({ data: { email_verified: false } });
    await signIn;
    expect(TestBed.inject(Router).url).toBe('/verify-email');
    http.verify();
  });
});
