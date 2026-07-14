import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { toSignal } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { ApiErrorBody, Employee } from '../../models/academy.models';
import { AcademyService } from '../../services/academy';

@Component({ selector: 'app-employees-page', imports: [ReactiveFormsModule], templateUrl: './employees.html', styleUrl: './employees.scss' })
export class EmployeesPage {
  private readonly api = inject(AcademyService);
  readonly employees = signal<Employee[]>([]);
  readonly loading = signal(false);
  readonly error = signal('');
  readonly search = new FormControl('', { nonNullable: true });
  private readonly searchValue = toSignal(this.search.valueChanges, { initialValue: '' });
  readonly visibleEmployees = computed(() => {
    const query = this.searchValue().trim().toLowerCase();
    if (!query) return this.employees();
    return this.employees().filter((employee) =>
      `${employee.nome} ${employee.cognome} ${employee.email}`.toLowerCase().includes(query)
    );
  });

  constructor() {
    this.loading.set(true);
    this.api.getEmployees().pipe(finalize(() => this.loading.set(false))).subscribe({
      next: (response) => this.employees.set(response.dipendenti),
      error: (error: HttpErrorResponse) => this.error.set((error.error as ApiErrorBody | null)?.message ?? 'Impossibile caricare i dipendenti.'),
    });
  }
}
