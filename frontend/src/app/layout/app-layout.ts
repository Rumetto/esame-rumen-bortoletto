import { Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { finalize } from 'rxjs/operators';
import { UserRole } from '../models/auth.models';
import { AuthService } from '../services/auth';

interface NavigationItem { label: string; path: string; icon: string; roles?: UserRole[]; }

@Component({
  selector: 'app-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './app-layout.html',
  styleUrl: './app-layout.scss'
})
export class AppLayout {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly user = this.auth.user;
  protected readonly menuOpen = signal(false);
  protected readonly loggingOut = signal(false);
  protected readonly navigation = computed(() => {
    const role = this.user()?.ruolo;
    return this.allItems.filter((item) => !item.roles || (role && item.roles.includes(role)));
  });

  private readonly allItems: NavigationItem[] = [
    { label: 'Dashboard', path: '/dashboard', icon: '⌂' },
    { label: 'I miei corsi', path: '/dipendente/corsi', icon: '▤', roles: ['DIPENDENTE'] },
    { label: 'Scadenze', path: '/dipendente/scadenze', icon: '◷', roles: ['DIPENDENTE'] },
    { label: 'Catalogo corsi', path: '/academy/corsi', icon: '▦', roles: ['REFERENTE_ACADEMY'] },
    { label: 'Dipendenti', path: '/academy/dipendenti', icon: '◉', roles: ['REFERENTE_ACADEMY'] },
    { label: 'Assegnazioni', path: '/academy/assegnazioni', icon: '✓', roles: ['REFERENTE_ACADEMY'] },
    { label: 'Statistiche', path: '/academy/statistiche', icon: '↗', roles: ['REFERENTE_ACADEMY'] }
  ];

  protected closeMenu(): void { this.menuOpen.set(false); }

  protected logout(): void {
    this.loggingOut.set(true);
    this.auth.logout().pipe(finalize(() => this.loggingOut.set(false))).subscribe({
      next: () => void this.router.navigate(['/login']),
      error: () => void this.router.navigate(['/login'])
    });
  }
}
