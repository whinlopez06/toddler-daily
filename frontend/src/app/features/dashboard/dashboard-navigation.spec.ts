import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { expect, it, vi } from 'vitest';
import { ApiClient } from '../../core/api/api-client';
import { AuthStore } from '../../core/auth/auth-store';
import { ChildrenPage } from '../children/children-page';
import { RecordsPage } from '../records/records-page';
import { DashboardPage } from './dashboard-page';

it('opens the selected child’s Medications tab from the dashboard and defaults to Timeline from Children', async () => {
  const child = { id: 'sample-child', display_name: 'Sample Child', birth_date: '2023-04-05' };
  const get = vi.fn(async (path: string) => {
    switch (path) {
      case 'dashboard': return { data: { children: [child], appointments: [], recent_entries: [], timezone: 'Asia/Manila' } };
      case 'children': return { data: [child] };
      case 'children/sample-child': return { data: child };
      default: return { data: [] };
    }
  });
  TestBed.configureTestingModule({ providers: [
    provideRouter([
      { path: '', pathMatch: 'full', component: DashboardPage },
      { path: 'children', component: ChildrenPage },
      { path: 'children/:child/:type', component: RecordsPage },
    ]),
    { provide: ApiClient, useValue: { get, post: vi.fn().mockResolvedValue(undefined) } },
    { provide: AuthStore, useValue: { user: () => null } },
  ] });
  const harness = await RouterTestingHarness.create('/');
  const router = TestBed.inject(Router);
  const clickLink = async (label: string) => {
    harness.detectChanges();
    const links = harness.routeNativeElement!.querySelectorAll<HTMLAnchorElement>('a');
    const link = Array.from(links).find(anchor => anchor.textContent.trim() === label);
    expect(link).toBeDefined();
    link!.click();
    await harness.fixture.whenStable();
    harness.detectChanges();
  };

  await clickLink('Manage medications');
  expect(router.url).toBe('/children?tab=medications');
  await clickLink('Sample Child');
  expect(router.url).toBe('/children/sample-child/medications');
  expect(harness.routeNativeElement!.querySelector('[aria-current="page"]')?.textContent).toBe('Medications');
  expect(get).toHaveBeenCalledWith('children/sample-child/medications', { page: 1 });

  await clickLink('Children');
  await clickLink('Sample Child');
  expect(router.url).toBe('/children/sample-child/timeline-entries');
});
