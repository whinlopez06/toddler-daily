import { TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { ActivatedRoute, Router, convertToParamMap, provideRouter } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthPage } from './auth-page';
import { AuthStore } from '../../core/auth/auth-store';

describe('account form validation', () => {
  const auth = { submit: vi.fn(), load: vi.fn(), loadOptions: vi.fn(), demoOptions: signal({ demo_mode: false, available: false }) };
  let mode = 'register';
  const setup = () => {
    TestBed.configureTestingModule({
      imports: [AuthPage],
      providers: [provideRouter([]), { provide: AuthStore, useValue: auth }, {
        provide: ActivatedRoute, useValue: { snapshot: { data: { mode }, queryParamMap: convertToParamMap({}) } },
      }],
    });
    const fixture = TestBed.createComponent(AuthPage);
    fixture.detectChanges();
    return fixture;
  };
  const valid = { name: 'Sample parent', family_name: 'Sample family',
    email: 'sample@example.test', password: 'synthetic-password', password_confirmation: 'synthetic-password',
    timezone: 'Asia/Manila', privacy_accepted: true };
  beforeEach(() => { mode = 'register'; vi.resetAllMocks(); auth.loadOptions.mockResolvedValue(undefined); auth.demoOptions.set({ demo_mode: false, available: false }); });

  it('offers ordinary sign-in and demo entry on the same login page', () => {
    mode = 'login'; auth.demoOptions.set({ demo_mode: false, available: true });
    const fixture = setup();
    for (const path of ['/demo', '/register', '/forgot-password']) {
      expect(fixture.nativeElement.querySelector(`a[href="${path}"]`)).not.toBeNull();
    }
  });

  it('starts a demo without family details or verification and requires privacy acknowledgement', async () => {
    mode = 'demo'; auth.demoOptions.set({ demo_mode: true, available: true });
    const fixture = setup(); const page = fixture.componentInstance;
    expect(fixture.nativeElement.querySelector('#name')).toBeNull();
    page.form.patchValue({ email: valid.email, password: valid.password, password_confirmation: valid.password });
    await page.submit();
    expect(auth.submit).not.toHaveBeenCalled();
    page.form.controls.privacy_accepted.setValue(true);
    auth.submit.mockResolvedValue(undefined); auth.load.mockResolvedValue({ is_demo: true, email_verified: false });
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
    await page.submit();
    expect(auth.submit).toHaveBeenCalledWith('demo', expect.objectContaining({ email: valid.email, privacy_accepted: true }));
    expect(navigate).toHaveBeenCalledWith('/');
  });

  it('does not submit when the demo is unavailable', async () => {
    mode = 'demo'; const fixture = setup(); const page = fixture.componentInstance;
    page.form.patchValue(valid); await page.submit();
    expect(auth.submit).not.toHaveBeenCalled();
    expect(fixture.nativeElement.querySelector('button').disabled).toBe(true);
  });

  it('reveals missing fields on submit, focuses the first, and sends no request', async () => {
    const fixture = setup();
    expect(fixture.nativeElement.querySelector('#name-error')).toBeNull();
    expect(fixture.nativeElement.querySelector('button').disabled).toBe(false);
    fixture.nativeElement.querySelector('button').click();
    fixture.detectChanges();
    expect(auth.submit).not.toHaveBeenCalled();
    expect(fixture.nativeElement.querySelector('#name-error').textContent).toContain('Enter your name');
    expect(fixture.nativeElement.querySelector('#privacy_accepted-error').textContent).toContain('privacy notice');
    expect(fixture.nativeElement.querySelector('#name').getAttribute('aria-describedby')).toContain('name-error');
    expect(document.activeElement?.id).toBe('name');
  });

  it('shows errors on blur and clears them after correction', () => {
    const fixture = setup();
    const email = fixture.nativeElement.querySelector('#email') as HTMLInputElement;
    email.value = 'invalid'; email.dispatchEvent(new Event('input')); email.dispatchEvent(new Event('blur'));
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('#email-error').textContent).toContain('valid email');
    email.value = 'sample@example.test'; email.dispatchEvent(new Event('input')); fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('#email-error')).toBeNull();
  });

  it('blocks whitespace names, short passwords, mismatches and invalid timezones', async () => {
    const fixture = setup(); const page = fixture.componentInstance;
    page.form.patchValue({ ...valid, name: '  ', password: 'short', timezone: 'not-a-timezone' });
    await page.submit(); fixture.detectChanges();
    expect(auth.submit).not.toHaveBeenCalled();
    for (const field of ['name', 'password', 'password_confirmation', 'timezone']) {
      expect(page.fieldMessage(field)).not.toBe('');
    }
    page.form.patchValue(valid); fixture.detectChanges();
    expect(page.form.valid).toBe(true);
    expect(page.fieldMessage('password_confirmation')).toBe('');
  });

  it('places duplicate email errors beside the email and clears them when edited', async () => {
    const fixture = setup(); const page = fixture.componentInstance;
    page.form.patchValue(valid);
    auth.submit.mockRejectedValue(new HttpErrorResponse({ status: 422, error: { message: 'Check your email.', errors: { email: ['This email address is already registered.'] } } }));
    await page.submit(); fixture.detectChanges();
    expect(auth.submit).toHaveBeenCalledTimes(1);
    expect(fixture.nativeElement.querySelector('#email-error').textContent).toContain('already registered');
    expect(document.activeElement?.id).toBe('email');
    page.form.controls.email.setValue('other@example.test');
    expect(page.fieldMessage('email')).toBe('');
  });

  it('submits valid signup without an invitation and routes to verification', async () => {
    const fixture = setup(); const page = fixture.componentInstance;
    expect(fixture.nativeElement.querySelector('#invitation_token')).toBeNull();
    expect(fixture.nativeElement.textContent).not.toContain('invitation');
    page.form.patchValue(valid);
    auth.submit.mockResolvedValue(undefined);
    auth.load.mockResolvedValue({ email_verified: false });
    const router = TestBed.inject(Router);
    const navigate = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    await page.submit();
    expect(auth.submit).toHaveBeenCalledWith('register', expect.objectContaining(valid));
    expect(auth.submit.mock.calls[0][1]).not.toHaveProperty('invitation_token');
    expect(navigate).toHaveBeenCalledWith('/verify-email');
  });

  it('validates recovery without requiring hidden registration or password fields', () => {
    mode = 'forgot-password'; const fixture = setup(); const page = fixture.componentInstance;
    page.form.controls.email.setValue('sample@example.test');
    expect(page.form.valid).toBe(true);
    expect(fixture.nativeElement.querySelector('#password')).toBeNull();
  });

  it('allows login after correcting only the password following a credentials rejection', async () => {
    mode = 'login'; const fixture = setup(); const page = fixture.componentInstance;
    page.form.patchValue({ email: 'sample@example.test', password: 'old-synthetic-password' });
    auth.submit.mockRejectedValueOnce(new HttpErrorResponse({ status: 422, error: {
      message: 'These credentials do not match our records.', errors: { email: ['These credentials do not match our records.'] },
    } }));
    await page.submit(); fixture.detectChanges();
    expect(page.form.invalid).toBe(true);
    expect(page.fieldMessage('email')).toContain('credentials');
    page.form.controls.password.setValue('new-synthetic-password');
    fixture.detectChanges();
    expect(page.form.valid).toBe(true);
    expect(page.fieldMessage('email')).toBe('');
    expect(page.error()).toBe('');
    auth.submit.mockResolvedValue(undefined);
    auth.load.mockResolvedValue({ email_verified: false });
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
    await page.submit();
    expect(auth.submit).toHaveBeenCalledTimes(2);
    expect(auth.submit).toHaveBeenLastCalledWith('login', expect.objectContaining({ password: 'new-synthetic-password' }));
    expect(navigate).toHaveBeenCalledWith('/verify-email');
  });

  it('retains local required-field validation when clearing a server error', async () => {
    const fixture = setup(); const page = fixture.componentInstance;
    page.form.patchValue(valid);
    page.form.controls.email.setErrors({ server: 'Already registered.' });
    page.form.controls.name.setValue('');
    expect(page.form.controls.email.hasError('server')).toBe(false);
    expect(page.form.controls.name.hasError('required')).toBe(true);
    await page.submit();
    expect(auth.submit).not.toHaveBeenCalled();
  });

  it('clears reset password fields and opens a fresh login after confirmed success', async () => {
    mode = 'reset-password'; const fixture = setup(); const page = fixture.componentInstance;
    page.form.patchValue({ email: 'sample@example.test', password: 'new-synthetic-password', password_confirmation: 'new-synthetic-password' });
    auth.submit.mockResolvedValue(undefined);
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
    await page.submit();
    expect(auth.submit).toHaveBeenCalledWith('reset-password', expect.objectContaining({ password: 'new-synthetic-password' }));
    expect(page.form.controls.password.value).toBe('');
    expect(page.form.controls.password_confirmation.value).toBe('');
    expect(navigate).toHaveBeenCalledWith('/login?password-reset=1');
  });
});
