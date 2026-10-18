/* Admin UI behaviours: sidebar, theme toggle, toasts, confirm modal,
   uploads, color pickers + a JS port of the contrast guard (app/Support/ColorGuard.php). */
(function () {
  'use strict';
  var root = document.documentElement;

  /* ---------------- Contrast guard (mirror of ColorGuard.php) ---------------- */
  var G = (function () {
    var WHITE = '#FFFFFF', INK = '#111827', BLACK = '#000000';
    function norm(h) {
      if (typeof h !== 'string') return null;
      h = h.trim().replace(/^#/, '');
      if (/^[0-9a-f]{3}$/i.test(h)) h = h.split('').map(function (c) { return c + c; }).join('');
      return /^[0-9a-f]{6}$/i.test(h) ? '#' + h.toUpperCase() : null;
    }
    function rgb(h) { h = norm(h) || '#000000'; return [parseInt(h.substr(1, 2), 16), parseInt(h.substr(3, 2), 16), parseInt(h.substr(5, 2), 16)]; }
    function hex(r, g, b) { return '#' + [r, g, b].map(function (v) { v = Math.max(0, Math.min(255, Math.round(v))); return (v < 16 ? '0' : '') + v.toString(16); }).join('').toUpperCase(); }
    function lin(c) { c /= 255; return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); }
    function delin(c) { return 255 * (c <= 0.0031308 ? 12.92 * c : 1.055 * Math.pow(c, 1 / 2.4) - 0.055); }
    function lum(h) { var c = rgb(h); return 0.2126 * lin(c[0]) + 0.7152 * lin(c[1]) + 0.0722 * lin(c[2]); }
    function contrast(a, b) { var x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
    function blend(fg, bg, a) { var f = rgb(fg), b = rgb(bg); return hex(f[0] * a + b[0] * (1 - a), f[1] * a + b[1] * (1 - a), f[2] * a + b[2] * (1 - a)); }
    function bestOn(bg) { var cw = contrast(WHITE, bg); if (cw >= 4.5) return WHITE; if (contrast(INK, bg) >= 4.5) return INK; return contrast(BLACK, bg) >= cw ? BLACK : WHITE; }
    function lab(h) {
      var c = rgb(h).map(lin);
      var x = (0.4124564 * c[0] + 0.3575761 * c[1] + 0.1804375 * c[2]) / 0.95047;
      var y = 0.2126729 * c[0] + 0.7151522 * c[1] + 0.0721750 * c[2];
      var z = (0.0193339 * c[0] + 0.1191920 * c[1] + 0.9503041 * c[2]) / 1.08883;
      function f(t) { return t > 216 / 24389 ? Math.cbrt(t) : (24389 / 27 * t + 16) / 116; }
      var fx = f(x), fy = f(y), fz = f(z);
      return [116 * fy - 16, 500 * (fx - fy), 200 * (fy - fz)];
    }
    function fromLab(l, a, b, clip) {
      var fy = (l + 16) / 116, fx = fy + a / 500, fz = fy - b / 200;
      function inv(t) { return t * t * t > 216 / 24389 ? t * t * t : (116 * t - 16) / (24389 / 27); }
      var x = inv(fx) * 0.95047, y = l > 8 ? fy * fy * fy : l / (24389 / 27), z = inv(fz) * 1.08883;
      var r = 3.2404542 * x - 1.5371385 * y - 0.4985314 * z, g = -0.9692660 * x + 1.8760108 * y + 0.0415560 * z, bb = 0.0556434 * x - 0.2040259 * y + 1.0572252 * z;
      if (!clip && [r, g, bb].some(function (v) { return v < -0.0005 || v > 1.0005; })) return null;
      return hex(delin(Math.max(0, Math.min(1, r))), delin(Math.max(0, Math.min(1, g))), delin(Math.max(0, Math.min(1, bb))));
    }
    function tone(h) { return lab(h)[0]; }
    function withTone(h, t) {
      var L = lab(h), a = L[1], b = L[2]; t = Math.max(0, Math.min(100, t));
      var direct = fromLab(t, a, b); if (direct) return direct;
      var lo = 0, hi = 1; for (var i = 0; i < 20; i++) { var m = (lo + hi) / 2; if (fromLab(t, a * m, b * m)) lo = m; else hi = m; }
      return fromLab(t, a * lo, b * lo, true);
    }
    function toneShift(h, against, ratio, max) {
      ratio = ratio || 4.5; max = max === undefined ? 12 : max; var t = tone(h);
      for (var d = 0; d <= max + 1e-9; d += 0.5) {
        var c = d === 0 ? norm(h) : withTone(h, t - d);
        if (against(c).every(function (x) { return contrast(c, x) >= ratio; })) return c;
      }
      return null;
    }
    function soft(c) { return blend(c, WHITE, 0.10); }
    function guardAction(seed, requestedOn, bg) {
      seed = norm(seed) || '#2F6FED'; requestedOn = norm(requestedOn); bg = bg || '#F7F8FA';
      var on0 = requestedOn || WHITE;
      if (contrast(on0, seed) >= 4.5 && contrast(seed, bg) >= 4.5 && contrast(seed, soft(seed)) >= 4.5) return { fill: seed, on: on0, text: seed, soft: soft(seed), step: 0, shift: 0 };
      var ag = function (c) { return [WHITE, bg, soft(c)]; };
      var s = toneShift(seed, ag);
      if (s && (!requestedOn || requestedOn === WHITE)) return { fill: s, on: WHITE, text: s, soft: soft(s), step: 1, shift: Math.round((tone(seed) - tone(s)) * 10) / 10 };
      var text = toneShift(seed, ag, 4.5, 60) || INK;
      return { fill: seed, on: bestOn(seed), text: text, soft: soft(seed), step: 2, shift: Math.round((tone(seed) - tone(text)) * 10) / 10 };
    }
    function guardOnWhite(h) { h = norm(h) || '#2F6FED'; return toneShift(h, function () { return [WHITE]; }, 4.5, 40) || h; }
    return { norm: norm, contrast: contrast, blend: blend, bestOn: bestOn, tone: tone, withTone: withTone, guardAction: guardAction, guardOnWhite: guardOnWhite, soft: soft };
  })();
  window.ColorGuard = G;

  /* ---------------- Sidebar (mobile) ---------------- */
  var sidebar = document.getElementById('sidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  function setSidebar(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('open', open);
    if (backdrop) backdrop.classList.toggle('show', open);
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-sidebar-toggle]')) { setSidebar(!sidebar.classList.contains('open')); }
    else if (e.target === backdrop) { setSidebar(false); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setSidebar(false); });
  var activeLink = sidebar && sidebar.querySelector('.nav-item-link.active');
  if (activeLink && activeLink.scrollIntoView) { activeLink.scrollIntoView({ block: 'center' }); }

  /* ---------------- Theme toggle (light / dark / auto) ---------------- */
  function applyTheme(pref) {
    var mode = pref === 'light' || pref === 'dark' ? pref : (window.__tenantThemeMode || 'system');
    var dark = mode === 'dark' || (mode === 'system' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
    root.setAttribute('data-theme', dark ? 'dark' : 'light');
    root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    root.setAttribute('data-theme-pref', pref || 'auto');
    document.querySelectorAll('[data-theme-icon]').forEach(function (i) { i.className = 'bi ' + (dark ? 'bi-moon-stars' : 'bi-sun'); });
    document.dispatchEvent(new CustomEvent('themechange', { detail: { dark: dark } }));
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-theme-set]');
    if (!t) return;
    var pref = t.getAttribute('data-theme-set');
    try { if (pref === 'auto') localStorage.removeItem('ui-theme'); else localStorage.setItem('ui-theme', pref); } catch (err) {}
    applyTheme(pref === 'auto' ? null : pref);
  });
  if (window.matchMedia) {
    var mq = matchMedia('(prefers-color-scheme: dark)');
    (mq.addEventListener ? mq.addEventListener.bind(mq, 'change') : mq.addListener.bind(mq))(function () {
      var s = null; try { s = localStorage.getItem('ui-theme'); } catch (err) {}
      if (!s) applyTheme(null);
    });
  }
  applyTheme((function () { try { return localStorage.getItem('ui-theme'); } catch (e) { return null; } })());

  /* ---------------- Toasts ---------------- */
  window.showToast = function (message, type) {
    var stack = document.getElementById('toastStack');
    if (!stack) return;
    var icons = { success: 'check-circle-fill', error: 'exclamation-octagon-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
    var el = document.createElement('div');
    el.className = 'x-toast ' + (type || 'info');
    el.setAttribute('role', 'status');
    el.innerHTML = '<i class="lead bi bi-' + (icons[type] || icons.info) + '"></i><div class="msg"></div><button type="button" aria-label="close"><i class="bi bi-x-lg"></i></button>';
    el.querySelector('.msg').textContent = message;
    stack.appendChild(el);
    arm(el);
  };
  function arm(el) {
    var close = function () { el.classList.add('hide'); setTimeout(function () { el.remove(); }, 220); };
    var btn = el.querySelector('button'); if (btn) btn.addEventListener('click', close);
    var ms = el.classList.contains('error') ? 9000 : 5000;
    setTimeout(close, ms);
  }
  document.querySelectorAll('.x-toast').forEach(arm);

  /* ---------------- Confirm modal ---------------- */
  var modalEl = document.getElementById('confirmModal');
  var pending = null;
  function askConfirm(opts, onOk) {
    if (!modalEl || !window.bootstrap) { if (window.confirm(opts.message)) onOk(); return; }
    modalEl.querySelector('[data-confirm-message]').textContent = opts.message || '';
    modalEl.querySelector('[data-confirm-title]').textContent = opts.title || modalEl.getAttribute('data-default-title');
    var ok = modalEl.querySelector('[data-confirm-ok]');
    ok.textContent = opts.ok || modalEl.getAttribute('data-default-ok');
    var danger = opts.tone !== 'primary';
    ok.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');
    var ico = modalEl.querySelector('.confirm-ico');
    ico.className = 'confirm-ico ' + (danger ? 'tone-error' : 'tone-brand');
    ico.innerHTML = '<i class="bi bi-' + (danger ? 'exclamation-triangle' : 'question-circle') + '"></i>';
    pending = onOk;
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
  if (modalEl) {
    modalEl.querySelector('[data-confirm-ok]').addEventListener('click', function () {
      var fn = pending; pending = null;
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      if (fn) fn();
    });
  }
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches || !form.matches('form[data-confirm]') || form.dataset.confirmed === '1') return;
    e.preventDefault();
    var submitter = e.submitter;
    askConfirm({ message: form.dataset.confirm, title: form.dataset.confirmTitle, ok: form.dataset.confirmOk, tone: form.dataset.confirmTone }, function () {
      form.dataset.confirmed = '1';
      if (submitter && submitter.name) {
        var h = document.createElement('input'); h.type = 'hidden'; h.name = submitter.name; h.value = submitter.value; form.appendChild(h);
      }
      form.submit();
    });
  }, true);
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-confirm]');
    if (!a) return;
    e.preventDefault();
    askConfirm({ message: a.dataset.confirm, tone: a.dataset.confirmTone }, function () { window.location = a.href; });
  });

  /* ---------------- Busy state for submit buttons (no double submit) ---------------- */
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    var btn = e.submitter;
    if (btn && btn.matches('[data-busy]')) {
      setTimeout(function () { btn.disabled = true; btn.style.width = btn.offsetWidth + 'px'; btn.innerHTML = '<span class="spinner-border" role="status"></span>'; }, 0);
    }
  });

  /* ---------------- File upload preview ---------------- */
  document.querySelectorAll('[data-upload]').forEach(function (box) {
    var input = box.querySelector('input[type=file]');
    var preview = box.querySelector('.preview');
    var name = box.querySelector('.u-file');
    if (!input) return;
    ['dragenter', 'dragover'].forEach(function (ev) { box.addEventListener(ev, function () { box.classList.add('drag'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { box.addEventListener(ev, function () { box.classList.remove('drag'); }); });
    input.addEventListener('change', function () {
      var f = input.files && input.files[0];
      if (!f) return;
      if (name) name.textContent = f.name + ' · ' + Math.round(f.size / 1024) + ' KB';
      if (preview && /^image\//.test(f.type)) {
        var url = URL.createObjectURL(f);
        preview.innerHTML = '<img alt="">';
        preview.querySelector('img').src = url;
        box.dispatchEvent(new CustomEvent('upload:preview', { bubbles: true, detail: { url: url, input: input } }));
      }
    });
  });

  /* ---------------- Color pickers ---------------- */
  function ratioChip(el, ratio, state) {
    if (!el) return;
    el.className = 'contrast-chip ' + state;
    el.textContent = ratio.toFixed(2) + ':1';
  }
  document.querySelectorAll('[data-color-field]').forEach(function (wrap) {
    var picker = wrap.querySelector('input[type=color]');
    var text = wrap.querySelector('input[type=text]');
    var sw = wrap.querySelector('.swatch span');
    var chip = wrap.parentNode.querySelector('[data-contrast]');
    var against = wrap.getAttribute('data-against');
    function sync(fromPicker) {
      if (fromPicker) text.value = picker.value.toUpperCase();
      var v = G.norm(text.value);
      if (v) { picker.value = v.toLowerCase(); sw.style.background = v; sw.style.backgroundImage = 'none'; }
      else { sw.style.background = 'repeating-conic-gradient(#ddd 0 25%, #fff 0 50%) 50%/10px 10px'; }
      if (chip && v && against) {
        var r = G.contrast(v, against);
        ratioChip(chip, r, r >= 4.5 ? 'ok' : 'fix');
      }
      text.dispatchEvent(new CustomEvent('color:change', { bubbles: true }));
    }
    picker.addEventListener('input', function () { sync(true); });
    text.addEventListener('input', function () { sync(false); });
    sync(false);
  });

  /* ---------------- Section nav (scrollspy-lite) ---------------- */
  var nav = document.querySelector('[data-section-nav]');
  if (nav && 'IntersectionObserver' in window) {
    var links = nav.querySelectorAll('a[href^="#"]');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          links.forEach(function (l) { l.classList.toggle('active', l.getAttribute('href') === '#' + en.target.id); });
        }
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    links.forEach(function (l) { var s = document.querySelector(l.getAttribute('href')); if (s) io.observe(s); });
  }

  /* A required field inside a hidden tab (e.g. the English tab) would
     silently block submit: show its tab and focus it. `invalid` does not
     bubble, so listen in the capture phase. */
  var revealingInvalid = false;
  document.addEventListener('invalid', function (e) {
    var field = e.target;
    if (revealingInvalid || !field || !field.closest) return;
    var pane = field.closest('.tab-pane');
    if (!pane || pane.classList.contains('active')) return;
    var trigger = pane.id ? document.querySelector('[data-bs-toggle="tab"][data-bs-target="#' + pane.id + '"], [data-bs-toggle="tab"][href="#' + pane.id + '"]') : null;
    if (!trigger) return;
    revealingInvalid = true; // only the first invalid field switches tabs
    setTimeout(function () { revealingInvalid = false; }, 0);
    if (window.bootstrap && bootstrap.Tab) {
      bootstrap.Tab.getOrCreateInstance(trigger).show();
    } else {
      trigger.click();
    }
    setTimeout(function () {
      try { field.focus({ preventScroll: false }); } catch (err) { field.focus(); }
      if (field.reportValidity) field.reportValidity();
    }, 160);
  }, true);

  /* Bootstrap tooltips */
  if (window.bootstrap) {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });
  }
})();
