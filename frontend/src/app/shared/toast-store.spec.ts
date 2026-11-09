import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ToastStore } from './toast-store';
import { ApiClient } from '../core/api/api-client';

describe('Mutation toasts', () => {
  beforeEach(() => { vi.useFakeTimers(); TestBed.configureTestingModule({providers:[provideHttpClient(),provideHttpClientTesting()]}); });
  afterEach(() => { TestBed.inject(HttpTestingController).verify(); vi.useRealTimers(); });
  it('dismisses after four seconds and restarts the timer for a newer message', () => {
    const store = TestBed.inject(ToastStore);
    store.show('Saved.'); vi.advanceTimersByTime(3999); expect(store.message()).toBe('Saved.');
    store.show('Updated.'); vi.advanceTimersByTime(1); expect(store.message()).toBe('Updated.');
    vi.advanceTimersByTime(3999); expect(store.message()).toBe('');
    store.show('Deleted.'); store.dismiss(); expect(store.message()).toBe('');
  });
  it('confirms successful writes but not failures or background refreshes', async () => {
    const api = TestBed.inject(ApiClient), http = TestBed.inject(HttpTestingController), store = TestBed.inject(ToastStore);
    const saved = api.post('children', {}); expect(store.message()).toBe('');
    http.expectOne('/api/v1/children').flush({}); await saved; expect(store.message()).toBe('Child profile saved.'); store.dismiss();
    const failed = api.patch('children/1', {}).catch(() => undefined);
    http.expectOne('/api/v1/children/1').flush({}, {status:422,statusText:'Invalid'}); await failed; expect(store.message()).toBe('');
    const refreshed = api.post('notifications/refresh', {}); http.expectOne('/api/v1/notifications/refresh').flush({}); await refreshed; expect(store.message()).toBe('');
    const deleted = api.delete('children/1/timeline-entries/2', {}); http.expectOne('/api/v1/children/1/timeline-entries/2').flush({}); await deleted; expect(store.message()).toBe('Timeline entry deleted.');
  });
});
