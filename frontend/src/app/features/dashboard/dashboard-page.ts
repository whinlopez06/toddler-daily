import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ApiClient, errorMessage } from '../../core/api/api-client';
import { Dashboard, Envelope } from '../../core/api/models';
import { AuthStore } from '../../core/auth/auth-store';
import { ageLabel, dateLabel, timeLabel } from '../../shared/date-format';

@Component({
  imports: [RouterLink, DatePipe],
  template: `
    <header class="page-header"><div><h1>Family overview</h1><p>{{ auth.user()?.family?.name }} · {{ auth.user()?.family?.timezone }}</p></div><a class="btn btn-primary" routerLink="/children" [queryParams]="{new:'true'}">Add a child</a></header>
    @if (error()) { <p class="error-notice" role="alert">{{ error() }} <button class="text-button" (click)="load()">Try again</button></p> }
    @if (data(); as overview) {
      @if (!allChildren().length) {
        <section class="panel dashboard-start"><img src="/toddler-play.svg" width="150" height="120" alt=""><div><h2>Add your first child</h2><p>Create a profile to keep their memories, growth, and health records together.</p><a class="btn btn-primary" routerLink="/children" [queryParams]="{new:'true'}">Create child profile</a></div></section>
      }
      <div class="scope-row"><label for="child-scope">Viewing</label><select id="child-scope" class="form-select" (change)="select($any($event.target).value)"><option value="">All children</option>@for (child of allChildren(); track child.id) { <option [value]="child.id">{{ child.display_name }}</option> }</select></div>
      <div class="dashboard-grid">
        <section class="panel span-8"><div class="section-heading"><h2>Upcoming appointments</h2><a routerLink="/appointments">View all</a></div>
          @for (appointment of overview.appointments; track appointment.id) {
            <a class="record-row" [routerLink]="['/children',appointment.child_id,'appointments']"><div><strong>{{ appointment.title }}</strong><span class="record-child-name">{{ appointment.child_name }}</span><span>{{ appointment.provider || 'Provider not recorded' }}</span></div><div class="appointment-details"><time>{{ time(appointment.starts_at, appointment.timezone || overview.timezone) }}</time><span>{{ appointment.clinic || 'Clinic not recorded' }}</span></div></a>
          } @empty { <div class="empty-state"><h3>No upcoming appointments</h3><p>Add an appointment from a child's profile when one is scheduled.</p><a routerLink="/children">Choose a child</a></div> }
          <p class="section-note">{{ overview.reminder_notice }}</p>
        </section>
        <section class="panel span-4"><div class="section-heading"><h2>Children</h2><a routerLink="/children">Manage</a></div>
          @for (child of overview.children; track child.id) {
            <a class="child-row" [routerLink]="['/children',child.id,'timeline-entries']"><span class="initial" aria-hidden="true">{{ child.display_name.charAt(0) }}</span><div><strong>{{ child.display_name }}</strong><span>{{ age(child.birth_date, undefined, overview.timezone) }} - {{ (child.birth_date + 'T12:00:00Z') | date:'MMMM d':'UTC' }}</span></div></a>
          } @empty { <div class="empty-state"><h3>Your family record starts here</h3><p>Add a child to begin saving memories and health records.</p></div> }
        </section>
        <section class="panel span-12">
          <div class="section-heading"><h2>Current medications</h2><a routerLink="/children" [queryParams]="{tab: 'medications'}">Manage medications</a></div>
          @for (medication of overview.medications ?? []; track medication.id) {
            <div class="record-row medication-row">
              <a class="medication-information" [routerLink]="['/children',medication.child_id,'medications']">
                <strong>{{ medication.name }}</strong>
                <span class="record-child-name">{{ medication.child_name }}</span>
                @if (medication.instructed_amount) {
                  <p>Recorded instructed amount: {{ medication.instructed_amount }} {{ medication.dose_unit }}</p>
                }
                @if (medication.instructions) { <p class="preserve-lines">{{ medication.instructions }}</p> }
              </a>
              <div class="medication-dates">
                <span>{{ date(medication.start_date) }} – {{ medication.end_date ? date(medication.end_date) : 'No end date recorded' }}</span>
                <a class="intake-history-link" [routerLink]="['/children',medication.child_id,'medications',medication.id,'intakes']">View intake history</a>
              </div>
            </div>
          } @empty {
            <div class="empty-state compact"><p>No medications with a recorded date range covering today.</p></div>
          }
          <p class="section-note">Based on recorded start and end dates in {{ overview.timezone }}. Follow the supplied instructions.</p>
        </section>
        <section class="panel span-12"><div class="section-heading"><h2>Recent memories and milestones</h2><a routerLink="/timeline">View timeline</a></div>
          @for (entry of overview.recent_entries; track entry.id) {
            <a class="record-row" [routerLink]="['/children',entry.child_id,'timeline-entries']"><div><strong>{{ entry.title }}</strong><span>{{ entry.child_name }} · {{ kindLabel(entry.kind) }}</span></div><time>{{ date(entry.entry_date) }}</time></a>
          } @empty { <div class="empty-state compact"><p>No memories or milestones recorded this year.</p></div> }
        </section>
      </div>
    } @else if (!error()) { <p class="loading-state" role="status">Loading your family records…</p> }
  `
})
export class DashboardPage {
  readonly auth = inject(AuthStore); private readonly api = inject(ApiClient);
  readonly data = signal<Dashboard | null>(null); readonly allChildren = signal<Dashboard['children']>([]); readonly error = signal('');
  private childId = '';
  readonly age = ageLabel; readonly date = dateLabel; readonly time = timeLabel;
  constructor() { void this.load(); void this.api.post('notifications/refresh', {}).catch(() => undefined); }
  async load(): Promise<void> {
    this.error.set('');
    try { const response = await this.api.get<Envelope<Dashboard>>('dashboard', this.childId ? {child_id:this.childId} : {}); this.data.set(response.data); if (!this.childId) this.allChildren.set(response.data.children); }
    catch (error) { this.error.set(errorMessage(error)); }
  }
  select(id: string): void { this.childId = id; void this.load(); }
  kindLabel(kind: string | undefined): string { return ({memory:'Memory', milestone:'Milestone', health_note:'Health note', life_event:'Life event'} as Record<string,string>)[kind ?? ''] ?? ''; }
}
