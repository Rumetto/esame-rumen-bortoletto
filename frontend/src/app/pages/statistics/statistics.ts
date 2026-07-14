import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime, finalize, forkJoin } from 'rxjs';
import { AcademyStatistic, ApiErrorBody, Course, Employee } from '../../models/academy.models';
import { AcademyService } from '../../services/academy';

@Component({ selector: 'app-statistics-page', imports: [ReactiveFormsModule], templateUrl: './statistics.html', styleUrl: './statistics.scss' })
export class StatisticsPage {
  private readonly api = inject(AcademyService);
  readonly statistics = signal<AcademyStatistic[]>([]);
  readonly courses = signal<Course[]>([]);
  readonly employees = signal<Employee[]>([]);
  readonly loading = signal(false);
  readonly error = signal('');
  readonly categories = computed(() => [...new Set(this.courses().map((course) => course.categoria))].sort());
  readonly totalAssigned = computed(() => this.statistics().reduce((total, row) => total + row.numeroAssegnazioni, 0));
  readonly totalCompleted = computed(() => this.statistics().reduce((total, row) => total + row.numeroCompletamenti, 0));
  readonly totalPercentage = computed(() => this.totalAssigned() === 0 ? 0 : Math.round(this.totalCompleted() * 10000 / this.totalAssigned()) / 100);
  readonly filters = new FormGroup({
    mese: new FormControl('', { nonNullable: true }),
    data_inizio: new FormControl('', { nonNullable: true }),
    data_fine: new FormControl('', { nonNullable: true }),
    categoria: new FormControl('', { nonNullable: true }),
    dipendente_id: new FormControl('', { nonNullable: true }),
  });

  constructor() {
    forkJoin({ courses: this.api.getCourses(), employees: this.api.getEmployees() }).subscribe({
      next: ({ courses, employees }) => { this.courses.set(courses.corsi); this.employees.set(employees.dipendenti); },
      error: (error) => this.error.set(this.errorMessage(error)),
    });
    this.filters.valueChanges
      .pipe(debounceTime(250), takeUntilDestroyed())
      .subscribe(() => this.load());
    this.load();
  }

  load(): void {
    const raw = this.filters.getRawValue();
    this.error.set('');
    if (!raw.mese && raw.data_inizio && raw.data_fine && raw.data_fine < raw.data_inizio) {
      this.error.set('La fine del periodo non può precedere l’inizio.'); return;
    }
    const params: Record<string, unknown> = raw.mese
      ? { mese: raw.mese, categoria: raw.categoria, dipendente_id: raw.dipendente_id }
      : raw;
    this.loading.set(true);
    this.api.getStatistics(params).pipe(finalize(() => this.loading.set(false))).subscribe({
      next: (response) => this.statistics.set(response.statistiche),
      error: (error) => this.error.set(this.errorMessage(error)),
    });
  }

  resetFilters(): void {
    this.filters.reset({ mese: '', data_inizio: '', data_fine: '', categoria: '', dipendente_id: '' });
  }
  private errorMessage(error: HttpErrorResponse): string { return (error.error as ApiErrorBody | null)?.message ?? 'Impossibile caricare le statistiche.'; }
}
