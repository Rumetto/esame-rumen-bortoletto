import { Component, inject } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';

@Component({
  selector: 'app-placeholder',
  imports: [RouterLink],
  template: `
    <section class="placeholder">
      <span>Prossima fase</span>
      <h1>{{ title }}</h1>
      <p>{{ description }}</p>
      <a routerLink="/dashboard">← Torna alla dashboard</a>
    </section>
  `,
  styles: [`
    .placeholder { max-width: 42rem; padding: clamp(1.5rem, 5vw, 3rem); border: 1px solid #e2e8f0; border-radius: 1rem; background: #fff; }
    span { color: #1d4ed8; font-size: .75rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
    h1 { margin: .6rem 0; color: #172033; font-size: clamp(2rem, 5vw, 3rem); letter-spacing: -.05em; }
    p { color: #64748b; line-height: 1.65; }
    a { display: inline-block; margin-top: 1.2rem; color: #1d4ed8; font-weight: 800; text-decoration: none; }
  `]
})
export class PlaceholderPage {
  private readonly route = inject(ActivatedRoute);
  protected readonly title = this.route.snapshot.data['title'] as string;
  protected readonly description = this.route.snapshot.data['description'] as string;
}
