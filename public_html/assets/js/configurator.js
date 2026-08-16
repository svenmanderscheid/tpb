// Öffentlicher Konfigurator (Vanilla JS, CSP-konform, keine Inline-Skripte).
// Liest den Datenblock #cfg-data, baut das Formular, ruft den serverseitigen
// Live-Preis (/api/price) und speichert Entwürfe (/api/config/save).

const data = JSON.parse(document.getElementById('cfg-data').textContent || '{}');
const product = data.product;
const isStandard = product.type === 'standard';

const state = {
  express: false,
  guest: { email: '', name: '', note: '' },
  technique: '',
  sizes: {},              // sku -> qty
  layers: [],             // {placement_code, asset_public_id, asset_name, width_mm, height_mm, offset_x_mm, offset_y_mm}
  units: [],              // {variant_sku, name, number}
  draft: new URLSearchParams(location.search).get('draft'),
};

const eur = (c) => (c / 100).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });

function el(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (k === 'class') node.className = v;
    else if (k === 'text') node.textContent = v;
    else if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.slice(2), v);
    else node.setAttribute(k, v);
  }
  for (const c of [].concat(children)) node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
  return node;
}

function field(labelText, control) {
  return el('div', { class: 'field' }, [el('label', { text: labelText }), control]);
}

// ---- Formularaufbau --------------------------------------------------------

let layersBox, unitsBox;

function renderForm() {
  const root = document.getElementById('cfg-form');
  root.replaceChildren();

  // Größenmatrix
  const sizeCard = el('div', { class: 'card' }, [el('h2', { text: 'Größen & Mengen' })]);
  const table = el('table');
  table.appendChild(el('thead', {}, el('tr', {}, [th('Variante'), th('Farbe'), th('Größe'), th('Menge')])));
  const tbody = el('tbody');
  for (const v of data.variants) {
    const qtyInput = el('input', { type: 'number', min: '0', inputmode: 'numeric', value: state.sizes[v.sku] || '' });
    qtyInput.addEventListener('input', () => {
      const q = parseInt(qtyInput.value, 10);
      if (q > 0) state.sizes[v.sku] = q; else delete state.sizes[v.sku];
      schedulePrice();
    });
    tbody.appendChild(el('tr', {}, [
      td(v.sku), td(v.color_name || '–'), td(v.size || '–'),
      el('td', { class: 'num' }, [qtyInput]),
    ]));
  }
  table.appendChild(tbody);
  sizeCard.appendChild(table);
  root.appendChild(sizeCard);

  if (!isStandard) {
    // Technik
    if (data.techniques.length) {
      const sel = el('select', {}, [el('option', { value: '', text: '– keine –' })]);
      for (const t of data.techniques) {
        const o = el('option', { value: t.code, text: t.name + ' (' + t.code + ')' });
        if (state.technique === t.code) o.selected = true;
        sel.appendChild(o);
      }
      sel.addEventListener('change', () => { state.technique = sel.value; schedulePrice(); });
      root.appendChild(el('div', { class: 'card' }, [el('h2', { text: 'Veredelung' }), field('Technik', sel)]));
    }

    // Designs (Layer)
    const designCard = el('div', { class: 'card' }, [el('h2', { text: 'Designs' })]);
    layersBox = el('div');
    designCard.appendChild(layersBox);
    designCard.appendChild(el('button', {
      class: 'btn secondary', type: 'button', text: '+ Design hinzufügen',
      onclick: () => { state.layers.push({ placement_code: data.placements[0] ? data.placements[0].code : '', layer_type: 'logo' }); renderLayers(); schedulePrice(); },
    }));
    root.appendChild(designCard);
    renderLayers();

    // Personalisierung
    const persoCard = el('div', { class: 'card' }, [el('h2', { text: 'Personalisierung (optional)' })]);
    unitsBox = el('div');
    persoCard.appendChild(unitsBox);
    persoCard.appendChild(el('button', {
      class: 'btn secondary', type: 'button', text: '+ Person hinzufügen',
      onclick: () => { state.units.push({ variant_sku: data.variants[0] ? data.variants[0].sku : '', name: '', number: '' }); renderUnits(); schedulePrice(); },
    }));
    root.appendChild(persoCard);
    renderUnits();
  }

  // Optionen
  const exp = el('input', { type: 'checkbox' });
  exp.checked = state.express;
  exp.addEventListener('change', () => { state.express = exp.checked; schedulePrice(); });
  root.appendChild(el('div', { class: 'card' }, [el('h2', { text: 'Optionen' }),
    el('label', { class: 'check' }, [exp, ' Express']),
  ]));

  // Kontakt (für Entwurf)
  const email = textInput(state.guest.email, (v) => state.guest.email = v);
  const name = textInput(state.guest.name, (v) => state.guest.name = v);
  const note = textInput(state.guest.note, (v) => state.guest.note = v);
  root.appendChild(el('div', { class: 'card' }, [el('h2', { text: 'Kontakt (optional, für Entwurf)' }),
    field('E-Mail', email), field('Name', name), field('Notiz', note),
  ]));
}

function renderLayers() {
  layersBox.replaceChildren();
  if (!state.layers.length) { layersBox.appendChild(el('p', { class: 'muted', text: 'Noch kein Design.' })); return; }
  state.layers.forEach((layer, i) => {
    const placeSel = el('select', {});
    for (const p of data.placements) {
      const o = el('option', { value: p.code, text: p.name + ' (' + p.side + ')' });
      if (layer.placement_code === p.code) o.selected = true;
      placeSel.appendChild(o);
    }
    placeSel.addEventListener('change', () => { layer.placement_code = placeSel.value; schedulePrice(); });

    const file = el('input', { type: 'file', accept: '.svg,.pdf,.png,.jpg,.jpeg' });
    const status = el('span', { class: 'muted', text: layer.asset_name ? ('✓ ' + layer.asset_name) : 'keine Datei' });
    file.addEventListener('change', () => {
      if (file.files && file.files[0]) uploadLayer(file.files[0], layer, status);
    });

    const row = el('div', { class: 'layer card-2' }, [
      el('div', { class: 'field-row' }, [
        field('Position', placeSel),
        field('Logo-Datei', file),
      ]),
      el('div', { class: 'field-row' }, [
        field('Breite (mm)', mmInput(layer, 'width_mm')),
        field('Höhe (mm)', mmInput(layer, 'height_mm')),
        field('X (mm)', mmInput(layer, 'offset_x_mm')),
        field('Y (mm)', mmInput(layer, 'offset_y_mm')),
      ]),
      el('div', {}, [status, ' ', el('button', {
        class: 'link-danger', type: 'button', text: 'entfernen',
        onclick: () => { state.layers.splice(i, 1); renderLayers(); schedulePrice(); },
      })]),
    ]);
    layersBox.appendChild(row);
  });
}

function renderUnits() {
  unitsBox.replaceChildren();
  if (!state.units.length) { unitsBox.appendChild(el('p', { class: 'muted', text: 'Keine Personalisierung.' })); return; }
  state.units.forEach((u, i) => {
    const vSel = el('select', {});
    for (const v of data.variants) {
      const o = el('option', { value: v.sku, text: v.sku });
      if (u.variant_sku === v.sku) o.selected = true;
      vSel.appendChild(o);
    }
    vSel.addEventListener('change', () => { u.variant_sku = vSel.value; schedulePrice(); });
    unitsBox.appendChild(el('div', { class: 'field-row' }, [
      field('Variante', vSel),
      field('Name', textInput(u.name, (v) => { u.name = v; schedulePrice(); })),
      field('Nummer', textInput(u.number, (v) => { u.number = v; schedulePrice(); })),
      field(' ', el('button', { class: 'link-danger', type: 'button', text: 'entfernen', onclick: () => { state.units.splice(i, 1); renderUnits(); schedulePrice(); } })),
    ]));
  });
}

function th(t) { return el('th', { text: t }); }
function td(t) { return el('td', { text: t }); }
function textInput(val, onInput) {
  const inp = el('input', { type: 'text', value: val || '' });
  inp.addEventListener('input', () => onInput(inp.value));
  return inp;
}
function mmInput(layer, key) {
  const inp = el('input', { type: 'text', inputmode: 'decimal', value: layer[key] || '' });
  inp.addEventListener('input', () => { layer[key] = inp.value; schedulePrice(); });
  return inp;
}

// ---- Nutzlast + Preis ------------------------------------------------------

function buildItem() {
  const sizes = Object.entries(state.sizes).filter(([, q]) => q > 0).map(([variant_sku, qty]) => ({ variant_sku, qty }));
  const item = { product: product.public_id, type: isStandard ? 'standard' : 'configured', sizes };
  if (!isStandard) {
    if (state.technique) item.technique_code = state.technique;
    item.layers = state.layers.filter((l) => l.placement_code).map((l) => ({
      placement_code: l.placement_code, layer_type: 'logo', asset_public_id: l.asset_public_id || null,
      width_mm: l.width_mm || null, height_mm: l.height_mm || null, offset_x_mm: l.offset_x_mm || null, offset_y_mm: l.offset_y_mm || null,
    }));
    item.units = state.units.filter((u) => u.variant_sku && (u.name || u.number)).map((u) => ({ variant_sku: u.variant_sku, name: u.name || null, number: u.number || null }));
  }
  return item;
}

function payload(includeGuest) {
  const item = buildItem();
  const p = { express: state.express, items: [item] };
  if (includeGuest) {
    p.guest_email = state.guest.email; p.guest_name = state.guest.name; p.note = state.guest.note;
    if (state.draft) p.public_id = state.draft;
  }
  return p;
}

let priceTimer = null;
function schedulePrice() {
  if (priceTimer) clearTimeout(priceTimer);
  priceTimer = setTimeout(price, 400);
}

async function price() {
  const box = document.getElementById('cfg-price');
  const hasQty = Object.values(state.sizes).some((q) => q > 0);
  if (!hasQty) { box.replaceChildren(el('p', { class: 'muted', text: 'Bitte Mengen wählen.' })); return; }
  try {
    const res = await fetch('/api/price', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload(false)) });
    const d = await res.json();
    if (!d.ok) { box.replaceChildren(el('p', { class: 'neg', text: d.error || 'Preis nicht verfügbar.' })); return; }
    const kids = [el('div', { class: 'price-total', text: eur(d.total_cents) })];
    if (d.below_min_order) kids.push(el('p', { class: 'neg', text: 'Unter Mindestbestellwert – Absenden nicht möglich.' }));
    box.replaceChildren(...kids);
  } catch {
    box.replaceChildren(el('p', { class: 'neg', text: 'Netzwerkfehler.' }));
  }
}

async function uploadLayer(file, layer, status) {
  status.textContent = 'lädt …';
  const fd = new FormData();
  fd.append('file', file);
  try {
    const res = await fetch('/api/config/upload', { method: 'POST', body: fd });
    const d = await res.json();
    if (!d.ok) { status.textContent = '✗ ' + (d.error || 'Upload fehlgeschlagen'); return; }
    layer.asset_public_id = d.asset_public_id;
    layer.asset_name = d.original_name;
    status.textContent = '✓ ' + d.original_name;
    schedulePrice();
  } catch {
    status.textContent = '✗ Netzwerkfehler';
  }
}

async function save() {
  const linkBox = document.getElementById('cfg-link');
  linkBox.replaceChildren(el('p', { class: 'muted', text: 'Speichere …' }));
  try {
    const res = await fetch('/api/config/save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload(true)) });
    const d = await res.json();
    if (!d.ok) { linkBox.replaceChildren(el('p', { class: 'neg', text: d.error || 'Speichern fehlgeschlagen.' })); return; }
    state.draft = d.public_id;
    const url = '/konfigurator/' + product.public_id + '?draft=' + d.public_id;
    history.replaceState(null, '', url);
    linkBox.replaceChildren(el('p', { class: 'ok-text' }, ['Gespeichert. Dein Link: ', el('a', { href: url, text: url })]));
  } catch {
    linkBox.replaceChildren(el('p', { class: 'neg', text: 'Netzwerkfehler.' }));
  }
}

async function loadDraft(id) {
  try {
    const res = await fetch('/api/config/' + encodeURIComponent(id));
    const d = await res.json();
    if (!d.ok) return;
    const c = d.config;
    state.express = !!c.express;
    state.guest = { email: c.guest_email || '', name: c.guest_name || '', note: c.note || '' };
    const item = (c.items || []).find((it) => it.product === product.public_id);
    if (item) {
      state.sizes = {};
      for (const s of item.sizes || []) state.sizes[s.variant_sku] = s.qty;
      state.technique = item.technique_code || '';
      state.layers = (item.layers || []).map((l) => ({
        placement_code: l.placement_code, layer_type: 'logo', asset_public_id: l.asset_public_id,
        asset_name: l.asset_public_id ? 'gespeichertes Logo' : null,
        width_mm: l.width_mm, height_mm: l.height_mm, offset_x_mm: l.offset_x_mm, offset_y_mm: l.offset_y_mm,
      }));
      state.units = (item.units || []).map((u) => ({ variant_sku: u.variant_sku, name: u.name || '', number: u.number || '' }));
    }
  } catch { /* ignore */ }
}

// ---- Init ------------------------------------------------------------------

document.getElementById('cfg-save').addEventListener('click', save);

(async function init() {
  if (state.draft) await loadDraft(state.draft);
  renderForm();
  price();
})();
