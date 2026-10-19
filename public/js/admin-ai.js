/* AI copilot for the admin panel (docs/ai_and_gate_contract.md §A5).
   - [data-ai-copilot] cards on the event / announcement forms: buttons call
     small JSON endpoints (CSRF) and insert the suggestion into the form
     fields; nothing is saved until the admin presses Save. Undo restores.
   - [data-ai-briefing] dashboard card: loads the cached daily briefing,
     "Regenerate" asks for a fresh one. */
(function () {
  'use strict';
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

  function json(el, attr) {
    try { return JSON.parse(el.getAttribute(attr) || '{}'); } catch (e) { return {}; }
  }

  function request(method, url, body, i18n) {
    return fetch(url, {
      method: method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok || data.success === false) {
          var msg = data.message || i18n.error;
          if (data.errors) { var first = Object.keys(data.errors)[0]; if (first && data.errors[first][0]) msg = data.errors[first][0]; }
          throw new Error(msg);
        }
        return data.data || {};
      });
    });
  }

  function toast(msg, type) { if (window.showToast) window.showToast(msg, type); }

  /* ---------------- Form copilot ---------------- */
  document.querySelectorAll('[data-ai-copilot]').forEach(function (card) {
    var kind = card.getAttribute('data-ai-copilot'); // event | announcement
    var urls = json(card, 'data-ai-urls');
    var i18n = json(card, 'data-ai-i18n');
    var form = card.closest('form') || document;
    var status = card.querySelector('[data-ai-status]');
    var undoBtn = card.querySelector('[data-ai-undo]');
    var buttons = card.querySelectorAll('[data-ai-action]');
    var undoStack = [];
    // Places / content forms (place | item | page) describe their own fields:
    // data-ai-fields (translated ids, "<id>" = English, "<id>_ar" = Arabic),
    // data-ai-body (shortened field), data-ai-describe (fact sources) and
    // data-ai-describe-out (response key -> field id).
    var customFields = json(card, 'data-ai-fields');
    var textFields = Array.isArray(customFields) && customFields.length ? customFields
      : (kind === 'event' ? ['title', 'description', 'location', 'organizer_name'] : ['title', 'content']);
    var bodyField = card.getAttribute('data-ai-body') || (kind === 'event' ? 'description' : 'content');
    var describeFrom = json(card, 'data-ai-describe');
    var describeOut = json(card, 'data-ai-describe-out');

    function field(id) { return form.querySelector('#' + id); }
    function val(id) { var el = field(id); return el ? String(el.value || '').trim() : ''; }
    function source(spec) {
      // "#select_id option:checked" = visible text of a select; otherwise a field id.
      if (spec.charAt(0) === '#') { var o = form.querySelector(spec); return o && o.value !== '' ? String(o.textContent || '').trim() : ''; }
      return val(spec);
    }
    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function paragraphs(text) {
      // Plain model text -> <p> paragraphs for rich-text fields.
      return String(text).split(/\n\s*\n/).map(function (p) { return p.trim(); }).filter(Boolean)
        .map(function (p) { return '<p>' + esc(p).replace(/\n/g, '<br>') + '</p>'; }).join('');
    }
    function setStatus(msg, tone) {
      if (!status) return;
      status.textContent = msg || '';
      status.className = 'small mt-2 ' + (tone === 'error' ? 'text-danger' : 'text-muted');
    }
    function busy(on, btn) {
      buttons.forEach(function (b) { b.disabled = on; });
      if (btn) {
        if (on) { btn.dataset.html = btn.innerHTML; btn.innerHTML = '<span class="spinner-border" role="status"></span> ' + i18n.working; }
        else if (btn.dataset.html) { btn.innerHTML = btn.dataset.html; }
      }
      card.setAttribute('aria-busy', on ? 'true' : 'false');
    }
    function revealTab(el) {
      var pane = el && el.closest('.tab-pane');
      if (!pane || pane.classList.contains('active') || !pane.id) return;
      var trigger = form.querySelector('[data-bs-target="#' + pane.id + '"]');
      if (trigger && window.bootstrap && bootstrap.Tab) bootstrap.Tab.getOrCreateInstance(trigger).show();
    }
    function apply(values) {
      var snapshot = {};
      var first = null;
      Object.keys(values).forEach(function (id) {
        var el = field(id);
        var v = values[id];
        if (!el || typeof v !== 'string' || v.trim() === '') return;
        snapshot[id] = el.value;
        v = v.trim();
        if (el.hasAttribute('data-rich') && !/<[a-z][^>]*>/i.test(v)) v = paragraphs(v);
        el.value = v;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.classList.add('ai-filled');
        setTimeout(function () { el.classList.remove('ai-filled'); }, 2600);
        if (!first) first = el;
      });
      if (!first) { setStatus(i18n.error, 'error'); return; }
      undoStack.push(snapshot);
      if (undoBtn) undoBtn.classList.remove('d-none');
      revealTab(first);
      setStatus(i18n.inserted);
      toast(i18n.inserted, 'success');
    }
    function activeLocale() {
      var pane = form.querySelector('.tab-pane.active');
      return pane && pane.getAttribute('dir') === 'ltr' ? 'en' : 'ar';
    }
    function run(btn, promise) {
      busy(true, btn);
      setStatus(i18n.working);
      promise.then(apply).catch(function (err) {
        setStatus(err.message || i18n.error, 'error');
        toast(err.message || i18n.error, 'error');
      }).finally(function () { busy(false, btn); });
    }

    card.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-ai-action]');
      if (!btn || btn.disabled) return;
      var action = btn.getAttribute('data-ai-action');

      if (action === 'describe' && describeFrom && describeFrom.kind) {
        var facts = { kind: describeFrom.kind };
        Object.keys(describeFrom).forEach(function (k) {
          if (k === 'kind') return;
          var v = source(describeFrom[k]); if (v !== '') facts[k] = v;
        });
        if (!facts.title && !facts.title_ar) { setStatus(i18n.need_title, 'error'); return; }
        if (facts.notes) facts.notes = facts.notes.slice(0, 1500);
        run(btn, request('POST', urls.describe, facts, i18n).then(function (d) {
          var out = {};
          Object.keys(describeOut).forEach(function (k) { if (d[k]) out[describeOut[k]] = d[k]; });
          return out;
        }));
        return;
      }

      if (action === 'describe') {
        if (!val('title') && !val('title_ar')) { setStatus(i18n.need_title, 'error'); return; }
        var body = {};
        ['title', 'title_ar', 'category_id', 'location', 'location_ar', 'map_location_id', 'start_time', 'end_time', 'price', 'organizer_name'].forEach(function (k) {
          var v = val(k); if (v !== '') body[k] = v;
        });
        run(btn, request('POST', urls.describe, body, i18n).then(function (d) {
          return { description_ar: d.description_ar, description: d.description };
        }));
      }

      if (action === 'translate') {
        var from = btn.getAttribute('data-from'), to = btn.getAttribute('data-to');
        var fields = {};
        textFields.forEach(function (f) { var v = val(from === 'ar' ? f + '_ar' : f); if (v) fields[f] = v; });
        if (!Object.keys(fields).length) { setStatus(i18n.nothing_to_translate, 'error'); return; }
        run(btn, request('POST', urls.translate, { from: from, to: to, fields: fields }, i18n).then(function (d) {
          var out = {};
          Object.keys(d.fields || {}).forEach(function (f) { out[to === 'ar' ? f + '_ar' : f] = d.fields[f]; });
          return out;
        }));
      }

      if (action === 'shorten') {
        var locale = activeLocale();
        var id = locale === 'ar' ? bodyField + '_ar' : bodyField;
        var text = val(id);
        if (!text) { setStatus(i18n.nothing_to_shorten, 'error'); return; }
        run(btn, request('POST', urls.shorten, { text: text, locale: locale }, i18n).then(function (d) {
          var o = {}; o[id] = d.text; return o;
        }));
      }

      if (action === 'announcement') {
        var briefEl = card.querySelector('[data-ai-brief]');
        var brief = briefEl ? briefEl.value.trim() : '';
        if (brief.length < 5) { setStatus(i18n.brief_required, 'error'); if (briefEl) briefEl.focus(); return; }
        run(btn, request('POST', urls.announcement, { brief: brief, type: val('type') || null }, i18n));
      }
    });

    // Enter in the brief must not submit the surrounding form.
    var briefInput = card.querySelector('[data-ai-brief]');
    if (briefInput) {
      briefInput.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var draft = card.querySelector('[data-ai-action="announcement"]');
        if (draft) draft.click();
      });
    }

    if (undoBtn) {
      undoBtn.addEventListener('click', function () {
        var snap = undoStack.pop();
        if (!snap) return;
        Object.keys(snap).forEach(function (id) { var el = field(id); if (el) { el.value = snap[id]; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); } });
        if (!undoStack.length) undoBtn.classList.add('d-none');
        setStatus(i18n.undone);
      });
    }
  });

  /* ---------------- Daily briefing ---------------- */
  document.querySelectorAll('[data-ai-briefing]').forEach(function (card) {
    var urls = json(card, 'data-ai-urls');
    var i18n = json(card, 'data-ai-i18n');
    var body = card.querySelector('[data-ai-body]');
    var btn = card.querySelector('[data-ai-regenerate]');

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function loading() {
      body.innerHTML = '<div class="d-flex align-items-center gap-2 text-muted small py-2"><span class="spinner-border spinner-border-sm" role="status"></span>' + esc(i18n.loading) + '</div>';
    }
    function render(d) {
      var html = '';
      if (d.headline) html += '<p class="fw-bold mb-2">' + esc(d.headline) + '</p>';
      if (d.bullets && d.bullets.length) html += '<ul class="mb-2 ps-3">' + d.bullets.map(function (b) { return '<li>' + esc(b) + '</li>'; }).join('') + '</ul>';
      if (d.actions && d.actions.length) {
        html += '<div class="small fw-bold text-muted mt-2 mb-1">' + esc(i18n.actions) + '</div><ul class="mb-0 ps-3 small">' + d.actions.map(function (a) { return '<li>' + esc(a) + '</li>'; }).join('') + '</ul>';
      }
      if (d.generated_at) {
        var t = new Date(d.generated_at);
        var time = isNaN(t) ? d.generated_at : t.toLocaleTimeString(document.documentElement.lang === 'ar' ? 'ar-u-nu-latn' : (document.documentElement.lang || undefined), { hour: '2-digit', minute: '2-digit', hour12: false });
        html += '<div class="form-text mt-2">' + esc(i18n.generated.replace(':time', time)) + '</div>';
      }
      body.innerHTML = html || '<p class="text-muted mb-0">—</p>';
    }
    function fail(err) {
      body.innerHTML = '<p class="text-danger small mb-0"><i class="bi bi-exclamation-triangle"></i> ' + esc(err.message || i18n.error) + '</p>';
    }
    function load(method) {
      loading();
      if (btn) btn.disabled = true;
      request(method, method === 'POST' ? urls.regenerate : urls.show, null, i18n).then(render).catch(fail).finally(function () { if (btn) btn.disabled = false; });
    }
    if (btn) btn.addEventListener('click', function () { load('POST'); });
    load('GET');
  });
})();
