/*
 * Ananya Tots CMS – admin panel behaviour.
 * No framework: small modules that enhance server-rendered Blade pages.
 */
import Sortable from 'sortablejs';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
const csrf = () => meta('csrf-token');
const clone = (o) => JSON.parse(JSON.stringify(o));

function h(tag, attrs = {}, ...children) {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs || {})) {
    if (v === null || v === undefined || v === false) continue;
    if (k === 'class') el.className = v;
    else if (k === 'text') el.textContent = v;
    else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2), v);
    else if (k === 'dataset') Object.assign(el.dataset, v);
    else if (v === true) el.setAttribute(k, '');
    else el.setAttribute(k, v);
  }
  for (const c of children.flat()) {
    if (c === null || c === undefined || c === false) continue;
    el.append(c instanceof Node ? c : document.createTextNode(String(c)));
  }
  return el;
}

const stripTags = (html) => { const d = document.createElement('div'); d.innerHTML = html || ''; return (d.textContent || '').trim(); };
const slugify = (s) => (s || '').toString().normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase()
  .replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120);

/* ------------------------------------------------------------ generic UI */
document.addEventListener('submit', (e) => {
  const form = e.target;
  if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
    e.preventDefault();
    e.stopImmediatePropagation();
  }
}, true);

$$('[data-toggle-side]').forEach((b) => b.addEventListener('click', () => {
  const side = $('#adm-side');
  const open = side.classList.toggle('open');
  b.setAttribute('aria-expanded', String(open));
}));

$$('dialog [data-close]').forEach((b) => b.addEventListener('click', () => b.closest('dialog').close()));

/* tabs (settings) */
$$('[data-tabs]').forEach((wrap) => {
  const tabs = $$('[role="tab"]', wrap);
  const show = (tab) => {
    tabs.forEach((t) => {
      const on = t === tab;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
    });
    const input = $('input[name="_tab"]', wrap.closest('form') || document);
    if (input) input.value = tab.dataset.key;
  };
  tabs.forEach((t, i) => {
    t.addEventListener('click', () => show(t));
    t.addEventListener('keydown', (e) => {
      const n = e.key === 'ArrowRight' ? tabs[(i + 1) % tabs.length] : e.key === 'ArrowLeft' ? tabs[(i - 1 + tabs.length) % tabs.length] : null;
      if (n) { e.preventDefault(); show(n); n.focus(); }
    });
  });
  const want = new URLSearchParams(location.search).get('tab');
  show(tabs.find((t) => t.dataset.key === want) || tabs[0]);
});

/* ------------------------------------------------------------ media picker */
const picker = (() => {
  const dlg = $('#media-modal');
  if (!dlg) return null;
  const grid = $('[data-picker-grid]', dlg);
  const more = $('[data-picker-more]', dlg);
  const search = $('[data-picker-search]', dlg);
  const folder = $('[data-picker-folder]', dlg);
  const status = $('[data-picker-status]', dlg);
  const upload = $('[data-picker-upload]', dlg);
  let onPick = null;
  let page = 1;
  let foldersLoaded = false;
  let timer = null;
  const known = {};

  const card = (m) => h('button', {
    type: 'button', class: 'pick', title: m.filename,
    onclick: () => { known[m.id] = m; dlg.close(); onPick && onPick(m); },
  }, h('img', { src: m.thumb, alt: '', loading: 'lazy' }), h('span', { text: m.filename }),
  h('small', { text: m.width && m.height ? `${m.width}×${m.height}` : m.mime }));

  async function load(reset) {
    if (reset) { page = 1; grid.innerHTML = ''; }
    status.textContent = 'Loading…';
    const url = new URL(meta('cms-picker'), location.href);
    url.searchParams.set('page', page);
    if (search.value) url.searchParams.set('search', search.value);
    if (folder.value) url.searchParams.set('folder', folder.value);
    try {
      const res = await fetch(url, { headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (!foldersLoaded) {
        data.folders.forEach((f) => folder.append(h('option', { value: f.id, text: f.name })));
        foldersLoaded = true;
        $('[data-picker-upload-wrap]', dlg).hidden = !data.canUpload;
      }
      data.items.forEach((m) => { known[m.id] = m; grid.append(card(m)); });
      status.textContent = grid.children.length ? '' : 'No images found. Upload one to get started.';
      more.hidden = !data.next;
      if (data.next) page = data.next;
    } catch (e) {
      status.textContent = 'Could not load the media library.';
    }
  }
  more.addEventListener('click', () => load(false));
  search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(true), 300); });
  folder.addEventListener('change', () => load(true));
  upload.addEventListener('change', async () => {
    if (!upload.files.length) return;
    const fd = new FormData();
    [...upload.files].forEach((f) => fd.append('files[]', f));
    if (folder.value) fd.append('folder_id', folder.value);
    status.textContent = 'Uploading and optimising…';
    try {
      const res = await fetch(meta('cms-upload'), { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
      const data = await res.json().catch(() => ({}));
      (data.items || []).reverse().forEach((m) => { known[m.id] = m; grid.prepend(card(m)); });
      const errs = (data.errors || []).concat(data.message && !data.items ? [data.message] : []);
      status.textContent = errs.length ? errs.join(' ') : `${(data.items || []).length} image(s) uploaded. Click one to use it.`;
    } catch (e) {
      status.textContent = 'Upload failed.';
    }
    upload.value = '';
  });

  return {
    open(cb) { onPick = cb; dlg.showModal(); if (!grid.children.length) load(true); search.focus(); },
    remember(map) { Object.assign(known, map || {}); },
    get(id) { return known[id]; },
  };
})();

/* image fields on normal forms (settings, page featured/OG image) */
$$('[data-imgfield]').forEach((wrap) => {
  const input = $('[data-imgfield-input]', wrap);
  const prev = $('[data-imgfield-prev]', wrap);
  const name = $('[data-imgfield-name]', wrap);
  const idMode = wrap.dataset.mode === 'id';
  const read = () => { if (idMode) return { media_id: input.value || null }; try { return JSON.parse(input.value || '{}'); } catch (e) { return {}; } };
  const write = (v) => { input.value = idMode ? (v.media_id || '') : JSON.stringify(v); };
  $('[data-imgfield-choose]', wrap)?.addEventListener('click', () => picker?.open((m) => {
    const v = read(); v.media_id = m.id; if (!v.alt) v.alt = m.alt || '';
    write(v);
    prev.innerHTML = ''; prev.append(h('img', { src: m.thumb, alt: '' }));
    name.textContent = m.filename;
  }));
  $('[data-imgfield-clear]', wrap)?.addEventListener('click', () => {
    const v = read(); v.media_id = null; write(v);
    prev.innerHTML = '';
    if (v.src) { prev.append(h('img', { src: (meta('cms-asset-base') || '/') + v.src, alt: '' })); name.textContent = 'Design default'; } else { prev.append(h('span', { class: 'muted', text: 'No image' })); name.textContent = ''; }
  });
});

/* ------------------------------------------------------------ text editors */
function richEditor(value, { inline = false, onChange }) {
  const area = h('div', { class: 'rte__area' + (inline ? ' rte__area--inline' : ''), contenteditable: 'true', role: 'textbox', 'aria-multiline': inline ? 'false' : 'true' });
  area.innerHTML = value || '';
  const cmd = (c, v = null) => { area.focus(); document.execCommand(c, false, v); onChange(area.innerHTML); };
  const btn = (label, title, fn) => h('button', { type: 'button', class: 'rte__b', title, 'aria-label': title, onmousedown: (e) => e.preventDefault(), onclick: fn }, label);
  const tools = h('div', { class: 'rte__tools' },
    btn(h('b', {}, 'B'), 'Bold', () => cmd('bold')),
    btn(h('i', {}, 'I'), 'Italic', () => cmd('italic')),
    btn('🔗', 'Link', () => {
      const url = window.prompt('Link address (e.g. /contact#enquiry or https://…)');
      if (url === null) return;
      if (url === '') cmd('unlink'); else if (/^(\/(?!\/)|#|https?:\/\/|mailto:|tel:)/i.test(url)) cmd('createLink', url); else window.alert('Links must start with /, #, https://, mailto: or tel:');
    }),
    inline ? btn('↵', 'Line break', () => cmd('insertHTML', '<br>')) : btn('•', 'Bulleted list', () => cmd('insertUnorderedList')),
    !inline && btn('¶', 'Paragraph', () => cmd('formatBlock', 'p')),
    btn('⌫', 'Clear formatting', () => cmd('removeFormat')),
  );
  area.addEventListener('input', () => onChange(area.innerHTML));
  area.addEventListener('paste', (e) => {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text/plain');
    if (inline) document.execCommand('insertText', false, text.replace(/\s*\n\s*/g, ' '));
    else document.execCommand('insertHTML', false, text.split(/\n{2,}/).map((p) => `<p>${p.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])).replace(/\n/g, '<br>')}</p>`).join(''));
    onChange(area.innerHTML);
  });
  if (inline) {
    area.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); document.execCommand('insertHTML', false, '<br>'); } });
  }
  return h('div', { class: 'rte' }, tools, area);
}

/* ------------------------------------------------------------ page editor */
const editorData = $('#editor-data');
if (editorData) initPageEditor(JSON.parse(editorData.textContent));

function initPageEditor(cfg) {
  const form = $('#page-form');
  const list = $('#sections');
  const types = cfg.types;
  const state = (cfg.sections || []).map((s) => ({ ...s, _key: Math.random().toString(36).slice(2) }));
  picker?.remember(cfg.media);
  let dirty = false;
  const touch = () => { dirty = true; };

  // datalist of internal pages for URL inputs
  const dl = h('datalist', { id: 'page-paths' }, cfg.pages.map((p) => h('option', { value: p.path, label: p.title })));
  document.body.append(dl);

  const fieldId = (() => { let i = 0; return () => `f${++i}`; })();

  function summary(sec) {
    const t = types[sec.type];
    let text = '';
    const walk = (fields, data) => {
      for (const f of fields) {
        if (text) return;
        const v = data[f.key];
        if (f.type === 'inline' || f.type === 'richtext' || f.type === 'text') text = stripTags(v);
      }
    };
    if (t) walk(t.fields, sec.data || {});
    return text.slice(0, 80);
  }

  function imageField(f, data, onChange) {
    const v = data[f.key] || (data[f.key] = { src: null, media_id: null, alt: '' });
    const prev = h('div', { class: 'imgfield__prev' });
    const info = h('span', { class: 'small muted' });
    const crop = h('a', { class: 'btn sm ghost', target: '_blank', rel: 'noopener', hidden: true }, 'Crop / edit ↗');
    const render = () => {
      prev.innerHTML = '';
      const m = v.media_id ? picker?.get(v.media_id) : null;
      if (m) { prev.append(h('img', { src: m.thumb, alt: '' })); info.textContent = m.filename; }
      else if (v.src) { prev.append(h('img', { src: cfg.assetBase + v.src, alt: '' })); info.textContent = 'Design image: ' + v.src.split('/').pop(); }
      else { prev.append(h('span', { class: 'muted', text: 'No image' })); info.textContent = ''; }
      crop.hidden = !v.media_id;
      if (v.media_id) {
        const ratio = v.w && v.h ? (v.w / v.h).toFixed(4) : '';
        crop.href = cfg.mediaEditUrl.replace('__ID__', v.media_id) + (ratio ? `?ratio=${ratio}` : '');
      }
    };
    render();
    const ratioHint = v.w && v.h ? h('p', { class: 'help', text: `Design size ${v.w}×${v.h}px – choose or crop an image with a similar shape (${(v.w / v.h).toFixed(2)}:1).` }) : null;
    const choose = h('button', { type: 'button', class: 'btn sm', onclick: () => picker?.open((m) => {
      v.media_id = m.id;
      if (!f.decorative && !v.alt) { v.alt = m.alt || ''; altInput && (altInput.value = v.alt); }
      render(); onChange();
    }) }, 'Choose image');
    const remove = h('button', { type: 'button', class: 'btn sm ghost', onclick: () => { v.media_id = null; render(); onChange(); } }, 'Use design image');
    let altInput = null;
    const id = fieldId();
    const altWrap = f.decorative ? h('p', { class: 'help', text: 'Decorative image – hidden from screen readers, no alt text needed.' })
      : h('label', { for: id }, 'Alt text (describe the image for screen readers and search engines)',
        altInput = h('input', { id, type: 'text', value: v.alt || '', maxlength: 300, oninput: (e) => { v.alt = e.target.value; onChange(); } }));
    return h('div', { class: 'imgfield' }, prev, h('div', { class: 'imgfield__act' }, choose, remove, crop, info), ratioHint, altWrap);
  }

  function renderFields(fields, data, onChange) {
    const wrap = h('div', { class: 'fields' });
    for (const f of fields) {
      const id = fieldId();
      const label = h('label', { class: 'flabel', for: id, text: f.label });
      let control;
      switch (f.type) {
        case 'inline':
        case 'richtext': {
          control = richEditor(data[f.key] || '', { inline: f.type === 'inline', onChange: (v) => { data[f.key] = v; onChange(); } });
          $('.rte__area', control).id = id;
          $('.rte__area', control).setAttribute('aria-label', f.label);
          break;
        }
        case 'text':
          control = h('input', { id, type: 'text', value: data[f.key] || '', maxlength: 500, oninput: (e) => { data[f.key] = e.target.value; onChange(); } });
          break;
        case 'url':
          control = h('input', { id, type: 'text', list: 'page-paths', value: data[f.key] || '', placeholder: '/page, #section or https://…', oninput: (e) => { data[f.key] = e.target.value; onChange(); } });
          break;
        case 'boolean':
          control = h('label', { class: 'check' }, h('input', { id, type: 'checkbox', checked: !!data[f.key], onchange: (e) => { data[f.key] = e.target.checked; onChange(); } }), ' ', f.label);
          wrap.append(h('div', { class: 'field' }, control, f.help ? h('p', { class: 'help', text: f.help }) : null));
          continue;
        case 'list':
          control = h('textarea', { id, rows: Math.min(10, Math.max(3, (data[f.key] || []).length + 1)), oninput: (e) => { data[f.key] = e.target.value.split('\n').filter((x) => x.trim() !== ''); onChange(); } });
          control.value = (data[f.key] || []).join('\n');
          wrap.append(h('div', { class: 'field' }, label, control, h('p', { class: 'help', text: 'One entry per line.' })));
          continue;
        case 'link': {
          const v = data[f.key] || (data[f.key] = { label: '', url: '', new_tab: false });
          control = h('div', { class: 'linkfield' },
            h('input', { id, type: 'text', value: stripTags(v.label) === v.label ? v.label : v.label, placeholder: 'Button text', 'aria-label': f.label + ' text', oninput: (e) => { v.label = e.target.value; onChange(); } }),
            h('input', { type: 'text', list: 'page-paths', value: v.url || '', placeholder: '/contact#enquiry or https://…', 'aria-label': f.label + ' link', oninput: (e) => { v.url = e.target.value; onChange(); } }),
            h('label', { class: 'check small' }, h('input', { type: 'checkbox', checked: !!v.new_tab, onchange: (e) => { v.new_tab = e.target.checked; onChange(); } }), ' Open in new tab'));
          break;
        }
        case 'image':
          wrap.append(h('div', { class: 'field' }, h('span', { class: 'flabel', text: f.label }), imageField(f, data, onChange)));
          continue;
        case 'repeater':
          wrap.append(repeater(f, data, onChange));
          continue;
        default:
          continue;
      }
      wrap.append(h('div', { class: 'field' }, label, control, f.help ? h('p', { class: 'help', text: f.help }) : null));
    }
    return wrap;
  }

  function repeater(f, data, onChange) {
    const items = data[f.key] || (data[f.key] = []);
    const box = h('fieldset', { class: 'rep' }, h('legend', { text: `${f.label} (${items.length})` }));
    const body = h('div', { class: 'rep__items' });
    const rerender = () => { box.replaceWith(repeater(f, data, onChange)); onChange(); };
    items.forEach((item, i) => {
      const title = stripTags(Object.values(item).find((v) => typeof v === 'string' && stripTags(v)) || '') || `${f.item_label || 'Item'} ${i + 1}`;
      const det = h('details', { class: 'rep__item' },
        h('summary', {}, h('span', { text: `${i + 1}. ${title.slice(0, 60)}` }),
          h('span', { class: 'rep__tools' },
            h('button', { type: 'button', class: 'mini', title: 'Move up', 'aria-label': 'Move up', disabled: i === 0, onclick: (e) => { e.preventDefault(); items.splice(i - 1, 0, items.splice(i, 1)[0]); rerender(); } }, '↑'),
            h('button', { type: 'button', class: 'mini', title: 'Move down', 'aria-label': 'Move down', disabled: i === items.length - 1, onclick: (e) => { e.preventDefault(); items.splice(i + 1, 0, items.splice(i, 1)[0]); rerender(); } }, '↓'),
            h('button', { type: 'button', class: 'mini', title: 'Duplicate', 'aria-label': 'Duplicate', onclick: (e) => { e.preventDefault(); items.splice(i + 1, 0, clone(item)); rerender(); } }, '⧉'),
            h('button', { type: 'button', class: 'mini danger', title: 'Remove', 'aria-label': 'Remove', onclick: (e) => { e.preventDefault(); if (window.confirm('Remove this item?')) { items.splice(i, 1); rerender(); } } }, '✕'))),
        renderFields(f.fields, item, onChange));
      body.append(det);
    });
    box.append(body, h('button', { type: 'button', class: 'btn sm ghost', onclick: () => {
      items.push(items.length ? clone(items[items.length - 1]) : Object.fromEntries(f.fields.map((x) => [x.key, x.type === 'repeater' || x.type === 'list' ? [] : x.type === 'link' ? { label: '', url: '', new_tab: false } : x.type === 'image' ? { src: null, media_id: null, alt: '' } : x.type === 'boolean' ? false : ''])));
      rerender();
      const last = box.isConnected ? null : null; // re-rendered box replaced
    } }, `+ Add ${(f.item_label || 'item').toLowerCase()}`));
    return box;
  }

  function sectionCard(sec, index) {
    const t = types[sec.type];
    const head = h('div', { class: 'sec__head' },
      h('span', { class: 'sec__drag', title: 'Drag to reorder', 'aria-hidden': 'true' }, '⠿'),
      h('button', { type: 'button', class: 'sec__title', 'aria-expanded': 'false', onclick: (e) => {
        const open = card.classList.toggle('open');
        e.currentTarget.setAttribute('aria-expanded', String(open));
        if (open && !body.childElementCount) build();
      } }, h('strong', { text: t ? t.label : sec.type }), h('span', { class: 'muted small', text: summary(sec) })),
      h('label', { class: 'check small', title: 'Show this section on the page' }, h('input', { type: 'checkbox', checked: sec.is_enabled !== false, onchange: (e) => { sec.is_enabled = e.target.checked; card.classList.toggle('off', !e.target.checked); touch(); } }), ' Visible'),
      h('span', { class: 'sec__tools' },
        h('button', { type: 'button', class: 'mini', title: 'Move up', 'aria-label': 'Move section up', onclick: () => move(sec, -1) }, '↑'),
        h('button', { type: 'button', class: 'mini', title: 'Move down', 'aria-label': 'Move section down', onclick: () => move(sec, 1) }, '↓'),
        h('button', { type: 'button', class: 'mini', title: 'Duplicate section', 'aria-label': 'Duplicate section', onclick: () => {
          const copy = { ...clone(sec), id: null, anchor: null, _key: Math.random().toString(36).slice(2) };
          state.splice(state.indexOf(sec) + 1, 0, copy); render(); touch();
        } }, '⧉'),
        h('button', { type: 'button', class: 'mini danger', title: 'Delete section', 'aria-label': 'Delete section', onclick: () => {
          if (!window.confirm(`Delete the section “${t ? t.label : sec.type}” from this page?`)) return;
          state.splice(state.indexOf(sec), 1); render(); touch();
        } }, '✕')));
    const body = h('div', { class: 'sec__body' });
    const build = () => {
      if (!t) { body.append(h('p', { class: 'alert bad', text: 'This section type no longer exists and will be removed when you save.' })); return; }
      if (t.anchor !== null && t.anchor !== undefined) {
        const id = fieldId();
        body.append(h('div', { class: 'field' }, h('label', { for: id, text: 'Anchor ID (for links like /page#anchor)' }),
          h('input', { id, type: 'text', value: sec.anchor || '', placeholder: t.anchor, pattern: '[a-z][a-z0-9-]*', oninput: (e) => { sec.anchor = e.target.value.trim() || null; touch(); } })));
      }
      body.append(renderFields(t.fields, sec.data || (sec.data = {}), touch));
    };
    const card = h('div', { class: 'sec' + (sec.is_enabled === false ? ' off' : ''), dataset: { key: sec._key } }, head, body);
    card._sec = sec;
    return card;
  }

  function move(sec, dir) {
    const i = state.indexOf(sec);
    const j = i + dir;
    if (j < 0 || j >= state.length) return;
    state.splice(j, 0, state.splice(i, 1)[0]);
    render(); touch();
  }

  function render() {
    const open = new Set($$('.sec.open', list).map((c) => c.dataset.key));
    list.innerHTML = '';
    if (!state.length) list.append(h('p', { class: 'muted', text: 'This page has no sections yet. Add one to start.' }));
    state.forEach((sec, i) => {
      const c = sectionCard(sec, i);
      list.append(c);
      if (open.has(sec._key)) $('.sec__title', c).click();
    });
  }
  render();
  Sortable.create(list, {
    handle: '.sec__drag', animation: 150,
    onEnd: () => {
      const order = $$('.sec', list).map((c) => c._sec);
      state.splice(0, state.length, ...order); touch();
    },
  });

  // section library
  const lib = $('#section-modal');
  const libList = $('[data-section-lib]', lib);
  const filter = $('[data-section-filter]', lib);
  const drawLib = () => {
    libList.innerHTML = '';
    const q = filter.value.toLowerCase();
    for (const [group, items] of Object.entries(cfg.groups)) {
      const hits = items.filter((i) => !q || i.label.toLowerCase().includes(q) || group.toLowerCase().includes(q));
      if (!hits.length) continue;
      libList.append(h('h3', { text: `From the ${group} page` }), h('div', { class: 'seclib__grid' }, hits.map((i) => h('button', {
        type: 'button', class: 'seclib__item', onclick: async () => {
          const res = await fetch(cfg.defaultsUrl.replace('__TYPE__', encodeURIComponent(i.key)), { headers: { Accept: 'application/json' } });
          const d = await res.json();
          const sec = { id: null, type: i.key, anchor: null, is_enabled: true, data: d.data, _key: Math.random().toString(36).slice(2) };
          state.push(sec); render(); touch(); lib.close();
          const card = $(`.sec[data-key="${sec._key}"]`, list);
          card?.scrollIntoView({ behavior: 'smooth', block: 'center' });
          $('.sec__title', card)?.click();
        },
      }, h('strong', { text: i.label }), h('small', { text: i.css === 'home' ? 'Home-page design' : 'Inner-page design' })))));
    }
  };
  filter.addEventListener('input', drawLib);
  $$('[data-add-section]').forEach((b) => b.addEventListener('click', () => { drawLib(); lib.showModal(); filter.focus(); }));

  // title → slug, SEO counters, SERP preview
  const title = $('[data-title-input]');
  const slug = $('[data-slug-input]');
  let slugTouched = slug && !slug.hasAttribute('data-auto');
  slug?.addEventListener('input', () => { slugTouched = true; });
  const serpT = $('[data-serp-title]');
  const serpD = $('[data-serp-desc]');
  const mt = $('[data-count="meta_title"]');
  const md = $('[data-count="meta_description"]');
  const updateSeo = () => {
    $$('[data-count]').forEach((inp) => {
      const out = $(`[data-count-for="${inp.dataset.count}"]`);
      const len = (inp.value || inp.placeholder || '').length;
      out.textContent = `${len} characters`;
      out.className = 'counter ' + (len >= +out.dataset.min && len <= +out.dataset.max ? 'good' : 'warn');
    });
    if (serpT) serpT.textContent = mt.value || (title.value + cfg.suffix);
    if (serpD) serpD.textContent = md.value || md.placeholder;
  };
  title?.addEventListener('input', () => {
    if (slug && !slugTouched) slug.value = slugify(title.value);
    if (mt) mt.placeholder = title.value + cfg.suffix;
    updateSeo(); touch();
  });
  [mt, md].forEach((x) => x?.addEventListener('input', updateSeo));
  updateSeo();

  form.addEventListener('input', touch);
  form.addEventListener('submit', (e) => {
    const sections = state.map(({ _key, ...s }) => s);
    $('#sections-input').value = JSON.stringify(sections);
    const action = e.submitter?.value;
    if (action !== 'preview') dirty = false;
  });
  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
}

/* ------------------------------------------------------------ media library: upload preview */
$$('[data-upload-form]').forEach((form) => {
  const input = $('input[type="file"]', form);
  const prev = $('[data-upload-preview]', form);
  input.addEventListener('change', () => {
    prev.innerHTML = '';
    [...input.files].forEach((f) => {
      const fig = h('figure', {}, f.type.startsWith('image/') ? h('img', { src: URL.createObjectURL(f), alt: '' }) : h('span', { text: '📄' }),
        h('figcaption', {}, `${f.name} (${(f.size / 1024).toFixed(0)} KB)`));
      prev.append(fig);
    });
  });
});

/* ------------------------------------------------------------ media: crop / rotate editor */
const cropImg = $('#crop-image');
if (cropImg) {
  const form = $('#crop-form');
  const fields = ['x', 'y', 'width', 'height', 'rotate'].reduce((o, k) => ({ ...o, [k]: $(`input[name="${k}"]`, form) }), {});
  const wIn = $('[data-crop-w]');
  const hIn = $('[data-crop-h]');
  const info = $('[data-crop-info]');
  const wantRatio = parseFloat(cropImg.dataset.ratio) || NaN;
  const cropper = new Cropper(cropImg, {
    viewMode: 2, autoCropArea: 1, responsive: true, background: false, preview: '.crop-preview',
    aspectRatio: wantRatio || NaN,
    crop(e) {
      const d = e.detail;
      fields.x.value = Math.round(d.x); fields.y.value = Math.round(d.y);
      fields.width.value = Math.round(d.width); fields.height.value = Math.round(d.height);
      fields.rotate.value = ((Math.round(d.rotate) % 360) + 360) % 360;
      if (document.activeElement !== wIn) wIn.value = Math.round(d.width);
      if (document.activeElement !== hIn) hIn.value = Math.round(d.height);
      info.textContent = `${Math.round(d.width)} × ${Math.round(d.height)} px`;
    },
  });
  $$('[data-ratio]').forEach((b) => b.addEventListener('click', () => {
    $$('[data-ratio]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    cropper.setAspectRatio(b.dataset.ratio === '' ? NaN : parseFloat(b.dataset.ratio));
  }));
  // keep the whole (rotated) image visible inside the stage and re-select all of it
  const fit = () => {
    const c = cropper.getContainerData();
    const img = cropper.getImageData();
    const turned = Math.abs(img.rotate || 0) % 180 === 90;
    const bw = turned ? img.naturalHeight : img.naturalWidth;
    const bh = turned ? img.naturalWidth : img.naturalHeight;
    cropper.zoomTo(Math.min(c.width / bw, c.height / bh));
    const cv = cropper.getCanvasData();
    cropper.setCanvasData({ left: (c.width - cv.width) / 2, top: (c.height - cv.height) / 2 });
    const box = cropper.getCanvasData();
    const ratio = cropper.options.aspectRatio;
    let w = box.width; let hh = box.height;
    if (ratio) { if (w / hh > ratio) w = hh * ratio; else hh = w / ratio; }
    cropper.setCropBoxData({ left: box.left + (box.width - w) / 2, top: box.top + (box.height - hh) / 2, width: w, height: hh });
  };
  $$('[data-rotate]').forEach((b) => b.addEventListener('click', () => { cropper.rotate(parseInt(b.dataset.rotate, 10)); fit(); }));
  $('[data-crop-reset]')?.addEventListener('click', () => { cropper.reset(); cropper.rotateTo(0); fit(); });
  const applySize = () => {
    const w = parseInt(wIn.value, 10); const hh = parseInt(hIn.value, 10);
    if (w > 0 && hh > 0) { cropper.setAspectRatio(NaN); cropper.setData({ width: w, height: hh }); }
  };
  [wIn, hIn].forEach((i) => i.addEventListener('change', applySize));
}

/* ------------------------------------------------------------ menu builder */
const menuData = $('#menu-data');
if (menuData) initMenuBuilder(JSON.parse(menuData.textContent));

function initMenuBuilder(cfg) {
  const root = $('#menu-tree');
  const form = $('#menu-form');
  const pageOpts = (sel) => [h('option', { value: '', text: '— Custom URL —' }), ...cfg.pages.map((p) => h('option', { value: p.id, selected: String(p.id) === String(sel), text: p.title + (p.status === 'draft' ? ' (draft)' : '') }))];
  const styleOpts = (sel) => Object.entries(cfg.styles).map(([k, v]) => h('option', { value: k, selected: (sel || '') === k, text: v }));

  function itemEl(it, depth) {
    const li = h('li', { class: 'mi' });
    const children = h('ul', { class: 'mi__children' });
    const page = h('select', { 'aria-label': 'Links to page' }, pageOpts(it.page_id));
    const url = h('input', { type: 'text', value: it.url || '', list: 'menu-paths', 'aria-label': 'URL or #anchor', placeholder: '/path, #anchor or https://…' });
    const row = h('div', { class: 'mi__row' },
      h('span', { class: 'sec__drag', title: 'Drag to reorder', 'aria-hidden': 'true' }, '⠿'),
      h('input', { type: 'text', value: it.label || '', class: 'mi__label', 'aria-label': 'Label', required: true, maxlength: 120 }),
      page, url,
      h('select', { 'aria-label': 'Open in', class: 'mi__target' }, h('option', { value: '_self', text: 'Same tab', selected: it.target !== '_blank' }), h('option', { value: '_blank', text: 'New tab', selected: it.target === '_blank' })),
      cfg.location !== 'header' ? h('select', { 'aria-label': 'Style', class: 'mi__style' }, styleOpts(it.style)) : null,
      h('label', { class: 'check small' }, h('input', { type: 'checkbox', class: 'mi__on', checked: it.is_enabled !== false }), ' Visible'),
      h('button', { type: 'button', class: 'mini danger', 'aria-label': 'Remove item', onclick: () => { if (window.confirm('Remove this menu item' + (children.children.length ? ' and its sub-items' : '') + '?')) li.remove(); } }, '✕'));
    li.append(row, children);
    (it.children || []).forEach((c) => children.append(itemEl(c, depth + 1)));
    sortable(children, depth + 1);
    return li;
  }
  function sortable(ul, depth) {
    Sortable.create(ul, {
      group: { name: 'menu', put: () => depth < cfg.maxDepth }, handle: '.sec__drag', animation: 150, fallbackOnBody: true, swapThreshold: 0.6,
      onMove: (evt) => {
        // prevent putting an item that has children into a sub-list
        const hasKids = evt.dragged.querySelector('.mi__children > li');
        return !(hasKids && evt.to !== root);
      },
    });
  }
  const dlp = h('datalist', { id: 'menu-paths' }, cfg.pages.map((p) => h('option', { value: p.path })));
  document.body.append(dlp);
  cfg.items.forEach((it) => root.append(itemEl(it, 1)));
  sortable(root, 1);
  $('[data-add-item]').addEventListener('click', () => {
    const li = itemEl({ label: 'New item', page_id: '', url: '', target: '_self', is_enabled: true, children: [] }, 1);
    root.append(li); $('.mi__label', li).select();
  });
  const serialise = (ul) => $$(':scope > li', ul).map((li) => {
    const row = $(':scope > .mi__row', li);
    return {
      label: $('.mi__label', row).value,
      page_id: $('select', row).value || null,
      url: $('input[list]', row).value,
      target: $('.mi__target', row).value,
      style: $('.mi__style', row)?.value || null,
      is_enabled: $('.mi__on', row).checked,
      children: serialise($(':scope > .mi__children', li)),
    };
  });
  form.addEventListener('submit', () => { $('#menu-items').value = JSON.stringify(serialise(root)); });
}
