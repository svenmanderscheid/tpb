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

// ---- Live-Vorschau (SVG-Mockup) -------------------------------------------
// PLATZHALTER-Farbzuordnung, bis echte Hex-Codes je Variante gepflegt sind
// (color_code wird bevorzugt genutzt, wenn es ein Hex-Wert ist). OFFENE-FRAGEN.md.
const NAME_HEX = [
  ['weiß', '#ffffff'], ['weiss', '#ffffff'], ['white', '#ffffff'],
  ['schwarz', '#1b1b1f'], ['black', '#1b1b1f'],
  ['navy', '#1f2a44'], ['marine', '#1f2a44'],
  ['royal', '#2f6fd8'], ['blau', '#2f6fd8'], ['blue', '#2f6fd8'],
  ['rot', '#d23b34'], ['red', '#d23b34'], ['bordeaux', '#7a1f2b'],
  ['grün', '#2f9e44'], ['gruen', '#2f9e44'], ['green', '#2f9e44'],
  ['gelb', '#f4c542'], ['yellow', '#f4c542'],
  ['orange', '#ff7a1a'],
  ['pink', '#e85aad'], ['rosa', '#f2a0c4'],
  ['lila', '#7d4bd8'], ['purple', '#7d4bd8'], ['violett', '#7d4bd8'],
  ['türkis', '#1bb5a0'], ['tuerkis', '#1bb5a0'], ['petrol', '#1b6b78'],
  ['grau', '#9aa0a6'], ['grey', '#9aa0a6'], ['gray', '#9aa0a6'], ['anthrazit', '#3a3d42'],
  ['braun', '#7a5230'], ['brown', '#7a5230'], ['beige', '#e8dcc0'], ['sand', '#d9c9a3'],
];

function resolveHex(v) {
  if (!v) return '#d9d9d6';
  const code = (v.color_code || '').trim();
  if (/^#?[0-9a-fA-F]{6}$/.test(code)) return code[0] === '#' ? code : ('#' + code);
  const name = (v.color_name || '').toLowerCase();
  for (const [k, hex] of NAME_HEX) if (name.includes(k)) return hex;
  return '#d9d9d6';
}

function luminance(hex) {
  const h = hex.replace('#', '');
  const r = parseInt(h.slice(0, 2), 16) / 255, g = parseInt(h.slice(2, 4), 16) / 255, b = parseInt(h.slice(4, 6), 16) / 255;
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function selectedVariant() {
  const sku = Object.keys(state.sizes).find((s) => state.sizes[s] > 0);
  return (sku ? data.variants.find((x) => x.sku === sku) : data.variants[0]) || null;
}

function sideOf(code) {
  const p = (data.placements || []).find((x) => x.code === code);
  return p ? (p.side || 'front') : 'front';
}

const PRINT = { x: 72, y: 80, w: 56, h: 72 };

function garmentPath(isHoodie) {
  // Front-Silhouette in viewBox 0 0 200 224.
  const body = 'M70 34 L54 28 L28 48 L44 70 L60 60 L60 196 A6 6 0 0 0 66 202 L134 202 A6 6 0 0 0 140 196 L140 60 L156 70 L172 48 L146 28 L130 34 C122 50 78 50 70 34 Z';
  return body;
}

function renderPreview() {
  const stage = document.getElementById('cfg-preview');
  if (!stage) return;
  const v = selectedVariant();
  const fill = resolveHex(v);
  const isHoodie = ((product.name || '') + ' ' + (product.type || '')).toLowerCase().includes('hoodie');
  const dark = luminance(fill) < 0.5;
  const outline = fill.toLowerCase() === '#ffffff' ? '#d0cfca' : 'rgba(0,0,0,.18)';
  const ink = dark ? 'rgba(255,255,255,.85)' : 'rgba(0,0,0,.5)';
  const seam = dark ? 'rgba(255,255,255,.22)' : 'rgba(0,0,0,.12)';

  const parts = [];
  parts.push(`<svg viewBox="0 0 200 224" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Vorschau">`);
  parts.push(`<path d="${garmentPath(isHoodie)}" fill="${fill}" stroke="${outline}" stroke-width="2" stroke-linejoin="round"/>`);
  // Kragen
  parts.push(`<path d="M78 40 Q100 58 122 40" fill="none" stroke="${seam}" stroke-width="2"/>`);
  if (isHoodie) {
    parts.push(`<path d="M80 38 Q100 66 120 38 L118 30 Q100 50 82 30 Z" fill="${fill}" stroke="${outline}" stroke-width="2" stroke-linejoin="round"/>`);
    parts.push(`<line x1="94" y1="52" x2="92" y2="78" stroke="${seam}" stroke-width="2"/><line x1="106" y1="52" x2="108" y2="78" stroke="${seam}" stroke-width="2"/>`);
    parts.push(`<path d="M72 150 L128 150 L124 176 L76 176 Z" fill="none" stroke="${seam}" stroke-width="2"/>`);
  }
  // Druckfläche (dezent gestrichelt)
  parts.push(`<rect x="${PRINT.x}" y="${PRINT.y}" width="${PRINT.w}" height="${PRINT.h}" rx="3" fill="none" stroke="${ink}" stroke-width="1" stroke-dasharray="3 3" opacity="0.5"/>`);

  // Motive (Front)
  const frontLayers = (state.layers || []).filter((l) => l.placement_code && sideOf(l.placement_code) === 'front');
  const scale = PRINT.w / 320;
  frontLayers.forEach((l) => {
    let w = l.width_mm ? Math.max(10, Math.min(PRINT.w, parseFloat(l.width_mm) * scale)) : PRINT.w * 0.62;
    let h = l.height_mm ? Math.max(10, Math.min(PRINT.h, parseFloat(l.height_mm) * scale)) : PRINT.h * 0.5;
    let cx = PRINT.x + PRINT.w / 2 + (l.offset_x_mm ? parseFloat(l.offset_x_mm) * scale : 0);
    let cy = PRINT.y + PRINT.h / 2 + (l.offset_y_mm ? parseFloat(l.offset_y_mm) * scale : 0);
    let x = Math.max(PRINT.x, Math.min(PRINT.x + PRINT.w - w, cx - w / 2));
    let y = Math.max(PRINT.y, Math.min(PRINT.y + PRINT.h - h, cy - h / 2));
    const has = !!l.asset_public_id;
    parts.push(`<rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${w.toFixed(1)}" height="${h.toFixed(1)}" rx="2" fill="${has ? 'rgba(255,90,60,.16)' : 'none'}" stroke="#ff5a3c" stroke-width="1.5" stroke-dasharray="${has ? '0' : '4 3'}"/>`);
    parts.push(`<text x="${(x + w / 2).toFixed(1)}" y="${(y + h / 2 + 3).toFixed(1)}" text-anchor="middle" font-family="Segoe UI, sans-serif" font-size="8" fill="#d8340f">${has ? '✓ Motiv' : l.placement_code}</text>`);
  });
  parts.push(`</svg>`);
  stage.innerHTML = parts.join('');

  // Meta-Chips: Farbe + Rückseiten-Hinweis
  const meta = document.getElementById('cfg-preview-meta');
  if (meta) {
    const chips = [];
    if (v && (v.color_name || v.color_code)) {
      chips.push(`<span class="swatch"><svg width="14" height="14" aria-hidden="true"><rect width="14" height="14" rx="3" fill="${fill}" stroke="rgba(0,0,0,.15)"/></svg>${(v.color_name || fill)}</span>`);
    }
    const back = (state.layers || []).filter((l) => l.placement_code && sideOf(l.placement_code) !== 'front').length;
    if (back > 0) chips.push(`<span class="swatch">+${back} auf Rückseite/Ärmel</span>`);
    meta.innerHTML = chips.join('');
  }
}

// ---- Formularaufbau --------------------------------------------------------

let layersBox, unitsBox;

function renderForm() {
  const root = document.getElementById('cfg-form');
  root.replaceChildren();

  // Größenmatrix
  const sizeCard = el('div', { class: 'card' }, [el('h2', { text: 'Größen & Mengen' })]);
  const table = el('table');
  table.appendChild(el('thead', {}, el('tr', {}, [th('Variante'), th('Farbe'), th('Größe'), th('Lager'), th('Menge')])));
  const tbody = el('tbody');
  for (const v of data.variants) {
    const avail = typeof v.available === 'number' ? v.available : null;
    const stockCell = avail === null
      ? td('–')
      : el('td', { class: avail <= 0 ? 'stock-low' : (avail <= 5 ? 'stock-low' : 'muted') },
          [avail <= 0 ? 'ausverkauft' : ('noch ' + avail)]);
    const warn = el('div', { class: 'stock-low' });
    const qtyInput = el('input', { type: 'number', min: '0', inputmode: 'numeric', value: state.sizes[v.sku] || '' });
    qtyInput.addEventListener('input', () => {
      const q = parseInt(qtyInput.value, 10);
      if (q > 0) state.sizes[v.sku] = q; else delete state.sizes[v.sku];
      if (avail !== null && q > avail) {
        qtyInput.classList.add('over');
        warn.textContent = 'Nur noch ' + Math.max(0, avail) + ' verfügbar.';
      } else {
        qtyInput.classList.remove('over');
        warn.textContent = (avail !== null && avail > 0 && avail <= 5 && q > 0) ? 'Geringer Bestand.' : '';
      }
      schedulePrice();
    });
    tbody.appendChild(el('tr', {}, [
      td(v.sku), td(v.color_name || '–'), td(v.size || '–'), stockCell,
      el('td', { class: 'num' }, [qtyInput, warn]),
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
  renderPreview();            // Vorschau reagiert sofort
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
    linkBox.replaceChildren(
      el('p', { class: 'ok-text' }, ['Gespeichert. Dein Link: ', el('a', { href: url, text: url })]),
      el('p', { class: 'mt' }, [el('a', { class: 'btn', href: '/checkout?config=' + encodeURIComponent(d.public_id), text: 'Jetzt kaufen' })]),
      el('p', { class: 'mt' }, [el('a', { class: 'btn secondary', href: '/anfrage?config=' + encodeURIComponent(d.public_id), text: 'Angebot anfragen (Verein/Großbestellung)' })])
    );
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
  renderPreview();
  price();
})();
