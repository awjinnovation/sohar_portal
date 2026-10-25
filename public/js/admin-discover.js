/* Places & content admin (docs/places_content_contract.md §4).
   - [data-icon-picker]     Material Symbols picker with search + live preview
   - [data-location-picker] Leaflet + OSM pin picker (click / drag / paste / map centre)
   - [data-rich-text]       light contenteditable editor synced to a textarea
   - [data-gallery]         gallery reorder (SortableJS), remove toggles, new-file previews
   - [data-sortable-rows]   drag-to-sort table rows → POST {ids: [...]} to data-url
   - [data-places-map]      full admin map of places with category legend
   - [data-location-mode]   item form: none / linked place / own location */
(function () {
  'use strict';
  var I18N = window.DISCOVER_I18N || {};
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
  var OSM = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  var OSM_ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

  function toast(msg, type) { if (window.showToast) window.showToast(msg, type); }
  function json(el, attr, fallback) { try { return JSON.parse(el.getAttribute(attr) || ''); } catch (e) { return fallback; } }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  /* ---------------- Icon picker ---------------- */
  document.querySelectorAll('[data-icon-picker]').forEach(function (box) {
    var input = box.querySelector('[data-ip-input]');
    var preview = box.querySelector('[data-ip-preview] .msr');
    var panel = box.querySelector('[data-ip-panel]');
    var toggle = box.querySelector('[data-ip-toggle]');
    var search = box.querySelector('[data-ip-search]');
    var items = box.querySelectorAll('.ip-item');

    function fallback() { return box.getAttribute('data-fallback') || 'place'; }
    function sync() {
      var v = (input.value || '').trim().toLowerCase().replace(/[^a-z0-9_]/g, '');
      preview.textContent = v || fallback();
      items.forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-icon') === v); });
    }
    function open(on) {
      panel.classList.toggle('d-none', !on);
      toggle.setAttribute('aria-expanded', on ? 'true' : 'false');
      if (on) { search.value = ''; filter(''); search.focus(); }
    }
    function filter(q) {
      q = q.trim().toLowerCase().replace(/\s+/g, '_');
      items.forEach(function (b) { b.hidden = q !== '' && b.getAttribute('data-icon').indexOf(q) === -1; });
    }
    input.addEventListener('input', sync);
    toggle.addEventListener('click', function () { open(panel.classList.contains('d-none')); });
    search.addEventListener('input', function () { filter(search.value); });
    search.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); var first = box.querySelector('.ip-item:not([hidden])'); if (first) first.click(); } if (e.key === 'Escape') open(false); });
    box.addEventListener('click', function (e) {
      var b = e.target.closest('.ip-item');
      if (!b) return;
      input.value = b.getAttribute('data-icon');
      sync(); open(false); input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    // Category change updates the fallback icon / colour (place form).
    box.addEventListener('ip:fallback', function (e) {
      box.setAttribute('data-fallback', e.detail.icon || 'place');
      input.placeholder = e.detail.icon || 'place';
      if (e.detail.color) box.querySelector('[data-ip-preview]').style.setProperty('--ip', e.detail.color);
      sync();
    });
    sync();
  });

  /* ---------------- Location picker ---------------- */
  function pinIcon(color, icon) {
    return L.divIcon({
      className: 'lp-pin',
      html: '<span class="lp-pin-dot" style="--pin:' + esc(color || '#2F8E80') + '"><span class="msr">' + esc(icon || 'location_on') + '</span></span>',
      iconSize: [34, 34], iconAnchor: [17, 34], popupAnchor: [0, -30]
    });
  }
  function parseCoords(text) {
    text = String(text || '').trim();
    // Google / Apple / OSM links: @lat,lng · q=lat,lng · ll=lat,lng · mlat=..&mlon=.. · #map=z/lat/lng
    var m = text.match(/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/) || text.match(/[?&](?:q|ll|query|destination)=(-?\d+(?:\.\d+)?)(?:,|%2C)\s*(-?\d+(?:\.\d+)?)/i)
      || text.match(/#map=\d+\/(-?\d+(?:\.\d+)?)\/(-?\d+(?:\.\d+)?)/) || text.match(/^(-?\d+(?:\.\d+)?)\s*[,،\s]\s*(-?\d+(?:\.\d+)?)$/);
    if (!m) {
      var a = text.match(/mlat=(-?\d+(?:\.\d+)?)/), b = text.match(/mlon=(-?\d+(?:\.\d+)?)/);
      if (a && b) m = [null, a[1], b[1]];
    }
    if (!m) return null;
    var lat = parseFloat(m[1]), lng = parseFloat(m[2]);
    if (isNaN(lat) || isNaN(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
    return [lat, lng];
  }

  document.querySelectorAll('[data-location-picker]').forEach(function (box) {
    if (!window.L) return;
    var center = json(box, 'data-center', { lat: 24.35, lng: 56.72, zoom: 12 });
    var others = json(box, 'data-others', []);
    var latIn = box.querySelector('[data-lp-lat]');
    var lngIn = box.querySelector('[data-lp-lng]');
    var msg = box.querySelector('[data-lp-msg]');
    var color = box.getAttribute('data-color') || getComputedStyle(document.documentElement).getPropertyValue('--brand').trim() || '#2F8E80';
    var icon = 'location_on';
    var start = (latIn.value && lngIn.value) ? [parseFloat(latIn.value), parseFloat(lngIn.value)] : null;
    var map = L.map(box.querySelector('[data-lp-map]'), { scrollWheelZoom: false }).setView(start || [center.lat, center.lng], start ? 15 : (center.zoom || 12));
    L.tileLayer(OSM, { maxZoom: 19, attribution: OSM_ATTR }).addTo(map);
    map.on('focus', function () { map.scrollWheelZoom.enable(); });
    map.on('blur', function () { map.scrollWheelZoom.disable(); });

    others.forEach(function (o) {
      if (!o || o[0] == null) return;
      L.circleMarker([o[0], o[1]], { radius: 6, color: o[3] || '#888', weight: 1, fillOpacity: 0.35, opacity: 0.55 })
        .bindTooltip(esc(o[2] || ''), { direction: 'top' }).addTo(map);
    });

    var marker = null;
    function place(lat, lng, pan) {
      lat = Math.round(lat * 1e7) / 1e7; lng = Math.round(lng * 1e7) / 1e7;
      latIn.value = lat.toFixed(7).replace(/0+$/, '').replace(/\.$/, '');
      lngIn.value = lng.toFixed(7).replace(/0+$/, '').replace(/\.$/, '');
      if (!marker) {
        marker = L.marker([lat, lng], { draggable: true, icon: pinIcon(color, icon), autoPan: true }).addTo(map);
        marker.on('dragend', function () { var p = marker.getLatLng(); place(p.lat, p.lng, false); });
      } else {
        marker.setLatLng([lat, lng]);
      }
      if (pan) map.panTo([lat, lng]);
      latIn.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (start) place(start[0], start[1], false);
    map.on('click', function (e) { place(e.latlng.lat, e.latlng.lng, false); });
    function fromInputs() {
      var lat = parseFloat(latIn.value), lng = parseFloat(lngIn.value);
      if (!isNaN(lat) && !isNaN(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) place(lat, lng, true);
    }
    latIn.addEventListener('change', function (e) { if (e.isTrusted) fromInputs(); });
    lngIn.addEventListener('change', fromInputs);
    box.querySelector('[data-lp-center]').addEventListener('click', function () { var c = map.getCenter(); place(c.lat, c.lng, false); });
    var paste = box.querySelector('[data-lp-paste]');
    function go() {
      var c = parseCoords(paste.value);
      if (!c) { msg.textContent = msg.getAttribute('data-bad'); msg.classList.add('text-danger'); return; }
      msg.classList.remove('text-danger');
      place(c[0], c[1], true); map.setZoom(Math.max(map.getZoom(), 15));
    }
    box.querySelector('[data-lp-go]').addEventListener('click', go);
    paste.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(); } });
    paste.addEventListener('paste', function () { setTimeout(go, 0); });
    // Pin follows the chosen icon / category colour.
    box.addEventListener('lp:style', function (e) {
      color = e.detail.color || color; icon = e.detail.icon || icon;
      if (marker) marker.setIcon(pinIcon(color, icon));
    });
    // Leaflet needs a size refresh when shown inside a hidden tab / collapsed block.
    box.addEventListener('lp:refresh', function () { setTimeout(function () { map.invalidateSize(); }, 50); });
    setTimeout(function () { map.invalidateSize(); }, 200);
    box._lpMap = map;
  });

  /* ---------------- Rich text ---------------- */
  document.querySelectorAll('[data-rich-text]').forEach(function (wrap) {
    var area = wrap.querySelector('.rich-area');
    var src = wrap.querySelector('textarea[data-rich]');
    var sourceMode = false;
    function load() { area.innerHTML = src.value || ''; }
    function save() { if (!sourceMode) src.value = area.innerHTML.replace(/<p><br><\/p>/g, '').trim(); }
    load();
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
    area.addEventListener('input', save);
    area.addEventListener('blur', save);
    area.addEventListener('paste', function (e) {
      // Paste as plain text: formatting from Word / web pages would be stripped by the server anyway.
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      if (!text) return;
      e.preventDefault();
      var html = text.split(/\n\s*\n/).map(function (p) { return '<p>' + esc(p.trim()).replace(/\n/g, '<br>') + '</p>'; }).join('');
      document.execCommand('insertHTML', false, html);
    });
    src.addEventListener('change', function () { if (!sourceMode) load(); });
    wrap.querySelector('.rich-toolbar').addEventListener('click', function (e) {
      var b = e.target.closest('[data-cmd]');
      if (!b) return;
      var cmd = b.getAttribute('data-cmd');
      if (cmd === 'source') {
        sourceMode = !sourceMode;
        if (sourceMode) { save(); sourceMode = true; src.classList.remove('d-none'); area.classList.add('d-none'); }
        else { src.classList.add('d-none'); area.classList.remove('d-none'); load(); }
        b.classList.toggle('active', sourceMode);
        return;
      }
      if (sourceMode) return;
      area.focus();
      if (cmd === 'link') {
        var url = window.prompt(b.getAttribute('data-prompt') || I18N.link_prompt || 'URL', 'https://');
        if (url && /^(https?:\/\/|mailto:|tel:)/i.test(url)) document.execCommand('createLink', false, url);
      } else if (cmd === 'h3') {
        document.execCommand('formatBlock', false, 'h3');
      } else {
        document.execCommand(cmd, false, null);
      }
      save();
    });
    var form = wrap.closest('form');
    if (form) form.addEventListener('submit', function () { if (!sourceMode) save(); });
  });

  /* ---------------- Gallery ---------------- */
  document.querySelectorAll('[data-gallery]').forEach(function (box) {
    var grid = box.querySelector('[data-mg-grid]');
    var input = box.querySelector('[data-mg-input]');
    var fresh = box.querySelector('[data-mg-new]');
    if (window.Sortable) {
      Sortable.create(grid, { animation: 150, draggable: '.mg-item', handle: '.mg-item', filter: '.mg-remove', preventOnFilter: false });
    }
    box.addEventListener('change', function (e) {
      if (e.target.matches('[data-mg-remove]')) e.target.closest('.mg-item').classList.toggle('removed', e.target.checked);
    });
    if (input) input.addEventListener('change', function () {
      fresh.innerHTML = '';
      Array.prototype.forEach.call(input.files || [], function (f) {
        if (!/^image\//.test(f.type)) return;
        var d = document.createElement('div');
        d.className = 'mg-item new';
        d.innerHTML = '<img alt=""><span class="mg-badge">' + esc(I18N.new_image || 'New') + '</span>';
        d.querySelector('img').src = URL.createObjectURL(f);
        fresh.appendChild(d);
      });
    });
  });

  /* ---------------- Single image preview with remove ---------------- */
  document.querySelectorAll('[data-remove-image]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var box = cb.closest('[data-image-field]');
      if (box) box.classList.toggle('removed', cb.checked);
    });
  });

  /* ---------------- Drag-to-sort rows ---------------- */
  document.querySelectorAll('[data-sortable-rows]').forEach(function (tbody) {
    if (!window.Sortable) return;
    var url = tbody.getAttribute('data-url');
    Sortable.create(tbody, {
      animation: 150, handle: '.drag-handle', draggable: 'tr[data-id]',
      onEnd: function (evt) {
        if (evt.oldIndex === evt.newIndex) return;
        var ids = Array.prototype.map.call(tbody.querySelectorAll('tr[data-id]'), function (tr) { return parseInt(tr.getAttribute('data-id'), 10); });
        fetch(url, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({ ids: ids })
        }).then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
          .then(function () { toast(I18N.saved_order || 'Saved', 'success'); })
          .catch(function () { toast(I18N.order_failed || 'Error', 'error'); });
      }
    });
  });

  /* ---------------- Places map (admin overview) ---------------- */
  document.querySelectorAll('[data-places-map]').forEach(function (box) {
    if (!window.L) return;
    var places = json(box, 'data-places', []);
    var center = json(box, 'data-center', { lat: 24.35, lng: 56.72, zoom: 11 });
    var map = L.map(box.querySelector('[data-pm-map]'), { scrollWheelZoom: true }).setView([center.lat, center.lng], center.zoom || 11);
    L.tileLayer(OSM, { maxZoom: 19, attribution: OSM_ATTR }).addTo(map);
    var layers = {};
    var bounds = [];
    places.forEach(function (p) {
      var key = String(p.category_id || 0);
      layers[key] = layers[key] || L.layerGroup().addTo(map);
      var m = L.marker([p.lat, p.lng], { icon: pinIcon(p.color, p.icon), opacity: p.status === 'published' ? 1 : 0.55 });
      var html = '<div class="pm-pop">' + (p.thumb ? '<img src="' + esc(p.thumb) + '" alt="">' : '')
        + '<strong>' + esc(p.name) + '</strong>'
        + (p.category ? '<div class="small text-muted">' + esc(p.category) + '</div>' : '')
        + (p.status !== 'published' ? '<span class="pill pill-neutral pill-sm">' + esc(I18N.draft || 'Draft') + '</span>' : '')
        + '<div><a class="btn btn-sm btn-primary mt-2" href="' + esc(p.edit) + '">' + esc(I18N.edit || 'Edit') + '</a></div></div>';
      m.bindPopup(html, { minWidth: 180 });
      m.bindTooltip(esc(p.name), { direction: 'top', offset: [0, -28] });
      m.addTo(layers[key]);
      bounds.push([p.lat, p.lng]);
    });
    if (bounds.length > 1) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
    else if (bounds.length === 1) map.setView(bounds[0], 14);
    box.querySelectorAll('[data-pm-toggle]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var layer = layers[cb.getAttribute('data-pm-toggle')];
        if (!layer) return;
        if (cb.checked) layer.addTo(map); else map.removeLayer(layer);
      });
    });
    box.querySelectorAll('[data-pm-focus]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var p = places.filter(function (x) { return String(x.id) === a.getAttribute('data-pm-focus'); })[0];
        if (p) map.setView([p.lat, p.lng], 15);
      });
    });
  });

  /* ---------------- Item location mode ---------------- */
  document.querySelectorAll('[data-location-mode]').forEach(function (group) {
    var form = group.closest('form') || document;
    function apply() {
      var checked = group.querySelector('input:checked');
      var mode = checked ? checked.value : 'none';
      form.querySelectorAll('[data-mode-pane]').forEach(function (pane) {
        var on = pane.getAttribute('data-mode-pane') === mode;
        pane.classList.toggle('d-none', !on);
        pane.querySelectorAll('input, select').forEach(function (el) { el.disabled = !on; });
        if (on) pane.querySelectorAll('[data-location-picker]').forEach(function (lp) { lp.dispatchEvent(new CustomEvent('lp:refresh')); });
      });
    }
    group.addEventListener('change', apply);
    apply();
  });

  /* ---------------- Place form: category → fallback icon / pin colour ---------------- */
  document.querySelectorAll('[data-category-select]').forEach(function (sel) {
    var form = sel.closest('form');
    function apply() {
      var o = sel.options[sel.selectedIndex];
      var detail = { icon: o ? o.getAttribute('data-icon') : null, color: o ? o.getAttribute('data-color') : null };
      form.querySelectorAll('[data-icon-picker]').forEach(function (b) { b.dispatchEvent(new CustomEvent('ip:fallback', { detail: detail })); });
      var iconInput = form.querySelector('[data-ip-input]');
      var lpDetail = { color: detail.color, icon: (iconInput && iconInput.value) || detail.icon };
      form.querySelectorAll('[data-location-picker]').forEach(function (b) { b.dispatchEvent(new CustomEvent('lp:style', { detail: lpDetail })); });
      form.querySelectorAll('[data-preview-card]').forEach(function (c) { if (detail.color) c.style.setProperty('--pc', detail.color); });
    }
    sel.addEventListener('change', apply);
    var iconInput = form.querySelector('[data-ip-input]');
    if (iconInput) iconInput.addEventListener('input', apply);
    apply();
  });

  /* ---------------- Live preview card (place / item) ---------------- */
  document.querySelectorAll('[data-preview-card]').forEach(function (card) {
    var form = card.closest('form');
    var map = json(card, 'data-preview-map', {});
    function text(id) { var el = form.querySelector('#' + id); return el ? String(el.value || '').trim() : ''; }
    function update() {
      Object.keys(map).forEach(function (slot) {
        var target = card.querySelector('[data-pv="' + slot + '"]');
        if (!target) return;
        var v = '';
        map[slot].some(function (id) { v = text(id); return v !== ''; });
        target.textContent = v;
        target.classList.toggle('d-none', v === '');
      });
      var icon = card.querySelector('[data-pv-icon]');
      var ip = form.querySelector('[data-icon-picker]');
      if (icon && ip) icon.textContent = ip.querySelector('[data-ip-preview] .msr').textContent;
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.addEventListener('upload:preview', function (e) {
      var img = card.querySelector('[data-pv-img]');
      if (img && e.detail && e.detail.url) { img.src = e.detail.url; img.classList.remove('d-none'); card.classList.add('has-img'); }
    });
    update();
  });

  // Bootstrap tabs: refresh maps that were hidden.
  document.addEventListener('shown.bs.tab', function () {
    document.querySelectorAll('[data-location-picker]').forEach(function (lp) { lp.dispatchEvent(new CustomEvent('lp:refresh')); });
  });
})();
