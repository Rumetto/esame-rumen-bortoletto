import { Component, inject, OnInit, signal } from '@angular/core';
import { ApiService } from './services/api';

@Component({
  selector: 'app-root',
  templateUrl: './app.html',
  styleUrl: './app.scss'
})
export class App implements OnInit {
  private readonly api = inject(ApiService);

  protected readonly loading = signal(true);
  protected readonly connected = signal(false);
  protected readonly message = signal('Verifica collegamento in corso...');

  ngOnInit(): void {
    this.api.testConnection().subscribe({
      next: (response) => {
        this.connected.set(response.success);
        this.message.set(response.message);
        this.loading.set(false);
      },
      error: () => {
        this.connected.set(false);
        this.message.set('Backend non raggiungibile');
        this.loading.set(false);
      }
    });
  }
}
