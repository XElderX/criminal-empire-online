const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8085/api';

export function getToken(): string | null {
  return localStorage.getItem('token');
}

export function setToken(token: string): void {
  localStorage.setItem('token', token);
}

export function clearToken(): void {
  localStorage.removeItem('token');
}

function emitApiFeedback(path: string, method: string, ok: boolean, data: unknown, error?: string): void {
  if (typeof window === 'undefined') return;
  window.dispatchEvent(new CustomEvent('ceo:api-feedback', {
    detail: { path, method, ok, data, error },
  }));
}

export async function api<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...((options.headers as Record<string, string>) || {}),
  };

  const token = getToken();

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const method = String(options.method || 'GET').toUpperCase();
  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers,
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message = typeof data?.message === 'string' ? data.message : 'API request failed.';
    emitApiFeedback(path, method, false, data, message);
    throw new Error(message);
  }

  emitApiFeedback(path, method, true, data);
  return data as T;
}
