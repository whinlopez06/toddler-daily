import { HttpClient, HttpErrorResponse, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ToastStore } from '../../shared/toast-store';

@Injectable({ providedIn: 'root' })
export class ApiClient {
  private readonly http = inject(HttpClient);
  private readonly toasts = inject(ToastStore);
  get<T>(path: string, params: Record<string, string | number> = {}): Promise<T> {
    return firstValueFrom(this.http.get<T>('/api/v1/' + path, { params: new HttpParams({ fromObject: params }) }));
  }
  post<T>(path: string, body: unknown): Promise<T> { return this.confirm(firstValueFrom(this.http.post<T>('/api/v1/' + path, body)),path,'saved',body); }
  patch<T>(path: string, body: unknown): Promise<T> { return this.confirm(firstValueFrom(this.http.patch<T>('/api/v1/' + path, body)),path,'updated',body); }
  delete<T>(path: string, body: unknown): Promise<T> { return this.confirm(firstValueFrom(this.http.delete<T>('/api/v1/' + path, { body })),path,'deleted',body); }
  private async confirm<T>(request:Promise<T>,path:string,action:string,body:unknown):Promise<T> {
    const result = await request;
    if (path === 'confirm-password' || path === 'notifications/refresh') return result;
    const labels:Record<string,string> = {children:'Child profile','timeline-entries':'Timeline entry','growth-measurements':'Growth measurement',medications:'Medication','vaccination-records':'Vaccination record',appointments:'Appointment',intakes:'Administration',corrections:'Correction',family:'Family details',me:'Email preference',notifications:'Notification'};
    const kind = path.split('/').reverse().find(part => labels[part]);
    const archived = (body as {archived?:boolean} | null)?.archived;
    if (typeof archived === 'boolean') action = archived ? 'archived' : 'restored';
    this.toasts.show(path === 'family' && action === 'deleted' ? 'Family deletion requested.' : kind === 'notifications' ? 'Notification marked as read.' : (kind ? labels[kind] : 'Record') + ' ' + action + '.');
    return result;
  }
  download(path: string): Promise<Blob> { return firstValueFrom(this.http.get('/api/v1/' + path, { responseType: 'blob' })); }
}
export function errorMessage(error: unknown): string {
  if (error instanceof HttpErrorResponse) {
    if (error.error?.code === 'DEMO_EXPIRED') return 'Your demo has expired or been disabled. Your visit history has been retained.';
    if ([0, 502, 503, 504].includes(error.status)) return 'The server is unavailable or starting up. Your changes have not been saved. Try again shortly.';
    if (error.status === 419) return 'Your session expired. Refresh this page before trying again.';
    if (error.status === 401) return 'Please sign in again. Your changes have not been saved.';
    if (error.status === 429) return 'Too many requests. Wait a moment before trying again.';
    return error.error?.message || 'The request could not be completed.';
  }
  return 'The request could not be completed. Your changes have not been saved.';
}
export function fieldErrors(error: unknown): Record<string, string[]> {
  return error instanceof HttpErrorResponse ? error.error?.errors ?? {} : {};
}
