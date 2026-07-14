import { computed, Injectable, signal } from '@angular/core';
import { AuthUser, UserRole } from '../models/auth.models';

const TOKEN_KEY = 'academy_token';
const USER_KEY = 'academy_user';

@Injectable({ providedIn: 'root' })
export class AuthStorageService {
  private readonly userSignal = signal<AuthUser | null>(this.readUser());

  readonly user = this.userSignal.asReadonly();
  readonly authenticated = computed(() => this.userSignal() !== null && this.token !== null);

  get token(): string | null {
    const token = localStorage.getItem(TOKEN_KEY);

    return token && !this.isExpired(token) ? token : null;
  }

  save(token: string, user: AuthUser): void {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
    this.userSignal.set(user);
  }

  clear(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    this.userSignal.set(null);
  }

  hasRole(role: UserRole): boolean {
    return this.userSignal()?.ruolo === role;
  }

  private readUser(): AuthUser | null {
    const token = localStorage.getItem(TOKEN_KEY);
    const rawUser = localStorage.getItem(USER_KEY);

    if (!token || !rawUser || this.isExpired(token)) {
      localStorage.removeItem(TOKEN_KEY);
      localStorage.removeItem(USER_KEY);
      return null;
    }

    try {
      return JSON.parse(rawUser) as AuthUser;
    } catch {
      localStorage.removeItem(TOKEN_KEY);
      localStorage.removeItem(USER_KEY);
      return null;
    }
  }

  private isExpired(token: string): boolean {
    try {
      const encodedPayload = token.split('.')[1]
        .replace(/-/g, '+')
        .replace(/_/g, '/');
      const paddedPayload = encodedPayload.padEnd(
        encodedPayload.length + (4 - encodedPayload.length % 4) % 4,
        '='
      );
      const payload = JSON.parse(atob(paddedPayload)) as { exp?: number };
      return typeof payload.exp !== 'number' || payload.exp * 1000 <= Date.now();
    } catch {
      return true;
    }
  }
}
