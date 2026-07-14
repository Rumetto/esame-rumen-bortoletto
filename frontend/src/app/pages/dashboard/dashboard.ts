import { Component, inject } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth';

@Component({
  selector: 'app-dashboard',
  imports: [RouterLink],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.scss'
})
export class DashboardPage {
  private readonly auth = inject(AuthService);
  private readonly route = inject(ActivatedRoute);

  protected readonly user = this.auth.user;
  protected readonly accessDenied = this.route.snapshot.queryParamMap.get('accesso') === 'negato';
}
