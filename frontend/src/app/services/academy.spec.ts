import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../environments/environment';
import { AssignmentPayload, EmployeePayload } from '../models/academy.models';
import { AcademyService } from './academy';

describe('AcademyService', () => {
  let service: AcademyService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(AcademyService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('invia i filtri del catalogo come parametri query', () => {
    service.getCourses({ categoria: 'Sicurezza', attivo: true, vuoto: '' }).subscribe();
    const request = http.expectOne((item) => item.url === `${environment.apiUrl}/corsi`);
    expect(request.request.method).toBe('GET');
    expect(request.request.params.get('categoria')).toBe('Sicurezza');
    expect(request.request.params.get('attivo')).toBe('true');
    expect(request.request.params.has('vuoto')).toBe(false);
    request.flush({ success: true, totale: 0, corsi: [] });
  });

  it('crea una assegnazione con il payload richiesto dal backend', () => {
    const payload: AssignmentPayload = {
      corso_id: 2, dipendente_id: 3, data_assegnazione: '2026-07-14',
      data_scadenza: '2026-08-14', stato: 'ASSEGNATO', data_completamento: null,
    };
    service.createAssignment(payload).subscribe();
    const request = http.expectOne(`${environment.apiUrl}/assegnazioni-corsi`);
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual(payload);
    request.flush({ success: true, message: 'ok', assegnazione: {} });
  });

  it('crea un dipendente senza permettere di scegliere il ruolo', () => {
    const payload: EmployeePayload = {
      nome: 'Paolo', cognome: 'Test', email: 'paolo.test@azienda.it', password: 'Password2026!',
    };
    service.createEmployee(payload).subscribe();
    const request = http.expectOne(`${environment.apiUrl}/utenti/register`);
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual(payload);
    expect(request.request.body.ruolo).toBeUndefined();
    request.flush({ success: true, message: 'Dipendente creato', utente: { id: 6, ...payload } });
  });

  it('usa l’endpoint protetto per completare un corso', () => {
    service.completeAssignment(7).subscribe();
    const request = http.expectOne(`${environment.apiUrl}/assegnazioni-corsi/7/completa`);
    expect(request.request.method).toBe('PUT');
    request.flush({ success: true, message: 'ok', assegnazione: {} });
  });
});
