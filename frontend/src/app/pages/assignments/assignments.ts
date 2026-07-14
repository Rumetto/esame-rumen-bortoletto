import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime, finalize, forkJoin } from 'rxjs';
import { ApiErrorBody, Assignment, AssignmentPayload, AssignmentStatus, Course, Employee } from '../../models/academy.models';
import { AcademyService } from '../../services/academy';

@Component({ selector: 'app-assignments-page', imports: [ReactiveFormsModule], templateUrl: './assignments.html', styleUrl: './assignments.scss' })
export class AssignmentsPage {
  private readonly api = inject(AcademyService);
  readonly assignments = signal<Assignment[]>([]);
  readonly courses = signal<Course[]>([]);
  readonly employees = signal<Employee[]>([]);
  readonly loading = signal(false);
  readonly saving = signal(false);
  readonly message = signal('');
  readonly error = signal('');
  readonly fieldErrors = signal<Record<string, string>>({});
  readonly editorOpen = signal(false);
  readonly editing = signal<Assignment | null>(null);
  readonly categories = computed(() => [...new Set(this.courses().map((course) => course.categoria))].sort());
  readonly statuses: AssignmentStatus[] = ['ASSEGNATO', 'COMPLETATO', 'SCADUTO', 'ANNULLATO'];
  readonly today = new Date().toISOString().slice(0, 10);

  readonly filters = new FormGroup({
    dipendente_id: new FormControl('', { nonNullable: true }),
    corso_id: new FormControl('', { nonNullable: true }),
    categoria: new FormControl('', { nonNullable: true }),
    stato: new FormControl('', { nonNullable: true }),
  });
  readonly form = new FormGroup({
    corso_id: new FormControl<number | null>(null, Validators.required),
    dipendente_id: new FormControl<number | null>(null, Validators.required),
    data_assegnazione: new FormControl('', { nonNullable: true, validators: Validators.required }),
    data_scadenza: new FormControl('', { nonNullable: true, validators: Validators.required }),
    stato: new FormControl<AssignmentStatus>('ASSEGNATO', { nonNullable: true, validators: Validators.required }),
    data_completamento: new FormControl<string | null>(null),
  });

  constructor() {
    this.loadReferenceData();
    this.filters.valueChanges
      .pipe(debounceTime(250), takeUntilDestroyed())
      .subscribe(() => this.load());
    this.load();
  }

  loadReferenceData(): void {
    forkJoin({ courses: this.api.getCourses(), employees: this.api.getEmployees() }).subscribe({
      next: ({ courses, employees }) => { this.courses.set(courses.corsi); this.employees.set(employees.dipendenti); },
      error: (error) => this.error.set(this.errorMessage(error)),
    });
  }

  load(): void {
    this.loading.set(true);
    this.error.set('');
    this.api.getAssignments(this.filters.getRawValue()).pipe(finalize(() => this.loading.set(false))).subscribe({
      next: (response) => this.assignments.set(response.assegnazioni),
      error: (error) => this.error.set(this.errorMessage(error)),
    });
  }

  resetFilters(): void { this.filters.reset({ dipendente_id: '', corso_id: '', categoria: '', stato: '' }); }

  create(): void {
    const start = this.isoDate(new Date());
    const due = new Date(); due.setDate(due.getDate() + 30);
    this.editing.set(null);
    this.fieldErrors.set({});
    this.form.reset({ corso_id: null, dipendente_id: null, data_assegnazione: start, data_scadenza: this.isoDate(due), stato: 'ASSEGNATO', data_completamento: null });
    this.editorOpen.set(true);
  }

  edit(item: Assignment): void {
    this.editing.set(item);
    this.fieldErrors.set({});
    this.form.reset({
      corso_id: item.corso.id, dipendente_id: item.dipendente.id,
      data_assegnazione: item.data_assegnazione, data_scadenza: item.data_scadenza,
      stato: item.stato, data_completamento: item.data_completamento,
    });
    this.editorOpen.set(true);
  }

  closeEditor(): void { this.editorOpen.set(false); }

  save(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid) return;
    const raw = this.form.getRawValue();
    if (raw.data_scadenza < raw.data_assegnazione) {
      this.fieldErrors.set({ data_scadenza: 'La scadenza non può precedere la data di assegnazione' }); return;
    }
    const completion = raw.stato === 'COMPLETATO' ? raw.data_completamento : null;
    if (raw.stato === 'COMPLETATO' && !completion) {
      this.fieldErrors.set({ data_completamento: 'Indica la data di completamento' }); return;
    }
    if (completion && completion < raw.data_assegnazione) {
      this.fieldErrors.set({ data_completamento: 'Il completamento non può precedere l’assegnazione' }); return;
    }
    if (completion && completion > this.today) {
      this.fieldErrors.set({ data_completamento: 'La data di completamento non può essere futura' }); return;
    }
    const payload: AssignmentPayload = {
      corso_id: Number(raw.corso_id), dipendente_id: Number(raw.dipendente_id),
      data_assegnazione: raw.data_assegnazione, data_scadenza: raw.data_scadenza,
      stato: raw.stato, data_completamento: completion,
    };
    const request = this.editing() ? this.api.updateAssignment(this.editing()!.id, payload) : this.api.createAssignment(payload);
    this.saving.set(true); this.fieldErrors.set({}); this.error.set('');
    request.pipe(finalize(() => this.saving.set(false))).subscribe({
      next: (response) => { this.editorOpen.set(false); this.message.set(response.message); this.load(); },
      error: (error: HttpErrorResponse) => {
        this.fieldErrors.set((error.error as ApiErrorBody | null)?.errors ?? {});
        this.error.set(this.errorMessage(error));
      },
    });
  }

  cancel(item: Assignment): void {
    if (!confirm(`Annullare l’assegnazione di “${item.corso.titolo}” a ${item.dipendente.nome} ${item.dipendente.cognome}?`)) return;
    this.api.cancelAssignment(item.id).subscribe({
      next: (response) => { this.message.set(response.message); this.load(); },
      error: (error) => this.error.set(this.errorMessage(error)),
    });
  }

  courseSelectable(course: Course): boolean { return course.attivo || course.id === this.editing()?.corso.id; }
  statusLabel(status: AssignmentStatus): string { return status.charAt(0) + status.slice(1).toLowerCase(); }
  private isoDate(date: Date): string { return date.toISOString().slice(0, 10); }
  private errorMessage(error: HttpErrorResponse): string { return (error.error as ApiErrorBody | null)?.message ?? 'Impossibile completare l’operazione.'; }
}
