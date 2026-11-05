/** Browser storage that never throws (private mode, storage full, blocked cookies). */
function safe<T>(fn: () => T, fallback: T): T {
  try {
    return fn();
  } catch {
    return fallback;
  }
}

export function readJson<T>(store: 'local' | 'session', key: string): T | null {
  return safe(() => {
    const raw = (store === 'local' ? localStorage : sessionStorage).getItem(key);
    return raw ? (JSON.parse(raw) as T) : null;
  }, null);
}

export function writeJson(store: 'local' | 'session', key: string, value: unknown): void {
  safe(() => {
    const s = store === 'local' ? localStorage : sessionStorage;
    if (value === null || value === undefined) s.removeItem(key);
    else s.setItem(key, JSON.stringify(value));
  }, undefined);
}

/** Sign-in token of the booking API (a Sanctum token, VISITOR_SESSION_DAYS). Users are global, so one token serves every event. */
export interface Auth {
  token: string;
  expires_at: string;
  phone_masked: string;
}

const AUTH_KEY = 'booking-auth';

export function loadAuth(): Auth | null {
  const auth = readJson<Auth>('local', AUTH_KEY);
  if (!auth?.token) return null;
  if (auth.expires_at && Date.parse(auth.expires_at) <= Date.now()) {
    writeJson('local', AUTH_KEY, null);
    return null;
  }
  return auth;
}

export function saveAuth(auth: Auth | null): void {
  writeJson('local', AUTH_KEY, auth);
}

/** The number a code was just sent to (this tab only, 10 minutes), so a reload stays on the code step. */
export interface PendingOtp {
  phone: string;
  phone_masked: string;
  at: number;
}

const OTP_KEY = 'booking-otp';

export function loadPendingOtp(): PendingOtp | null {
  const otp = readJson<PendingOtp>('session', OTP_KEY);
  return otp && Date.now() - otp.at < 10 * 60 * 1000 ? otp : null;
}

export function savePendingOtp(otp: PendingOtp | null): void {
  writeJson('session', OTP_KEY, otp);
}
