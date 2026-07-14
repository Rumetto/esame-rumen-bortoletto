import { TestBed } from '@angular/core/testing';
import { AuthUser } from '../models/auth.models';
import { AuthStorageService } from './auth-storage';

describe('AuthStorageService', () => {
  const user: AuthUser = {
    id: 1,
    nome: 'Anna',
    cognome: 'Academy',
    email: 'academy@azienda.it',
    ruolo: 'REFERENTE_ACADEMY',
  };

  beforeEach(() => {
    localStorage.clear();
    TestBed.resetTestingModule();
  });

  afterEach(() => localStorage.clear());

  it('salva una sessione valida e riconosce il ruolo', () => {
    const service = TestBed.inject(AuthStorageService);
    const token = createToken(Math.floor(Date.now() / 1000) + 3600);

    service.save(token, user);

    expect(service.authenticated()).toBe(true);
    expect(service.token).toBe(token);
    expect(service.user()).toEqual(user);
    expect(service.hasRole('REFERENTE_ACADEMY')).toBe(true);
    expect(service.hasRole('DIPENDENTE')).toBe(false);
  });

  it('rifiuta un token scaduto', () => {
    const service = TestBed.inject(AuthStorageService);
    const token = createToken(Math.floor(Date.now() / 1000) - 60);

    service.save(token, user);

    expect(service.token).toBeNull();
    expect(service.authenticated()).toBe(false);
  });

  it('cancella la sessione al logout', () => {
    const service = TestBed.inject(AuthStorageService);
    service.save(createToken(Math.floor(Date.now() / 1000) + 3600), user);

    service.clear();

    expect(service.authenticated()).toBe(false);
    expect(service.user()).toBeNull();
    expect(localStorage.length).toBe(0);
  });
});

function createToken(exp: number): string {
  const payload = btoa(JSON.stringify({ exp }))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');

  return `header.${payload}.signature`;
}
