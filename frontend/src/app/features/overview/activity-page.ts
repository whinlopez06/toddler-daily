import { Component, inject, signal } from '@angular/core';
import { RouterLink, ActivatedRoute } from '@angular/router';
import { ApiClient, errorMessage } from '../../core/api/api-client';
import { Child, FamilyRecord, Page } from '../../core/api/models';
import { dateLabel, timeLabel } from '../../shared/date-format';

@Component({
  imports: [RouterLink],
  template: `
    <header class="page-header"><div><h1>{{ appointments ? 'Family appointments' : 'Family timeline' }}</h1><p>{{ appointments ? 'Visits across your family, in date order.' : 'Memories, milestones, and health notes across your children.' }}</p></div><a class="btn btn-primary" routerLink="/children">{{ appointments ? 'Add appointment' : 'Add entry' }}</a></header>
    <div class="filter-row"><label for="activity-child">Child</label><select id="activity-child" class="form-select" (change)="childId=$any($event.target).value; load(1)"><option value="">All children</option>@for(child of children();track child.id) { <option [value]="child.id">{{ child.display_name }}</option> }</select>
      @if(appointments) { <label for="activity-status">Status</label><select id="activity-status" class="form-select" (change)="status=$any($event.target).value; load(1)"><option value="">All statuses</option><option value="scheduled">Scheduled</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select> }
    </div>
    @if(error()) { <p class="error-notice" role="alert">{{ error() }}</p> }
    <section class="panel"><h2>{{ appointments ? 'Appointments' : 'Timeline entries' }}</h2>
      @for(record of records();track record.id) {
        <a class="record-row" [routerLink]="['/children',record.child_id,appointments ? 'appointments' : 'timeline-entries']"><div><strong>{{ record.title }}</strong><span>{{ record.child_name }}@if(record.status) { · {{ record.status }} }@if(record.kind && !appointments) { · {{ record.kind.replaceAll('_',' ') }} }</span></div><time>{{ appointments ? time(record.starts_at,record.timezone || 'Asia/Manila') : date(record.entry_date) }}</time></a>
      } @empty { <div class="empty-state"><h3>{{ appointments ? 'No appointments recorded' : 'No timeline entries recorded' }}</h3><p>Choose a child to add the first {{ appointments ? 'appointment' : 'memory, milestone, or health note' }}.</p></div> }
      @if(lastPage() > 1) { <div class="pagination-row"><button class="btn btn-outline-secondary" [disabled]="page() === 1" (click)="load(page()-1)">Previous</button><span>Page {{ page() }} of {{ lastPage() }}</span><button class="btn btn-outline-secondary" [disabled]="page() === lastPage()" (click)="load(page()+1)">Next</button></div> }
    </section>
  `
})
export class ActivityPage {
  private readonly api = inject(ApiClient);
  readonly appointments = inject(ActivatedRoute).snapshot.data['appointments'] === true;
  readonly records = signal<FamilyRecord[]>([]); readonly children = signal<Child[]>([]); readonly error = signal('');
  readonly page = signal(1); readonly lastPage = signal(1); readonly date = dateLabel; readonly time = timeLabel;
  childId = ''; status = '';
  constructor() { void this.load(); void this.api.get<Page<Child>>('children',{per_page:100}).then(r => this.children.set(r.data)).catch(error => this.error.set(errorMessage(error))); }
  async load(page = 1): Promise<void> {
    this.error.set('');
    try { const query: Record<string,string|number> = {page}; if(this.childId) query['child_id'] = this.childId; if(this.status && this.appointments) query['status'] = this.status;
      const result = await this.api.get<Page<FamilyRecord>>(this.appointments ? 'appointments' : 'timeline',query);
      this.records.set(result.data); this.page.set(result.meta?.current_page ?? 1); this.lastPage.set(result.meta?.last_page ?? 1);
    } catch(error) { this.error.set(errorMessage(error)); }
  }
}
