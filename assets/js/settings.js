// LimeColumns 設定UI（ビルド不要のESモジュール / 依存なし）
const boot = JSON.parse(document.getElementById('lime-columns-boot').textContent);
const t = (s) => boot.i18n[s] ?? s;
const root = document.getElementById('lime-columns-app');

// 通信は WordPress 標準の admin-ajax.php（wp_ajax_*）に POST する。
// REST API を制限する環境でも動き、パス・メソッド・URL 形式の差に左右されない。
// 配列は max_input_vars に左右されないよう、JSON 文字列(payload)にして送る。
async function api(action, key, payload) {
  const form = new FormData();
  form.set('action', action);
  form.set('_ajax_nonce', boot.nonce);
  if (key != null) form.set('key', key);
  if (payload != null) form.set('payload', JSON.stringify(payload));
  const res = await fetch(boot.ajax, { method: 'POST', credentials: 'same-origin', body: form });
  const text = await res.text();
  let json = null;
  try { json = JSON.parse(text); } catch (e) { /* JSON 以外の応答（サーバーのエラーページ等） */ }
  if (!res.ok || !json || json.success !== true) {
    throw new Error((json && json.data && json.data.message) || res.statusText || ('HTTP ' + res.status));
  }
  return json.data;
}

function h(tag, attrs = {}, ...children) {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
    else if (k === 'class') el.className = v;
    else if (v === true) el.setAttribute(k, '');
    else if (v !== false && v != null) el.setAttribute(k, v);
  }
  for (const c of children.flat()) el.append(c instanceof Node ? c : document.createTextNode(c ?? ''));
  return el;
}

const state = { screens: [], key: null, data: null, dirty: false, notice: '' };
const uid = () => 'c' + Math.random().toString(16).slice(2, 12);

async function init() {
  state.screens = await api('lime_columns_screens');
  const saved = new URLSearchParams(location.search).get('list');
  await select(saved && state.screens.some((s) => s.key === saved) ? saved : state.screens[0]?.key);
}

async function select(key) {
  if (state.dirty && !confirm(t('Discard unsaved changes?'))) return;
  state.key = key;
  state.data = await api('lime_columns_get', key);
  state.dirty = false;
  state.notice = '';
  render();
}

/** 画面右上に出る通知。成功は数秒で消え、エラーは手動で閉じるまで残る。 */
function toast(message, kind = 'success') {
  document.querySelector('.lc-toast')?.remove();
  const el = h('div', { class: 'lc-toast lc-toast-' + kind, role: kind === 'error' ? 'alert' : 'status' },
    h('span', { class: 'lc-toast-icon', 'aria-hidden': 'true' }, kind === 'error' ? '!' : '✓'),
    h('span', { class: 'lc-toast-msg' }, message),
    h('button', { type: 'button', class: 'lc-toast-close', 'aria-label': t('Close'), onclick: () => el.remove() }, '×'));
  document.body.append(el);
  if (kind !== 'error') setTimeout(() => { el.classList.add('is-leaving'); setTimeout(() => el.remove(), 300); }, 3000);
}

function typeOf(id) { return state.data.types.find((x) => x.id === id); }
function touch() { state.dirty = true; state.notice = ''; }

function render() {
  root.replaceChildren(
    h('div', { class: 'lc-layout' }, renderSidebar(), state.data ? renderMain() : ''),
    renderImport()
  );
}

function renderSidebar() {
  const groups = {};
  for (const s of state.screens) (groups[s.group] ??= []).push(s);
  // ACF 関連のグループは、常に最下部に置く（tail は言語に依存しない目印）。
  const isTail = (list) => list.every((s) => s.tail);
  return h('nav', { class: 'lc-side' },
    Object.entries(groups).sort(([, a], [, b]) => Number(isTail(a)) - Number(isTail(b))).map(([g, list]) => h('div', {},
      h('h3', {}, g),
      h('ul', {}, list.map((s) => h('li', { class: s.key === state.key ? 'is-active' : '' },
        h('a', { href: '#', onclick: (e) => { e.preventDefault(); select(s.key); } },
          s.label, s.configured ? h('span', { class: 'lc-dot', title: t('Configured') }) : ''))))
    )));
}

function renderMain() {
  const c = state.data.config;
  const names = c.columns.map((x) => x.name);
  const settings = c.settings || {};
  c.settings = settings;

  return h('section', { class: 'lc-main' },
    h('div', { class: 'lc-bar' },
      h('h2', {}, state.screens.find((s) => s.key === state.key)?.label),
      !state.data.saved ? h('span', { class: 'lc-badge' }, t('Not configured')) : '',
      h('select', { class: 'lc-status', onchange: (e) => { c.status = e.target.value; touch(); } },
        h('option', { value: 'active', selected: c.status === 'active' }, t('Active')),
        h('option', { value: 'inactive', selected: c.status === 'inactive' }, t('Inactive')))),

    h('ol', { class: 'lc-cols' }, c.columns.map((col, i) => renderColumn(col, i))),

    h('div', { class: 'lc-add' },
      (() => {
        const sel = h('select', {}, h('option', { value: '' }, t('Choose a column type') + '…'));
        const used = new Set(names);
        const byGroup = {};
        for (const ty of state.data.types) {
          if (ty.id === 'foreign') continue;
          if (ty.native_key && used.has(ty.native_key)) continue;
          (byGroup[ty.group] ??= []).push(h('option', { value: ty.id }, ty.label));
        }
        for (const [g, opts] of Object.entries(byGroup)) sel.append(h('optgroup', { label: g }, opts));
        if (state.data.acf.fields.length) sel.append(h('optgroup', { label: 'ACF' },
          state.data.acf.fields.map((f) => h('option', { value: 'acf:' + f.key }, `${f.group} › ${f.label}`))));
        const foreign = Object.entries(state.data.foreign).filter(([k]) => !used.has(k));
        if (foreign.length) sel.append(h('optgroup', { label: t('Other plugins') },
          foreign.map(([k, l]) => h('option', { value: 'foreign:' + k }, l))));
        return [sel, h('button', { class: 'button', type: 'button', onclick: () => addColumn(sel.value) }, t('Add column'))];
      })()),

    h('fieldset', { class: 'lc-settings' },
      h('label', {}, t('Default sort') + ' ',
        h('select', { onchange: (e) => { settings.default_orderby = e.target.value; touch(); } },
          h('option', { value: '' }, t('None')),
          c.columns.filter((x) => typeOf(x.type)?.caps.sort).map((x) =>
            h('option', { value: x.name, selected: settings.default_orderby === x.name }, strip(x.label) || x.name))),
        h('select', { onchange: (e) => { settings.default_order = e.target.value; touch(); } },
          h('option', { value: 'asc', selected: settings.default_order === 'asc' }, t('Ascending')),
          h('option', { value: 'desc', selected: settings.default_order !== 'asc' }, t('Descending')))),
      h('div', {}, h('strong', {}, t('Roles')), ' ',
        Object.entries(state.data.roles).map(([slug, name]) => h('label', { class: 'lc-inline' },
          h('input', { type: 'checkbox', checked: (settings.roles || []).includes(slug), onchange: (e) => {
            const set = new Set(settings.roles || []);
            e.target.checked ? set.add(slug) : set.delete(slug);
            settings.roles = [...set]; touch();
          } }), ' ' + name)))),

    h('p', { class: 'lc-actions' },
      h('button', { class: 'button button-primary', type: 'button', onclick: save }, t('Save')),
      state.data.saved ? h('button', { class: 'button', type: 'button', onclick: reset }, t('Reset')) : '')
  );
}

const strip = (s) => (s || '').replace(/<[^>]*>/g, '').trim();

function renderColumn(col, i) {
  const ty = typeOf(col.type);
  const cols = state.data.config.columns;
  const row = h('li', { class: 'lc-col' });
  // ドラッグを始められるのは「⠿」のつまみだけ。入力欄などではテキストを通常どおり選択できる。
  row.addEventListener('mousedown', (e) => {
    if (!e.target.closest('.lc-grip')) return;
    row.draggable = true;
    window.addEventListener('mouseup', () => { row.draggable = false; }, { once: true });
  });
  row.addEventListener('dragstart', (e) => { e.dataTransfer.setData('text/plain', String(i)); row.classList.add('is-drag'); });
  row.addEventListener('dragend', () => { row.classList.remove('is-drag'); row.draggable = false; });
  row.addEventListener('dragover', (e) => e.preventDefault());
  row.addEventListener('drop', (e) => {
    e.preventDefault();
    const from = Number(e.dataTransfer.getData('text/plain'));
    if (Number.isNaN(from) || from === i) return;
    cols.splice(i, 0, cols.splice(from, 1)[0]); touch(); render();
  });

  // 幅: スライダーと数値欄を同期する（単位が % のときは 0〜100、px のときは 0〜600）。
  const sliderMax = () => (col.width_unit === 'px' ? 600 : 100);
  const widthRange = h('input', { type: 'range', min: 0, step: 1, class: 'lc-width-range', 'aria-label': t('Width') });
  const widthNum = h('input', { type: 'number', min: 0, value: col.width, class: 'small-text' });
  const syncRange = () => { widthRange.max = String(sliderMax()); widthRange.value = String(Math.min(Number(col.width) || 0, sliderMax())); };
  widthRange.addEventListener('input', () => { col.width = widthRange.value === '0' ? '' : widthRange.value; widthNum.value = col.width; touch(); });
  widthNum.addEventListener('input', () => { col.width = widthNum.value; syncRange(); touch(); });
  const widthUnit = h('select', { onchange: (e) => { col.width_unit = e.target.value; syncRange(); touch(); } },
    ['%', 'px'].map((u) => h('option', { value: u, selected: col.width_unit === u }, u)));
  syncRange();

  const body = h('div', { class: 'lc-body', hidden: true },
    h('div', { class: 'lc-row' },
      h('label', {}, t('Label') + ' ', h('input', { type: 'text', value: col.label, class: 'regular-text', oninput: (e) => { col.label = e.target.value; touch(); } })),
      h('label', {}, t('Width') + ' ', widthRange, widthNum, widthUnit)),
    flag(col, 'sort', 'Sortable', ty), flag(col, 'search', 'Searchable', ty), flag(col, 'export', 'Exportable', ty),
    renderFields(col, ty));

  row.append(
    h('div', { class: 'lc-head' },
      h('span', { class: 'lc-grip', title: t('Drag to reorder') }, '⠿'),
      h('button', { type: 'button', class: 'lc-title', onclick: () => { body.hidden = !body.hidden; } },
        strip(col.label) || col.name, h('small', {}, ' ' + (ty?.label ?? col.type))),
      h('button', { type: 'button', class: 'button-link', 'aria-label': t('Move up'), disabled: i === 0, onclick: () => move(i, -1) }, '↑'),
      h('button', { type: 'button', class: 'button-link', 'aria-label': t('Move down'), disabled: i === cols.length - 1, onclick: () => move(i, 1) }, '↓'),
      h('button', { type: 'button', class: 'button-link-delete', onclick: () => { cols.splice(i, 1); touch(); render(); } }, t('Remove'))),
    body);
  return row;
}

function flag(col, key, label, ty) {
  if (!ty?.caps[key]) return '';
  return h('label', { class: 'lc-inline' },
    h('input', { type: 'checkbox', checked: !!col[key], onchange: (e) => { col[key] = e.target.checked; touch(); } }), ' ' + t(label));
}

function renderFields(col, ty) {
  // 空の設定は PHP から [] で届く。配列のままだと名前付きの値が JSON に載らず保存されない。
  if (!col.settings || Array.isArray(col.settings)) col.settings = {};
  const wrap = h('div', { class: 'lc-fields' });
  for (const f of ty?.fields ?? []) {
    const val = col.settings[f.name] ?? f.default ?? '';
    const visible = () => !f.show_if || Object.entries(f.show_if).every(([k, v]) => (col.settings[k] ?? ty.fields.find((x) => x.name === k)?.default) === v);
    let input;
    if (f.type === 'select') {
      input = h('select', { onchange: (e) => { col.settings[f.name] = e.target.value; touch(); sync(); } },
        Object.entries(f.options || {}).map(([v, l]) => h('option', { value: v, selected: String(val) === v }, l)));
    } else if (f.type === 'checkbox') {
      input = h('input', { type: 'checkbox', checked: !!val, onchange: (e) => { col.settings[f.name] = e.target.checked; touch(); } });
    } else {
      input = h('input', { type: f.type === 'number' ? 'number' : 'text', value: val, class: 'regular-text', oninput: (e) => { col.settings[f.name] = e.target.value; touch(); } });
    }
    const label = h('label', { class: 'lc-field', 'data-name': f.name }, f.label + ' ', input, f.help ? h('small', {}, ' ' + f.help) : '');
    label._visible = visible;
    wrap.append(label);
  }
  function sync() { wrap.querySelectorAll('.lc-field').forEach((l) => { l.hidden = !l._visible(); }); }
  queueMicrotask(sync);
  return wrap;
}

function move(i, d) {
  const cols = state.data.config.columns;
  cols.splice(i + d, 0, cols.splice(i, 1)[0]); touch(); render();
}

function addColumn(v) {
  if (!v) return;
  const cols = state.data.config.columns;
  if (v.startsWith('foreign:')) {
    const k = v.slice(8);
    cols.push({ name: k, type: 'foreign', label: state.data.foreign[k], width: '', width_unit: '%', sort: false, search: false, export: false, settings: {} });
  } else if (v.startsWith('acf:')) {
    const f = state.data.acf.fields.find((x) => x.key === v.slice(4));
    const ty = typeOf(state.data.acf.type);
    cols.push({ name: uid(), type: ty.id, label: f.label, width: '', width_unit: '%', sort: false, search: false, export: ty.caps.export, settings: { field_key: f.key } });
  } else {
    const ty = typeOf(v);
    cols.push({ name: ty.native_key || uid(), type: v, label: ty.label, width: '', width_unit: '%', sort: false, search: false, export: ty.caps.export, settings: {} });
  }
  touch(); render();
}

async function save() {
  const c = state.data.config;
  try {
    const res = await api('lime_columns_save', state.key, {
      title: c.title, status: c.status, settings: c.settings, columns: c.columns,
    });
    state.data.config = res.config; state.data.saved = true; state.dirty = false; toast(t('Saved'));
    state.screens.find((s) => s.key === state.key).configured = true;
  } catch (e) { toast(e.message, 'error'); }
  render();
}

async function reset() {
  if (!confirm(t('Confirm reset'))) return;
  await api('lime_columns_reset', state.key);
  state.screens.find((s) => s.key === state.key).configured = false;
  state.dirty = false;
  await select(state.key);
}

function renderImport() {
  const out = h('pre', { class: 'lc-report', hidden: true });
  let overwrite = false;
  const run = async (dry) => {
    out.hidden = false; out.textContent = '…';
    try {
      const r = await api('lime_columns_import', null, { dry_run: dry, overwrite });
      out.textContent = !r.source_exists ? t('Admin Columns table not found.') : r.lists.map((l) =>
        `[${l.source} → ${l.target || '-'}] ${t(l.status)}\n  ${t('Mapped')}: ${l.mapped.length}\n` +
        l.mapped.map((m) => '    ✓ ' + m).join('\n') + (l.unmapped.length ? '\n  ' + t('Unmapped') + ':\n' + l.unmapped.map((m) => '    ✗ ' + m).join('\n') : '')
      ).join('\n\n');
      if (!dry) { state.screens = await api('lime_columns_screens'); }
    } catch (e) { out.textContent = e.message; }
  };
  return h('details', { class: 'lc-import' },
    h('summary', {}, t('Import from Admin Columns')),
    h('label', { class: 'lc-inline' }, h('input', { type: 'checkbox', onchange: (e) => { overwrite = e.target.checked; } }), ' ' + t('Overwrite')),
    h('p', {}, h('button', { class: 'button', type: 'button', onclick: () => run(true) }, t('Preview')), ' ',
      h('button', { class: 'button', type: 'button', onclick: () => run(false) }, t('Import'))),
    out);
}

init().catch((e) => { root.textContent = e.message; });
