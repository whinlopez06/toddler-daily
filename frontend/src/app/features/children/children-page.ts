import { Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiClient, errorMessage, fieldErrors } from '../../core/api/api-client';
import { Child, Envelope, Page } from '../../core/api/models';
import { AuthStore } from '../../core/auth/auth-store';
import { ageLabel, dateLabel } from '../../shared/date-format';

@Component({
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <header class="page-header"><div><h1>Children</h1><p>A separate history for each child, together in your family.</p></div><button class="btn btn-primary" (click)="create()">Add a child</button></header>
    @if (error()) { <p class="error-notice" role="alert">{{ error() }}</p> }
    <label class="check-label"><input type="checkbox" (change)="includeArchived = $any($event.target).checked; load()"> Include archived profiles</label>
    <div class="children-layout">
      <section class="panel"><h2>Your children</h2>
        @for (child of children(); track child.id) {
          <div class="child-profile-row"><span class="initial" aria-hidden="true">{{ child.display_name.charAt(0) }}</span><div class="grow"><a [routerLink]="['/children', child.id, recordTab()]"><strong>{{ child.display_name }}</strong></a><p>{{ age(child.birth_date, undefined, auth.user()?.family?.timezone) }} · Born {{ date(child.birth_date) }}</p>@if (child.archived_at) { <span class="status-chip">Archived</span> }</div><button class="text-button" (click)="edit(child)">Edit</button></div>
        } @empty { <div class="empty-state"><h3>No child profiles yet</h3><p>Add your first child to begin a private family record.</p></div> }
      </section>
      @if (formOpen()) {
        <section class="panel"><h2>{{ editing() ? 'Edit child profile' : 'Add a child' }}</h2><form [formGroup]="form" (ngSubmit)="save()">
          @if (editing() && !editing()?.first_name) { <p class="field-help">Existing name: {{ editing()?.display_name }}. Enter the name parts below to update this profile.</p> }
          <label for="first-name">First name</label><input id="first-name" class="form-control" formControlName="first_name" maxlength="100" required>
          <label for="middle-name">Middle name (optional)</label><input id="middle-name" class="form-control" formControlName="middle_name" maxlength="100">
          <label for="last-name">Last name</label><input id="last-name" class="form-control" formControlName="last_name" maxlength="100" required>
          <label for="suffix">Suffix (optional)</label><input id="suffix" class="form-control" formControlName="suffix" maxlength="20" placeholder="e.g. Jr., III">
          <fieldset class="gender-field"><legend>Gender</legend><div class="gender-options"><label class="check-label"><input type="radio" formControlName="gender" value="male"> Male</label><label class="check-label"><input type="radio" formControlName="gender" value="female"> Female</label></div></fieldset>
          <label for="birth-date">Birth date</label><input id="birth-date" class="form-control" type="date" formControlName="birth_date" required>
          <label for="child-age">Age</label><input id="child-age" class="form-control" readonly [value]="calculatedAge()" aria-describedby="age-help"><p id="age-help" class="field-help">Calculated from birth date and today's date in your family timezone.</p>
          @for (item of errors(); track item[0]) { <p class="field-error">{{ item[1][0] }}</p> }
          <div class="button-row"><button class="btn btn-primary" type="submit" [disabled]="busy() || form.invalid">{{ busy() ? 'Saving…' : 'Save profile' }}</button><button class="btn btn-outline-secondary" type="button" (click)="formOpen.set(false)">Cancel</button></div>
        </form>
        @if (editing(); as child) { <div class="separate-action"><p>{{ child.archived_at ? 'Restore this profile to add records again.' : 'Archiving keeps existing records and stops pending appointment reminders.' }}</p><button class="btn btn-outline-secondary" [disabled]="busy()" (click)="archive(child)">{{ child.archived_at ? 'Restore profile' : 'Archive profile' }}</button> <button type="button" class="btn btn-outline-danger" [disabled]="busy()" (click)="remove(child)">Delete profile</button></div> }
        </section>
      }
    </div>
  `
})
export class ChildrenPage {
  private readonly api = inject(ApiClient); readonly auth = inject(AuthStore); private readonly fb = inject(FormBuilder);
  private readonly route = inject(ActivatedRoute);
  private readonly queryParams = toSignal(this.route.queryParamMap);
  readonly recordTab = computed(() => this.queryParams()?.get('tab') === 'medications' ? 'medications' : 'timeline-entries');
  readonly children = signal<Child[]>([]); readonly editing = signal<Child | null>(null); readonly formOpen = signal(false);
  readonly busy = signal(false); readonly error = signal(''); readonly errors = signal<[string,string[]][]>([]);
  readonly form = this.fb.nonNullable.group({first_name:['',[Validators.required,Validators.pattern(/\S/)]], middle_name:[''], last_name:['',[Validators.required,Validators.pattern(/\S/)]], suffix:[''], gender:['',Validators.required], birth_date:['',Validators.required]});
  readonly age = ageLabel; readonly date = dateLabel; includeArchived = false;
  constructor() { this.formOpen.set(this.route.snapshot.queryParamMap.has('new')); void this.load(); }
  async load(): Promise<void> {
    try { const result = await this.api.get<Page<Child>>('children', {per_page:100, archived:String(this.includeArchived)}); this.children.set(result.data); if (!result.data.length) this.formOpen.set(true); }
    catch (error) { this.error.set(errorMessage(error)); }
  }
  create(): void { this.editing.set(null); this.form.reset(); this.errors.set([]); this.formOpen.set(true); }
  edit(child: Child): void { this.editing.set(child); this.form.setValue({first_name:child.first_name ?? '',middle_name:child.middle_name ?? '',last_name:child.last_name ?? '',suffix:child.suffix ?? '',gender:child.gender ?? '',birth_date:child.birth_date}); this.errors.set([]); this.formOpen.set(true); }
  calculatedAge(): string {
    const value = this.form.controls.birth_date.value;
    if (!value) return 'Enter a birth date';
    const today = new Intl.DateTimeFormat('en-CA', {timeZone:this.auth.user()?.family?.timezone || 'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
    if (value > today) return 'Birth date cannot be in the future';
    return ageLabel(value, new Date(), this.auth.user()?.family?.timezone);
  }
  async save(): Promise<void> {
    if (this.form.invalid || this.busy()) return;
    this.busy.set(true); this.error.set(''); this.errors.set([]);
    try {
      const child = this.editing();
      if (child) await this.api.patch<Envelope<Child>>('children/'+child.id, {...this.form.getRawValue(),version:child.version});
      else await this.api.post<Envelope<Child>>('children',this.form.getRawValue());
      this.formOpen.set(false); this.editing.set(null); this.form.reset(); await this.load();
    } catch (error) { this.error.set(errorMessage(error)); this.errors.set(Object.entries(fieldErrors(error))); }
    finally { this.busy.set(false); }
  }
  async remove(child: Child): Promise<void> {
    if (this.busy() || !window.confirm('Delete the profile for '+child.display_name+'? Only profiles with no remaining records can be deleted.')) return;
    this.busy.set(true);
    this.error.set('');
    try {
      await this.api.delete('children/'+child.id, {version:child.version});
      this.editing.set(null);
      this.formOpen.set(false);
      this.form.reset();
      await this.load();
    } catch (error) { this.error.set(errorMessage(error)); }
    finally { this.busy.set(false); }
  }
  async archive(child: Child): Promise<void> {
    this.busy.set(true);
    try { await this.api.patch('children/'+child.id,{display_name:child.display_name,birth_date:child.birth_date,version:child.version,archived:!child.archived_at}); this.formOpen.set(false); await this.load(); }
    catch (error) { this.error.set(errorMessage(error)); }
    finally { this.busy.set(false); }
  }
}
