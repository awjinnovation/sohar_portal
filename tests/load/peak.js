// Evening peak (16:00–01:00): visitors opening the app. One iteration = one
// app open: the home screen's parallel requests (as the Flutter app sends
// them), then an event page. 70 % of visitors are signed in.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';

const BASE = __ENV.BASE || 'http://127.0.0.1:18080/api/v1';
const PEAK = Number(__ENV.PEAK || 30); // app opens per second at the top
const tokens = new SharedArray('tokens', () => JSON.parse(open('./tokens.json')));
const events = JSON.parse(open('./events.json'));

export const options = {
  scenarios: {
    evening_peak: {
      executor: 'ramping-arrival-rate',
      startRate: 2, timeUnit: '1s',
      preAllocatedVUs: 200, maxVUs: 1500,
      stages: [
        { target: Math.round(PEAK / 3), duration: '20s' },
        { target: PEAK, duration: '40s' },
        { target: PEAK, duration: '60s' },
        { target: 0, duration: '10s' },
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{kind:public}': ['p(95)<500'],
    'http_req_duration{kind:personal}': ['p(95)<800'],
  },
  summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

export default function () {
  const signedIn = Math.random() < 0.7;
  const base = { 'X-Tenant': 'north-batinah', Accept: 'application/json', 'Accept-Language': 'ar' };
  const auth = signedIn ? { ...base, Authorization: `Bearer ${tokens[Math.floor(Math.random() * tokens.length)]}` } : base;
  const pub = (path) => ['GET', `${BASE}${path}`, null, { headers: auth, tags: { kind: 'public', name: path.split('?')[0].replace(/\d+/g, ':id') } }];
  const own = (path) => ['GET', `${BASE}${path}`, null, { headers: auth, tags: { kind: 'personal', name: path } }];

  const home = [
    pub('/tenant/config'),
    pub('/events/featured?lang=ar'),
    pub('/events/today?lang=ar'),
    pub('/announcements/active?lang=ar'),
    pub('/places?featured=1&page=1&lang=ar'),
    pub('/content/collections?lang=ar'),
    pub('/content/collections/services/items?page=1&lang=ar'),
    pub('/restaurants?lang=ar'),
  ];
  if (signedIn) {
    home.push(own('/wallet?lang=ar'), own('/wallet/transactions?page=1&lang=ar'), own('/notifications/unread-count?lang=ar'));
  }
  const res = http.batch(home);
  check(res, { 'home ok': (rs) => rs.every((r) => r.status === 200) });
  sleep(1 + Math.random() * 2);

  const id = events[Math.floor(Math.random() * events.length)];
  const detail = http.batch([pub(`/events/${id}?lang=ar`), pub(`/events/${id}/daily-availability?lang=ar`)]);
  check(detail, { 'event ok': (rs) => rs.every((r) => r.status === 200) });
}
