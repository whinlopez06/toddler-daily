import { Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, FormRecord, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiClient, errorMessage, fieldErrors } from '../../core/api/api-client';
import { Child, Envelope, FamilyRecord, Page } from '../../core/api/models';
import { AuthStore } from '../../core/auth/auth-store';
import { dateLabel, timeLabel } from '../../shared/date-format';
import { instantToLocal, localToInstant } from '../../shared/zoned-time';
import { definitions, RecordDefinition } from './record-fields';

@Component({
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <header class="page-header"><div><a class="back-link" routerLink="/children">Children</a><h1>{{ child()?.display_name || 'Child records' }}</h1><p>{{ definition().title }}@if (child()?.archived_at) { · Archived profile }</p></div><button class="btn btn-primary" (click)="create()" [disabled]="!child() || !!child()?.archived_at">Add {{ definition().singular }}</button></header>
    <nav class="section-tabs" aria-label="Child records">@for (tab of tabs; track tab.key) { <a [routerLink]="['/children', childId, tab.key]" [class.selected]="type() === tab.key" [attr.aria-current]="type() === tab.key ? 'page' : null">{{ tab.label }}</a> }</nav>
    @if (error()) { <p class="error-notice" role="alert">{{ error() }}</p> }
    @if (child()?.archived_at) { <p class="notice">This child's history is available to read. Restore the profile from Children to add or change records.</p> }
    <div class="records-layout" [class.with-editor]="formOpen()">
      <section class="panel"><div class="section-heading"><h2>{{ definition().title }}</h2><span>{{ total() }} recorded</span></div>
        @if (loading()) { <p role="status">Loading records…</p> }
        @for (record of records(); track record.id) {
          <article class="record-entry"><div class="record-entry-heading"><h3>{{ title(record) }}</h3><time>{{ when(record) }}</time></div>
            @if (record.description || record.instructions || record.notes || record.reason) { <p class="preserve-lines">{{ record.description || record.instructions || record.notes || record.reason }}</p> }
            @if (record.kind && type() === 'timeline-entries') { <p class="muted">{{ record.kind.replaceAll('_',' ') }}@if(record.category) { · {{ record.category }} }</p> }
            @if (type() === 'growth-measurements') { <p>{{ measurement(record) }}</p> }
            @if (type() === 'medications' && record.instructed_amount) { <p>Recorded instructed amount: {{ formatInstructedAmount(record.instructed_amount) }} {{ record.dose_unit }}</p> }
            @if (record.archived_at) { <span class="status-chip">Archived</span> }
            @if (record.status) { <p class="status-chip">{{ record.status }}</p> }
            @if (record.provider || record.clinic) { <p>{{ record.provider }}@if(record.clinic) { · {{ record.clinic }} }</p> }
            @if (record.visit_summary) { <p class="preserve-lines"><strong>Visit summary:</strong> {{ record.visit_summary }}</p> }
            <div class="row-actions">
              <button class="text-button" (click)="edit(record)" [disabled]="!!child()?.archived_at">Edit</button>
              @if (type() === 'medications') { <a [routerLink]="['/children',childId,'medications',record.id,'intakes']">Intake history</a><button class="text-button" (click)="archiveMedication(record)" [disabled]="busy() || !!child()?.archived_at">{{ record.archived_at ? 'Restore medication' : 'Archive medication' }}</button> }
              <button class="text-button danger-text" (click)="remove(record)" [disabled]="busy() || !!child()?.archived_at">Delete</button>
            </div>
          </article>
        } @empty { @if (!loading()) { <div class="empty-state"><h3>No {{ definition().title.toLowerCase() }} recorded</h3><p>Add the first {{ definition().singular }} when you have something to record.</p></div> } }
        @if (lastPage() > 1) { <div class="pagination-row"><button class="btn btn-outline-secondary" [disabled]="page() <= 1" (click)="load(page()-1)">Previous</button><span>Page {{ page() }} of {{ lastPage() }}</span><button class="btn btn-outline-secondary" [disabled]="page() >= lastPage()" (click)="load(page()+1)">Next</button></div> }
      </section>
      @if (formOpen()) {
        <section class="panel editor"><h2>{{ editing() ? 'Edit ' : 'Add ' }}{{ definition().singular }}</h2>
          @if (type() === 'appointments') { <p class="field-help">Appointment reminders are best effort. Email reminders also require opt-in in Settings.</p> }
          <form [formGroup]="form" (ngSubmit)="save()">
            @for (field of definition().fields; track field.key) {
              <div class="form-field"><label [for]="'record-'+field.key">{{ field.label }}</label>
                @switch (field.type) {
                  @case ('textarea') { <textarea [id]="'record-'+field.key" class="form-control" [formControlName]="field.key" rows="3" maxlength="10000"></textarea> }
                  @case ('select') { <select [id]="'record-'+field.key" class="form-select" [formControlName]="field.key">@for(option of field.options; track option[0]) { <option [value]="option[0]">{{ option[1] }}</option> }</select> }
                  @default { <input [id]="'record-'+field.key" class="form-control" [type]="field.type || 'text'" [formControlName]="field.key" [attr.step]="field.type === 'number' ? 'any' : null" [attr.min]="field.type === 'number' ? '0.001' : null" [attr.aria-describedby]="field.help ? 'help-'+field.key : null"> }
                }
                @if (field.help) { <p class="field-help" [id]="'help-'+field.key">{{ field.help }}</p> }
                @for(message of errors()[field.key] || []; track message) { <p class="field-error">{{ message }}</p> }
              </div>
            }
            @if (type() === 'appointments') { <label for="time-occurrence">When a local time repeats</label><select id="time-occurrence" class="form-select" [value]="occurrence" (change)="occurrence=$any($event.target).value"><option value="reject">Ask me to choose</option><option value="earlier">Earlier occurrence</option><option value="later">Later occurrence</option></select> }
            <div class="button-row"><button class="btn btn-primary" type="submit" [disabled]="busy() || form.invalid">{{ busy() ? 'Saving…' : 'Save '+definition().singular }}</button><button class="btn btn-outline-secondary" type="button" (click)="formOpen.set(false)">Cancel</button></div>
          </form>
        </section>
      }
    </div>
  `
})
export class RecordsPage {
  private readonly route = inject(ActivatedRoute); private readonly api = inject(ApiClient); private readonly auth = inject(AuthStore);
  readonly child = signal<Child | null>(null); readonly type = signal('timeline-entries'); readonly definition = signal<RecordDefinition>(definitions['timeline-entries']);
  readonly records = signal<FamilyRecord[]>([]); readonly editing = signal<FamilyRecord | null>(null); readonly formOpen = signal(false);
  readonly form = new FormRecord<FormControl<string>>({}); readonly error = signal(''); readonly errors = signal<Record<string,string[]>>({});
  readonly busy = signal(false); readonly loading = signal(false); readonly page = signal(1); readonly lastPage = signal(1); readonly total = signal(0);
  readonly tabs = ['medications', 'appointments', 'vaccination-records', 'timeline-entries', 'growth-measurements']
    .map(key => ({key, label:definitions[key].title}));
  childId = ''; occurrence = 'reject'; private generation = 0;
  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed()).subscribe(params => {
      this.childId = params.get('child') ?? ''; const type = params.get('type') ?? 'timeline-entries';
      if (!definitions[type]) { this.error.set('This record section does not exist.'); return; }
      this.type.set(type); this.definition.set(definitions[type]); this.child.set(null); this.records.set([]); this.formOpen.set(false); this.editing.set(null); this.buildForm(); void this.load(1);
    });
  }
  private buildForm(): void {
    for(const key of Object.keys(this.form.controls)) this.form.removeControl(key);
    for(const field of this.definition().fields) this.form.addControl(field.key,new FormControl('', {nonNullable:true,validators:field.required ? [Validators.required] : []}));
    this.defaults();
  }
  private defaults(): void {
    const timezone = this.auth.user()?.family?.timezone ?? 'Asia/Manila';
    this.form.reset();
    const defaults: Record<string,string> = {kind:this.type() === 'appointments' ? 'general' : 'memory',status:'scheduled',reminders_enabled:'true',timezone};
    for(const [key,value] of Object.entries(defaults)) this.form.controls[key]?.setValue(value);
    this.occurrence = 'reject';
  }
  async load(page = this.page()): Promise<void> {
    const generation = ++this.generation; this.loading.set(true); this.error.set('');
    try {
      const [child,response] = await Promise.all([this.api.get<Envelope<Child>>('children/'+this.childId),this.api.get<Page<FamilyRecord>>(this.path(),{page})]);
      if(generation !== this.generation) return;
      this.child.set(child.data); this.records.set(response.data); this.page.set(response.meta?.current_page ?? 1); this.lastPage.set(response.meta?.last_page ?? 1); this.total.set(response.meta?.total ?? response.data.length);
    } catch(error) { if(generation === this.generation) this.error.set(errorMessage(error)); }
    finally { if(generation === this.generation) this.loading.set(false); }
  }
  create(): void { this.editing.set(null); this.defaults(); this.errors.set({}); this.formOpen.set(true); }
  edit(record: FamilyRecord): void {
    this.editing.set(record); this.defaults();
    for(const field of this.definition().fields) {
      const value = (record as unknown as Record<string,unknown>)[field.key];
      const inputValue = field.key === 'instructed_amount'
        ? this.formatInstructedAmount(record.instructed_amount)
        : String(value ?? '');
      this.form.controls[field.key].setValue(inputValue);
    }
    if(record.starts_at) this.form.controls['starts_at']?.setValue(instantToLocal(record.starts_at,record.timezone ?? 'Asia/Manila'));
    this.errors.set({}); this.formOpen.set(true);
  }
  async save(): Promise<void> {
    if(this.form.invalid || this.busy()) return;
    this.busy.set(true); this.error.set(''); this.errors.set({});
    try {
      const values: Record<string,unknown> = Object.fromEntries(Object.entries(this.form.getRawValue()).map(([key,value]) => [key,value === '' ? null : value]));
      if(this.type() === 'appointments') {
        values['starts_at'] = localToInstant(String(values['starts_at']),String(values['timezone']),this.occurrence);
        values['reminders_enabled'] = values['reminders_enabled'] === 'true';
      }
      const editing = this.editing();
      if(editing) await this.api.patch(this.path()+'/'+editing.id,{...values,version:editing.version});
      else await this.api.post(this.path(),values);
      this.formOpen.set(false); this.editing.set(null); await this.load(1);
    } catch(error) {
      this.error.set(error instanceof Error && error.name === 'Error' ? error.message : errorMessage(error));
      this.errors.set(fieldErrors(error));
    } finally { this.busy.set(false); }
  }
  async remove(record: FamilyRecord): Promise<void> {
    const reminderNote = this.type() === 'appointments' ? ' Its reminders will also be removed.' : '';
    if (this.busy() || !window.confirm('Delete this '+this.definition().singular+' ('+this.title(record)+')?'+reminderNote)) return;
    this.error.set('');
    this.busy.set(true);
    try { await this.api.delete(this.path()+'/'+record.id,{version:record.version});
      if (this.editing()?.id === record.id) { this.editing.set(null); this.formOpen.set(false); }
      await this.load(); }
    catch(error) { this.error.set(errorMessage(error)); } finally { this.busy.set(false); }
  }
  async archiveMedication(record: FamilyRecord): Promise<void> {
    this.busy.set(true);
    const values = Object.fromEntries(this.definition().fields.map(field => [field.key,(record as unknown as Record<string,unknown>)[field.key] ?? null]));
    try { await this.api.patch(this.path()+'/'+record.id,{...values,version:record.version,archived:!record.archived_at}); await this.load(); }
    catch(error) { this.error.set(errorMessage(error)); } finally { this.busy.set(false); }
  }
  title(record: FamilyRecord): string { return record.title || record.name || record.vaccine_name || 'Growth measurement'; }
  formatInstructedAmount(amount: string | null | undefined): string {
    const value = String(amount ?? '');
    return value.includes('.') ? value.replace(/0+$/, '').replace(/\.$/, '') : value;
  }
  when(record: FamilyRecord): string {
    const value = (record as unknown as Record<string,string>)[this.definition().dateField];
    return this.type() === 'appointments' ? timeLabel(value,record.timezone ?? 'Asia/Manila') : dateLabel(value);
  }
  measurement(record: FamilyRecord): string { return [record.weight_value ? record.weight_value+' '+record.weight_unit : '',record.height_value ? record.height_value+' '+record.height_unit : ''].filter(Boolean).join(' · '); }
  private path(): string { return 'children/'+this.childId+'/'+this.type(); }
}
