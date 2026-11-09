import { HttpInterceptorFn } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { finalize, timeout } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class RequestState {
  readonly slow = signal(false);
  private active = 0;
  private timer?: ReturnType<typeof setTimeout>;
  begin(): void {
    if (++this.active === 1) this.timer = setTimeout(() => this.slow.set(true), 7000);
  }
  end(): void {
    if (--this.active <= 0) { this.active = 0; clearTimeout(this.timer); this.slow.set(false); }
  }
}
export const requestInterceptor: HttpInterceptorFn = (request, next) => {
  const state = inject(RequestState);
  state.begin();
  return next(request.clone({ withCredentials: true, setHeaders: { Accept: 'application/json' } }))
    .pipe(timeout(90000), finalize(() => state.end()));
};
