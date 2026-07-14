import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { finalize } from 'rxjs/operators';
import { ApiMessage } from '../../models/auth.models';
import { AuthService } from '../../services/auth';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule],
  templateUrl: './login.html',
  styleUrl: './login.scss'
})
export class LoginPage {
  private readonly formBuilder = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  protected readonly loading = signal(false);
  protected readonly errorMessage = signal('');
  protected readonly showPassword = signal(false);
  protected readonly sessionExpired = this.route.snapshot.queryParamMap.get('sessione') === 'scaduta';

  protected readonly form = this.formBuilder.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required]
  });

  protected submit(): void {
    this.errorMessage.set('');

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.loading.set(true);
    this.auth.login(this.form.getRawValue()).pipe(
      finalize(() => this.loading.set(false))
    ).subscribe({
      next: () => {
        const requestedUrl = this.route.snapshot.queryParamMap.get('returnUrl');
        const destination = requestedUrl?.startsWith('/') ? requestedUrl : '/dashboard';
        void this.router.navigateByUrl(destination);
      },
      error: (error: HttpErrorResponse) => {
        const body = error.error as ApiMessage | undefined;
        this.errorMessage.set(
          body?.message ?? 'Non è stato possibile accedere. Controlla la connessione e riprova.'
        );
      }
    });
  }
}
