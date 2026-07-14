import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { environment } from '../../environments/environment';
import { AuthStorageService } from '../services/auth-storage';

export const authInterceptor: HttpInterceptorFn = (request, next) => {
  const storage = inject(AuthStorageService);
  const router = inject(Router);
  const token = storage.token;
  const isApiRequest = request.url.startsWith(environment.apiUrl);

  const authorizedRequest = token && isApiRequest
    ? request.clone({ setHeaders: { Authorization: `Bearer ${token}` } })
    : request;

  return next(authorizedRequest).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse
        && error.status === 401
        && !request.url.endsWith('/utenti/login')) {
        storage.clear();
        void router.navigate(['/login'], { queryParams: { sessione: 'scaduta' } });
      }

      return throwError(() => error);
    })
  );
};
