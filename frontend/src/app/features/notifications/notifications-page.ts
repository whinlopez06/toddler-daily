import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ApiClient, errorMessage } from '../../core/api/api-client';
import { Notification, Page } from '../../core/api/models';
import { AuthStore } from '../../core/auth/auth-store';
import { timeLabel } from '../../shared/date-format';

@Component({
  imports: [RouterLink],
  template: `
    <header class="page-header"><div><h1>Notifications</h1><p>Appointment reminders are best effort and may arrive late.</p></div><button class="btn btn-outline-secondary" (click)="refresh()" [disabled]="busy()">Refresh reminders</button></header>
    @if(error()) { <p class="error-notice" role="alert">{{ error() }}</p> }
    <section class="panel"><h2>Recent reminders</h2>
      @for(notification of notifications();track notification.id) { <article class="record-entry"><div class="record-entry-heading"><h3>{{ notification.data.message }}</h3><time>{{ time(notification.created_at,auth.user()?.family?.timezone || 'Asia/Manila') }}</time></div><div class="row-actions"><a [routerLink]="['/children',notification.data.child_id,'appointments']">View appointment</a>@if(!notification.read_at) { <button class="text-button" (click)="markRead(notification)">Mark as read</button> } @else { <span class="muted">Read</span> }</div></article> }
      @empty { <div class="empty-state"><h3>No reminders yet</h3><p>Upcoming appointment reminders will appear here when they are processed.</p></div> }
      @if(lastPage() > 1) { <div class="pagination-row"><button class="btn btn-outline-secondary" [disabled]="page() === 1" (click)="load(page()-1)">Previous</button><span>Page {{ page() }} of {{ lastPage() }}</span><button class="btn btn-outline-secondary" [disabled]="page() === lastPage()" (click)="load(page()+1)">Next</button></div> }
    </section>
  `
})
export class NotificationsPage {
  private readonly api = inject(ApiClient); readonly auth = inject(AuthStore);
  readonly notifications = signal<Notification[]>([]); readonly error = signal(''); readonly busy = signal(false); readonly page = signal(1); readonly lastPage = signal(1); readonly time = timeLabel;
  constructor() { void this.load(); }
  async load(page = 1): Promise<void> {
    try { const result = await this.api.get<Page<Notification>>('notifications',{page}); this.notifications.set(result.data); this.page.set(result.current_page ?? 1); this.lastPage.set(result.last_page ?? 1); }
    catch(error) { this.error.set(errorMessage(error)); }
  }
  async refresh(): Promise<void> {
    this.busy.set(true); this.error.set('');
    try { await this.api.post('notifications/refresh',{}); await this.load(); }
    catch(error) { this.error.set(errorMessage(error)); } finally { this.busy.set(false); }
  }
  async markRead(notification: Notification): Promise<void> {
    try { await this.api.patch('notifications/'+notification.id,{read:true}); await this.load(this.page()); }
    catch(error) { this.error.set(errorMessage(error)); }
  }
}
