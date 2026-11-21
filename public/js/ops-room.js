/* Operations room wall screen (resources/views/admin/ops-room.blade.php).
   Renders window.OPS_ROOM.state, then polls dataUrl every OPS_ROOM.poll ms.
   Keeps the last data on screen when the server is unreachable and says so. */
(function () {
    'use strict';
    var C = window.OPS_ROOM || {};
    var T = C.T || {};
    var state = C.state || {};
    var lastOk = Date.now();
    var failures = 0;
    var timer = null;

    var $ = function (sel) { return document.querySelector(sel); };
    var all = function (sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); };
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var fmt = function (n) { return Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 1 }); };
    var t = function (key, vars) {
        var s = String(T[key] == null ? key : T[key]);
        Object.keys(vars || {}).forEach(function (k) { s = s.split(':' + k).join(vars[k]); });
        return s;
    };
    var dur = function (sec) {
        if (sec == null) return T.never || '-';
        sec = Math.max(0, Math.round(sec));
        if (sec < 60) return t('sec', { n: sec });
        if (sec < 3600) return t('min', { n: Math.floor(sec / 60) });
        return t('hour', { n: Math.floor(sec / 3600) });
    };
    var timeIn = function (date, opts) {
        try { return new Intl.DateTimeFormat(T.locale || 'en-GB', Object.assign({ timeZone: state.zone || undefined }, opts)).format(date); } catch (e) { return ''; }
    };

    // Set every [data-k=key] to text; flash it when the value changed.
    function set(key, text) {
        all('[data-k="' + key + '"]').forEach(function (el) {
            var v = String(text);
            if (el.textContent !== v) {
                var had = el.textContent !== '';
                el.textContent = v;
                if (had) { el.classList.remove('flash'); void el.offsetWidth; el.classList.add('flash'); }
            }
        });
    }
    function flag(attr, key, on, cls) {
        all('[' + attr + '="' + key + '"]').forEach(function (el) { el.classList.toggle(cls, !!on); });
    }
    function pill(key, status) {
        all('[data-k="' + key + '"]').forEach(function (el) {
            el.className = 'ops-pill ' + status;
            el.textContent = (T.status || {})[status] || status;
        });
    }

    function renderKpis(s) {
        var tk = s.tickets || {}, g = s.gates || {}, sms = s.sms || {}, o = s.orders || {};
        set('tickets.today', fmt(tk.today));
        set('tickets.hint', t('paid_free', { paid: fmt(tk.paid), free: fmt(tk.free) }));
        set('gates.in', fmt(g.in));
        set('gates.hint', t('per_min', { n: fmt(g.per_minute) }));
        set('gates.inside', fmt(g.inside));
        set('gates.per_minute', fmt(g.per_minute));
        set('sms.pending', fmt(sms.pending));
        set('sms.hint', sms.oldest_wait ? t('oldest', { time: dur(sms.oldest_wait) }) : (T.empty_queue || ''));
        set('orders.waiting', fmt((o.checkout || 0) + (o.links || 0)));
        var smsKpi = $('.ops-kpi[data-tone="sms"]'), ordKpi = $('.ops-kpi[data-tone="orders"]');
        if (smsKpi) smsKpi.setAttribute('data-state', sms.status || 'ok');
        if (ordKpi) ordKpi.setAttribute('data-state', o.status || 'ok');
    }

    function renderSms(sms) {
        $('#smsPanel').setAttribute('data-state', sms.status || 'ok');
        pill('sms.status', sms.status || 'ok');
        set('sms.wait', sms.oldest_wait ? t('oldest', { time: dur(sms.oldest_wait) }) : (T.empty_queue || ''));
        ['reserved', 'delayed', 'sent_hour', 'sent_today', 'failed_today'].forEach(function (k) { set('sms.' + k, fmt(sms[k])); });
        set('sms.last_wait', sms.last_wait == null ? '-' : dur(sms.last_wait));
        flag('data-alert', 'sms.failed_today', sms.failed_today > 0, 'is-alert');
        set('sms.foot', foot(sms, sms.last_sent_ago != null ? t('last_sent', { time: dur(sms.last_sent_ago) }) : null));
    }

    function renderOrders(o) {
        $('#ordersPanel').setAttribute('data-state', o.status || 'ok');
        pill('orders.status', o.status || 'ok');
        set('orders.wait', o.oldest_wait ? t('oldest_job', { time: dur(o.oldest_wait) }) : (T.empty_queue || ''));
        set('orders.checkout', fmt(o.checkout));
        set('orders.links', fmt(o.links));
        set('orders.jobs', fmt((o.pending || 0) + (o.reserved || 0) + (o.delayed || 0)));
        set('orders.unissued', fmt(o.unissued));
        set('orders.review', fmt(o.review));
        set('orders.failed_jobs', fmt(o.failed_jobs));
        flag('data-alert', 'orders.unissued', o.unissued > 0, 'is-alert');
        flag('data-alert', 'orders.failed_jobs', o.failed_jobs > 0, 'is-alert');
        flag('data-warn', 'orders.review', o.review > 0, 'is-warn');
        set('orders.foot', foot(o, null));
    }

    // Queue name, its Horizon workers and an extra note, under a queue panel.
    function foot(q, extra) {
        var parts = [q.queue ? t('queue', { name: q.queue }) : ''];
        if (q.workers != null) parts.push(t('workers', { n: q.workers }));
        if (extra) parts.push(extra);
        return parts.filter(Boolean).join(' · ');
    }

    function spark(values, cls) {
        var max = Math.max.apply(null, values.concat([1]));
        return values.map(function (v) {
            var h = v > 0 ? Math.max(8, Math.round(v / max * 100)) : 0;
            return '<i class="' + (v > 0 ? '' : 'zero') + '" style="height:' + (h || 6) + '%"' + (cls === 'labels' && v > 0 ? '><b class="num">' + fmt(v) + '</b></i>' : '></i>');
        }).join('');
    }

    function renderGates(g) {
        var list = g.list || [];
        var el = $('#gateList');
        if (!list.length) { el.innerHTML = '<li class="ops-empty">' + esc(T.no_gates) + '</li>'; return; }
        var max = Math.max.apply(null, list.map(function (x) { return x.in_15m; }).concat([1]));
        el.innerHTML = list.map(function (x, i) {
            var top = i < 3 && x.in_15m > 0;
            var devCls = x.devices === 0 ? '' : (x.devices_online === 0 ? 'bad' : (x.devices_online < x.devices ? 'warn' : 'good'));
            var lastCls = x.last_scan_ago == null || !x.active ? '' : (x.last_scan_ago > 1800 ? 'warn' : '');
            return '<li class="ops-gate' + (top ? ' top' : '') + (x.active ? '' : ' closed') + '" style="--load:' + Math.round(x.in_15m / max * 100) + '%">' +
                '<span class="ops-rank num">' + (i + 1) + '</span>' +
                '<span class="ops-gate-name"><b>' + esc(x.name) + '</b><span>' +
                    (x.code ? '<span class="tag num">' + esc(x.code) + '</span>' : '') +
                    (x.active ? '' : '<span class="tag closed">' + esc(T.closed) + '</span>') +
                    '<span class="num">' + esc(t('per_min', { n: fmt(x.per_minute) })) + '</span>' +
                '</span></span>' +
                '<span class="ops-spark" role="img" aria-label="' + esc(T.gate_chart) + '">' + spark(x.series || []) + '</span>' +
                '<span class="ops-cell hot num">' + fmt(x.in_15m) + '</span>' +
                '<span class="ops-cell num">' + fmt(x.in_today) + '</span>' +
                '<span class="ops-cell num' + (x.rejects_15m > 0 ? ' bad' : '') + '">' + fmt(x.rejects_today) + (x.rejects_15m > 0 ? '<small>+' + fmt(x.rejects_15m) + '</small>' : '') + '</span>' +
                '<span class="ops-cell num ' + devCls + '">' + (x.devices ? esc(t('devices', { online: x.devices_online, total: x.devices })) : '-') + '</span>' +
                '<span class="ops-cell num ' + lastCls + '">' + (x.last_scan_ago == null ? '<small>' + esc(T.no_scans) + '</small>' : esc(dur(x.last_scan_ago))) + '</span>' +
            '</li>';
        }).join('');
    }

    function renderTickets(tk) {
        ['paid', 'free', 'people', 'last_15m', 'last_hour'].forEach(function (k) { set('tickets.' + k, fmt(tk[k])); });
        var counts = (tk.series || {}).counts || [];
        var bars = $('#ticketBars');
        bars.innerHTML = spark(counts, 'labels');
        bars.setAttribute('aria-label', T.chart_label + ': ' + counts.join(', '));
        $('#axisFrom').textContent = T.two_hours;
        $('#axisMid').textContent = T.one_hour;
        $('#axisNow').textContent = T.now;
        var ch = tk.channels || [];
        var total = ch.reduce(function (n, c) { return n + c.count; }, 0) || 1;
        var colors = { online: 'var(--brand)', link: 'var(--info)', card: 'var(--accent)', wallet: 'var(--success)', other: 'var(--outline-strong)' };
        $('#ticketChannels').innerHTML = ch.map(function (c) {
            return '<li><span>' + esc((T.channels || {})[c.key] || c.key) + '</span><b class="num">' + fmt(c.count) + '</b>' +
                '<i style="--share:' + Math.round(c.count / total * 100) + '%;--c:' + colors[c.key] + '"></i></li>';
        }).join('');
    }

    function renderHealth(s) {
        var sys = s.system || {}, g = s.gates || {};
        var proc = function (key, p) {
            var el = $('[data-k="' + key + '"]'); if (!el) return;
            var st = (p || {}).status || 'unknown';
            el.className = 'ops-health-item ' + (st === 'running' ? 'ok' : (st === 'stopped' ? 'critical' : 'warn'));
            el.querySelector('b').textContent = ((T.process || {})[st] || st) + (p && p.ago != null && st !== 'running' ? ' · ' + t('ago', { time: dur(p.ago) }) : '');
        };
        proc('system.worker', sys.worker);
        var hz = $('[data-k="system.horizon"]');
        hz.hidden = !sys.horizon;
        if (sys.horizon) {
            hz.className = 'ops-health-item ' + (sys.horizon === 'running' ? 'ok' : (sys.horizon === 'paused' ? 'warn' : 'critical'));
            hz.querySelector('b').textContent = (T.horizon || {})[sys.horizon] || sys.horizon;
        }
        proc('system.scheduler', sys.scheduler);
        var dev = $('[data-k="system.devices"]');
        dev.className = 'ops-health-item ' + (g.devices_total && !g.devices_online ? 'critical' : (g.devices_online < g.devices_total ? 'warn' : 'ok'));
        dev.querySelector('b').textContent = t('devices', { online: g.devices_online || 0, total: g.devices_total || 0 });
        var rej = $('[data-k="system.rejects"]');
        rej.className = 'ops-health-item';
        rej.querySelector('b').textContent = fmt(g.rejects);
        set('system.driver', sys.queue_driver || '');
    }

    function render(s) {
        state = s;
        renderKpis(s);
        renderGates(s.gates || {});
        renderSms(s.sms || {});
        renderOrders(s.orders || {});
        renderTickets(s.tickets || {});
        renderHealth(s);
        var at = s.now ? new Date(s.now * 1000) : new Date();
        set('opsUpdated', '');
        $('#opsUpdated').textContent = t('updated', { time: timeIn(at, { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }) });
    }

    function banner(html) {
        var b = $('#opsBanner');
        if (!html) { b.hidden = true; b.innerHTML = ''; return; }
        b.innerHTML = html; b.hidden = false;
    }
    function live(ok) {
        var p = $('#opsLive');
        p.className = 'ops-pill ' + (ok ? 'ok' : 'critical');
    }

    function poll() {
        clearTimeout(timer);
        fetch(C.dataUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) {
                // Signed out: 401/419, or redirected to the login page.
                if (r.status === 401 || r.status === 419 || (r.redirected && /\/login/.test(r.url))) {
                    var e = new Error('session'); e.session = true; throw e;
                }
                if (!r.ok || !/json/.test(r.headers.get('content-type') || '')) throw new Error('http ' + r.status);
                return r.json();
            })
            .then(function (j) {
                failures = 0; lastOk = Date.now();
                render(j.data || {});
                banner(null); live(true);
                timer = setTimeout(poll, C.poll || 10000);
            })
            .catch(function (e) {
                failures++;
                live(false);
                if (e && e.session) {
                    banner(esc(T.session) + ' <a href="' + esc(C.loginUrl) + '">' + esc(T.login) + '</a>');
                    timer = setTimeout(poll, 60000);
                    return;
                }
                banner(esc(t('offline', { time: timeIn(new Date(lastOk), { hour: '2-digit', minute: '2-digit', hour12: false }) })));
                // Back off up to a minute while the server is unreachable.
                timer = setTimeout(poll, Math.min(60000, (C.poll || 10000) * Math.min(6, failures)));
            });
    }

    // Clock in the tenant's time zone.
    function tick() {
        var now = new Date();
        $('#opsClock').textContent = timeIn(now, { hour: '2-digit', minute: '2-digit', hour12: false });
        $('#opsDate').textContent = timeIn(now, { weekday: 'long', day: 'numeric', month: 'long' });
    }

    // Full screen (button or the F key), screen kept awake, cursor hidden when idle.
    function toggleFullscreen() {
        var d = document;
        if (d.fullscreenElement) { d.exitFullscreen && d.exitFullscreen(); }
        else if (d.documentElement.requestFullscreen) { d.documentElement.requestFullscreen().catch(function () {}); }
    }
    var fsBtn = $('#opsFullscreen');
    fsBtn.addEventListener('click', toggleFullscreen);
    document.addEventListener('keydown', function (e) { if ((e.key === 'f' || e.key === 'F') && !e.metaKey && !e.ctrlKey && !e.altKey) toggleFullscreen(); });
    document.addEventListener('fullscreenchange', function () {
        var on = !!document.fullscreenElement;
        fsBtn.title = on ? T.exit_fullscreen : T.fullscreen;
        fsBtn.setAttribute('aria-label', fsBtn.title);
        fsBtn.querySelector('i').className = 'bi ' + (on ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen');
    });
    var wake = null;
    function keepAwake() {
        if (!('wakeLock' in navigator) || document.visibilityState !== 'visible') return;
        navigator.wakeLock.request('screen').then(function (l) { wake = l; }).catch(function () {});
    }
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') { keepAwake(); poll(); }
    });
    var idle = null;
    function awake() {
        document.body.classList.remove('idle');
        clearTimeout(idle);
        idle = setTimeout(function () { document.body.classList.add('idle'); }, 4000);
    }
    ['mousemove', 'keydown', 'touchstart'].forEach(function (ev) { document.addEventListener(ev, awake, { passive: true }); });

    render(state);
    tick(); setInterval(tick, 1000);
    keepAwake(); awake();
    timer = setTimeout(poll, C.poll || 10000);
})();
