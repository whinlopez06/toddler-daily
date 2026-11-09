import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { ApiClient, errorMessage, fieldErrors } from '../../core/api/api-client';
import { AuthStore } from '../../core/auth/auth-store';

@Component({
  imports: [ReactiveFormsModule,RouterLink],
  template: `
    <header class="page-header"><div><h1>Settings</h1><p>Family preferences and control over your records.</p></div></header>
    @if(error()) { <p class="error-notice" role="alert">{{ error() }}</p> }
    @if(success()) { <p class="success-notice" role="status">{{ success() }}</p> }
    <section class="settings-section"><div><h2>Family details</h2><p>New appointments use the family timezone by default. Existing appointments keep their recorded timezone.</p></div><form class="panel" [formGroup]="familyForm" (ngSubmit)="saveFamily()"><label for="family-settings-name">Family name</label><input id="family-settings-name" class="form-control" formControlName="name" required><label for="family-settings-zone">Family timezone</label><input id="family-settings-zone" class="form-control" formControlName="timezone" required><button class="btn btn-primary submit-button" [disabled]="busy() || familyForm.invalid">Save family details</button></form></section>
    @if (!auth.user()?.is_demo) {
    <section class="settings-section"><div><h2>Email reminders</h2><p>Email messages contain a generic appointment reminder and a link to sign in.</p></div><form class="panel" [formGroup]="preferenceForm" (ngSubmit)="savePreferences()"><label class="check-label"><input type="checkbox" formControlName="email_reminders_enabled"> Send appointment reminders by email</label><p class="field-help">Delivery requires an available sending service. In-app reminders remain available without email.</p><button class="btn btn-primary submit-button" [disabled]="busy()">Save email preference</button></form></section>
    } @else { <p class="notice">Email and appointment reminders are disabled in the demo.</p> }
    <section class="settings-section"><div><h2>Export your records</h2><p>Download your family's records as a JSON file, including medication correction history. The download contains private information.</p></div><div class="panel"><label for="export-password">Confirm your password</label><input id="export-password" class="form-control" type="password" autocomplete="current-password" [value]="exportPassword" (input)="exportPassword=$any($event.target).value"><button class="btn btn-outline-secondary submit-button" [disabled]="busy() || !exportPassword" (click)="exportRecords()">Download family records</button></div></section>
    @if (!auth.user()?.is_demo) {
    <section class="settings-section danger-section"><div><h2>Delete your family account</h2><p>Access and reminders stop immediately. Erasure is processed after a deletion ledger is safely stored. Backup copies expire within 30 days.</p><a routerLink="/privacy">Read the privacy notice</a></div><form class="panel" [formGroup]="deletionForm" (ngSubmit)="deleteFamily()"><label for="delete-family-name">Type your family name to confirm</label><input id="delete-family-name" class="form-control" formControlName="family_name" autocomplete="off"><label for="delete-password">Confirm your password</label><input id="delete-password" class="form-control" type="password" formControlName="password" autocomplete="current-password"><label class="check-label"><input type="checkbox" formControlName="confirm"> I understand that my family account and records will be erased.</label><button class="btn btn-outline-danger submit-button" [disabled]="busy() || deletionForm.invalid">Request family deletion</button></form></section>
    } @else { <section class="settings-section"><div><h2>Demo access and visit history</h2><p>Your demo expires after 24 hours. Your supplied email and login history remain available to the portfolio owner. Contact the owner through the portfolio to request removal.</p><a routerLink="/privacy">Read the demo privacy notice</a></div></section> }
  `
})
export class SettingsPage {
  readonly auth = inject(AuthStore); private readonly api = inject(ApiClient); private readonly fb = inject(FormBuilder); private readonly router = inject(Router);
  readonly error = signal(''); readonly success = signal(''); readonly busy = signal(false); exportPassword = '';
  readonly familyForm = this.fb.nonNullable.group({name:[this.auth.user()?.family?.name ?? '',Validators.required],timezone:[this.auth.user()?.family?.timezone ?? 'Asia/Manila',Validators.required]});
  readonly preferenceForm = this.fb.nonNullable.group({email_reminders_enabled:[this.auth.user()?.email_reminders_enabled ?? false]});
  readonly deletionForm = this.fb.nonNullable.group({family_name:['',Validators.required],password:['',Validators.required],confirm:[false,Validators.requiredTrue]});
  private begin(): void { this.busy.set(true); this.error.set(''); this.success.set(''); }
  async saveFamily(): Promise<void> {
    this.begin();
    try { await this.api.patch('family',{...this.familyForm.getRawValue(),version:this.auth.user()?.family?.version}); await this.auth.load(true);  }
    catch(error) { this.error.set(errorMessage(error)+' '+Object.values(fieldErrors(error)).flat().join(' ')); } finally { this.busy.set(false); }
  }
  async savePreferences(): Promise<void> {
    this.begin();
    try { await this.api.patch('me',this.preferenceForm.getRawValue()); await this.auth.load(true);  }
    catch(error) { this.error.set(errorMessage(error)); } finally { this.busy.set(false); }
  }
  async exportRecords(): Promise<void> {
    this.begin();
    try {
      await this.api.post('confirm-password',{password:this.exportPassword}); this.exportPassword = '';
      const blob = await this.api.download('family/export'); const url = URL.createObjectURL(blob);
      const link = document.createElement('a'); link.href = url; link.download = 'weegrow-family-records.json'; link.click();
      setTimeout(() => URL.revokeObjectURL(url),1000); this.success.set('Family records downloaded.');
    } catch(error) { this.error.set(errorMessage(error)); } finally { this.busy.set(false); }
  }
  async deleteFamily(): Promise<void> {
    if(this.deletionForm.invalid || !window.confirm('Request permanent erasure of this family account and its records?')) return;
    this.begin();
    try {
      const values = this.deletionForm.getRawValue(); await this.api.post('confirm-password',{password:values.password});
      await this.api.delete('family',{family_name:values.family_name,confirm:values.confirm});
      this.deletionForm.reset(); this.auth.clear(); await this.router.navigateByUrl('/deletion-requested');
    } catch(error) { this.error.set(errorMessage(error)); } finally { this.busy.set(false); }
  }
}
