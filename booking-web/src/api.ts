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
  /** Fewest tickets of this type in one order when it is chosen (MSR-402); absent on older servers = 1. */
  min_quantity?: number;
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
    /** For search engines (absent from an API older than MSR-396). */
    description?: string | null;
    /** The event's and organizer's names in the other language. */
    other_names?: string[];
    /** Other names and search words typed by the organizer. */
    search_terms?: string[];
    starts_at?: string | null;
    ends_at?: string | null;
  };
  ticket_types: TicketType[];
  dates: BookingDate[];
  today: string;
  /** Two or more payment gateways on: one pay button each (names in the page language); empty or absent = one button. */
  payments?: { gateways: { id: string; name: string }[]; guest?: boolean };
}

/** One approved (published) event, for the index at "/" (public catalogue GET /api/v1/events). */
export interface EventSummary {
  id: number;
  title: string;
  image_url: string | null;
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

/**
 * The public catalogue (outside /booking): a token-less GET, so no preflight and
 * CDN-cacheable. The endpoint localizes by Accept-Language (a CORS-safelisted header),
 * so the title follows the page language, not the browser's.
 */
async function catalogue<T>(path: string, lang: Lang | null, signal?: AbortSignal): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (lang) headers['Accept-Language'] = lang;
  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}/api/v1${path}`, { headers, signal, credentials: 'omit', mode: 'cors', referrerPolicy: 'no-referrer' });
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
    throw new ApiError(response.status, json?.code ?? 'UNAVAILABLE', (json?.data as Record<string, unknown>) ?? {});
  }
  return json.data as T;
}

export const api = {
  event: (id: number, lang: Lang | null, signal?: AbortSignal) =>
    request<EventPayload>(`${ev(id)}${lang ? `?lang=${lang}` : ''}`, { signal }),

  /** Approved (published) events for the index at "/"; titles follow the language. */
  events: (lang: Lang | null, signal?: AbortSignal): Promise<EventSummary[]> =>
    catalogue<{ id: number | string; title: string; image_url: string | null }[]>(`/events${lang ? `?lang=${lang}` : ''}`, lang, signal).then((rows) =>
      rows.map((r) => ({ id: Number(r.id), title: r.title, image_url: r.image_url ?? null })),
    ),

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
    body: { lang: Lang; form_key: string; booking_date: string | null; items: { ticket_type_id: number; quantity: number }[]; gateway?: string },
  ) => request<{ status: 'completed' | 'pending'; payment_url?: string }>(`${ev(id)}/checkout`, { method: 'POST', token, body }),

  logout: (id: number, token: string) => request<void>(`${ev(id)}/logout`, { method: 'POST', token, body: {} }),

  /** Booking without signing in: the answer carries the key that shows this order's tickets. */
  guestCheckout: (
    id: number,
    body: { lang: Lang; form_key: string; booking_date: string | null; items: { ticket_type_id: number; quantity: number }[]; gateway?: string },
  ) =>
    request<{ status: 'completed' | 'pending'; payment_url?: string; transaction_id: string; guest_token: string }>(`${ev(id)}/guest/checkout`, {
      method: 'POST',
      body,
    }),

  guestTickets: (id: number, transactionId: string, key: string, lang: Lang, signal?: AbortSignal) =>
    request<{ status: string; tickets: Ticket[] }>(
      `${ev(id)}/guest/tickets?${new URLSearchParams({ transaction_id: transactionId, token: key, lang })}`,
      { signal },
    ),
};
