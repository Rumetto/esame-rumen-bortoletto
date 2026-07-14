import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { finalize } from 'rxjs';
import { ApiErrorBody, Course, CoursePayload } from '../../models/academy.models';
import { AcademyService } from '../../services/academy';

@Component({
  selector: 'app-courses-page',
  imports: [ReactiveFormsModule],
  templateUrl: './courses.html',
  styleUrl: './courses.scss',
})
export class CoursesPage {
  private readonly api = inject(AcademyService);
  readonly courses = signal<Course[]>([]);
  readonly loading = signal(false);
  readonly saving = signal(false);
  readonly message = signal('');
  readonly error = signal('');
  readonly fieldErrors = signal<Record<string, string>>({});
  readonly editorOpen = signal(false);
  readonly editing = signal<Course | null>(null);
  readonly categories = computed(() => [...new Set(this.courses().map((course) => course.categoria))].sort());

  readonly filters = new FormGroup({
    categoria: new FormControl('', { nonNullable: true }),
    attivo: new FormControl('', { nonNullable: true }),
  });
  readonly form = new FormGroup({
    titolo: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.maxLength(160)] }),
    descrizione: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    categoria: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.maxLength(100)] }),
    durata_ore: new FormControl(1, { nonNullable: true, validators: [Validators.required, Validators.min(0.01)] }),
    obbligatorio: new FormControl(false, { nonNullable: true }),
    attivo: new FormControl(true, { nonNullable: true }),
  });

  constructor() { this.load(); }

  load(): void {
    this.loading.set(true);
    this.error.set('');
    this.api.getCourses(this.filters.getRawValue()).pipe(finalize(() => this.loading.set(false))).subscribe({
      next: (response) => this.courses.set(response.corsi),
      error: (error) => this.error.set(this.errorMessage(error)),
    });
  }

  resetFilters(): void {
    this.filters.reset({ categoria: '', attivo: '' });
    this.load();
  }

  create(): void {
    this.editing.set(null);
    this.fieldErrors.set({});
    this.form.reset({ titolo: '', descrizione: '', categoria: '', durata_ore: 1, obbligatorio: false, attivo: true });
    this.editorOpen.set(true);
  }

  edit(course: Course): void {
    this.editing.set(course);
    this.fieldErrors.set({});
    this.form.reset({
      titolo: course.titolo,
      descrizione: course.descrizione,
      categoria: course.categoria,
      durata_ore: course.durata_ore,
      obbligatorio: course.obbligatorio,
      attivo: course.attivo,
    });
    this.editorOpen.set(true);
  }

  closeEditor(): void { this.editorOpen.set(false); }

  save(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid) return;
    const raw = this.form.getRawValue();
    const payload: CoursePayload = { ...raw, durata_ore: Number(raw.durata_ore) };
    const request = this.editing()
      ? this.api.updateCourse(this.editing()!.id, payload)
      : this.api.createCourse(payload);
    this.saving.set(true);
    this.fieldErrors.set({});
    request.pipe(finalize(() => this.saving.set(false))).subscribe({
      next: (response) => {
        this.editorOpen.set(false);
        this.showMessage(response.message ?? 'Operazione completata');
        this.load();
      },
      error: (error: HttpErrorResponse) => {
        this.fieldErrors.set((error.error as ApiErrorBody | null)?.errors ?? {});
        this.error.set(this.errorMessage(error));
      },
    });
  }

  deactivate(course: Course): void {
    if (!confirm(`Disattivare il corso “${course.titolo}”?`)) return;
    this.api.deactivateCourse(course.id).subscribe({
      next: (response) => { this.showMessage(response.message ?? 'Corso disattivato'); this.load(); },
      error: (error) => this.error.set(this.errorMessage(error)),
    });
  }

  remove(course: Course): void {
    if (!confirm(`Eliminare definitivamente il corso “${course.titolo}”?`)) return;
    this.api.deleteCourse(course.id).subscribe({
      next: (response) => { this.showMessage(response.message); this.load(); },
      error: (error) => this.error.set(this.errorMessage(error)),
    });
  }

  private showMessage(message: string): void {
    this.error.set('');
    this.message.set(message);
  }

  private errorMessage(error: HttpErrorResponse): string {
    return (error.error as ApiErrorBody | null)?.message ?? 'Impossibile completare l’operazione. Riprova.';
  }
}
