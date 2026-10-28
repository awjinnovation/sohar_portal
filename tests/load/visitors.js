// Visitors using the app at the same time: each one opens the app (home
// screen API batch + the images it has not cached yet), reads, opens an
// event, reads, browses events and places, reads, and repeats. After 3–6
// rounds the visitor leaves and a new one takes the slot (empty image cache,
// fresh app open). ~11 API requests per visitor per ~70 s (≈ 0.2 per second).
//
// Signed in with TOKENS (from `php artisan loadtest:users`, staging only):
// the home screen also loads the wallet, its transactions and the unread
// count, and each round visits one personal screen in turn (my tickets,
// notifications, wallet, points). One account per visitor slot.
//
// Bookings (signed in only): BOOK is the chance per round that a visitor
// books one ticket, then opens my tickets. PAY=wallet (needs wallet credit:
// loadtest:users --balance), card (fake gateway only: test servers with
// PAYMENT_DRIVER=fake; the visitor presses "Simulate success") or mixed.
// A day that is sold out (422 SOLD_OUT) is counted, not treated as an error.
//
// Maximum search: STEPS=500,1000,2000 holds each number of visitors for
// STEP_HOLD (default 3m) after a STEP_RAMP (default 1m); the summary
// (--summary-export) has every step's response times, errors and bookings
// under the tag step:<visitors>. IMAGES=0 skips the images (Cloudflare serves
// them; they do not reach the server). The run stops itself when a visitor's
// last 30 requests take over STOP_MS (default 5000) at the median or over
// STOP_FAIL (default 0.3) of them fail.
//
//   k6 run -e BASE=https://v2-mawsim.awj.om -e USERS=300 visitors.js
//   k6 run -e BASE=https://v2-mawsim.awj.om -e USERS=300 -e TOKENS=tokens.json visitors.js
//   k6 run -e BASE=… -e TOKENS=tokens.json -e BOOK=0.2 -e PAY=wallet visitors.js
//   k6 run -e BASE=… -e TOKENS=tokens.json -e STEPS=500,1000,2000,3000 -e IMAGES=0 \
//     -e BOOK=0.05 -e PAY=wallet --summary-export=max.json visitors.js
//
// On the server itself (skips Cloudflare, the tunnel and the office line;
// HOST_IP sends the requests for BASE's host name to that address):
//   docker run --rm --network host -v "$PWD":/t -w /t grafana/k6:0.57.0 run \
//     -e BASE=http://v2-mawsim.awj.om -e HOST_IP=127.0.0.1 -e USERS=1000 visitors.js
import http from 'k6/http';
import exec from 'k6/execution';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { SharedArray } from 'k6/data';

const ORIGIN = (__ENV.BASE || 'https://v2-mawsim.awj.om').replace(/\/+$/, '');
const API = `${ORIGIN}/api/v1`;
const TENANT = __ENV.TENANT || 'north-batinah';
const USERS = Number(__ENV.USERS || 300);
const HOLD = __ENV.HOLD || '14m';
const THINK = Number(__ENV.THINK ?? 1); // 0 = no reading time (smoke test)
const host = ORIGIN.replace(/^https?:\/\//, '').split('/')[0];
// One copy for all visitors (5,000 visitors would otherwise hold 5,000 copies).
const TOKENS = new SharedArray('tokens', () => (__ENV.TOKENS ? JSON.parse(open(__ENV.TOKENS)) : []));
const BOOK = Number(__ENV.BOOK || 0);
const PAY = __ENV.PAY || 'wallet';
const IMAGES = (__ENV.IMAGES ?? '1') !== '0';
const STEPS = (__ENV.STEPS || '').split(',').map(Number).filter((n) => n > 0);
const STEP_RAMP = __ENV.STEP_RAMP || '1m';
const STEP_HOLD = __ENV.STEP_HOLD || '3m';
const STOP_MS = Number(__ENV.STOP_MS || (STEPS.length ? 5000 : 0));
const STOP_FAIL = Number(__ENV.STOP_FAIL || 0.3);

function seconds(d) {
  let total = 0;
  const re = /([\d.]+)([smh])/g;
  for (let m = re.exec(d); m; m = re.exec(d)) total += parseFloat(m[1]) * { s: 1, m: 60, h: 3600 }[m[2]];
  return total;
}

const stages = STEPS.length
  ? [...STEPS.flatMap((n) => [{ target: n, duration: STEP_RAMP }, { target: n, duration: STEP_HOLD }]), { target: 0, duration: '30s' }]
  : [
    { target: Math.round(USERS / 3), duration: '2m' },
    { target: Math.round((USERS * 2) / 3), duration: '2m' },
    { target: USERS, duration: '2m' },
    { target: USERS, duration: HOLD },
    { target: 0, duration: '30s' },
  ];

// Per-step figures in the summary: a threshold per step makes k6 keep them.
const stepThresholds = Object.fromEntries(STEPS.flatMap((n) => [
  [`http_req_duration{step:${n}}`, ['p(95)<600000']],
  [`http_req_duration{step:${n},kind:api}`, ['p(95)<600000']],
  [`http_req_duration{step:${n},kind:personal}`, ['p(95)<600000']],
  [`http_req_duration{step:${n},kind:booking}`, ['p(95)<600000']],
  [`http_req_failed{step:${n}}`, ['rate<=1']],
  [`http_reqs{step:${n}}`, ['count>=0']],
  [`bookings_completed{step:${n}}`, ['count>=0']],
  [`bookings_sold_out{step:${n}}`, ['count>=0']],
]));

export const options = {
  scenarios: {
    visitors: { executor: 'ramping-vus', startVUs: 0, gracefulRampDown: '30s', stages },
  },
  hosts: __ENV.HOST_IP ? { [host]: __ENV.HOST_IP } : {},
  insecureSkipTLSVerify: !!__ENV.HOST_IP,
  discardResponseBodies: true,
  summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
  thresholds: {
    // Only a breakdown stops the run early (see STOP_MS / STOP_FAIL too).
    http_req_failed: [{ threshold: 'rate<0.5', abortOnFail: true, delayAbortEval: '2m' }],
    'http_req_duration{kind:api}': ['p(95)<1000'],
    'http_req_duration{kind:image}': ['p(95)<2000'],
    'http_req_duration{kind:personal}': ['p(95)<1000'],
    'http_req_duration{kind:booking}': ['p(95)<3000'],
    bookings_completed: ['count>=0'],
    bookings_sold_out: ['count>=0'],
    ...stepThresholds,
  },
};

const bookingsCompleted = new Counter('bookings_completed');
const bookingsSoldOut = new Counter('bookings_sold_out');
const headers = { 'X-Tenant': TENANT, Accept: 'application/json', 'Accept-Language': 'ar' };

// Event ids and the server's own images (logo, covers…), read once.
export function setup() {
  const get = (path) => http.get(`${API}${path}`, { headers, responseType: 'text' }).body || '';
  const list = JSON.parse(get('/events?page=1&per_page=50&lang=ar') || '{}');
  const rows = (list.data && (list.data.events || list.data.data || list.data)) || [];
  const events = (Array.isArray(rows) ? rows : []).map((e) => e.id).slice(0, 20);
  const bodies = ['/tenant/config', '/places?page=1&lang=ar', '/places?featured=1&page=1&lang=ar']
    .map(get).join(' ').replace(/\\\//g, '/');
  // The server's own uploads and branding (any of its host names; served here via BASE).
  const own = /https?:\/\/[^"\s/]+\/(?:storage|tenants)\/[^"\s]+\.(?:jpe?g|png|webp|gif|svg)/g;
  const images = [...new Set(bodies.match(own) || [])].map((u) => u.replace(/^https?:\/\/[^/]+/, ORIGIN));
  if (!events.length) throw new Error(`no events from ${API}/events (check BASE / TENANT)`);

  // Dates that can be booked: the first open days of each event.
  const bookable = [];
  if (BOOK > 0) {
    for (const id of events) {
      const days = (JSON.parse(get(`/events/${id}/daily-availability?lang=ar`) || '{}').data || {}).availability || [];
      days.filter((d) => d.is_available && !d.is_sold_out).slice(0, 5)
        .forEach((d) => bookable.push({ event_id: Number(id), booking_date: d.date }));
    }
    if (!bookable.length) throw new Error('BOOK is set but no event has an open day');
  }
  console.log(`events: ${events.length}, images: ${IMAGES ? images.length : 'off'}, bookable days: ${bookable.length}${STEPS.length ? `, steps: ${STEPS.join(' → ')}` : ''}`);
  return { events, images, bookable, start: Date.now() };
}

// The step whose hold window we are in (ramps are tagged "ramp").
function currentStep(start) {
  const t = (Date.now() - start) / 1000;
  const ramp = seconds(STEP_RAMP);
  const hold = seconds(STEP_HOLD);
  const i = Math.floor(t / (ramp + hold));
  return i < STEPS.length && t - i * (ramp + hold) >= ramp ? String(STEPS[i]) : 'ramp';
}

const token = () => (TOKENS.length ? TOKENS[(__VU - 1) % TOKENS.length] : null);
const withAuth = () => (token() ? { ...headers, Authorization: `Bearer ${token()}` } : headers);
const api = (path, name) => ['GET', `${API}${path}`, null, { headers: withAuth(), tags: { kind: 'api', name }, timeout: '30s' }];
const own = (path, name) => ['GET', `${API}${path}`, null, { headers: withAuth(), tags: { kind: 'personal', name }, timeout: '30s' }];
const screens = () => [
  [own('/tickets/my-tickets?lang=ar', 'my_tickets')],
  [own('/notifications?page=1&lang=ar', 'notifications')],
  [own('/wallet?lang=ar', 'wallet'), own('/wallet/transactions?page=1&lang=ar', 'wallet_transactions')],
  [own('/points?lang=ar', 'points'), own('/points/history?page=1&lang=ar', 'points_history')],
];
const img = (url) => ['GET', url, null, { tags: { kind: 'image', name: 'image' }, timeout: '30s' }];
const think = (a, b) => sleep((a + Math.random() * (b - a)) * THINK);

let cached = new Set();
let roundsLeft = 0;
let round = 0;
let recent = []; // [duration ms, failed] of this visitor's last requests

// Safety brake: stop the whole run once the server is clearly overloaded.
function watch(responses) {
  if (!STOP_MS) return responses;
  for (const r of [].concat(responses)) {
    recent.push([r.timings.duration, r.status === 0 || r.status >= 500]);
  }
  recent = recent.slice(-30);
  if (recent.length === 30) {
    const ms = recent.map((x) => x[0]).sort((a, b) => a - b)[15];
    const failed = recent.filter((x) => x[1]).length / 30;
    if (ms > STOP_MS || failed > STOP_FAIL) {
      exec.test.abort(`stopped at ${exec.instance.vusActive} visitors: median ${Math.round(ms)} ms, ${Math.round(failed * 100)}% failed over the last 30 requests`);
    }
  }
  return responses;
}

// One ticket: checkout, then (card) the payment page and "Simulate success".
function book(data) {
  const b = data.bookable[Math.floor(Math.random() * data.bookable.length)];
  const method = PAY === 'mixed' ? (Math.random() < 0.5 ? 'wallet' : 'card') : PAY;
  const res = watch(http.post(`${API}/payments/initialize`, JSON.stringify({
    event_id: b.event_id, booking_date: b.booking_date, quantity: 1,
    method: method === 'card' ? 'gateway' : 'wallet',
    idempotency_key: `load-${__VU}-${__ITER}-${Date.now()}`,
  }), {
    headers: { ...withAuth(), 'Content-Type': 'application/json' }, tags: { kind: 'booking', name: `checkout_${method}` },
    responseType: 'text', timeout: '30s', responseCallback: http.expectedStatuses({ min: 200, max: 299 }, 422),
  }));
  let body = {};
  try { body = JSON.parse(res.body); } catch (e) { /* counted below */ }
  if (res.status === 422 && (body.code === 'SOLD_OUT' || (body.error && body.error.code === 'SOLD_OUT'))) {
    bookingsSoldOut.add(1);
    return;
  }
  const order = body.data || body;
  let done = (res.status === 200 || res.status === 201) && order.status === 'completed';

  if (!done && method === 'card' && order.payment_url) {
    const page = watch(http.get(order.payment_url, { tags: { kind: 'booking', name: 'payment_page' }, responseType: 'text', timeout: '30s' }));
    const action = ((page.body || '').match(/<form method="POST" action="([^"]+)"/) || [])[1];
    const csrf = ((page.body || '').match(/name="_token" value="([^"]+)"/) || [])[1];
    if (action && csrf) {
      const paid = watch(http.post(action.replace(/&amp;/g, '&'), { _token: csrf, result: 'success' },
        { tags: { kind: 'booking', name: 'card_success' }, responseType: 'text', timeout: '30s' }));
      done = paid.status === 200 && /payment\/success/.test(paid.url);
    }
  }
  if (done) bookingsCompleted.add(1);
  check(res, { [`booking ${method} completed`]: () => done });
}

function fetchImages(urls) {
  const todo = urls.filter((u) => !cached.has(u));
  if (!IMAGES || !todo.length) return;
  const res = watch(http.batch(todo.map(img)));
  todo.forEach((u, i) => { if (res[i].status === 200) cached.add(u); });
  check(res, { 'images ok': (rs) => rs.every((r) => r.status === 200) });
}

export default function (data) {
  if (STEPS.length) exec.vu.metrics.tags.step = currentStep(data.start);
  if (roundsLeft <= 0) { // a new visitor in this slot
    cached = new Set();
    roundsLeft = 3 + Math.floor(Math.random() * 4);
  }
  roundsLeft--;

  const home = [
    api('/tenant/config', 'tenant_config'),
    api('/events/featured?lang=ar', 'featured'),
    api('/events/today?lang=ar', 'today'),
    api('/announcements/active?lang=ar', 'announcements'),
    api('/places?featured=1&page=1&lang=ar', 'places_featured'),
    api('/content/collections?lang=ar', 'collections'),
    api('/content/collections/services/items?page=1&lang=ar', 'services'),
    api('/restaurants?lang=ar', 'restaurants'),
  ];
  if (token()) {
    home.push(own('/wallet?lang=ar', 'wallet'), own('/wallet/transactions?page=1&lang=ar', 'wallet_transactions'),
      own('/notifications/unread-count?lang=ar', 'unread_count'));
  }
  check(watch(http.batch(home)), { 'home ok': (rs) => rs.every((r) => r.status === 200) });
  fetchImages(data.images);
  think(15, 25);

  const id = data.events[Math.floor(Math.random() * data.events.length)];
  check(watch(http.batch([api(`/events/${id}?lang=ar`, 'event'), api(`/events/${id}/daily-availability?lang=ar`, 'availability')])),
    { 'event ok': (rs) => rs.every((r) => r.status === 200) });
  think(15, 25);

  check(watch(http.batch([api('/events?page=1&lang=ar', 'events_list'), api('/places?page=1&lang=ar', 'places_list')])),
    { 'lists ok': (rs) => rs.every((r) => r.status === 200) });
  think(15, 25);

  if (STEPS.length) exec.vu.metrics.tags.step = currentStep(data.start);
  if (token() && BOOK > 0 && Math.random() < BOOK) {
    book(data);
    check(watch(http.batch([own('/tickets/my-tickets?lang=ar', 'my_tickets')])), { 'personal ok': (rs) => rs.every((r) => r.status === 200) });
    think(10, 20);
  } else if (token()) {
    const list = screens();
    check(watch(http.batch(list[round++ % list.length])), { 'personal ok': (rs) => rs.every((r) => r.status === 200) });
    think(10, 20);
  }
}
