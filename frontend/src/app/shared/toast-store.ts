import { Injectable, signal } from '@angular/core';
@Injectable({providedIn:'root'})
export class ToastStore {
  readonly message = signal('');
  private timer?: ReturnType<typeof setTimeout>;
  show(message: string): void { clearTimeout(this.timer); this.message.set(message); this.timer = setTimeout(() => this.dismiss(), 4000); }
  dismiss(): void { clearTimeout(this.timer); this.message.set(''); }
}
