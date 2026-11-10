// Visitors on the booking site (booking-web/): someone scans an event's
// printed QR, the page loads the event (one public API call, cached), the
// visitor reads and chooses, signs in, pays by card and gets the tickets,
// which the page then loads. Each iteration is one new visitor.
//
// Sign-in uses ready accounts with API tokens (TOKENS, from
// `php artisan loadtest:users`, refused in production). The SMS code step is
// left out on purpose: on a server without OTP_TEST_CODE it would text real
// phone numbers. Without TOKENS every visitor only opens the page.
//
//   SIGNED  share of visitors who sign in (default 0.6, needs TOKENS)
//   BOOK    chance a signed-in visitor books (default 0). Card payments go
//           through the fake gateway only (PAYMENT_DRIVER=fake); setup()
//           stops the run if the server sends visitors anywhere else.
//   SITE    the booking site (https://book…): each visitor also loads
//           /e/{id} and its JS/CSS. Off by default (a CDN serves them).
//   EVENTS  event ids (1,2,3); default: the tenant's events that sell online.
//
// From one machine every visitor shares one IP, so the server's per-IP page
// limit (300/min) applies to the whole test unless the server has
// OTP_TEST_CODE (staging), which lifts it. 429s are counted (rate_limited).
//
//   k6 run -e BASE=https://v2-mawsim.awj.om -e USERS=300 booking.js
//   k6 run -e BASE=… -e TOKENS=tokens.json -e USERS=300 -e BOOK=0.3 booking.js
//   k6 run -e BASE=… -e SITE=https://book.mawsim.awj.om -e USERS=300 booking.js
//   k6 run --vus 1 --iterations 2 -e THINK=0 booking.js        # smoke test
//
// On the server itself (skips Cloudflare): see visitors.js (HOST_IP).
import http from 'k6/http';
import exec from 'k6/execution';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { SharedArray } from 'k6/data';

const ORIGIN = (__ENV.BASE || 'https://v2-mawsim.awj.om').replace(/\/+$/, '');
const API = `${ORIGIN}/api/v1`;
const BOOKING = `${API}/booking/events`;
const SITE = (__ENV.SITE || '').replace(/\/+$/, '');
const TENANT = __ENV.TENANT || 'north-batinah';
const USERS = Number(__ENV.USERS || 200);
const HOLD = __ENV.HOLD || '10m';
const THINK = Number(__ENV.THINK ?? 1);
const TOKENS = new SharedArray('tokens', () => (__ENV.TOKENS ? JSON.parse(open(__ENV.TOKENS)) : []));
const SIGNED = Number(__ENV.SIGNED ?? 0.6);
const BOOK = Number(__ENV.BOOK || 0);
const STOP_MS = Number(__ENV.STOP_MS || 5000);
const STOP_FAIL = Number(__ENV.STOP_FAIL || 0.3);
const host = ORIGIN.replace(/^https?:\/\//, '').split('/')[0];

export const options = {
  scenarios: {
    visitors: {
      executor: 'ramping-vus',
      startVUs: 0,
      gracefulRampDown: '30s',
      stages: [
        { target: Math.round(USERS / 3), duration: '2m' },
        { target: Math.round((USERS * 2) / 3), duration: '2m' },
        { target: USERS, duration: '2m' },
        { target: USERS, duration: HOLD },
        { target: 0, duration: '30s' },
      ],
    },
  },
  hosts: __ENV.HOST_IP ? { [host]: __ENV.HOST_IP } : {},
  insecureSkipTLSVerify: !!__ENV.HOST_IP,
  discardResponseBodies: true,
  summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
  thresholds: {
    http_req_failed: [{ threshold: 'rate<0.5', abortOnFail: true, delayAbortEval: '2m' }],
    'http_req_duration{kind:page}': ['p(95)<1000'],
    'http_req_duration{kind:site}': ['p(95)<2000'],
    'http_req_duration{kind:personal}': ['p(95)<1000'],
    'http_req_duration{kind:booking}': ['p(95)<3000'],
    bookings_completed: ['count>=0'],
    bookings_sold_out: ['count>=0'],
    rate_limited: ['count>=0'],
  },
};

const bookingsCompleted = new Counter('bookings_completed');
const bookingsSoldOut = new Counter('bookings_sold_out');
const rateLimited = new Counter('rate_limited');

const pick = (list) => list[Math.floor(Math.random() * list.length)];
const parse = (res) => { try { return JSON.parse(res.body); } catch (e) { return {}; } };
const think = (a, b) => sleep((a + Math.random() * (b - a)) * THINK);
// gzip like a browser (k6 does not ask for it by default).
const pageHeaders = SITE ? { Accept: 'application/json', 'Accept-Encoding': 'gzip', Origin: SITE } : { Accept: 'application/json', 'Accept-Encoding': 'gzip' };

// Events that sell online, each with its open ticket types and days.
export function setup() {
  let ids = (__ENV.EVENTS || '').split(',').map(Number).filter((n) => n > 0);
  if (!ids.length) {
    const list = parse(http.get(`${API}/events?page=1&per_page=50&lang=ar`,
      { headers: { 'X-Tenant': TENANT, Accept: 'application/json', 'Accept-Encoding': 'gzip' }, responseType: 'text' }));
    const rows = (list.data && (list.data.events || list.data.data || list.data)) || [];
    ids = (Array.isArray(rows) ? rows : []).map((e) => e.id).slice(0, 30);
  }
  const events = [];
  for (const id of ids) {
    const res = http.get(`${BOOKING}/${id}?lang=ar`, { headers: pageHeaders, responseType: 'text' });
    const d = res.status === 200 ? parse(res).data : null;
    if (!d || d.event.ended) continue;
    const dates = (d.dates || []).filter((x) => x.available !== 0).map((x) => x.date).slice(0, 5);
    const types = (d.ticket_types || []).filter((t) => t.sales_open && (!t.dated || dates.length))
      .map((t) => ({ id: t.id, dated: t.dated, paid: t.price_minor > 0 }));
    events.push({ id, types, dates });
  }
  if (!events.length) throw new Error(`no event sells online at ${BOOKING} (check BASE, TENANT or EVENTS)`);
  const bookable = events.filter((e) => e.types.length);

  if (BOOK > 0) {
    if (!TOKENS.length) throw new Error('BOOK needs TOKENS (php artisan loadtest:users)');
    if (!bookable.length) throw new Error('BOOK is set but no event has an open ticket type');
    // One order first: card payments must stay on this server's fake gateway.
    const ev = bookable.find((e) => e.types.some((t) => t.paid));
    if (ev) {
      const t = ev.types.find((x) => x.paid);
      const res = checkout(ev, t, TOKENS[0], 'setup');
      if (res.status === 401) throw new Error('TOKENS were refused (401): create them on this server with loadtest:users');
      const url = (parse(res).data || {}).payment_url || '';
      if (res.status === 200 && url) {
        // The fake gateway answers with its own page; a real one redirects
        // (Thawani) or posts a form to the bank (Bank Muscat).
        const page = http.get(url, { redirects: 0, responseType: 'text' });
        if (page.status !== 200 || !/<form method="POST" action="[^"]*\/payment\/fake\//.test(page.body || '')) {
          throw new Error(`card payments do not use the fake gateway (HTTP ${page.status}${page.headers.Location ? ` → ${page.headers.Location.split('?')[0]}` : ''}). Stopped so the payment provider is not load-tested.`);
        }
      }
    }
  }

  let assets = [];
  if (SITE) {
    const page = http.get(`${SITE}/e/${events[0].id}`, { responseType: 'text' });
    if (page.status !== 200) throw new Error(`${SITE}/e/${events[0].id}: HTTP ${page.status}`);
    assets = [...new Set((page.body || '').match(/\/assets\/[^"']+\.(?:js|css)/g) || [])].map((p) => `${SITE}${p}`);
  }
  console.log(`events: ${events.length} (${bookable.length} bookable), accounts: ${TOKENS.length}, site: ${SITE ? `${assets.length} assets` : 'off'}`);
  return { events, bookable, assets };
}

let recent = []; // [duration ms, failed] of this slot's last requests

// Counts 429s, and stops the whole run once the server is clearly overloaded.
function watch(res) {
  if (res.status === 429) rateLimited.add(1);
  recent.push([res.timings.duration, res.status === 0 || res.status >= 500]);
  recent = recent.slice(-30);
  if (recent.length === 30) {
    const ms = recent.map((x) => x[0]).sort((a, b) => a - b)[15];
    const failed = recent.filter((x) => x[1]).length / 30;
    if (ms > STOP_MS || failed > STOP_FAIL) {
      exec.test.abort(`stopped at ${exec.instance.vusActive} visitors: median ${Math.round(ms)} ms, ${Math.round(failed * 100)}% failed over the last 30 requests`);
    }
  }
  return res;
}

function checkout(ev, type, token, key) {
  return http.post(`${BOOKING}/${ev.id}/checkout`, JSON.stringify({
    lang: 'ar',
    form_key: `load-${key}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    booking_date: type.dated ? pick(ev.dates) : null,
    items: [{ ticket_type_id: type.id, quantity: 1 }],
  }), {
    headers: { Accept: 'application/json', 'Accept-Encoding': 'gzip', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
    tags: { kind: 'booking', name: 'checkout' },
    responseType: 'text',
    timeout: '30s',
    responseCallback: http.expectedStatuses({ min: 200, max: 299 }, 422),
  });
}

const absolute = (url) => (/^https?:/.test(url) ? url : `${ORIGIN}${url}`);
const tickets = (ev, token) => watch(http.get(`${BOOKING}/${ev.id}/tickets?lang=ar`, {
  headers: { Accept: 'application/json', 'Accept-Encoding': 'gzip', Authorization: `Bearer ${token}` }, tags: { kind: 'personal', name: 'tickets' }, timeout: '30s',
}));

// Card through the fake gateway: payment page → "Simulate success" →
// /payment/success → back to the booking site with ?payment=success.
function book(ev, token) {
  const res = watch(checkout(ev, pick(ev.types), token, `${__VU}-${__ITER}`));
  const body = parse(res);
  if (res.status === 422 && (body.code === 'SOLD_OUT' || (body.error && body.error.code === 'SOLD_OUT'))) {
    bookingsSoldOut.add(1);
    return;
  }
  const order = body.data || {};
  let done = res.status === 200 && order.status === 'completed';

  if (!done && order.payment_url) {
    const page = watch(http.get(order.payment_url, { tags: { kind: 'booking', name: 'payment_page' }, responseType: 'text', timeout: '30s' }));
    const action = ((page.body || '').match(/<form method="POST" action="([^"]+)"/) || [])[1];
    const csrf = ((page.body || '').match(/name="_token" value="([^"]+)"/) || [])[1];
    if (action && csrf) {
      const paid = watch(http.post(action.replace(/&amp;/g, '&'), { _token: csrf, result: 'success' },
        { redirects: 0, tags: { kind: 'booking', name: 'card_success' }, timeout: '30s' }));
      const success = paid.status === 302 && watch(http.get(absolute(paid.headers.Location || ''),
        { redirects: 0, tags: { kind: 'booking', name: 'payment_return' }, timeout: '30s' }));
      done = !!success && success.status === 302 && /[?&]payment=success/.test(success.headers.Location || '');
    }
  }
  check(res, { 'booking completed': () => done });
  if (done) {
    bookingsCompleted.add(1);
    tickets(ev, token); // the page loads the new tickets
  }
}

export default function (data) {
  const ev = pick(data.events);
  if (SITE) {
    const site = http.batch([`${SITE}/e/${ev.id}`, ...data.assets].map((url) => ['GET', url, null, { tags: { kind: 'site', name: 'site' }, timeout: '30s' }]));
    site.forEach(watch);
    check(site, { 'site ok': (rs) => rs.every((r) => r.status === 200) });
  }
  const page = watch(http.get(`${BOOKING}/${ev.id}?lang=ar`, { headers: pageHeaders, tags: { kind: 'page', name: 'event' }, timeout: '30s' }));
  check(page, { 'event ok': (r) => r.status === 200 });
  think(10, 20);

  const token = TOKENS.length && Math.random() < SIGNED ? TOKENS[(__VU - 1) % TOKENS.length] : null;
  if (!token) return;
  check(tickets(ev, token), { 'tickets ok': (r) => r.status === 200 });
  think(5, 15);

  const bookable = data.bookable.find((e) => e.id === ev.id);
  if (bookable && Math.random() < BOOK) {
    book(bookable, token);
    think(10, 20);
  }
}
