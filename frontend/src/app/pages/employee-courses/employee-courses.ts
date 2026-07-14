import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { ApiErrorBody, Assignment, AssignmentStatus } from '../../models/academy.models';
import { AcademyService } from '../../services/academy';

@Component({ selector: 'app-employee-courses-page', imports: [ReactiveFormsModule], templateUrl: './employee-courses.html', styleUrl: './employee-courses.scss' })
export class EmployeeCoursesPage {
  private readonly api = inject(AcademyService);
  private readonly route = inject(ActivatedRoute);
  readonly deadlineOnly = this.route.snapshot.data['deadlineOnly'] === true;
  readonly assignments = signal<Assignment[]>([]);
  readonly loading = signal(false);
  readonly completing = signal<number | null>(null);
  readonly message = signal('');
  readonly error = signal('');
  readonly selected = signal<Assignment | null>(null);
  readonly categories = computed(() => [...new Set(this.assignments().map((item) => item.corso.categoria))].sort());
  readonly statuses: AssignmentStatus[] = ['ASSEGNATO', 'COMPLETATO', 'SCADUTO', 'ANNULLATO'];
  readonly filters = new FormGroup({
    stato: new FormControl('', { nonNullable: true }),
    categoria: new FormControl('', { nonNullable: true }),
    scadenza_da: new FormControl('', { nonNullable: true }),
    scadenza_a: new FormControl('', { nonNullable: true }),
  });
  private readonly filterValues = toSignal(this.filters.valueChanges, { initialValue: this.filters.getRawValue() });
  readonly visibleAssignments = computed(() => {
    const filters = this.filterValues();
    return this.assignments().filter((item) => {
      if (this.deadlineOnly && ['COMPLETATO', 'ANNULLATO'].includes(item.stato)) return false;
      if (filters.stato && item.stato !== filters.stato) return false;
      if (filters.categoria && item.corso.categoria !== filters.categoria) return false;
      if (filters.scadenza_da && item.data_scadenza < filters.scadenza_da) return false;
      if (filters.scadenza_a && item.data_scadenza > filters.scadenza_a) return false;
      return true;
    });
  });

  constructor() { this.load(); }

  load(): void {
    this.loading.set(true); this.error.set('');
    this.api.getAssignments().pipe(finalize(() => this.loading.set(false))).subscribe({
      next: (response) => this.assignments.set(response.assegnazioni),
      error: (error) => this.error.set(this.errorMessage(error)),
    });
  }

  resetFilters(): void { this.filters.reset({ stato: '', categoria: '', scadenza_da: '', scadenza_a: '' }); }
  showDetail(item: Assignment): void { this.selected.set(item); }
  closeDetail(): void { this.selected.set(null); }

  complete(item: Assignment): void {
    if (!confirm(`Confermi di aver completato il corso “${item.corso.titolo}”?`)) return;
    this.completing.set(item.id); this.error.set('');
    this.api.completeAssignment(item.id).pipe(finalize(() => this.completing.set(null))).subscribe({
      next: (response) => { this.selected.set(response.assegnazione); this.message.set(response.message); this.load(); },
      error: (error) => this.error.set(this.errorMessage(error)),
    });
  }

  canComplete(item: Assignment): boolean { return item.stato === 'ASSEGNATO' || item.stato === 'SCADUTO'; }
  statusLabel(status: AssignmentStatus): string { return status.charAt(0) + status.slice(1).toLowerCase(); }
  private errorMessage(error: HttpErrorResponse): string { return (error.error as ApiErrorBody | null)?.message ?? 'Impossibile caricare i corsi.'; }
}
