/* Event form → Ticket types editor (docs/ticket_types_contract.md §3).
   Renders the list from #ttConfig, edits in #ttModal, saves through the
   admin JSON endpoints (session + CSRF) and never submits the event form. */
(function () {
  var cfgEl = document.getElementById('ttConfig');
  var list = document.getElementById('ttList');
  if (!cfgEl || !list) return;
  var cfg = JSON.parse(cfgEl.textContent);
  var T = cfg.t;
  var types = cfg.types || [];
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;
  var modalEl = document.getElementById('ttModal');
  var modal = window.bootstrap ? new bootstrap.Modal(modalEl) : null;
  var form = document.getElementById('ttForm');
  var errorBox = document.getElementById('ttError');
  var colorDefault = document.getElementById('tt_color_default');
  var editingId = null;
  var ar = cfg.locale === 'ar';

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function fmt(str, n) { return str.replace(':count', n).replace(':age', n); }
  function money(v) { return '<span class="money" dir="ltr"><span class="cur">' + esc(cfg.currency) + '</span>' + esc(v) + '</span>'; }
  function field(name) { return form.querySelector('[data-f="' + name + '"]'); }

  function render() {
    if (!types.length) { list.innerHTML = '<div class="text-muted small">' + esc(T.empty) + '</div>'; return; }
    list.innerHTML = types.map(function (t) {
      var cat = cfg.categories[t.category] || cfg.categories.other;
      var name = (ar ? t.name.ar : t.name.en) || t.name_text;
      var badges = [];
      if (t.admits > 1) badges.push('<span class="pill pill-brand pill-sm"><i class="bi bi-people"></i>' + esc(fmt(T.admits, t.admits)) + '</span>');
      if (t.visibility === 'box_office') badges.push('<span class="pill pill-info pill-sm"><i class="bi bi-door-open"></i>' + esc(T.boxOffice) + '</span>');
      if (!t.is_active) badges.push('<span class="pill pill-neutral pill-sm">' + esc(T.inactive) + '</span>');
      else if (!t.sales_open) badges.push('<span class="pill pill-warning pill-sm">' + esc(T.salesClosed) + '</span>');
      if (t.requires_id) badges.push('<span class="pill pill-warning pill-sm"><i class="bi bi-person-vcard"></i>' + esc(T.id) + '</span>');
      if (t.max_age != null) badges.push('<span class="pill pill-neutral pill-sm">' + esc(fmt(T.ageMax, t.max_age)) + '</span>');
      if (t.min_age != null) badges.push('<span class="pill pill-neutral pill-sm">' + esc(fmt(T.ageMin, t.min_age)) + '</span>');
      if (t.capacity != null) badges.push('<span class="pill pill-neutral pill-sm">' + esc(fmt(T.capacity, t.capacity)) + '</span>');
      badges.push('<span class="pill pill-neutral pill-sm">' + esc(fmt(T.perOrder, t.per_order_max)) + '</span>');
      var left = t.available == null ? T.unlimited : t.available;
      return '<div class="tt-item' + (t.is_active ? '' : ' is-off') + '" data-id="' + t.id + '" data-key="' + esc(t.key) + '">' +
        '<span class="tt-drag"><i class="bi bi-grip-vertical"></i></span>' +
        '<span class="tt-ico" style="background:' + esc(t.color) + '"><i class="bi bi-' + esc(cat.icon) + '"></i></span>' +
        '<div class="tt-main"><div class="tt-name">' + esc(name) + ' <code class="ltr">' + esc(t.key) + '</code></div>' +
        '<div class="tt-badges">' + badges.join('') + '</div></div>' +
        '<div class="tt-stats"><span><b class="num">' + esc(t.sold) + '</b>' + esc(T.sold) + '</span><span><b class="num">' + esc(left) + '</b>' + esc(T.left) + '</span><span><b>' + money(t.revenue) + '</b>' + esc(T.revenue) + '</span></div>' +
        '<div class="tt-price">' + (t.price_minor === 0 ? esc(T.free) : money(t.price)) + '</div>' +
        '<div class="row-actions"><button type="button" class="icon-btn brand" data-tt-edit="' + t.id + '" title="' + esc(T.edit) + '" aria-label="' + esc(T.edit) + '"><i class="bi bi-pencil"></i></button>' +
        '<button type="button" class="icon-btn danger" data-tt-delete="' + t.id + '" title="' + esc(t.has_sales ? T.cannotDelete : T.delete) + '" aria-label="' + esc(T.delete) + '"' + (t.has_sales ? ' disabled' : '') + '><i class="bi bi-trash"></i></button></div>' +
        '</div>';
    }).join('');
  }

  function request(method, url, body) {
    return fetch(url, {
      method: method, credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); });
  }

  function uniqueKey(key) {
    if (!key) return '';
    var taken = types.map(function (t) { return t.key; });
    if (taken.indexOf(key) < 0) return key;
    for (var i = 2; i < 50; i++) { if (taken.indexOf(key + '_' + i) < 0) return key + '_' + i; }
    return key + '_' + Date.now();
  }

  function fill(v) {
    ['name_ar', 'name_en', 'description_ar', 'description_en', 'key', 'price', 'admits', 'capacity', 'per_order_max', 'min_age', 'max_age', 'sales_start', 'sales_end'].forEach(function (k) {
      field(k).value = v[k] == null ? '' : v[k];
    });
    field('category').value = v.category || 'general';
    field('visibility').value = v.visibility || 'public';
    field('requires_id').checked = !!v.requires_id;
    field('is_active').checked = v.is_active !== false;
    colorDefault.checked = !v.color;
    field('color').value = v.color || cfg.colors[v.category || 'general'] || '#2F8E80';
    errorBox.classList.add('d-none');
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
  }

  function open(values, id) {
    editingId = id || null;
    document.getElementById('ttModalTitle').textContent = id ? T.editType : T.newType;
    fill(values);
    if (modal) modal.show();
  }

  document.querySelectorAll('[data-tt-template]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tpl = cfg.templates[btn.getAttribute('data-tt-template')] || { category: 'other', admits: 1 };
      var v = Object.assign({ price: '0.000', per_order_max: 10, visibility: 'public', is_active: true }, tpl);
      v.key = uniqueKey(tpl.key || '');
      open(v, null);
    });
  });

  list.addEventListener('click', function (e) {
    var edit = e.target.closest('[data-tt-edit]');
    var del = e.target.closest('[data-tt-delete]');
    if (edit) {
      var t = types.filter(function (x) { return String(x.id) === edit.getAttribute('data-tt-edit'); })[0];
      if (!t) return;
      open({
        name_ar: t.name.ar, name_en: t.name.en, description_ar: t.description.ar, description_en: t.description.en, key: t.key,
        category: t.category, price: t.price_input, admits: t.admits, capacity: t.capacity, per_order_max: t.per_order_max,
        min_age: t.min_age, max_age: t.max_age, sales_start: t.sales_start_local, sales_end: t.sales_end_local,
        visibility: t.visibility, color: t.own_color, requires_id: t.requires_id, is_active: t.is_active
      }, t.id);
    }
    if (del && !del.disabled) {
      if (!window.confirm(T.confirmDelete)) return;
      request('DELETE', cfg.base + '/' + del.getAttribute('data-tt-delete')).then(function (r) {
        if (r.ok) { types = r.data.data; render(); } else { window.alert(r.data.message || T.error); }
      });
    }
  });

  field('category').addEventListener('change', function () {
    if (colorDefault.checked) field('color').value = cfg.colors[field('category').value] || '#6B7280';
  });
  field('color').addEventListener('input', function () { colorDefault.checked = false; });
  field('name_en').addEventListener('input', function () {
    if (editingId || field('key').dataset.touched) return;
    field('key').value = uniqueKey(field('name_en').value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40));
  });
  field('key').addEventListener('input', function () { field('key').dataset.touched = '1'; });

  document.getElementById('ttSave').addEventListener('click', function () {
    var body = {};
    ['name_ar', 'name_en', 'description_ar', 'description_en', 'key', 'category', 'price', 'admits', 'capacity', 'per_order_max', 'min_age', 'max_age', 'sales_start', 'sales_end', 'visibility'].forEach(function (k) {
      var v = field(k).value; body[k] = v === '' ? null : v;
    });
    body.requires_id = field('requires_id').checked;
    body.is_active = field('is_active').checked;
    body.color = colorDefault.checked ? null : field('color').value;
    var btn = this; btn.disabled = true;
    request(editingId ? 'PUT' : 'POST', editingId ? cfg.base + '/' + editingId : cfg.base, body).then(function (r) {
      btn.disabled = false;
      if (r.ok) { types = r.data.data; render(); if (modal) modal.hide(); delete field('key').dataset.touched; return; }
      var errs = r.data.errors || {};
      form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
      Object.keys(errs).forEach(function (k) { var el = field(k); if (el) el.classList.add('is-invalid'); });
      errorBox.textContent = Object.keys(errs).length ? Object.keys(errs).map(function (k) { return errs[k][0]; }).join(' ') : (r.data.message || T.error);
      errorBox.classList.remove('d-none');
    }).catch(function () { btn.disabled = false; errorBox.textContent = T.error; errorBox.classList.remove('d-none'); });
  });

  render();
  if (window.Sortable) {
    Sortable.create(list, {
      animation: 150, handle: '.tt-drag', draggable: '.tt-item',
      onEnd: function (evt) {
        if (evt.oldIndex === evt.newIndex) return;
        var ids = Array.prototype.map.call(list.querySelectorAll('.tt-item'), function (el) { return parseInt(el.getAttribute('data-id'), 10); });
        request('POST', cfg.reorder, { ids: ids }).then(function (r) { if (r.ok) { types = r.data.data; render(); } });
      }
    });
  }
})();
