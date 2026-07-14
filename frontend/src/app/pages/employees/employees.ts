import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { toSignal } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { ApiErrorBody, Employee, EmployeePayload } from '../../models/academy.models';
import { AcademyService } from '../../services/academy';

@Component({ selector: 'app-employees-page', imports: [ReactiveFormsModule], templateUrl: './employees.html', styleUrl: './employees.scss' })
export class EmployeesPage {
  private readonly api = inject(AcademyService);
  readonly employees = signal<Employee[]>([]);
  readonly loading = signal(false);
  readonly saving = signal(false);
  readonly message = signal('');
  readonly error = signal('');
  readonly fieldErrors = signal<Record<string, string>>({});
  readonly editorOpen = signal(false);
  readonly search = new FormControl('', { nonNullable: true });
  readonly form = new FormGroup({
    nome: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.maxLength(80)] }),
    cognome: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.maxLength(80)] }),
    email: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.email, Validators.maxLength(190)] }),
    password: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.minLength(8)] }),
    confermaPassword: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
  });
  private readonly searchValue = toSignal(this.search.valueChanges, { initialValue: '' });
  readonly visibleEmployees = computed(() => {
    const query = this.searchValue().trim().toLowerCase();
    if (!query) return this.employees();
    return this.employees().filter((employee) =>
      `${employee.nome} ${employee.cognome} ${employee.email}`.toLowerCase().includes(query)
    );
  });

  constructor() { this.load(); }

  load(): void {
    this.loading.set(true);
    this.error.set('');
    this.api.getEmployees().pipe(finalize(() => this.loading.set(false))).subscribe({
      next: (response) => this.employees.set(response.dipendenti),
      error: (error: HttpErrorResponse) => this.error.set((error.error as ApiErrorBody | null)?.message ?? 'Impossibile caricare i dipendenti.'),
    });
  }

  create(): void {
    this.message.set('');
    this.error.set('');
    this.fieldErrors.set({});
    this.form.reset({ nome: '', cognome: '', email: '', password: '', confermaPassword: '' });
    this.editorOpen.set(true);
  }

  closeEditor(): void { this.editorOpen.set(false); }

  save(): void {
    this.form.markAllAsTouched();
    const raw = this.form.getRawValue();
    if (raw.password !== raw.confermaPassword) {
      this.fieldErrors.set({ confermaPassword: 'Le password non coincidono' });
      return;
    }
    if (this.form.invalid) return;

    const payload: EmployeePayload = {
      nome: raw.nome.trim(),
      cognome: raw.cognome.trim(),
      email: raw.email.trim().toLowerCase(),
      password: raw.password,
    };
    this.saving.set(true);
    this.fieldErrors.set({});
    this.api.createEmployee(payload).pipe(finalize(() => this.saving.set(false))).subscribe({
      next: (response) => {
        this.editorOpen.set(false);
        this.message.set(response.message);
        this.error.set('');
        this.load();
      },
      error: (error: HttpErrorResponse) => {
        const body = error.error as ApiErrorBody | null;
        this.fieldErrors.set(body?.errors ?? {});
        this.error.set(body?.message ?? 'Impossibile creare il dipendente. Riprova.');
      },
    });
  }
}
