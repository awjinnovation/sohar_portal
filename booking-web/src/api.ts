import { API_BASE_URL } from './config';

/** Shapes of /api/v1/booking/events/{event}/* (backend app/Http/Controllers/Api/BookingController.php). */
export type Lang = 'ar' | 'en';

export interface Theme {
  mode: 'system' | 'light' | 'dark';
  root: Record<string, string>;
  light: Record<string, string>;
  dark: Record<string, string>;
  fonts_url: string;
  logo: string | null;
  logo_chip: boolean;
  icon: string | null;
  header_image: string | null;
  theme_color: string;
}

export interface Tenant {
  slug: string;
  name: string;
  file_prefix: string;
  locales: Lang[];
  organization: string | null;
  support_email: string | null;
  support_phone: string | null;
  privacy_url: string | null;
  terms_url: string | null;
  country_code: string;
  phone_hint: string;
  phone_length: number;
  currency: string;
  currency_decimals: number;
  theme: Theme;
}

export interface TicketType {
  id: number;
  name: string;
  color: string;
  price_minor: number;
  dated: boolean;
  validity_label: string | null;
  admits: number;
  admits_label: string | null;
  requires_id: boolean;
  max_quantity: number;
  sales_open: boolean;
}

export interface BookingDate {
  date: string;
  available: number; // -1 = unlimited
  relative: 'today' | 'tomorrow' | null;
  day_label: string;
  num_label: string;
}

export interface EventPayload {
  lang: Lang;
  tenant: Tenant;
  event: {
    id: number;
    title: string;
    date_label: string | null;
    time_label: string | null;
    venue: string | null;
    image_url: string | null;
    ended: boolean;
  };
  ticket_types: TicketType[];
  dates: BookingDate[];
  today: string;
}

export interface Ticket {
  id: number;
  number: string;
  qr_code: string;
  status: 'active' | 'used' | 'expired' | 'cancelled' | 'refunded' | string;
  type_name: string;
  type_color: string;
  when_label: string;
  admits: number;
  admits_label: string | null;
  requires_id: boolean;
}

/** An error answer ({success:false, code, data}) or NETWORK when the API could not be reached. */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    public readonly data: Record<string, unknown> = {},
  ) {
    super(code);
  }
}

interface Options {
  method?: 'GET' | 'POST';
  body?: unknown;
  token?: string | null;
  signal?: AbortSignal;
}

async function request<T>(path: string, { method = 'GET', body, token, signal }: Options = {}): Promise<T> {
  // A GET without a token sends only CORS-safelisted headers, so the public
  // event payload needs no preflight and can come straight from a CDN.
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (token) headers.Authorization = `Bearer ${token}`;

  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}/api/v1/booking${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      signal,
      credentials: 'omit',
      mode: 'cors',
      referrerPolicy: 'no-referrer',
    });
  } catch (e) {
    if ((e as Error).name === 'AbortError') throw e;
    throw new ApiError(0, 'NETWORK');
  }

  let json: { success?: boolean; code?: string; data?: unknown } | null = null;
  try {
    json = await response.json();
  } catch {
    json = null;
  }
  if (!response.ok || !json || json.success === false) {
    const code = json?.code ?? (response.status === 429 ? 'TOO_MANY_REQUESTS' : response.status === 401 ? 'UNAUTHENTICATED' : 'UNAVAILABLE');
    throw new ApiError(response.status, code, (json?.data as Record<string, unknown>) ?? {});
  }
  return json.data as T;
}

const ev = (id: number) => `/events/${id}`;

export const api = {
  event: (id: number, lang: Lang | null, signal?: AbortSignal) =>
    request<EventPayload>(`${ev(id)}${lang ? `?lang=${lang}` : ''}`, { signal }),

  sendOtp: (id: number, phone: string, lang: Lang) =>
    request<{ phone: string; phone_masked: string; expires_in: number; otp?: string }>(`${ev(id)}/otp`, {
      method: 'POST',
      body: { phone_number: phone, lang },
    }),

  verifyOtp: (id: number, phone: string, otp: string, lang: Lang) =>
    request<{ token: string; expires_at: string; phone_masked: string }>(`${ev(id)}/verify`, {
      method: 'POST',
      body: { phone_number: phone, otp, lang },
    }),

  tickets: (id: number, lang: Lang, token: string, signal?: AbortSignal) =>
    request<{ phone_masked: string; tickets: Ticket[] }>(`${ev(id)}/tickets?lang=${lang}`, { token, signal }),

  checkout: (
    id: number,
    token: string,
    body: { lang: Lang; form_key: string; booking_date: string | null; items: { ticket_type_id: number; quantity: number }[] },
  ) => request<{ status: 'completed' | 'pending'; payment_url?: string }>(`${ev(id)}/checkout`, { method: 'POST', token, body }),

  logout: (id: number, token: string) => request<void>(`${ev(id)}/logout`, { method: 'POST', token, body: {} }),
};
