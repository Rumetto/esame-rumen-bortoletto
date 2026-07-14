import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { AcademyStatistic, Assignment, AssignmentPayload, Course, CoursePayload, Employee } from '../models/academy.models';
import { ApiMessage } from '../models/auth.models';

interface CourseListResponse { success: true; totale: number; corsi: Course[]; }
interface CourseResponse { success: true; message?: string; corso: Course; }
interface EmployeeListResponse { success: true; totale: number; dipendenti: Employee[]; }
interface AssignmentListResponse { success: true; totale: number; assegnazioni: Assignment[]; }
interface AssignmentResponse { success: true; message: string; assegnazione: Assignment; }
interface StatisticsResponse { success: true; totale: number; statistiche: AcademyStatistic[]; }

@Injectable({ providedIn: 'root' })
export class AcademyService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  getCourses(filters: Record<string, unknown> = {}): Observable<CourseListResponse> {
    return this.http.get<CourseListResponse>(`${this.apiUrl}/corsi`, { params: this.params(filters) });
  }
  createCourse(payload: CoursePayload): Observable<CourseResponse> {
    return this.http.post<CourseResponse>(`${this.apiUrl}/corsi`, payload);
  }
  updateCourse(id: number, payload: CoursePayload): Observable<CourseResponse> {
    return this.http.put<CourseResponse>(`${this.apiUrl}/corsi/${id}`, payload);
  }
  deactivateCourse(id: number): Observable<CourseResponse> {
    return this.http.put<CourseResponse>(`${this.apiUrl}/corsi/${id}/disattiva`, {});
  }
  deleteCourse(id: number): Observable<ApiMessage> {
    return this.http.delete<ApiMessage>(`${this.apiUrl}/corsi/${id}`);
  }
  getEmployees(): Observable<EmployeeListResponse> {
    return this.http.get<EmployeeListResponse>(`${this.apiUrl}/utenti/dipendenti`);
  }
  getAssignments(filters: Record<string, unknown> = {}): Observable<AssignmentListResponse> {
    return this.http.get<AssignmentListResponse>(`${this.apiUrl}/assegnazioni-corsi`, { params: this.params(filters) });
  }
  createAssignment(payload: AssignmentPayload): Observable<AssignmentResponse> {
    return this.http.post<AssignmentResponse>(`${this.apiUrl}/assegnazioni-corsi`, payload);
  }
  updateAssignment(id: number, payload: AssignmentPayload): Observable<AssignmentResponse> {
    return this.http.put<AssignmentResponse>(`${this.apiUrl}/assegnazioni-corsi/${id}`, payload);
  }
  cancelAssignment(id: number): Observable<AssignmentResponse> {
    return this.http.put<AssignmentResponse>(`${this.apiUrl}/assegnazioni-corsi/${id}/annulla`, {});
  }
  completeAssignment(id: number): Observable<AssignmentResponse> {
    return this.http.put<AssignmentResponse>(`${this.apiUrl}/assegnazioni-corsi/${id}/completa`, {});
  }
  getStatistics(filters: Record<string, unknown> = {}): Observable<StatisticsResponse> {
    return this.http.get<StatisticsResponse>(`${this.apiUrl}/statistiche/academy`, { params: this.params(filters) });
  }

  private params(filters: Record<string, unknown>): HttpParams {
    let params = new HttpParams();
    for (const [key, value] of Object.entries(filters)) {
      if (value !== '' && value !== null && value !== undefined) params = params.set(key, String(value));
    }
    return params;
  }
}
