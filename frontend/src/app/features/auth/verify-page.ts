import { Component, inject, isDevMode, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { AuthStore } from '../../core/auth/auth-store';
import { errorMessage } from '../../core/api/api-client';

@Component({
  imports: [RouterLink],
  template: `<main class="standalone-page"><a class="brand" routerLink="/">WeeGrow</a><h1>Verify your email</h1><p>Open the verification link before adding family records.</p>@if (localInbox) { <p>This local version delivers emails to a test inbox on this computer, rather than your personal mailbox.</p><p><a href="http://127.0.0.1:8025" target="_blank" rel="noopener noreferrer">Open local email inbox</a>, select your verification email, and follow its link while signed in.</p> } @else { <p>Check the inbox and spam folder for the email address you used to sign up.</p> } @if (message()) { <p role="status">{{ message() }}</p> }<div class="button-row"><button class="btn btn-primary" (click)="resend()" [disabled]="busy()">Resend verification</button><button class="btn btn-outline-secondary" (click)="check()" [disabled]="busy()">I've verified my email</button><a routerLink="/login">Back to sign in</a></div></main>`
})
export class VerifyPage {
  readonly localInbox = isDevMode() && ['localhost', '127.0.0.1', '[::1]'].includes(globalThis.location?.hostname ?? '');
  private readonly auth = inject(AuthStore); private readonly router = inject(Router);
  readonly message = signal(''); readonly busy = signal(false);
  async resend(): Promise<void> {
    this.busy.set(true);
    try { await this.auth.submit('email/verification-notification', {}); this.message.set(this.localInbox ? 'A verification link has been sent to the local email inbox on this computer.' : 'A verification link has been sent.'); }
    catch (error) { this.message.set(errorMessage(error)); }
    finally { this.busy.set(false); }
  }
  async check(): Promise<void> {
    this.busy.set(true);
    try { const user = await this.auth.load(true); if (user?.email_verified) await this.router.navigateByUrl('/'); else this.message.set('Your email is not verified yet. Open the link in your email first.'); }
    catch (error) { this.message.set(errorMessage(error)); }
    finally { this.busy.set(false); }
  }
}
