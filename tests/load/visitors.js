// Visitors using the app at the same time (signed out): each one opens the
// app (home screen API batch + the images it has not cached yet), reads,
// opens an event, reads, browses events and places, reads, and repeats.
// After 3–6 rounds the visitor leaves and a new one takes the slot (empty
// image cache, fresh app open). ~11 API requests per visitor per ~70 s.
//
//   k6 run -e BASE=https://v2-mawsim.awj.om -e USERS=300 visitors.js
//
// On the server itself (skips Cloudflare, the tunnel and the office line;
// HOST_IP sends the requests for BASE's host name to that address):
//   docker run --rm --network host -v "$PWD":/t -w /t grafana/k6:0.57.0 run \
//     -e BASE=http://v2-mawsim.awj.om -e HOST_IP=127.0.0.1 -e USERS=1000 visitors.js
import http from 'k6/http';
import { check, sleep } from 'k6';

const ORIGIN = (__ENV.BASE || 'https://v2-mawsim.awj.om').replace(/\/+$/, '');
const API = `${ORIGIN}/api/v1`;
const TENANT = __ENV.TENANT || 'north-batinah';
const USERS = Number(__ENV.USERS || 300);
const HOLD = __ENV.HOLD || '14m';
const THINK = Number(__ENV.THINK ?? 1); // 0 = no reading time (smoke test)
const host = ORIGIN.replace(/^https?:\/\//, '').split('/')[0];

export const options = {
  scenarios: {
    visitors: {
      executor: 'ramping-vus', startVUs: 0, gracefulRampDown: '30s',
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
    // Only a breakdown stops the run early.
    http_req_failed: [{ threshold: 'rate<0.5', abortOnFail: true, delayAbortEval: '2m' }],
    'http_req_duration{kind:api}': ['p(95)<1000'],
    'http_req_duration{kind:image}': ['p(95)<2000'],
  },
};

const headers = { 'X-Tenant': TENANT, Accept: 'application/json', 'Accept-Language': 'ar' };

// Event ids and the server's own images (logo, covers…), read once.
export function setup() {
  const get = (path) => http.get(`${API}${path}`, { headers, responseType: 'text' }).body || '';
  const list = JSON.parse(get('/events?page=1&per_page=50&lang=ar') || '{}');
  const rows = (list.data && (list.data.events || list.data.data || list.data)) || [];
  const events = (Array.isArray(rows) ? rows : []).map((e) => e.id).slice(0, 20);
  const bodies = ['/tenant/config', '/places?page=1&lang=ar', '/places?featured=1&page=1&lang=ar']
    .map(get).join(' ').replace(/\\\//g, '/');
  const own = new RegExp(`https?://${host.replace(/\./g, '\\.')}/[^"\\s]+\\.(?:jpe?g|png|webp|gif|svg)`, 'g');
  const images = [...new Set(bodies.match(own) || [])].map((u) => u.replace(/^https?:\/\/[^/]+/, ORIGIN));
  if (!events.length) throw new Error(`no events from ${API}/events (check BASE / TENANT)`);
  console.log(`events: ${events.length}, images: ${images.length}`);
  return { events, images };
}

const api = (path, name) => ['GET', `${API}${path}`, null, { headers, tags: { kind: 'api', name }, timeout: '30s' }];
const img = (url) => ['GET', url, null, { tags: { kind: 'image', name: 'image' }, timeout: '30s' }];
const think = (a, b) => sleep((a + Math.random() * (b - a)) * THINK);

let cached = new Set();
let roundsLeft = 0;

function fetchImages(urls) {
  const todo = urls.filter((u) => !cached.has(u));
  if (!todo.length) return;
  const res = http.batch(todo.map(img));
  todo.forEach((u, i) => { if (res[i].status === 200) cached.add(u); });
  check(res, { 'images ok': (rs) => rs.every((r) => r.status === 200) });
}

export default function (data) {
  if (roundsLeft <= 0) { // a new visitor in this slot
    cached = new Set();
    roundsLeft = 3 + Math.floor(Math.random() * 4);
  }
  roundsLeft--;

  check(http.batch([
    api('/tenant/config', 'tenant_config'),
    api('/events/featured?lang=ar', 'featured'),
    api('/events/today?lang=ar', 'today'),
    api('/announcements/active?lang=ar', 'announcements'),
    api('/places?featured=1&page=1&lang=ar', 'places_featured'),
    api('/content/collections?lang=ar', 'collections'),
    api('/content/collections/services/items?page=1&lang=ar', 'services'),
    api('/restaurants?lang=ar', 'restaurants'),
  ]), { 'home ok': (rs) => rs.every((r) => r.status === 200) });
  fetchImages(data.images);
  think(15, 25);

  const id = data.events[Math.floor(Math.random() * data.events.length)];
  check(http.batch([api(`/events/${id}?lang=ar`, 'event'), api(`/events/${id}/daily-availability?lang=ar`, 'availability')]),
    { 'event ok': (rs) => rs.every((r) => r.status === 200) });
  think(15, 25);

  check(http.batch([api('/events?page=1&lang=ar', 'events_list'), api('/places?page=1&lang=ar', 'places_list')]),
    { 'lists ok': (rs) => rs.every((r) => r.status === 200) });
  think(15, 25);
}
