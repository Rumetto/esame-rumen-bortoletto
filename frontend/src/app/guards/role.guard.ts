import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { UserRole } from '../models/auth.models';
import { AuthStorageService } from '../services/auth-storage';

export const roleGuard = (role: UserRole): CanActivateFn => () => {
  const storage = inject(AuthStorageService);

  return storage.hasRole(role)
    ? true
    : inject(Router).createUrlTree(['/dashboard'], { queryParams: { accesso: 'negato' } });
};
