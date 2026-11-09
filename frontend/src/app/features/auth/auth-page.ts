import { NgTemplateOutlet } from '@angular/common';
import { Component, ElementRef, inject, isDevMode, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AbstractControl, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthStore } from '../../core/auth/auth-store';
import { errorMessage, fieldErrors } from '../../core/api/api-client';

@Component({
  selector: 'app-auth-page', imports: [ReactiveFormsModule, RouterLink, NgTemplateOutlet],
  styleUrl: './auth-page.scss',
  template: `
    <main class="auth-layout">
      <section class="auth-intro">
        <a class="brand" routerLink="/"><img src="/toddler-mark.svg" width="48" height="48" alt=""><span>WeeGrow</span></a>
        <div class="auth-story">
          <h2>Little days.<br>Lasting memories.</h2>
          <p>A place for your children's milestones, memories, and everyday care.</p>
          <img class="toddler-scene" src="/toddler-play.svg" width="420" height="300" alt="Illustration of a toddler playing with wooden blocks">
        </div>
        <p class="intro-footer">Their story, kept together.</p>
      </section>
      <section class="auth-form-panel" aria-label="Account form">
        <header class="auth-form-heading"><h1>{{ title }}</h1><p>{{ introduction }}</p></header>
        @if (mode === 'demo') {
          <p class="field-help">Your own sample family, private to this account. Use fictional records only. Access ends 24 hours after you start, including if you sign out and return.</p>
          @if (auth.demoOptions() && !auth.demoOptions()?.available) { <p class="error-notice" role="status">The portfolio demo is not available on this installation.</p> }
        }
        @if (mode === 'forgot-password') {
          <p class="field-help">Requesting a reset link signs out any current session in this browser.</p>
          @if (localInbox) { <p class="field-help">Reset emails go to the <a href="http://127.0.0.1:8025" target="_blank" rel="noopener noreferrer">local email inbox</a> on this computer, rather than your personal mailbox.</p> }
        }
        @if (success()) { <p class="success-notice" role="status">{{ success() }}</p> }
        @if (error()) { <p class="error-notice" role="alert">{{ error() }}</p> }
        <p class="field-help">All fields are required.</p>
        <p class="field-error" role="alert">{{ validationNotice() }}</p>
        <ng-template #validation let-field>
          @if (fieldMessage(field); as message) { <p class="field-error" [id]="field + '-error'">{{ message }}</p> }
        </ng-template>
        <form [formGroup]="form" (ngSubmit)="submit()" novalidate [attr.aria-busy]="busy()">
          @if (mode === 'register') {
            <label for="name">Your name</label><input id="name" required class="form-control" formControlName="name" [attr.aria-invalid]="fieldMessage('name') ? true : null" [attr.aria-describedby]="descriptionFor('name')" autocomplete="name" maxlength="100">
            <ng-container [ngTemplateOutlet]="validation" [ngTemplateOutletContext]="{ $implicit: 'name' }" />
            <label for="family_name">Family name</label><input id="family_name" required class="form-control" formControlName="family_name" [attr.aria-invalid]="fieldMessage('family_name') ? true : null" [attr.aria-describedby]="descriptionFor('family_name')" maxlength="100">
            <ng-container [ngTemplateOutlet]="validation" [ngTemplateOutletContext]="{ $implicit: 'family_name' }" />
          }
          <label for="email">Email address</label><input id="email" class="form-control" type="email" formControlName="email" [attr.aria-invalid]="fieldMessage('email') ? true : null" [attr.aria-describedby]="descriptionFor('email')" autocomplete="email" required>
            <ng-container [ngTemplateOutlet]="validation" [ngTemplateOutletContext]="{ $implicit: 'email' }" />
          @if (mode !== 'forgot-password') {
            <div class="password-label"><label for="password">Password</label>@if (mode === 'login') { <a routerLink="/forgot-password">Forgot password?</a> }</div><input id="password" class="form-control" type="password" formControlName="password" [attr.aria-invalid]="fieldMessage('password') ? true : null" [attr.aria-describedby]="descriptionFor('password')" [attr.autocomplete]="mode === 'login' ? 'current-password' : 'new-password'"  required>
            <ng-container [ngTemplateOutlet]="validation" [ngTemplateOutletContext]="{ $implicit: 'password' }" />
            @if (mode !== 'login') {
              <p id="password-help" class="field-help">Use at least 12 characters.</p>
              <label for="password_confirmation">Confirm password</label><input id="password_confirmation" required class="form-control" type="password" formControlName="password_confirmation" [attr.aria-invalid]="fieldMessage('password_confirmation') ? true : null" [attr.aria-describedby]="descriptionFor('password_confirmation')" autocomplete="new-password">
            <ng-container [ngTemplateOutlet]="validation" [ngTemplateOutletContext]="{ $implicit: 'password_confirmation' }" />
            }
          }
          @if (mode === 'register') {
            <label for="timezone">Family timezone</label><input id="timezone" required class="form-control" formControlName="timezone" [attr.aria-invalid]="fieldMessage('timezone') ? true : null" [attr.aria-describedby]="descriptionFor('timezone')">
            <ng-container [ngTemplateOutlet]="validation" [ngTemplateOutletContext]="{ $implicit: 'timezone' }" />
            <label class="check-label"><input id="privacy_accepted" type="checkbox" formControlName="privacy_accepted" [attr.aria-invalid]="fieldMessage('privacy_accepted') ? true : null" [attr.aria-describedby]="descriptionFor('privacy_accepted')"> I have read the <a routerLink="/privacy">privacy notice</a> and am authorized to manage these family records.</label>
            <ng-container [ngTemplateOutlet]="validation" [ngTemplateOutletContext]="{ $implicit: 'privacy_accepted' }" />
          }
          @if (mode === 'demo') {
            <label class="check-label"><input id="privacy_accepted" type="checkbox" formControlName="privacy_accepted" [attr.aria-invalid]="fieldMessage('privacy_accepted') ? true : null" [attr.aria-describedby]="descriptionFor('privacy_accepted')"> I understand that my supplied email and demo login history are retained after access expires, as explained in the <a routerLink="/privacy">privacy notice</a>.</label>
            <ng-container [ngTemplateOutlet]="validation" [ngTemplateOutletContext]="{ $implicit: 'privacy_accepted' }" />
            <p class="field-help">No verification email is sent. Save your password to return within 24 hours; password recovery is unavailable for demo accounts.</p>
          }
          @for (item of errors(); track item[0]) { <p class="field-error">{{ item[1][0] }}</p> }
          <button class="btn btn-primary submit-button" type="submit" [disabled]="busy() || (mode === 'demo' && !auth.demoOptions()?.available)">{{ busy() ? 'Please waitâ€¦' : actionLabel }}</button>
        </form>
        <div class="auth-links">
          @if (mode === 'login') {
            <p>New to WeeGrow? <a routerLink="/register">Create an account</a></p>
            @if (auth.demoOptions()?.available) { <p><a routerLink="/demo">Try the demo â€” free for 24 hours</a></p> }
          }
          @else { <a routerLink="/login">Back to sign in</a> }
        </div>
        <a class="auth-privacy" routerLink="/privacy">Privacy and your family's records</a>
      </section>
    </main>
  `
})
export class AuthPage {
  readonly localInbox = isDevMode() && ['localhost', '127.0.0.1', '[::1]'].includes(globalThis.location?.hostname ?? '');
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);
  readonly auth = inject(AuthStore);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly fb = inject(FormBuilder);
  readonly mode = this.route.snapshot.data['mode'] as string;
  readonly busy = signal(false); readonly error = signal(''); readonly success = signal('');
  readonly validationNotice = signal('');
  readonly errors = signal<[string, string[]][]>([]);
  readonly form = this.fb.nonNullable.group({
    name: [''], family_name: [''],
    email: [this.route.snapshot.queryParamMap.get('email') ?? '', [Validators.required, Validators.email]],
    password: [''], password_confirmation: [''], timezone: ['Asia/Manila'], privacy_accepted: [false],
  }, { validators: (group: AbstractControl) => {
    const password = group.get('password')?.value;
    const confirmation = group.get('password_confirmation')?.value;
    return confirmation && password !== confirmation ? { passwordMismatch: true } : null;
  } });
  get title(): string { return ({ demo: 'Try WeeGrow', login: 'Welcome back', register: 'Start your family record', 'forgot-password': 'Reset your password', 'reset-password': 'Choose a new password' } as Record<string,string>)[this.mode]; }
  get introduction(): string { return ({ demo: 'Explore a private demo, free for 24 hours.', login: 'Sign in to your private family record.', register: 'Create a private account for your family.', 'forgot-password': 'Enter your email to request a password reset link.', 'reset-password': 'Choose a new password for your family account.' } as Record<string,string>)[this.mode]; }
  get actionLabel(): string { return ({ demo: 'Start my 24-hour demo', login: 'Sign in', register: 'Create account', 'forgot-password': 'Send reset link', 'reset-password': 'Save password' } as Record<string,string>)[this.mode]; }
  constructor() {
    void this.auth.loadOptions().catch(() => { if (this.mode === 'demo') this.error.set('Demo availability could not be checked. Refresh to try again.'); });
    if (this.mode !== 'forgot-password') this.form.controls.password.addValidators(Validators.required);
    if (['register','demo','reset-password'].includes(this.mode)) {
      this.form.controls.password.addValidators([Validators.minLength(12), Validators.maxLength(128)]);
      this.form.controls.password_confirmation.addValidators(Validators.required);
    }
    if (this.mode === 'demo') this.form.controls.privacy_accepted.addValidators(Validators.requiredTrue);
    if (this.mode === 'register') {
      for (const key of ['name','family_name'] as const) this.form.controls[key].addValidators(Validators.required);
      for (const key of ['name','family_name'] as const) this.form.controls[key].addValidators([Validators.pattern(/\S/), Validators.maxLength(100)]);
      this.form.controls.timezone.addValidators([Validators.required, (control: AbstractControl) => {
        if (!control.value) return null;
        try { new Intl.DateTimeFormat('en', { timeZone: control.value }); return null; }
        catch { return { timezone: true }; }
      }]);
      this.form.controls.privacy_accepted.addValidators(Validators.requiredTrue);
    }
    this.form.controls.email.addValidators(Validators.maxLength(254));
    for (const control of Object.values(this.form.controls)) control.updateValueAndValidity();
    this.form.valueChanges.pipe(takeUntilDestroyed()).subscribe(() => {
      // Server validation can involve several fields (for example, credentials
      // are reported against email). An edit must allow a fresh submission.
      this.error.set(''); this.errors.set([]); this.validationNotice.set(''); this.success.set('');
      for (const control of Object.values(this.form.controls)) {
        if (!control.hasError('server')) continue;
        const { server, ...remaining } = control.errors!;
        control.setErrors(Object.keys(remaining).length ? remaining : null, { emitEvent: false });
      }
    });
    if (this.route.snapshot.queryParamMap.has('unavailable')) this.error.set('The server is starting or unavailable. Try signing in shortly.');
    if (this.route.snapshot.queryParamMap.get('demo-expired') === '1') this.error.set('Your 24-hour demo has ended. Signing in again cannot extend access. Your visit history has been retained.');
    if (this.mode === 'login' && this.route.snapshot.queryParamMap.get('password-reset') === '1') this.success.set('Your password was updated. Enter your new password to sign in. Check that your browser has not filled in an older saved password.');
  }
  fieldMessage(field: string): string {
    const control = this.form.get(field);
    if (!control?.touched) return '';
    if (control.hasError('server')) return control.getError('server');
    const labels: Record<string, string> = { name: 'your name', family_name: 'your family name', email: 'your email address', password: 'your password', password_confirmation: 'your password again', timezone: 'your family timezone' };
    if (control.hasError('required')) return field === 'privacy_accepted' ? (this.mode === 'demo' ? 'Read the privacy notice and acknowledge that demo visit history is retained.' : 'Read the privacy notice and confirm you are authorized to manage these records.') : `Enter ${labels[field]}.`;
    if (control.hasError('email')) return 'Enter a valid email address, such as name@example.com.';
    if (control.hasError('minlength')) return 'Use at least 12 characters for your password.';
    if (control.hasError('maxlength')) return `Use no more than ${control.getError('maxlength').requiredLength} characters.`;
    if (control.hasError('pattern')) return `Enter ${labels[field]}.`;
    if (control.hasError('timezone')) return 'Enter a valid timezone, such as Asia/Manila.';
    if (field === 'password_confirmation' && this.form.hasError('passwordMismatch')) return 'Your passwords do not match.';
    return '';
  }
  descriptionFor(field: string): string | null {
    const ids = [];
    if (field === 'password' && this.mode !== 'login') ids.push('password-help');
    if (this.fieldMessage(field)) ids.push(field + '-error');
    return ids.join(' ') || null;
  }
  private focusFirstError(): void {
    const field = Object.keys(this.form.controls).find(key => this.fieldMessage(key));
    if (field) this.host.nativeElement.querySelector<HTMLElement>(`[id="${field}"]`)?.focus();
  }
  async submit(): Promise<void> {
    if (this.busy()) return;
    if (this.mode === 'demo' && !this.auth.demoOptions()?.available) return;
    this.form.markAllAsTouched();
    if (this.form.invalid) {
      this.validationNotice.set('Check the highlighted fields before continuing.');
      this.focusFirstError();
      return;
    }
    this.validationNotice.set('');
    this.busy.set(true); this.error.set(''); this.errors.set([]);
    try {
      await this.auth.submit(this.mode === 'login' ? 'login' : this.mode === 'register' ? 'register' : this.mode, {
        ...this.form.getRawValue(), token: this.route.snapshot.queryParamMap.get('token'),
      });
      if (['login','register','demo'].includes(this.mode)) {
        const user = await this.auth.load(true);
        await this.router.navigateByUrl(user && (user.email_verified || user.is_demo) ? '/' : '/verify-email');
      } else if (this.mode === 'reset-password') {
        this.form.controls.password.reset(); this.form.controls.password_confirmation.reset();
        await this.router.navigateByUrl('/login?password-reset=1');
      } else this.success.set('If this address has an account, a reset link has been sent.');
    } catch (error) {
      this.error.set(errorMessage(error));
      const unmatched: [string, string[]][] = [];
      for (const [field, messages] of Object.entries(fieldErrors(error))) {
        const control = this.form.get(field);
        if (control) { control.setErrors({ ...control.errors, server: messages[0] }); control.markAsTouched(); }
        else unmatched.push([field, messages]);
      }
      this.errors.set(unmatched);
      this.focusFirstError();
    }
    finally { this.busy.set(false); }
  }
}
