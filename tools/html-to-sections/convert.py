#!/usr/bin/env python3
"""Convert the approved static Ananya Tots pages into Laravel Blade section types.

For every top-level block inside <main> we emit:
  * a Blade partial  resources/views/sections/<type>.blade.php
  * a schema         resources/cms/sections/<type>.json   (editable fields)
  * seed data        database/seeders/data/pages.json     (field values per page)

Static design pieces (decorative images, SVGs, wrappers, classes, animations) stay
literal in the template so the rendered HTML matches the original markup.
Text, content images, links, lists and repeating cards become CMS fields.
"""
import json, re, sys, html, hashlib, os
from pathlib import Path
from bs4 import BeautifulSoup, NavigableString, Comment, Tag

# usage: python3 convert.py <path-to-static-html-site> <path-to-laravel-project>
# One-time import tool: it OVERWRITES resources/views/sections, resources/cms and
# database/seeders/data/pages.json. Requires: pip install beautifulsoup4 lxml
SITE = Path(sys.argv[1] if len(sys.argv) > 1 else 'static-site')
OUT = Path(sys.argv[2] if len(sys.argv) > 2 else '.')
VIEWS = OUT / 'resources/views/sections'
SCHEMAS = OUT / 'resources/cms/sections'
SEED = OUT / 'database/seeders/data'
DEFAULTS = OUT / 'resources/cms/defaults'
for d in (VIEWS, SCHEMAS, SEED):
    d.mkdir(parents=True, exist_ok=True)

PAGES = ['index', 'about', 'programs', 'learning', 'learning-approach', 'campus-life', 'life-at-tots',
         'facilities', 'parents', 'parents-corner', 'admissions', 'careers', 'contact']
VOID = {'img', 'br', 'input', 'meta', 'link', 'source', 'hr', 'area', 'col', 'wbr'}
INLINE = {'br', 'em', 'strong', 'b', 'i', 'span', 'small', 'sup', 'sub', 'u', 'a', 'abbr', 'mark'}
STATE = re.compile(r'^is-')
WARN = []


def page_url(href):
    """static link -> site-relative URL used by Laravel routes"""
    if not href:
        return href
    if href.startswith(('http:', 'https:', 'mailto:', 'tel:', '#', '/')):
        return href
    m = re.match(r'^([a-z0-9-]+)\.html(#.*)?$', href)
    if m:
        slug = m.group(1)
        return ('/' if slug == 'index' else '/' + slug) + (m.group(2) or '')
    return href


def fix_links(v):
    if isinstance(v, str):
        return re.sub(r'href="([^"]+)"', lambda m: f'href="{page_url(m.group(1))}"', v)
    if isinstance(v, list):
        return [fix_links(x) for x in v]
    if isinstance(v, dict):
        return {k: fix_links(x) for k, x in v.items()}
    return v


def blade_safe(s):
    return s.replace('@', '&#64;').replace('{{', '&#123;&#123;').replace('{!!', '&#123;!!')


def attr_val(v):
    if isinstance(v, list):
        v = ' '.join(v)
    return v


def esc_attr(v):
    return blade_safe(html.escape(v, quote=True))


SVG_FIX = {'viewbox': 'viewBox', 'preserveaspectratio': 'preserveAspectRatio'}


def static_attrs(el, ctx, skip=()):
    out = []
    sf = el.get('data-state-first')
    for k, v in el.attrs.items():
        if k in skip or k == 'data-state-first':
            continue
        if k == 'class' and sf:
            out.append(f'class="{esc_attr(attr_val(v))}{{{{ $loop->first ? \' {sf}\' : \'\' }}}}"')
            sf = None
            continue
        v = attr_val(v)
        k2 = SVG_FIX.get(k, k)
        if k in ('id',) and v:
            if el is ctx.root:
                out.append(f'id="{{{{ $s->anchor }}}}"')
                continue
            out.append(f'id="{esc_attr(v)}{{{{ $s->uid }}}}"')
            continue
        if k in ('for', 'aria-labelledby', 'aria-controls', 'aria-describedby') and v:
            parts = []
            for p in v.split():
                if p == ctx.root_id:
                    parts.append('{{ $s->anchor }}')
                else:
                    parts.append(esc_attr(p) + '{{ $s->uid }}')
            out.append(f'{k2}="{" ".join(parts)}"')
            continue
        if k == 'src':
            out.append(f'src="{{{{ asset(\'{v}\') }}}}"')
            continue
        if k == 'href':
            u = page_url(v)
            if u.startswith('/'):
                out.append(f'href="{{{{ url(\'{u}\') }}}}"')
            else:
                out.append(f'href="{esc_attr(u)}"')
            continue
        if k == 'style' and 'url(' in v:
            v = re.sub(r"url\('?([^')]+)'?\)", lambda m: "url('{{ asset('" + m.group(1) + "') }}')", v)
            out.append(f'style="{v}"')
            continue
        if v == '' and k in ('hidden', 'required', 'novalidate', 'checked', 'data-single', 'disabled', 'selected'):
            out.append(k)
            continue
        out.append(f'{k2}="{esc_attr(v)}"')
    if sf:
        out.append(f'{{!! $loop->first ? \'class="{sf}"\' : \'\' !!}}')
    return (' ' + ' '.join(out)) if out else ''


def inner_html(el):
    return ''.join(str(c) for c in el.contents)


def text_of(el):
    return re.sub(r'\s+', ' ', el.get_text(' ')).strip()


def is_leaf(el):
    """element containing only text and simple inline markup"""
    if not text_of(el):
        return False
    kids = [c for c in el.children if isinstance(c, Tag)]
    if len(kids) >= 2 and all(k.name == 'span' and k.get('class') for k in kids) \
            and not any(isinstance(c, NavigableString) and str(c).strip() for c in el.children):
        return False   # e.g. <h2><span class="d">..</span><span class="s">..</span></h2> -> one field per line
    for d in el.descendants:
        if isinstance(d, Tag):
            if d.name not in INLINE:
                return False
            if d.name == 'span' and set(d.attrs) - {'class'}:
                return False
            if d.name == 'a' and set(d.attrs) - {'href', 'target', 'rel'}:
                return False
            if d.name not in ('span', 'a') and d.attrs:
                return False
    return True


def plain_list(el):
    if el.name not in ('ul', 'ol'):
        return False
    lis = [c for c in el.children if isinstance(c, Tag)]
    if not lis or any(c.name != 'li' or c.attrs or not is_leaf(c) for c in lis):
        return False
    return all(isinstance(c, Tag) or not str(c).strip() for c in el.children)


def is_prose(el):
    cls = el.get('class') or []
    if 'copy' not in cls:
        return False
    kids = [c for c in el.children if isinstance(c, Tag)]
    return all(k.name in ('p', 'ul', 'ol', 'h3', 'h4', 'blockquote') for k in kids)


def classes(el):
    return [c for c in (el.get('class') or []) if not STATE.match(c)]


def skeleton(el):
    if isinstance(el, NavigableString):
        return ''
    if is_leaf(el):
        return f'{el.name}.{".".join(classes(el))}:L'
    kids = ','.join(skeleton(c) for c in el.children if isinstance(c, Tag))
    return f'{el.name}.{".".join(classes(el))}[{kids}]'


LABELS = {
    'd': 'Heading – first line', 's': 'Heading – script line', 'h-display': 'Heading – first line',
    'h-script': 'Heading – script line', 'eyebrow': 'Eyebrow text', 'copy': 'Body text', 'btn': 'Button',
    'sub': 'Subtitle', 'time': 'Timing', 'by': 'Attribution', 'affirm': 'Affirmation', 'qm': 'Quote mark',
    'tab__t': 'Tab title', 'tab__s': 'Tab subtitle', 'checklist__title': 'Checklist title',
}
TAGLABEL = {'h1': 'Heading', 'h2': 'Heading', 'h3': 'Title', 'h4': 'Title', 'p': 'Text', 'li': 'Item',
            'figcaption': 'Caption', 'blockquote': 'Quote', 'strong': 'Title', 'span': 'Text', 'a': 'Link',
            'img': 'Image', 'ul': 'List', 'ol': 'List', 'label': 'Label', 'legend': 'Label', 'button': 'Button'}


def slug(s):
    return re.sub(r'[^a-z0-9]+', '_', s.lower()).strip('_') or 'f'


class Scope:
    def __init__(self):
        self.fields = []
        self.data = {}
        self.keys = set()
        self.labels = {}

    def add(self, base_label, ftype, value, extra=None, key=None):
        value = fix_links(value)
        label = base_label
        n = self.labels.get(base_label, 0) + 1
        self.labels[base_label] = n
        if n > 1:
            label = f'{base_label} {n}'
            if n == 2:
                for f in self.fields:
                    if f['label'] == base_label:
                        f['label'] = base_label + ' 1'
        key = key or slug(label)
        i = 2
        while key in self.keys:
            key = f'{slug(label)}_{i}'
            i += 1
        self.keys.add(key)
        f = {'key': key, 'type': ftype, 'label': label}
        if extra:
            f.update(extra)
        self.fields.append(f)
        self.data[key] = value
        return key


class Ctx:
    def __init__(self, root):
        self.root = root
        self.root_id = root.get('id')


def label_for(el):
    for c in el.get('class') or []:
        if c in LABELS:
            return LABELS[c]
    return TAGLABEL.get(el.name, 'Text')


def img_field(el, scope, var, decorative=False, label=None):
    src = el.get('src')
    w, h = el.get('width'), el.get('height')
    extra = {'decorative': decorative}
    val = {'src': src, 'media_id': None, 'alt': '' if decorative else el.get('alt', '')}
    if w and h:
        val.update({'w': int(w), 'h': int(h)})
    key = scope.add(label or ('Icon' if decorative else 'Image'), 'image', val, extra)
    attrs = static_attrs(el, ctx_stack[-1], skip=('src', 'alt', 'width', 'height'))
    return f'<img {{!! {var}->img(\'{key}\') !!}}{attrs}>'


ctx_stack = []


def emit(node, scope, var='$s', in_rep=False):
    ctx = ctx_stack[-1]
    if isinstance(node, Comment):
        return ''
    if isinstance(node, NavigableString):
        t = str(node)
        if t.strip() and in_rep is not None:
            # loose text in mixed content -> plain text field
            key = scope.add('Text', 'text', t.strip())
            lead = t[:len(t) - len(t.lstrip())]
            trail = t[len(t.rstrip()):]
            return f'{lead}{{{{ {var}->text(\'{key}\') }}}}{trail}'
        return blade_safe(html.escape(t, quote=False))
    el = node
    name = el.name
    # ---- special hooks ------------------------------------------------
    if name == 'nav' and 'crumb2' in (el.get('class') or []):
        return "@include('site.partials.crumb', ['anim' => true])"
    if name == 'div' and 'faq' in (el.get('class') or []):
        items = []
        for it in el.select('.faq__item'):
            q = it.select_one('.faq__q span')
            a = it.select_one('.faq__a > div')
            items.append({'question': inner_html(q).strip(), 'answer': inner_html(a).strip()})
        scope.add('Questions', 'repeater', items, key='faq_items', extra={'item_label': 'Question', 'fields': [
            {'key': 'question', 'type': 'inline', 'label': 'Question'},
            {'key': 'answer', 'type': 'richtext', 'label': 'Answer'}]})
        scope.add('Open the first question by default', 'boolean', True, key='faq_first_open')
        scope.add('Output FAQPage structured data', 'boolean', False, {'help': FAQ_HELP}, key='faq_schema')
        return "@include('sections.partials.faq-list', ['s' => $s])"
    if name == 'form':
        kind = 'careers' if 'cr-name' in str(el) else 'enquiry'
        variant = 'white' if 'form--white' in (el.get('class') or []) else 'plain'
        prefix = re.search(r'id="([a-z]+)-name"', str(el)).group(1)
        brk = 'true' if '<br/>Read' in str(el) or '<br>Read' in str(el) else 'false'
        return f"@include('site.forms.{kind}', ['white' => {str(variant == 'white').lower()}, 'prefix' => '{prefix}' . $s->uid, 'breakConsent' => {brk}])"
    if name == 'img':
        decorative = el.get('alt', '') == '' or el.get('aria-hidden') == 'true'
        if decorative and not in_rep:
            return f'<img{static_attrs(el, ctx)}>'
        return img_field(el, scope, var, decorative=decorative)
    if name == 'svg':
        s = str(el)
        for a, b in SVG_FIX.items():
            s = s.replace(f' {a}=', f' {b}=')
        return blade_safe(s)
    if name in ('input', 'select', 'textarea', 'button') and el.find_parent('form'):
        return blade_safe(str(el))
    attrs = static_attrs(el, ctx)
    open_t = f'<{name}{attrs}>'
    close_t = '' if name in VOID else f'</{name}>'
    # style background image inside repeaters
    if in_rep and el.get('style') and 'url(' in el.get('style'):
        m = re.search(r"url\('?([^')]+)'?\)", el['style'])
        key = scope.add('Background image', 'image', {'src': m.group(1), 'media_id': None, 'alt': ''},
                        {'decorative': True})
        st = el['style'].replace(m.group(0), "url('{{ " + var + "->src('" + key + "') }}')")
        open_t = f'<{name}{static_attrs(el, ctx, skip=("style",))} style="{st}">'
    if plain_list(el):
        lis = [c for c in el.children if isinstance(c, Tag)]
        key = scope.add(label_for(el) if el.get('class') else 'List items', 'list',
                        [inner_html(li).strip() for li in lis])
        sep = ''
        if len(lis) > 1:
            between = lis[0].next_sibling
            sep = str(between) if isinstance(between, NavigableString) else ''
        lead = el.contents[0] if el.contents and isinstance(el.contents[0], NavigableString) else ''
        return (f'{open_t}{lead}@foreach({var}->list(\'{key}\') as $li)<li>{{!! $li !!}}</li>'
                f'{sep}@endforeach{close_t}')
    if is_prose(el):
        key = scope.add('Body text', 'richtext', inner_html(el).strip())
        return f'{open_t}{{!! {var}->rich(\'{key}\') !!}}{close_t}'
    if name == 'a' and is_leaf(el):
        key = scope.add(label_for(el), 'link',
                        {'label': inner_html(el).strip(), 'url': page_url(el.get('href', '')), 'new_tab': False})
        a2 = static_attrs(el, ctx, skip=('href', 'target', 'rel'))
        return f'<a {{!! {var}->linkAttrs(\'{key}\') !!}}{a2}>{{!! {var}->linkLabel(\'{key}\') !!}}</a>'
    if is_leaf(el) and name not in ('button',):
        key = scope.add(label_for(el), 'inline', inner_html(el).strip())
        inner = inner_html(el)
        lead = inner[:len(inner) - len(inner.lstrip())]
        trail = inner[len(inner.rstrip()):]
        return f'{open_t}{lead}{{!! {var}->html(\'{key}\') !!}}{trail}{close_t}'
    if name == 'a' and el.get('href') is not None:
        key = scope.add('Link URL', 'url', page_url(el['href']))
        open_t = f'<a href="{{{{ {var}->url(\'{key}\') }}}}"{static_attrs(el, ctx, skip=("href",))}>'
    # ---- repeater detection ------------------------------------------
    kids = [c for c in el.children if isinstance(c, Tag)]
    if len(kids) >= 2 and len({(k.name, tuple(classes(k))) for k in kids}) == 1 \
            and len({skeleton(k) for k in kids}) == 1 \
            and all(isinstance(c, Tag) or not str(c).strip() for c in el.children):
        rep = try_repeater(el, kids, scope, var)
        if rep is not None:
            return open_t + rep + close_t
    body = ''.join(emit(c, scope, var, in_rep) for c in el.children)
    return open_t + body + close_t


def strip_state(k, mark):
    k2 = BeautifulSoup(str(k), 'html.parser').find(k.name)
    for e in [k2] + k2.find_all(True):
        st = [c for c in (e.get('class') or []) if STATE.match(c)]
        if st:
            rest = [c for c in e['class'] if not STATE.match(c)]
            if rest:
                e['class'] = rest
            else:
                del e['class']
            if mark:
                e['data-state-first'] = ' '.join(st)
    return k2


def try_repeater(el, kids, scope, var):
    item_scopes, tpls = [], []
    depth = var.count('$') + len(re.findall(r'it\d*', var))
    ivar = f'$it{len(ctx_stack[-1].__dict__.setdefault("rv", []))}'
    ctx_stack[-1].rv.append(ivar)
    for k in kids:
        sc = Scope()
        tpls.append(emit(strip_state(k, False), sc, ivar, True))
        item_scopes.append(sc)
    if len(set(tpls)) != 1 or len({tuple(f['key'] for f in s.fields) for s in item_scopes}) != 1:
        ctx_stack[-1].rv.pop()
        WARN.append(f'repeater fallback in <{el.name} class={el.get("class")}>')
        return None
    tpl = emit(strip_state(kids[0], True), Scope(), ivar, True)
    between = kids[0].next_sibling
    sep = str(between) if isinstance(between, NavigableString) else ''
    lead = el.contents[0] if isinstance(el.contents[0], NavigableString) else ''
    tail = el.contents[-1] if isinstance(el.contents[-1], NavigableString) else ''
    label = label_for(kids[0])
    if label in ('Text', 'Item'):
        label = 'Items'
    else:
        label = label + 's' if not label.endswith('s') else label
    for f in item_scopes[0].fields:   # merge image dimension info
        pass
    key = scope.add(label if label != 'Images' else 'Gallery', 'repeater', [s.data for s in item_scopes],
                    {'fields': item_scopes[0].fields, 'item_label': label_for(kids[0])})
    sep_b = blade_safe(sep)
    if tail and sep_b == blade_safe(str(tail)):
        tail = ''
    return (f'{blade_safe(str(lead))}@foreach({var}->items(\'{key}\') as {ivar}){tpl}'
            f'{sep_b}@endforeach{blade_safe(str(tail))}')


def section_label(el, page):
    cls = el.get('class') or []
    if 'hero2' in cls:
        return 'Page hero'
    if 'subnav' in cls:
        return 'On-this-page links'
    if el.get('aria-label'):
        return el['aria-label']
    if el.get('aria-labelledby'):
        h = el.find(id=el['aria-labelledby'])
        if h:
            return text_of(h)
    h = el.find(['h1', 'h2', 'h3'])
    if h:
        return text_of(h)
    return ' '.join(el.get('class') or [el.name])


FAQ_HELP = ('Only enable when this section is a genuine list of questions and answers. Google currently shows '
            'FAQ rich results mainly for authoritative government and health sites, so this is optional.')
PAGE_TITLES = {}
types = {}       # signature -> type key
type_meta = {}
pages_out = []
MANUAL = {'tabs'}


def convert_page(p):
    soup = BeautifulSoup((SITE / f'{p}.html').read_text(), 'lxml')
    head = soup.head
    title = head.title.get_text()
    desc = head.find('meta', attrs={'name': 'description'})['content']
    robots = head.find('meta', attrs={'name': 'robots'})
    crumb = soup.select_one('nav.crumb2 [aria-current]')
    h1 = soup.find('h1')
    main = soup.find('main')
    blocks = [c for c in main.children if isinstance(c, Tag)]
    secs = []
    css = 'home' if p == 'index' else 'inner'
    for i, b in enumerate(blocks):
        cls = b.get('class') or []
        ctx = Ctx(b)
        ctx_stack.append(ctx)
        scope = Scope()
        if 'fl--tabs' in cls:
            kind = 'programme-tabs'
            data = tabs_data(b)
            DEFAULTS.mkdir(parents=True, exist_ok=True)
            (DEFAULTS / 'programme-tabs.json').write_text(json.dumps(data, indent=1, ensure_ascii=False))
            secs.append({'type': kind, 'anchor': b.get('id'), 'data': data})
            ctx_stack.pop()
            continue
        tpl = emit(b, scope)
        ctx_stack.pop()
        sig = hashlib.sha1((tpl + json.dumps(scope.fields, sort_keys=True)).encode()).hexdigest()
        if sig not in types:
            base = slug(section_label(b, p)).replace('_', '-')[:40]
            key = f'{"home" if p == "index" else "inner"}-{base}'
            n = 2
            while key in type_meta:
                key = f'{key.rsplit("--", 1)[0]}--{n}'
                n += 1
            types[sig] = key
            type_meta[key] = {'key': key, 'label': section_label(b, p), 'origin': p, 'css': css,
                              'fields': scope.fields, 'anchor': b.get('id'),
                              'dynamic': "@include('site.forms" in tpl}
            DEFAULTS.mkdir(parents=True, exist_ok=True)
            (DEFAULTS / f'{key}.json').write_text(json.dumps(scope.data, indent=1, ensure_ascii=False))
            (VIEWS / f'{key}.blade.php').write_text(
                '{{-- Generated from ' + p + '.html — design-matched section. --}}\n' + tpl.strip() + '\n')
        key = types[sig]
        type_meta[key].setdefault('used_on', []).append(p)
        secs.append({'type': key, 'anchor': b.get('id'), 'data': scope.data})
    pages_out.append({
        'slug': '' if p == 'index' else p, 'file': p,
        'title': text_of(crumb) if crumb else 'Home',
        'template': css,
        'meta_title': title, 'meta_description': desc,
        'robots': robots['content'] if robots else 'index, follow',
        'sections': secs,
    })


# ------------------------------------------------------------------ specials
def heading_data(b):
    hx = b.select_one('.hx h2, .hx h3')
    return {'heading_first_line': inner_html(hx.select_one('.d')).strip(),
            'heading_script_line': inner_html(hx.select_one('.s')).strip()}


def faq_data(b, scope, ctx):
    d = heading_data(b)
    items = []
    for it in b.select('.faq__item'):
        q = it.select_one('.faq__q span')
        a = it.select_one('.faq__a > div')
        ans = inner_html(a).strip()
        ans = re.sub(r'href="([^"]+)"', lambda m: f'href="{page_url(m.group(1))}"', ans)
        items.append({'question': inner_html(q).strip(), 'answer': ans})
    d['items'] = items
    d['first_open'] = True
    d['schema'] = False
    return d


def tabs_data(b):
    items = []
    for t in b.select('[role=tab]'):
        panel = b.find(id=t['aria-controls'])
        img = panel.select_one('.media img')
        papers = []
        for pa in panel.select('.paper'):
            ps = pa.find_all('p', recursive=False)
            body = [x for x in ps if not x.get('class')]
            papers.append({
                'title': inner_html(pa.h4).strip(),
                'subtitle': inner_html(pa.select_one('.sub')).strip() if pa.select_one('.sub') else '',
                'text': inner_html(body[0]).strip() if body else '',
                'points': [inner_html(li).strip() for li in pa.select('ul > li')],
                'timing': inner_html(pa.select_one('.time span')).strip() if pa.select_one('.time span') else '',
            })
        btn = panel.select_one('.acts a')
        items.append({
            'slug': t['id'].replace('tab-', ''),
            'tab_title': inner_html(t.select_one('.tab__t')).strip(),
            'tab_subtitle': inner_html(t.select_one('.tab__s')).strip(),
            'tab_image': {'src': t.select_one('img')['src'], 'media_id': None, 'alt': ''},
            'image': {'src': img['src'], 'media_id': None, 'alt': img.get('alt', '')},
            'eyebrow': inner_html(panel.select_one('.eyebrow')).strip(),
            'heading_first_line': inner_html(panel.select_one('h3 .d')).strip(),
            'heading_script_line': inner_html(panel.select_one('h3 .s')).strip(),
            'body': inner_html(panel.select_one('.copy')).strip(),
            'papers': papers,
            'button': {'label': inner_html(btn).strip(), 'url': page_url(btn['href']), 'new_tab': False} if btn else None,
            'selected': t.get('aria-selected') == 'true',
        })
    return {'items': items}


for p in PAGES:
    convert_page(p)
import shutil
for f in (Path(__file__).parent / 'manual').glob('*.blade.php'):
    shutil.copy(f, VIEWS / f.name)
for f in (Path(__file__).parent / 'manual').glob('*.json'):
    shutil.copy(f, SCHEMAS / f.name)

(SEED / 'pages.json').write_text(json.dumps(pages_out, indent=1, ensure_ascii=False))
for k, m in type_meta.items():
    (SCHEMAS / f'{k}.json').write_text(json.dumps(m, indent=1, ensure_ascii=False))
print(len(type_meta), 'section types;', sum(len(p['sections']) for p in pages_out), 'section instances')
for w in sorted(set(WARN)):
    print('WARN', w)
