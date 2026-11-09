import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { describe, it, expect, afterEach, beforeEach } from 'vitest';
import { AuthStore } from './auth-store';

describe('account requests', () => {
  let auth: AuthStore; let http: HttpTestingController;
  beforeEach(() => { TestBed.configureTestingModule({providers:[provideHttpClient(),provideHttpClientTesting()]}); auth=TestBed.inject(AuthStore); http=TestBed.inject(HttpTestingController); });
  afterEach(() => http.verify());
  async function endSession(status = 204): Promise<void> {
    http.expectOne('/sanctum/csrf-cookie').flush(null); await Promise.resolve();
    const logout = http.expectOne('/auth/logout');
    if (status === 401) logout.flush({ message: 'Unauthenticated.' }, { status: 401, statusText: 'Unauthorized' });
    else logout.flush(null);
    await Promise.resolve(); await Promise.resolve(); await Promise.resolve();
  }
  it('initializes CSRF before submitting credentials', async () => {
    const pending=auth.submit('login',{email:'sample@example.test',password:'synthetic-password'});
    await endSession(401);
    http.expectOne('/sanctum/csrf-cookie').flush(null); await Promise.resolve();
    const login=http.expectOne('/auth/login'); expect(login.request.method).toBe('POST'); login.flush({});
    await pending;
  });
  it('never retries a failed mutation automatically', async () => {
    const pending=auth.submit('login',{email:'sample@example.test',password:'synthetic-password'});
    const rejected=expect(pending).rejects.toBeTruthy();
    await endSession(401);
    http.expectOne('/sanctum/csrf-cookie').flush(null); await Promise.resolve();
    http.expectOne('/auth/login').flush({message:'Unavailable'},{status:503,statusText:'Unavailable'});
    await rejected; http.expectNone('/auth/login');
  });
  it('treats an absent session as signed out', async () => {
    const pending=auth.load();
    http.expectOne('/api/v1/me').flush({},{status:401,statusText:'Unauthorized'});
    expect(await pending).toBeNull(); expect(auth.user()).toBeNull();
  });
  for (const endpoint of ['login', 'register', 'forgot-password', 'reset-password']) {
    for (const status of [204, 401]) {
    it(`prepares ${endpoint} after logout status ${status} and refreshes CSRF`, async () => {
      const pending = auth.submit(endpoint, { email: 'sample@example.test' });
      await endSession(status);
      http.expectOne('/sanctum/csrf-cookie').flush(null); await Promise.resolve();
      http.expectOne('/auth/' + endpoint).flush(endpoint === 'reset-password' ? { password_reset: true } : {});
      await pending;
      expect(auth.user()).toBeNull();
    });
    }
  }
  it('does not request a reset when ending the session fails', async () => {
    const pending = auth.submit('forgot-password', { email: 'sample@example.test' });
    const rejected = expect(pending).rejects.toBeTruthy();
    http.expectOne('/sanctum/csrf-cookie').flush(null); await Promise.resolve();
    http.expectOne('/auth/logout').flush({}, {status:503, statusText:'Unavailable'});
    await rejected;
    http.expectNone('/auth/forgot-password');
  });

  it('does not report reset success without explicit server confirmation', async () => {
    const pending = auth.submit('reset-password', { email: 'sample@example.test' });
    const rejected = expect(pending).rejects.toMatchObject({ status: 409 });
    http.expectOne('/sanctum/csrf-cookie').flush(null); await Promise.resolve();
    http.expectOne('/auth/logout').flush(null);
    await Promise.resolve(); await Promise.resolve(); await Promise.resolve();
    http.expectOne('/sanctum/csrf-cookie').flush(null); await Promise.resolve();
    http.expectOne('/auth/reset-password').flush({ message: 'Unrelated successful response' });
    await rejected;
  });
});
