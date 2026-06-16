const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8085/api';

export function getToken(): string | null { return localStorage.getItem('token'); }
export function setToken(token: string): void { localStorage.setItem('token', token); }
export function clearToken(): void { localStorage.removeItem('token'); }

export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers: Record<string, string> = { 'Content-Type': 'application/json', ...(options.headers as Record<string, string> || {}) };
  const token = getToken();
  if (token) headers.Authorization = `Bearer ${token}`;
  const res = await fetch(`${API_URL}${path}`, { ...options, headers });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.message || 'API error');
  return data;
}
