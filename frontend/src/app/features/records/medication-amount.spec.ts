import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { expect, it, vi } from 'vitest';
import { ApiClient } from '../../core/api/api-client';
import { AuthStore } from '../../core/auth/auth-store';
import { RecordsPage } from './records-page';

it('displays saved medication amounts without trailing zeros and pre-fills the edit field the same way', async () => {
  const medications = [
    { id: 'sample-1', name: 'Sample medication 1', instructed_amount: '2.500', dose_unit: 'mL' },
    { id: 'sample-2', name: 'Sample medication 2', instructed_amount: '10.000', dose_unit: 'mL' },
    { id: 'sample-3', name: 'Sample medication 3', instructed_amount: '0.125', dose_unit: 'mL' },
    { id: 'sample-4', name: 'Sample medication 4', instructed_amount: null },
  ];
  const post = vi.fn().mockResolvedValue(undefined);
  TestBed.configureTestingModule({ providers: [
    provideRouter([{ path: 'children/:child/:type', component: RecordsPage }]),
    { provide: ApiClient, useValue: { post, get: async (path: string) => ({
      data: path === 'children/sample-child' ? { display_name: 'Sample Child' } : medications,
    }) } },
    { provide: AuthStore, useValue: { user: () => null } },
  ] });
  const harness = await RouterTestingHarness.create();
  const page = await harness.navigateByUrl('/children/sample-child/medications', RecordsPage);
  const root = harness.routeNativeElement!;
  const amountInput = () => root.querySelector<HTMLInputElement>('#record-instructed_amount')!;

  page.create();
  harness.detectChanges();
  expect(amountInput().value).toBe('');
  amountInput().value = '2.5';
  amountInput().dispatchEvent(new Event('input'));
  page.form.patchValue({ name: 'Sample medication', dose_unit: 'mL' });
  await page.save();
  harness.detectChanges();
  expect(post).toHaveBeenCalledWith('children/sample-child/medications', expect.objectContaining({ instructed_amount: '2.5' }));

  expect(Array.from(root.querySelectorAll('.record-entry p'), p => p.textContent.trim())).toEqual([
    'Recorded instructed amount: 2.5 mL',
    'Recorded instructed amount: 10 mL',
    'Recorded instructed amount: 0.125 mL',
  ]);
  const editButtons = Array.from(root.querySelectorAll<HTMLButtonElement>('.record-entry button'))
    .filter(button => button.textContent === 'Edit');
  expect(editButtons.map(button => {
    button.click();
    harness.detectChanges();
    return amountInput().value;
  })).toEqual(['2.5', '10', '0.125', '']);
});
