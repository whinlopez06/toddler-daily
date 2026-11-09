import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiClient, errorMessage, fieldErrors } from '../../core/api/api-client';
import { Envelope, FamilyRecord, Page } from '../../core/api/models';
import { AuthStore } from '../../core/auth/auth-store';
import { instantToLocal, localToInstant } from '../../shared/zoned-time';
import { timeLabel } from '../../shared/date-format';

@Component({
  imports: [ReactiveFormsModule,RouterLink],
  template: `
    <header class="page-header"><div><a class="back-link" [routerLink]="['/children',childId,'medications']">Medications</a><h1>{{ medication()?.name || 'Intake history' }}</h1><p>Actual administrations and their correction history.</p></div></header>
    @if(error()) { <p class="error-notice" role="alert">{{ error() }}</p> }
    <div class="records-layout with-editor">
      <section class="panel"><h2>Administration history</h2>
        @for(record of records();track record.id) {
          <article class="record-entry"><div class="record-entry-heading"><h3>{{ record.state === 'voided' ? 'Voided entry' : 'Recorded administration' }}</h3><time>{{ time(record.administered_at,record.timezone || 'Asia/Manila') }}</time></div>
            <p>{{ record.actual_amount ? record.actual_amount+' '+record.dose_unit : 'Actual dose not recorded' }}</p>
            @if(record.notes) { <p class="preserve-lines">{{ record.notes }}</p> }
            @if(record.correction_reason) { <p>Correction reason: {{ record.correction_reason }}</p> }
            @if(record.correction_of_id) { <p class="muted">A correction of an earlier entry.</p> }
            @if(record.replacement_id) { <span class="status-chip">Replaced by a correction</span> }
            @else { <button class="text-button" (click)="correct(record)">Correct entry</button> @if (record.state !== 'voided') { <button class="text-button danger-text" (click)="correct(record, 'voided')">Void entry</button> } }
            <details><summary>Instructions recorded at the time</summary><p>{{ record.snapshot?.name }}</p><p class="preserve-lines">{{ record.snapshot?.instructions || 'No instructions recorded.' }}</p></details>
          </article>
        } @empty { <div class="empty-state"><h3>No administrations recorded</h3><p>Record what was actually given. An empty history does not mean a dose was missed.</p></div> }
        @if(lastPage() > 1) { <div class="pagination-row"><button class="btn btn-outline-secondary" [disabled]="page() === 1" (click)="load(page()-1)">Previous</button><span>Page {{ page() }} of {{ lastPage() }}</span><button class="btn btn-outline-secondary" [disabled]="page() === lastPage()" (click)="load(page()+1)">Next</button></div> }
      </section>
      <section class="panel"><h2>{{ correction() ? 'Correct administration' : 'Record administration' }}</h2>
        <p class="field-help">Enter the actual administration. The app does not recommend or calculate a dose.</p>
        <form [formGroup]="form" (ngSubmit)="save()">
          <label for="administered-at">Date and time given</label><input id="administered-at" class="form-control" type="datetime-local" formControlName="administered_at" required>
          <label for="intake-timezone">Timezone</label><input id="intake-timezone" class="form-control" formControlName="timezone" required>
          <label for="actual-amount">Actual amount given</label><input id="actual-amount" class="form-control" type="number" min="0.001" step="any" formControlName="actual_amount"><p class="field-help">Leave amount and unit blank if the actual dose is unknown.</p>
          <label for="intake-unit">Dose unit</label><input id="intake-unit" class="form-control" formControlName="dose_unit">
          <label for="intake-notes">Notes</label><textarea id="intake-notes" class="form-control" formControlName="notes" rows="3"></textarea>
          @if(correction()) {
            <label for="intake-state">Correction type</label><select id="intake-state" class="form-select" formControlName="state"><option value="recorded">Replace with corrected information</option><option value="voided">Void this entry</option></select>
            <label for="correction-reason">Reason for correction</label><textarea id="correction-reason" class="form-control" formControlName="correction_reason" rows="2" required></textarea>
          }
          <label for="intake-occurrence">When a local time repeats</label><select id="intake-occurrence" class="form-select" [value]="occurrence" (change)="occurrence=$any($event.target).value"><option value="reject">Ask me to choose</option><option value="earlier">Earlier occurrence</option><option value="later">Later occurrence</option></select>
          @for(item of errors();track item[0]) { <p class="field-error">{{ item[1][0] }}</p> }
          <div class="button-row"><button class="btn btn-primary" type="submit" [disabled]="busy() || form.invalid">{{ busy() ? 'Saving…' : correction() ? 'Save correction' : 'Save administration' }}</button>@if(correction()) { <button class="btn btn-outline-secondary" type="button" (click)="reset()">Cancel correction</button> }</div>
        </form>
      </section>
    </div>
  `
})
export class IntakesPage {
  private readonly route = inject(ActivatedRoute); private readonly api = inject(ApiClient); private readonly fb = inject(FormBuilder); private readonly auth = inject(AuthStore);
  readonly childId = this.route.snapshot.paramMap.get('child') ?? ''; readonly medicationId = this.route.snapshot.paramMap.get('medication') ?? '';
  readonly medication = signal<FamilyRecord | null>(null); readonly records = signal<FamilyRecord[]>([]); readonly correction = signal<FamilyRecord | null>(null);
  readonly error = signal(''); readonly errors = signal<[string,string[]][]>([]); readonly busy = signal(false); readonly page = signal(1); readonly lastPage = signal(1);
  readonly time = timeLabel; occurrence = 'reject'; private requestId = crypto.randomUUID();
  readonly form = this.fb.nonNullable.group({administered_at:['',Validators.required],timezone:[this.auth.user()?.family?.timezone ?? 'Asia/Manila',Validators.required],actual_amount:[''],dose_unit:[''],notes:[''],state:['recorded'],correction_reason:['']});
  constructor() { void this.load(); }
  async load(page = 1): Promise<void> {
    try {
      const [medication,result] = await Promise.all([this.api.get<Envelope<FamilyRecord>>(this.path()),this.api.get<Page<FamilyRecord>>(this.path()+'/intakes',{page})]);
      this.medication.set(medication.data); this.records.set(result.data); this.page.set(result.meta?.current_page ?? 1); this.lastPage.set(result.meta?.last_page ?? 1);
    } catch(error) { this.error.set(errorMessage(error)); }
  }
  correct(record: FamilyRecord, state = 'recorded'): void {
    this.correction.set(record); this.requestId = crypto.randomUUID();
    this.form.setValue({administered_at:instantToLocal(record.administered_at!,record.timezone!),timezone:record.timezone!,actual_amount:record.actual_amount ?? '',dose_unit:record.dose_unit ?? '',notes:record.notes ?? '',state,correction_reason:''});
    this.form.controls.correction_reason.addValidators(Validators.required); this.form.controls.correction_reason.updateValueAndValidity();
  }
  reset(): void {
    this.correction.set(null); this.requestId = crypto.randomUUID(); this.form.reset({timezone:this.auth.user()?.family?.timezone ?? 'Asia/Manila',state:'recorded'});
    this.form.controls.correction_reason.clearValidators(); this.form.controls.correction_reason.updateValueAndValidity(); this.errors.set([]);
  }
  async save(): Promise<void> {
    if(this.busy() || this.form.invalid) return;
    this.busy.set(true); this.error.set(''); this.errors.set([]);
    try {
      const raw = this.form.getRawValue();
      const payload: Record<string,unknown> = {...raw,administered_at:localToInstant(raw.administered_at,raw.timezone,this.occurrence),actual_amount:raw.actual_amount || null,dose_unit:raw.dose_unit || null,notes:raw.notes || null,client_request_id:this.requestId};
      const correction = this.correction();
      if(!correction) delete payload['correction_reason'];
      await this.api.post(this.path()+'/intakes'+(correction ? '/'+correction.id+'/corrections' : ''),payload);
      this.reset(); await this.load();
    } catch(error) { this.error.set(error instanceof Error && error.name === 'Error' ? error.message : errorMessage(error)); this.errors.set(Object.entries(fieldErrors(error))); }
    finally { this.busy.set(false); }
  }
  private path(): string { return 'children/'+this.childId+'/medications/'+this.medicationId; }
}
