export type AssignmentStatus = 'ASSEGNATO' | 'COMPLETATO' | 'SCADUTO' | 'ANNULLATO';

export interface Course {
  id: number;
  titolo: string;
  descrizione: string;
  categoria: string;
  durata_ore: number;
  obbligatorio: boolean;
  attivo: boolean;
  creato_il?: string;
  aggiornato_il?: string;
}

export type CoursePayload = Omit<Course, 'id' | 'creato_il' | 'aggiornato_il'>;

export interface Employee {
  id: number;
  nome: string;
  cognome: string;
  email: string;
}

export interface EmployeePayload {
  nome: string;
  cognome: string;
  email: string;
  password: string;
}

export interface Assignment {
  id: number;
  data_assegnazione: string;
  data_scadenza: string;
  stato: AssignmentStatus;
  data_completamento: string | null;
  corso: Course;
  dipendente: Employee;
}

export interface AssignmentPayload {
  corso_id: number;
  dipendente_id: number;
  data_assegnazione: string;
  data_scadenza: string;
  stato: AssignmentStatus;
  data_completamento: string | null;
}

export interface AcademyStatistic {
  mese: string;
  categoria: string;
  numeroAssegnazioni: number;
  numeroCompletamenti: number;
  percentualeCompletamento: number;
}

export interface ApiErrorBody {
  success: false;
  message: string;
  errors?: Record<string, string>;
}
