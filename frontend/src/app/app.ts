import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { RequestState } from './core/api/request-state';
import { ToastStore } from './shared/toast-store';

@Component({
  imports: [RouterOutlet],
  selector: 'app-root',
  styleUrl: './app.scss',
  templateUrl: './app.html',
})
export class App {
  readonly toasts = inject(ToastStore);
  protected readonly requests = inject(RequestState);
}
