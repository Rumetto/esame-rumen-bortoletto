export type UserRole = 'DIPENDENTE' | 'REFERENTE_ACADEMY';

export interface AuthUser {
  id: number;
  nome: string;
  cognome: string;
  email: string;
  ruolo: UserRole;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface LoginResponse {
  success: boolean;
  message: string;
  token: string;
  utente: AuthUser;
}

export interface ApiMessage {
  success: boolean;
  message: string;
  errors?: Record<string, string>;
}
