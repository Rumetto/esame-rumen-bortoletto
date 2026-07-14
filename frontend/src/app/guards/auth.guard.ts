import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthStorageService } from '../services/auth-storage';

export const authGuard: CanActivateFn = (_, state) => {
  const storage = inject(AuthStorageService);

  return storage.authenticated()
    ? true
    : inject(Router).createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
};

export const guestGuard: CanActivateFn = () => {
  const storage = inject(AuthStorageService);

  return storage.authenticated() ? inject(Router).createUrlTree(['/dashboard']) : true;
};
