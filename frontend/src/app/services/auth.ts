import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { finalize, tap } from 'rxjs/operators';
import { environment } from '../../environments/environment';
import { ApiMessage, LoginCredentials, LoginResponse } from '../models/auth.models';
import { AuthStorageService } from './auth-storage';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly storage = inject(AuthStorageService);
  private readonly apiUrl = environment.apiUrl;

  readonly user = this.storage.user;
  readonly authenticated = this.storage.authenticated;

  login(credentials: LoginCredentials): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(`${this.apiUrl}/utenti/login`, credentials)
      .pipe(tap((response) => this.storage.save(response.token, response.utente)));
  }

  logout(): Observable<ApiMessage> {
    return this.http
      .post<ApiMessage>(`${this.apiUrl}/utenti/logout`, {})
      .pipe(finalize(() => this.storage.clear()));
  }

  clearSession(): void {
    this.storage.clear();
  }
}
