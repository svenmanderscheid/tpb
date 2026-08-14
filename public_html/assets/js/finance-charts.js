// Finanz-Diagramme als Inline-SVG (Vanilla JS, keine Bibliothek/CDN, CSP-konform).
// Liest den JSON-Datenblock #finance-data und rendert in vorbereitete Container.
// Es werden ausschließlich SVG-Präsentationsattribute genutzt (keine inline styles).

const NS = 'http://www.w3.org/2000/svg';
const MONTHS = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
const COLORS = { income: '#3fb950', expense: '#e5534b', bar: '#4c8bf5', axis: '#2a2f3a', text: '#9aa2af' };

function readData() {
  const node = document.getElementById('finance-data');
  if (!node) return null;
  try { return JSON.parse(node.textContent || '{}'); } catch { return null; }
}

function el(name, attrs) {
  const node = document.createElementNS(NS, name);
  for (const [k, v] of Object.entries(attrs || {})) node.setAttribute(k, String(v));
  return node;
}

function svgRoot(w, h) {
  const svg = el('svg', { viewBox: `0 0 ${w} ${h}`, role: 'img', preserveAspectRatio: 'xMidYMid meet' });
  return svg;
}

function text(x, y, str, opts = {}) {
  const t = el('text', {
    x, y,
    fill: opts.fill || COLORS.text,
    'font-size': opts.size || 11,
    'font-family': 'system-ui, sans-serif',
    'text-anchor': opts.anchor || 'start',
  });
  t.textContent = str;
  return t;
}

const eur = (cents) => (cents / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const eurShort = (cents) => {
  const v = cents / 100;
  if (Math.abs(v) >= 1000) return Math.round(v / 100) / 10 + 'k';
  return String(Math.round(v));
};

function setEmpty(container, msg) {
  container.replaceChildren();
  const p = document.createElement('p');
  p.className = 'empty';
  p.textContent = msg;
  container.appendChild(p);
}

function renderMonthly(container, months) {
  const maxVal = Math.max(1, ...months.map((m) => Math.max(m.income, m.expense)));
  if (maxVal <= 1 && months.every((m) => m.income === 0 && m.expense === 0)) {
    setEmpty(container, 'Keine Buchungen in diesem Jahr.');
    return;
  }
  const W = 720, H = 280, padL = 44, padR = 12, padT = 12, padB = 28;
  const plotW = W - padL - padR, plotH = H - padT - padB;
  const svg = svgRoot(W, H);

  // Y-Gitter (4 Linien)
  for (let i = 0; i <= 4; i++) {
    const y = padT + (plotH * i) / 4;
    svg.appendChild(el('line', { x1: padL, y1: y, x2: W - padR, y2: y, stroke: COLORS.axis, 'stroke-width': 1 }));
    const val = maxVal * (1 - i / 4);
    svg.appendChild(text(padL - 6, y + 3, eurShort(val), { anchor: 'end' }));
  }

  const groupW = plotW / 12;
  const barW = Math.max(3, (groupW - 6) / 2);
  months.forEach((m, idx) => {
    const gx = padL + groupW * idx + 3;
    const hi = (m.income / maxVal) * plotH;
    const he = (m.expense / maxVal) * plotH;
    svg.appendChild(el('rect', { x: gx, y: padT + plotH - hi, width: barW, height: hi, fill: COLORS.income, rx: 1 }));
    svg.appendChild(el('rect', { x: gx + barW + 1, y: padT + plotH - he, width: barW, height: he, fill: COLORS.expense, rx: 1 }));
    svg.appendChild(text(gx + barW, H - 10, MONTHS[idx], { anchor: 'middle' }));
  });

  container.replaceChildren(svg);
}

function renderCategories(container, cats) {
  if (!cats || cats.length === 0) {
    setEmpty(container, 'Keine Ausgaben erfasst.');
    return;
  }
  const top = cats.slice(0, 8);
  const maxVal = Math.max(1, ...top.map((c) => c.total));
  const rowH = 30, padL = 8, padR = 8, labelW = 110, barMax = 320;
  const W = padL + labelW + barMax + 90 + padR;
  const H = top.length * rowH + 12;
  const svg = svgRoot(W, H);

  top.forEach((c, i) => {
    const y = 6 + i * rowH;
    const w = Math.max(2, (c.total / maxVal) * barMax);
    svg.appendChild(text(padL, y + rowH / 2 + 3, c.category, { fill: '#e6e8ec' }));
    svg.appendChild(el('rect', { x: padL + labelW, y: y + 4, width: w, height: rowH - 12, fill: COLORS.bar, rx: 2 }));
    svg.appendChild(text(padL + labelW + w + 6, y + rowH / 2 + 3, eur(c.total)));
  });

  container.replaceChildren(svg);
}

function init() {
  const data = readData();
  if (!data) return;
  const monthly = document.getElementById('chart-monthly');
  const cats = document.getElementById('chart-expense-cat');
  if (monthly) renderMonthly(monthly, data.months || []);
  if (cats) renderCategories(cats, data.expense_by_category || []);
}

init();
